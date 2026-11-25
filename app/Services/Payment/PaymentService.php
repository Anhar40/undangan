<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Data\PaymentInstructionData;
use App\Data\PaymentRequestData;
use App\Data\PaymentResponseData;
use App\Enums\InvitationStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\Payment\GatewayException;
use App\Exceptions\Payment\PaymentException;
use App\Exceptions\Payment\PaymentNotAllowedException;
use App\Services\Concerns\RetriesDeadlocks;
use App\Models\ActivityLog;
use App\Models\Invitation;
use App\Models\InvitationBride;
use App\Models\InvitationGroom;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Voucher;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymentService
{
    use RetriesDeadlocks;

    public function __construct(private readonly PaymentGateway $gateway) {}

    public function begin(
        Invitation $invitation,
        User $user,
        string $paymentType = 'qris',
        ?string $voucherCode = null,
    ): Transaction {
        $this->assertPaymentType($paymentType);
        $this->assertOwner($invitation, $user);

        $expiryMinutes = max(1, (int) config('services.midtrans.expiry_minutes', 30));
        $lock = Cache::lock('payment:start:' . $invitation->id, 120);

        if (! $lock->get()) {
            throw new PaymentNotAllowedException('Pembayaran sedang diproses. Silakan coba beberapa saat lagi.');
        }

        try {
            // Phase 1: a short DB transaction reserves the attempt. No gateway
            // network call happens while the invitation row is locked.
            $reservation = $this->transactionWithRetry(function () use (
                $invitation,
                $user,
                $paymentType,
                $expiryMinutes,
                $voucherCode,
            ): array {
                $locked = Invitation::whereKey($invitation->id)->lockForUpdate()->firstOrFail();
                $this->assertOwner($locked, $user);
                $this->assertPayable($locked);

                if (
                    $locked->status === InvitationStatus::Preview
                    && $locked->preview_expires_at?->isPast()
                ) {
                    $locked->update(['status' => InvitationStatus::Expired]);
                }

                if (
                    $locked->status === InvitationStatus::Active
                    && $locked->isActiveExpired()
                ) {
                    $locked->update(['status' => InvitationStatus::Expired]);
                }

                $voucher = $this->resolveVoucher($voucherCode, (int) $locked->price);
                $originalAmount = (int) $locked->price;
                $discountAmount = $voucher?->discountFor($originalAmount) ?? 0;
                $grossAmount = max(0, $originalAmount - $discountAmount);

                if ($grossAmount <= 0) {
                    if (! $voucher instanceof Voucher) {
                        throw new PaymentNotAllowedException('Nominal pembayaran setelah diskon tidak valid.');
                    }

                    $existingFree = $locked->transactions()
                        ->where('voucher_id', $voucher->id)
                        ->whereIn('transaction_status', [PaymentStatus::Paid->value, PaymentStatus::Settlement->value])
                        ->latest()
                        ->first();

                    if ($existingFree instanceof Transaction) {
                        return ['existing' => $existingFree];
                    }

                    $attempt = (int) $locked->transactions()->count() + 1;
                    $idempotencyKey = hash('sha256', implode('|', [
                        $locked->id,
                        $user->id,
                        'free_voucher',
                        0,
                        (int) $voucher->id,
                        $attempt,
                    ]));
                    $orderId = 'UD-' . $locked->id . '-FREE-' . strtoupper(substr($idempotencyKey, 0, 8)) . '-' . $attempt;

                    $known = Transaction::where('idempotency_key', $idempotencyKey)->first();
                    if ($known instanceof Transaction) {
                        return ['existing' => $known];
                    }

                    $lockedVoucher = Voucher::whereKey($voucher->id)->lockForUpdate()->first();
                    if (! $lockedVoucher || ! $lockedVoucher->isAvailableForAmount($originalAmount)) {
                        throw new PaymentNotAllowedException('Voucher tidak dapat digunakan untuk transaksi ini.');
                    }

                    $transaction = Transaction::create([
                        'user_id' => (int) $user->id,
                        'invitation_id' => (int) $locked->id,
                        'voucher_id' => $lockedVoucher->id,
                        'voucher_code' => $lockedVoucher->code,
                        'order_id' => $orderId,
                        'idempotency_key' => $idempotencyKey,
                        'gross_amount' => 0,
                        'original_amount' => $originalAmount,
                        'voucher_discount_amount' => $lockedVoucher->discountFor($originalAmount),
                        'payment_method' => 'voucher',
                        'payment_type' => 'voucher',
                        'payment_label' => 'Voucher 100%',
                        'payment_reference' => $lockedVoucher->code,
                        'payment_instructions' => [
                            'label' => 'Voucher 100%',
                            'reference' => $lockedVoucher->code,
                            'steps' => ['Undangan diaktifkan menggunakan voucher gratis.'],
                        ],
                        'transaction_status' => PaymentStatus::Settlement,
                        'paid_at' => now(),
                        'verified_at' => now(),
                        'voucher_redeemed_at' => now(),
                        'midtrans_response' => [
                            'source' => 'voucher',
                            'voucher_code' => $lockedVoucher->code,
                        ],
                    ]);

                    $lockedVoucher->increment('used_count');

                    $wasActive = $locked->status === InvitationStatus::Active;
                    $isSuspended = $locked->status === InvitationStatus::Suspended;
                    $update = ['payment_status' => PaymentStatus::Settlement];
                    if (! $wasActive && ! $isSuspended) {
                        $update['status'] = InvitationStatus::Active;
                        $update['activated_at'] = now();
                        $update['active_expires_at'] = now()->addYear();
                        $update['published_at'] = $locked->published_at ?? now();
                    }
                    $locked->update($update);

                    ActivityLog::create([
                        'user_id' => (int) $user->id,
                        'action' => 'invitation.activated_voucher',
                        'subject_type' => 'invitation',
                        'subject_id' => (int) $locked->id,
                        'description' => 'Undangan ' . $locked->slug . ' diaktifkan dengan voucher gratis ' . $voucher->code . '.',
                        'ip_address' => request()->ip(),
                    ]);

                    return ['existing' => $transaction->fresh()];
                }

                $pending = $locked->transactions()
                    ->where('transaction_status', PaymentStatus::Pending->value)
                    ->where(function ($query) use ($expiryMinutes) {
                        $query->whereNull('payment_expires_at')
                            ->orWhere('payment_expires_at', '>', now());
                    })
                    ->where('created_at', '>=', now()->subMinutes($expiryMinutes))
                    ->latest()
                    ->first();

                // Same method + same voucher + unexpired order is a true retry.
                $sameVoucher = (int) ($pending?->voucher_id ?? 0) === (int) ($voucher?->id ?? 0);
                if ($pending instanceof Transaction && $pending->payment_type === $paymentType && $sameVoucher) {
                    $this->markPendingPayment($locked, $user);

                    return ['existing' => $pending];
                }

                $attempt = (int) $locked->transactions()->count() + 1;
                $idempotencyKey = hash('sha256', implode('|', [
                    $locked->id,
                    $user->id,
                    $paymentType,
                    $grossAmount,
                    (int) ($voucher?->id ?? 0),
                    $attempt,
                ]));
                $orderId = 'UD-' . $locked->id . '-' . strtoupper(substr($idempotencyKey, 0, 10)) . '-' . $attempt;

                $known = Transaction::where('idempotency_key', $idempotencyKey)->first();

                if ($known instanceof Transaction) {
                    if ($known->transaction_status === PaymentStatus::Pending) {
                        $this->markPendingPayment($locked, $user);

                        return ['existing' => $known];
                    }

                    throw new PaymentNotAllowedException('Pembayaran untuk undangan ini sudah dibuat.');
                }

                $cancelledOrderId = null;
                $oldTransactionId = null;

                // A different method is an explicit replacement, not a retry.
                // Close the old order and make it impossible to activate the
                // invitation from a late callback for that order.
                if ($pending instanceof Transaction) {
                    $this->markReplacedForMethodChange($pending, $orderId, $user);
                    $cancelledOrderId = (string) $pending->order_id;
                    $oldTransactionId = (int) $pending->id;
                }
                $request = new PaymentRequestData(
                    orderId: $orderId,
                    grossAmount: $grossAmount,
                    customerName: $user->name,
                    customerEmail: $user->email,
                    itemName: 'Aktivasi ' . $locked->title,
                    finishUrl: route('invitations.payment.status', $locked),
                    notificationUrl: (string) config('services.midtrans.notification_url') ?: route('payment.notification'),
                    invitationId: (int) $locked->id,
                    paymentType: $paymentType,
                    expiryMinutes: $expiryMinutes,
                );

                $previousStatus = $locked->status;
                $previousPaymentStatus = $locked->payment_status;

                $locked->update([
                    'status' => InvitationStatus::PendingPayment,
                    'payment_status' => PaymentStatus::Pending,
                ]);

                ActivityLog::create([
                    'user_id' => $user->id,
                    'action' => 'payment.started',
                    'subject_type' => 'invitation',
                    'subject_id' => $locked->id,
                    'description' => 'Customer memulai aktivasi untuk undangan ' . $locked->slug,
                    'ip_address' => request()->ip(),
                ]);

                return [
                    'request' => $request,
                    'idempotency_key' => $idempotencyKey,
                    'invitation_id' => (int) $locked->id,
                    'user_id' => (int) $user->id,
                    'previous_status' => $previousStatus,
                    'previous_payment_status' => $previousPaymentStatus,
                    'cancel_order_id' => $cancelledOrderId,
                    'old_transaction_id' => $oldTransactionId,
                    'voucher_id' => $voucher?->id,
                    'voucher_code' => $voucher?->code,
                    'original_amount' => $originalAmount,
                    'voucher_discount_amount' => $discountAmount,
                ];
            });

            if (isset($reservation['existing']) && $reservation['existing'] instanceof Transaction) {
                return $reservation['existing'];
            }

            // Best-effort provider cancel. The database flag above is the
            // authoritative guard if the provider cannot be reached.
            if (! empty($reservation['cancel_order_id'])) {
                try {
                    $this->gateway->cancel((string) $reservation['cancel_order_id']);
                } catch (Throwable $cancelException) {
                    Log::error('payment.replace_cancel_failed', [
                        'order_id' => preg_replace('/[^A-Za-z0-9_-]/', '', (string) $reservation['cancel_order_id']),
                        'message' => $cancelException->getMessage(),
                    ]);
                }
            }

            /** @var PaymentRequestData $request */
            $request = $reservation['request'];
            $chargeCreated = false;

            try {
                // Phase 2: external I/O without a DB row lock.
                $response = $this->gateway->createTransaction($request);
                $chargeCreated = true;

                if (! $response instanceof PaymentResponseData || ! $response->isValid()) {
                    throw new GatewayException('Respons sistem pembayaran tidak valid.');
                }

                $instructions = PaymentInstructionData::fromResponse($response, $request->paymentType);
                $expiresAt = $this->parseExpiry($response->expiryTime, $expiryMinutes);

                // Phase 3: persist the confirmed charge in a short transaction.
                return $this->transactionWithRetry(function () use (
                    $reservation,
                    $request,
                    $response,
                    $instructions,
                    $expiresAt,
                ): Transaction {
                    $invitation = Invitation::whereKey($reservation['invitation_id'])->firstOrFail();

                    return $invitation->transactions()->create([
                        'user_id' => $reservation['user_id'],
                        'voucher_id' => $reservation['voucher_id'] ?? null,
                        'voucher_code' => $reservation['voucher_code'] ?? null,
                        'order_id' => $request->orderId,
                        'idempotency_key' => $reservation['idempotency_key'],
                        'gross_amount' => $request->grossAmount,
                        'original_amount' => $reservation['original_amount'] ?? $request->grossAmount,
                        'voucher_discount_amount' => $reservation['voucher_discount_amount'] ?? 0,
                        // Core API returns "bank_transfer" for every VA. Keep the
                        // customer's canonical method for labels, history and hooks.
                        'payment_type' => $request->paymentType,
                        'payment_label' => $instructions->label,
                        'payment_reference' => $instructions->reference,
                        'payment_instructions' => $instructions->toArray(),
                        'payment_expires_at' => $expiresAt,
                        'transaction_status' => PaymentStatus::Pending,
                        'snap_token' => $response->token !== '' ? $response->token : null,
                        'snap_url' => $response->redirectUrl !== '' ? $response->redirectUrl : null,
                        'midtrans_response' => $response->raw,
                    ]);
                });
            } catch (Throwable $exception) {
                if ($chargeCreated) {
                    try {
                        $this->gateway->cancel($request->orderId);
                    } catch (Throwable $cancelException) {
                        Log::error('payment.compensation_failed', [
                            'order_id' => preg_replace('/[^A-Za-z0-9_-]/', '', $request->orderId),
                            'message' => $cancelException->getMessage(),
                        ]);
                    }
                }

                $this->clearReplacementPointer($reservation);
                $this->restoreAttempt($reservation);

                if ($exception instanceof PaymentException) {
                    throw $exception;
                }

                throw new GatewayException('Gagal membuat transaksi pembayaran.', 0, $exception);
            }
        } finally {
            $lock->release();
        }
    }

    private function assertPaymentType(string $paymentType): void
    {
        if (! PaymentRequestData::isSupported($paymentType)) {
            throw new PaymentNotAllowedException('Metode pembayaran tidak didukung.');
        }
    }

    private function assertOwner(Invitation $invitation, User $user): void
    {
        if ($user->id !== $invitation->user_id) {
            throw new PaymentNotAllowedException('Anda tidak memiliki akses ke undangan ini.');
        }
    }

    private function assertPayable(Invitation $invitation): void
    {
        $allowed = in_array($invitation->status, [
            InvitationStatus::Draft,
            InvitationStatus::Preview,
            InvitationStatus::Expired,
            InvitationStatus::PendingPayment,
        ], true);

        $activeExpired = $invitation->status === InvitationStatus::Active
            && $invitation->isActiveExpired();

        if (! $allowed && ! $activeExpired) {
            throw new PaymentNotAllowedException('Undangan ini tidak dapat diaktifkan.');
        }

        if ((int) $invitation->price <= 0) {
            throw new PaymentNotAllowedException('Harga aktivasi belum ditentukan.');
        }

        $this->assertReadyForActivation($invitation);
    }

    /**
     * An invitation must contain real customer data before payment starts.
     * This prevents an empty/sample-looking public page from being activated.
     */
    private function assertReadyForActivation(Invitation $invitation): void
    {
        $invitation->loadMissing(['groom', 'bride', 'events']);

        if (trim((string) $invitation->title) === '') {
            throw new PaymentNotAllowedException('Judul undangan belum diisi.');
        }

        if (! $this->personHasName($invitation->bride) || ! $this->personHasName($invitation->groom)) {
            throw new PaymentNotAllowedException('Nama kedua mempelai belum lengkap.');
        }

        $events = $invitation->events->filter(fn ($event) => filled($event->event_date));

        if ($events->isEmpty()) {
            throw new PaymentNotAllowedException('Minimal satu acara dengan tanggal harus diisi.');
        }
    }

    private function personHasName(InvitationBride|InvitationGroom|null $person): bool
    {
        return $person !== null
            && (trim((string) $person->full_name) !== '' || trim((string) $person->nickname) !== '');
    }

    private function parseExpiry(?string $value, int $fallbackMinutes): Carbon
    {
        if ($value !== null && $value !== '') {
            try {
                return Carbon::parse(
                    $value,
                    (string) config('services.midtrans.timestamp_timezone', 'Asia/Jakarta'),
                )
                    ->timezone((string) config('app.timezone', 'Asia/Makassar'));
            } catch (Throwable) {
                // Fall through to the configured window.
            }
        }

        return now()->addMinutes($fallbackMinutes);
    }

    private function markReplacedForMethodChange(
        Transaction $previous,
        string $newOrderId,
        User $user,
    ): void {
        $previousResponse = is_array($previous->midtrans_response)
            ? $previous->midtrans_response
            : [];

        $previous->fill([
            'transaction_status' => PaymentStatus::Cancel,
            'replaced_by_order_id' => $newOrderId,
            'midtrans_response' => array_replace_recursive($previousResponse, [
                'cancelled_for_method_change' => true,
                'replaced_by_order_id' => $newOrderId,
                'replaced_at' => now()->toIso8601String(),
            ]),
        ])->save();

        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'payment.method_changed',
            'subject_type' => 'invitation',
            'subject_id' => $previous->invitation_id,
            'description' => 'Customer mengganti metode pembayaran; order ' . $previous->order_id . ' dibatalkan.',
            'ip_address' => request()->ip(),
        ]);
    }

    /**
     * If the new charge fails, do not leave the old order pointing at an order
     * that was never created. It stays cancelled and still cannot activate.
     *
     * @param array<string, mixed> $reservation
     */
    private function clearReplacementPointer(array $reservation): void
    {
        if (empty($reservation['old_transaction_id'])) {
            return;
        }

        $previous = Transaction::find($reservation['old_transaction_id']);

        if (! $previous instanceof Transaction) {
            return;
        }

        $previousResponse = is_array($previous->midtrans_response)
            ? $previous->midtrans_response
            : [];

        unset($previousResponse['replaced_by_order_id']);
        $previousResponse['replacement_failed'] = true;

        $previous->update([
            'replaced_by_order_id' => null,
            'midtrans_response' => $previousResponse,
        ]);
    }

    /** @param array<string, mixed> $reservation */
    private function restoreAttempt(array $reservation): void
    {
        $this->transactionWithRetry(function () use ($reservation): void {
            $invitation = Invitation::whereKey($reservation['invitation_id'])->lockForUpdate()->first();

            if (! $invitation || $invitation->status !== InvitationStatus::PendingPayment) {
                return;
            }

            if ($invitation->transactions()->where('transaction_status', PaymentStatus::Pending)->exists()) {
                return;
            }

            $invitation->update([
                'status' => $reservation['previous_status'],
                'payment_status' => $reservation['previous_payment_status'],
            ]);
        });
    }

    private function markPendingPayment(Invitation $invitation, User $user): void
    {
        if ($invitation->status !== InvitationStatus::PendingPayment) {
            $invitation->update([
                'status' => InvitationStatus::PendingPayment,
                'payment_status' => PaymentStatus::Pending,
            ]);

            ActivityLog::create([
                'user_id' => $user->id,
                'action' => 'payment.started',
                'subject_type' => 'invitation',
                'subject_id' => $invitation->id,
                'description' => 'Customer memulai aktivasi untuk undangan ' . $invitation->slug,
                'ip_address' => request()->ip(),
            ]);
        }
    }

    private function resolveVoucher(?string $voucherCode, int $originalAmount): ?Voucher
    {
        if ($voucherCode === null || trim($voucherCode) === '') {
            return null;
        }

        $code = strtoupper(trim($voucherCode));
        $voucher = Voucher::where('code', $code)->lockForUpdate()->first();

        if (! $voucher) {
            throw new PaymentNotAllowedException('Kode voucher tidak ditemukan.');
        }

        if (! $voucher->isAvailableForAmount($originalAmount)) {
            throw new PaymentNotAllowedException('Voucher tidak dapat digunakan untuk transaksi ini.');
        }

        if ($voucher->discountFor($originalAmount) <= 0) {
            throw new PaymentNotAllowedException('Voucher tidak memberikan diskon untuk transaksi ini.');
        }

        return $voucher;
    }
}
