<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VoucherRequest;
use App\Models\Voucher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VoucherController extends Controller
{
    public function index(Request $request): View
    {
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $status = (string) $request->query('status', '');

        $vouchers = Voucher::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->whereRaw('LOWER(code) LIKE ?', ['%'.mb_strtolower($search).'%'])
                        ->orWhereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($search).'%']);
                });
            })
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.vouchers.index', compact('vouchers', 'search', 'status'));
    }

    public function create(): View
    {
        return view('admin.vouchers.form', [
            'voucher' => new Voucher(['is_active' => true, 'type' => 'fixed']),
        ]);
    }

    public function store(VoucherRequest $request): RedirectResponse
    {
        Voucher::create($request->validated());

        return redirect()
            ->route('admin.vouchers.index')
            ->with('status', 'Voucher berhasil ditambahkan.');
    }

    public function edit(Voucher $voucher): View
    {
        return view('admin.vouchers.form', compact('voucher'));
    }

    public function update(VoucherRequest $request, Voucher $voucher): RedirectResponse
    {
        $voucher->update($request->validated());

        return redirect()
            ->route('admin.vouchers.edit', $voucher)
            ->with('status', 'Voucher berhasil diperbarui.');
    }

    public function toggleStatus(Voucher $voucher): RedirectResponse
    {
        $voucher->update(['is_active' => ! $voucher->is_active]);

        return redirect()
            ->route('admin.vouchers.index')
            ->with('status', 'Status voucher '.$voucher->code.' diperbarui.');
    }

    public function destroy(Voucher $voucher): RedirectResponse
    {
        $voucher->delete();

        return redirect()
            ->route('admin.vouchers.index')
            ->with('status', 'Voucher dihapus.');
    }
}
