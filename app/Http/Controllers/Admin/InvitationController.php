<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InvitationStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateInvitationStatusRequest;
use App\Models\ActivityLog;
use App\Models\Invitation;
use App\Services\Admin\AdminActionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvitationController extends Controller
{
    public function index(Request $request): View
    {
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $status = (string) $request->query('status', '');
        $payment = (string) $request->query('payment', '');

        $statusValues = array_map(static fn (InvitationStatus $case): string => $case->value, InvitationStatus::cases());

        $invitations = Invitation::query()
            ->with(['user', 'template', 'transactions'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->whereRaw('LOWER(title) LIKE ?', ['%'.mb_strtolower($search).'%'])
                        ->orWhereRaw('LOWER(slug) LIKE ?', ['%'.mb_strtolower($search).'%'])
                        ->orWhereHas('user', function ($query) use ($search) {
                            $query->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($search).'%'])
                                ->orWhereRaw('LOWER(email) LIKE ?', ['%'.mb_strtolower($search).'%']);
                        });
                });
            })
            ->when(in_array($status, $statusValues, true), fn ($query) => $query->where('status', $status))
            ->when($payment === 'success', fn ($query) => $query->whereIn('payment_status', [
                PaymentStatus::Paid->value,
                PaymentStatus::Settlement->value,
            ]))
            ->when($payment === 'pending', fn ($query) => $query->whereIn('payment_status', [
                PaymentStatus::Pending->value,
                PaymentStatus::Authorize->value,
                PaymentStatus::Challenge->value,
            ]))
            ->when($payment === 'failed', fn ($query) => $query->whereIn('payment_status', [
                PaymentStatus::Expire->value,
                PaymentStatus::Cancel->value,
                PaymentStatus::Deny->value,
                PaymentStatus::Failure->value,
            ]))
            ->when($payment === 'refunded', fn ($query) => $query->whereIn('payment_status', [
                PaymentStatus::Refund->value,
                PaymentStatus::PartialRefund->value,
                PaymentStatus::Chargeback->value,
                PaymentStatus::PartialChargeback->value,
            ]))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.invitations.index', compact('invitations', 'search', 'status', 'payment'));
    }

    public function show(Invitation $invitation, Request $request): View
    {
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'invitation.viewed_by_admin',
            'subject_type' => 'invitation',
            'subject_id' => $invitation->id,
            'description' => 'Super Admin membuka detail undangan '.$invitation->slug,
            'ip_address' => $request->ip(),
        ]);

        $invitation->load([
            'user',
            'template',
            'events',
            'groom',
            'bride',
            'transactions' => fn ($query) => $query->latest('id'),
        ]);

        $auditLogs = ActivityLog::where('subject_type', 'invitation')
            ->where('subject_id', $invitation->id)
            ->with('user')
            ->latest('id')
            ->limit(20)
            ->get();

        return view('admin.invitations.show', compact('invitation', 'auditLogs'));
    }

    public function updateStatus(
        UpdateInvitationStatusRequest $request,
        Invitation $invitation,
        AdminActionService $service,
    ): RedirectResponse {
        $data = $request->validated();
        $service->setInvitationStatus(
            $invitation,
            InvitationStatus::from((string) $data['status']),
            $request->user(),
            $data['reason'],
            $request->ip(),
        );

        return back()->with('status', 'Status undangan berhasil diperbarui.');
    }
}
