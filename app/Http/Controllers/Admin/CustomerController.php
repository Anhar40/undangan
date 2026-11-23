<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCustomerStatusRequest;
use App\Models\User;
use App\Services\Admin\AdminActionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $status = (string) $request->query('status', '');

        $customers = User::query()
            ->where('role', 'customer')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($search).'%'])
                        ->orWhereRaw('LOWER(email) LIKE ?', ['%'.mb_strtolower($search).'%']);
                });
            })
            ->when(in_array($status, ['active', 'inactive'], true), fn ($query) => $query->where('status', $status))
            ->withCount(['invitations', 'transactions'])
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.customers.index', compact('customers', 'search', 'status'));
    }

    public function show(User $user): View
    {
        abort_if($user->isSuperAdmin(), 404);

        $user->load([
            'invitations' => fn ($query) => $query->latest('id')->with('template'),
            'transactions' => fn ($query) => $query->latest('id'),
        ]);

        return view('admin.customers.show', ['customer' => $user]);
    }

    public function updateStatus(
        UpdateCustomerStatusRequest $request,
        User $user,
        AdminActionService $service,
    ): RedirectResponse {
        $data = $request->validated();
        $service->setCustomerStatus(
            $user,
            UserStatus::from((string) $data['status']),
            $request->user(),
            $data['reason'] ?? null,
            $request->ip(),
        );

        return back()->with('status', 'Status akun customer berhasil diperbarui.');
    }
}
