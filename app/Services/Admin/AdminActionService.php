<?php

namespace App\Services\Admin;

use App\Contracts\PaymentGateway;
use App\Data\PaymentVerificationResult;
use App\Enums\InvitationStatus;
use App\Enums\UserStatus;
use App\Models\ActivityLog;
use App\Models\Invitation;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Payment\PaymentVerificationService;
use Illuminate\Support\Facades\DB;

class AdminActionService
{
    public function __construct(private readonly PaymentVerificationService $verification)
    {
    }

    /** Change a customer account state and keep an auditable trail. */
    public function setCustomerStatus(
        User $customer,
        UserStatus $status,
        User $admin,
        ?string $reason,
        ?string $ipAddress,
    ): void {
        if ($customer->isSuperAdmin() || $customer->id === $admin->id) {
            abort(403, 'Akun ini tidak dapat dinonaktifkan.');
        }

        DB::transaction(function () use ($customer, $status, $admin, $reason, $ipAddress): void {
            $customer->update(['status' => $status]);

            ActivityLog::create([
                'user_id' => $admin->id,
                'action' => $status === UserStatus::Active
                    ? 'customer.activated_by_admin'
                    : 'customer.deactivated_by_admin',
                'subject_type' => 'customer',
                'subject_id' => $customer->id,
                'description' => 'Super Admin mengubah status akun ' . $customer->email . ' menjadi ' . $status->value . '.',
                'reason' => $reason,
                'ip_address' => $ipAddress,
            ]);
        });
    }

    /**
     * Manually publish or suspend an invitation. Payment status is deliberately
     * left untouched — publication and payment are separate state machines.
     */
    public function setInvitationStatus(
        Invitation $invitation,
        InvitationStatus $status,
        User $admin,
        string $reason,
        ?string $ipAddress,
    ): void {
        DB::transaction(function () use ($invitation, $status, $admin, $reason, $ipAddress): void {
            $update = ['status' => $status];

            if ($status === InvitationStatus::Active) {
                $activatedAt = $invitation->activated_at ?? now();
                $update['activated_at'] = $activatedAt;
                $update['active_expires_at'] = now()->addYear();
                $update['published_at'] = $invitation->published_at ?? now();
            }

            $invitation->update($update);

            ActivityLog::create([
                'user_id' => $admin->id,
                'action' => $status === InvitationStatus::Active
                    ? 'invitation.activated_by_admin'
                    : 'invitation.suspended_by_admin',
                'subject_type' => 'invitation',
                'subject_id' => $invitation->id,
                'description' => 'Super Admin mengubah status undangan ' . $invitation->slug . ' menjadi ' . $status->value . '.',
                'reason' => $reason,
                'ip_address' => $ipAddress,
            ]);
        });
    }

    /** Re-check a transaction through the provider and apply the same verification path as a webhook. */
    public function recheckTransaction(
        Transaction $transaction,
        PaymentGateway $gateway,
        User $admin,
        ?string $ipAddress,
    ): PaymentVerificationResult {
        $payload = $gateway->fetchStatus($transaction->order_id);
        $result = $this->verification->handleProviderStatus($transaction, $payload);

        ActivityLog::create([
            'user_id' => $admin->id,
            'action' => 'transaction.rechecked_by_admin',
            'subject_type' => 'transaction',
            'subject_id' => $transaction->id,
            'description' => 'Super Admin memeriksa ulang transaksi ' . $transaction->order_id . '.',
            'reason' => 'Pengecekan status transaksi dari dashboard admin.',
            'ip_address' => $ipAddress,
        ]);

        return $result;
    }
}
