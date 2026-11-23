<?php

namespace App\Services\Dashboard;

use App\Enums\InvitationStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Invitation;
use App\Models\Template;
use App\Models\Transaction;
use App\Models\User;

class AdminDashboardService
{
    /**
     * All figures required by PRD 13.1, plus the latest records for quick review.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return [
            'stats' => [
                'customers' => User::where('role', Role::Customer->value)->count(),
                'invitations' => Invitation::count(),
                'active_invitations' => Invitation::where('status', InvitationStatus::Active->value)->count(),
                'draft_invitations' => Invitation::whereIn('status', [
                    InvitationStatus::Draft->value,
                    InvitationStatus::Preview->value,
                ])->count(),
                'transactions' => Transaction::count(),
                // SUM(DECIMAL) is returned as an exact string by MySQL. Keep the
                // cents intact instead of truncating them with an int cast.
                'revenue' => bcadd((string) Transaction::successful()->sum('gross_amount'), '0.00', 2),
                'pending_transactions' => Transaction::where('transaction_status', PaymentStatus::Pending->value)->count(),
                'templates' => Template::active()->count(),
            ],
            'recentTransactions' => Transaction::with(['user', 'invitation'])
                ->latest('id')
                ->limit(8)
                ->get(),
            'recentInvitations' => Invitation::with(['user', 'template'])
                ->latest('id')
                ->limit(8)
                ->get(),
            'recentActivity' => ActivityLog::with('user')
                ->latest('id')
                ->limit(8)
                ->get(),
        ];
    }
}
