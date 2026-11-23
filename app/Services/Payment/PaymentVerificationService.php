<?php

namespace App\Services\Payment;

use App\Data\PaymentVerificationResult;
use App\Enums\InvitationStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\Payment\AmountMismatchException;
use App\Exceptions\Payment\InvalidSignatureException;
use App\Services\Concerns\RetriesDeadlocks;
use App\Models\ActivityLog;
use App\Models\Invitation;
use App\Models\Transaction;
use App\Models\Voucher;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymentVerificationService
{
    use RetriesDeadlocks;

    /**
     * Verify a Midtrans webhook and apply its status. Safe to call repeatedly.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleNotification(array $payload): PaymentVerificationResult
    {
        $this->verifySignature($payload);

        return $this->apply($payload, true);
    }

    /** Apply a status fetched directly from the Midtrans API (manual re-check). */
    public function handleProviderStatus(Transaction $transaction, array $payload): PaymentVerificationResult
    {
        $data = array_merge($payload, [
            'order_id' => $transaction->order_id,
            'status_code' => (string) ($payload['status_code']
                ?? ($transaction->midtrans_response['status_code'] ?? '200')),
        ]);

        return $this->apply($data, false);
    }

    /** @param array<string, mixed> $payload */
    private function verifySignature(array $payload): void
    {
        $serverKey = (string) config('services.midtrans.server_key', '');

        if ($serverKey === '') {
            throw new InvalidSignatureException('Kunci server pembayaran belum dikonfigurasi.');
        }

        foreach (['order_id', 'status_code', 'gross_amount', 'signature_key'] as $field) {
            if (! isset($payload[$field]) || ! is_scalar($payload[$field])) {
                throw new InvalidSignatureException('Notifikasi pembayaran tidak lengkap.');
            }
        }

        $expected = hash('sha512', implode('', [
            (string) $payload['order_id'],
            (string) $payload['status_code'],
            (string) $payload['gross_amount'],
            $serverKey,
        ]));

        if (! hash_equals($expected, strtolower((string) $payload['signature_key']))) {
            throw new InvalidSignatureException('Signature key pembayaran tidak valid.');
        }
    }

    /** @param array<string, mixed> $payload */
    private function apply(array $payload, bool $countNotification): PaymentVerificationResult
    {
        $log = [];

        $result = $this->transactionWithRetry(function () use ($payload, $countNotification, &$log): PaymentVerificationResult {
            $orderId = (string) ($payload['order_id'] ?? '');
            $hint = Transaction::where('order_id', $orderId)
                ->select('id', 'invitation_id')
                ->firstOrFail();

            // Always lock invitation before transaction. Checkout does the
            // same, which prevents InnoDB deadlocks between webhook and
            // checkout under concurrent traffic.
            $invitation = Invitation::withTrashed()
                ->whereKey($hint->invitation_id)
                ->lockForUpdate()
                ->firstOrFail();

            $transaction = Transaction::whereKey($hint->id)->lockForUpdate()->firstOrFail();
            $transaction->setRelation('invitation', $invitation);

            $payloadAmount = $this->normalizeAmount($payload['gross_amount'] ?? null);
            $storedAmount = $this->normalizeAmount($transaction->gross_amount);

            if ($payloadAmount === null || $storedAmount === null
                || bccomp($payloadAmount, $storedAmount, 2) !== 0) {
                throw new AmountMismatchException('Nominal pembayaran tidak sesuai.');
            }

            $currentStatus = $transaction->transaction_status ?? PaymentStatus::Pending;
            $paymentStatus = $this->statusFrom($payload);
            $stale = $paymentStatus->rank() < $currentStatus->rank();

            if ($countNotification) {
                $transaction->increment('notification_count');
            }

            // An order the customer explicitly abandoned when changing method
            // is never allowed to activate the invitation, even if the
            // provider reports a late settlement for it.
            if ($transaction->wasReplacedForMethodChange() && $paymentStatus->isSuccessful()) {
                $storedStatus = $this->storedStatus($transaction, PaymentStatus::Cancel);
                $log = $this->logContext($transaction->refresh(), $storedStatus, false, true);
                Log::info('payment.replaced_order_ignored', $log);

                return new PaymentVerificationResult($transaction, $storedStatus, false, true, false);
            }

            if ($stale) {
                $transaction->refresh();

                // Report the effective stored status, not the late incoming one,
                // so webhook responses and audit logs never look like a downgrade.
                $storedStatus = $this->storedStatus($transaction, $paymentStatus);

                $log = $this->logContext($transaction, $storedStatus, false, true);

                return new PaymentVerificationResult($transaction, $storedStatus, false, true, false);
            }

            $wasActive = $transaction->invitation?->status === InvitationStatus::Active;
            $isSuspended = $transaction->invitation?->status === InvitationStatus::Suspended;
            $activated = false;
            $accessRevoked = false;

            $previousResponse = is_array($transaction->midtrans_response)
                ? $transaction->midtrans_response
                : [];

            $transaction->fill([
                'transaction_status' => $paymentStatus,
                'fraud_status' => $payload['fraud_status'] ?? null,
                // Provider reports "bank_transfer" for VA payments; never let it
                // overwrite the customer's canonical method (bca_va, bni_va, ...).
                'payment_type' => $transaction->payment_type,
                'midtrans_transaction_id' => $payload['transaction_id'] ?? $transaction->midtrans_transaction_id,
                'transaction_time' => $this->parseDate($payload['transaction_time'] ?? null) ?? $transaction->transaction_time,
                'paid_at' => $paymentStatus->isSuccessful()
                    ? ($transaction->paid_at ?? now())
                    : $transaction->paid_at,
                'verified_at' => $paymentStatus->isSuccessful()
                    ? ($transaction->verified_at ?? now())
                    : $transaction->verified_at,
                'midtrans_response' => array_replace_recursive(
                    $previousResponse,
                    Arr::except($payload, ['signature_key']),
                ),
            ])->save();

            if ($paymentStatus->isSuccessful() && $transaction->voucher_id && ! $transaction->voucher_redeemed_at) {
                $voucher = Voucher::whereKey($transaction->voucher_id)->lockForUpdate()->first();
                if ($voucher) {
                    $voucher->increment('used_count');
                    $transaction->forceFill(['voucher_redeemed_at' => now()])->save();
                }
            }

            $invitation = $transaction->invitation;

            if ($invitation !== null) {
                $update = ['payment_status' => $paymentStatus];

                if ($paymentStatus->isSuccessful() && ! $wasActive && ! $isSuspended) {
                    $update['status'] = InvitationStatus::Active;
                    $update['activated_at'] = now();
                    $update['active_expires_at'] = now()->addYear();
                    $update['published_at'] = $invitation->published_at ?? now();
                    $activated = true;
                } elseif ($paymentStatus->revokesAccess() && $wasActive) {
                    $update['status'] = InvitationStatus::Suspended;
                    $accessRevoked = true;
                }

                $invitation->update($update);

                if ($activated) {
                    ActivityLog::create([
                        'user_id' => $invitation->user_id,
                        'action' => 'invitation.activated',
                        'subject_type' => 'invitation',
                        'subject_id' => $invitation->id,
                        'description' => 'Undangan ' . $invitation->slug . ' aktif setelah pembayaran terverifikasi.',
                        'ip_address' => request()->ip(),
                    ]);
                }

                if ($accessRevoked) {
                    ActivityLog::create([
                        'user_id' => $invitation->user_id,
                        'action' => 'payment.access_revoked',
                        'subject_type' => 'invitation',
                        'subject_id' => $invitation->id,
                        'description' => 'Akses publik undangan ' . $invitation->slug . ' dicabut setelah pembayaran dibatalkan.',
                        'ip_address' => request()->ip(),
                    ]);
                }

                if ($paymentStatus->isSuccessful() && $isSuspended) {
                    ActivityLog::create([
                        'user_id' => $invitation->user_id,
                        'action' => 'payment.suspended_not_reactivated',
                        'subject_type' => 'invitation',
                        'subject_id' => $invitation->id,
                        'description' => 'Pembayaran terverifikasi untuk undangan ' . $invitation->slug . ' tidak mengaktifkan ulang status SUSPENDED.',
                        'ip_address' => request()->ip(),
                    ]);
                }
            }

            $log = $this->logContext($transaction->refresh(), $paymentStatus, $activated, false, $accessRevoked);

            return new PaymentVerificationResult($transaction, $paymentStatus, $activated, false, $accessRevoked);
        });

        Log::info('payment.status_processed', $log);

        return $result;
    }

    /** @param array<string, mixed> $payload */
    private function statusFrom(array $payload): PaymentStatus
    {
        $rawStatus = strtolower((string) ($payload['transaction_status'] ?? ''));
        $fraudStatus = strtolower((string) ($payload['fraud_status'] ?? ''));

        // Status is determined by the documented transaction_status and fraud
        // result. status_code is supporting metadata: Midtrans may return a
        // different code for a valid settlement, and the signature already
        // proves the callback came from the gateway.
        return match ($rawStatus) {
            'settlement' => match ($fraudStatus) {
                'deny' => PaymentStatus::Deny,
                'challenge' => PaymentStatus::Pending,
                default => PaymentStatus::Settlement,
            },
            'capture' => match ($fraudStatus) {
                'accept' => PaymentStatus::Paid,
                'deny' => PaymentStatus::Deny,
                default => PaymentStatus::Pending,
            },
            'pending' => PaymentStatus::Pending,
            'authorize' => PaymentStatus::Authorize,
            'challenge' => PaymentStatus::Challenge,
            'expire' => PaymentStatus::Expire,
            'cancel' => PaymentStatus::Cancel,
            'deny' => PaymentStatus::Deny,
            'failure' => PaymentStatus::Failure,
            'refund' => PaymentStatus::Refund,
            'partial_refund' => PaymentStatus::PartialRefund,
            'chargeback' => PaymentStatus::Chargeback,
            'partial_chargeback' => PaymentStatus::PartialChargeback,
            default => PaymentStatus::Pending,
        };
    }

    private function storedStatus(Transaction $transaction, PaymentStatus $fallback): PaymentStatus
    {
        $status = $transaction->transaction_status;

        if ($status instanceof PaymentStatus) {
            return $status;
        }

        return PaymentStatus::tryFrom((string) $status) ?? $fallback;
    }

    private function normalizeAmount(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $amount = trim((string) $value);

        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $amount, $matches)) {
            return null;
        }

        return $matches[1] . '.' . str_pad($matches[2] ?? '', 2, '0');
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse(
                $value,
                (string) config('services.midtrans.timestamp_timezone', 'Asia/Jakarta'),
            );
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array<string, mixed> */
    private function logContext(
        Transaction $transaction,
        PaymentStatus $paymentStatus,
        bool $activated,
        bool $stale,
        bool $accessRevoked = false,
    ): array {
        return [
            'order_id' => preg_replace('/[^A-Za-z0-9_-]/', '', $transaction->order_id),
            'payment_status' => $paymentStatus->value,
            'activated' => $activated,
            'stale' => $stale,
            'access_revoked' => $accessRevoked,
            'status_code' => $transaction->midtrans_response['status_code'] ?? null,
            'transaction_id' => $transaction->midtrans_transaction_id,
            'fraud_status' => $transaction->fraud_status,
            'payment_type' => $transaction->payment_type,
            'merchant_id' => $transaction->midtrans_response['merchant_id'] ?? null,
        ];
    }
}
