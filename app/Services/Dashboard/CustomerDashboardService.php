<?php

namespace App\Services\Dashboard;

use App\Enums\InvitationStatus;
use App\Models\User;

class CustomerDashboardService
{
    /**
     * Build the complete PRD 12 dashboard payload for one customer.
     * All queries are scoped to the authenticated user.
     *
     * @return array<string, mixed>
     */
    public function for(User $user): array
    {
        $invitations = $user->invitations()
            ->with(['template', 'groom', 'bride', 'events'])
            ->latest('id')
            ->get();

        $transactions = $user->transactions()
            ->with('invitation')
            ->latest('id')
            ->get();

        $count = fn (InvitationStatus ...$statuses): int => $invitations
            ->filter(fn ($invitation) => in_array($invitation->status, $statuses, true))
            ->count();

        $needsActivation = $invitations->first(
            fn ($invitation) => ! in_array($invitation->status, [
                InvitationStatus::Active,
                InvitationStatus::Suspended,
            ], true),
        );

        return [
            'invitations' => $invitations,
            'transactions' => $transactions,
            'stats' => [
                'total' => $invitations->count(),
                'active' => $count(InvitationStatus::Active),
                'draft' => $count(InvitationStatus::Draft, InvitationStatus::Preview),
                'expired' => $count(InvitationStatus::Expired),
                'pending_payment' => $count(InvitationStatus::PendingPayment),
            ],
            'needsActivation' => $needsActivation,
            'latestTransaction' => $transactions->first(),
        ];
    }
}
