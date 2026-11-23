<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Exceptions\Payment\PaymentException;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\Admin\AdminActionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function index(Request $request): View
    {
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $status = (string) $request->query('status', '');

        $transactions = Transaction::query()
            ->with(['user', 'invitation'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->whereRaw('LOWER(order_id) LIKE ?', ['%'.mb_strtolower($search).'%'])
                        ->orWhereRaw('LOWER(midtrans_transaction_id) LIKE ?', ['%'.mb_strtolower($search).'%'])
                        ->orWhereHas('user', function ($query) use ($search) {
                            $query->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($search).'%'])
                                ->orWhereRaw('LOWER(email) LIKE ?', ['%'.mb_strtolower($search).'%']);
                        })
                        ->orWhereHas('invitation', function ($query) use ($search) {
                            $query->whereRaw('LOWER(title) LIKE ?', ['%'.mb_strtolower($search).'%'])
                                ->orWhereRaw('LOWER(slug) LIKE ?', ['%'.mb_strtolower($search).'%']);
                        });
                });
            })
            ->when($status === 'success', fn ($query) => $query->whereIn('transaction_status', [
                PaymentStatus::Paid->value,
                PaymentStatus::Settlement->value,
            ]))
            ->when($status === 'pending', fn ($query) => $query->whereIn('transaction_status', [
                PaymentStatus::Pending->value,
                PaymentStatus::Authorize->value,
                PaymentStatus::Challenge->value,
            ]))
            ->when($status === 'failed', fn ($query) => $query->whereIn('transaction_status', [
                PaymentStatus::Expire->value,
                PaymentStatus::Cancel->value,
                PaymentStatus::Deny->value,
                PaymentStatus::Failure->value,
            ]))
            ->when($status === 'refunded', fn ($query) => $query->whereIn('transaction_status', [
                PaymentStatus::Refund->value,
                PaymentStatus::PartialRefund->value,
                PaymentStatus::Chargeback->value,
                PaymentStatus::PartialChargeback->value,
            ]))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.transactions.index', compact('transactions', 'search', 'status'));
    }

    public function show(Transaction $transaction): View
    {
        $transaction->load(['user', 'invitation']);

        return view('admin.transactions.show', compact('transaction'));
    }

    public function check(
        Request $request,
        Transaction $transaction,
        PaymentGateway $gateway,
        AdminActionService $service,
    ): RedirectResponse {
        try {
            $result = $service->recheckTransaction($transaction, $gateway, $request->user(), $request->ip());
        } catch (PaymentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with(
            'status',
            $result->activated
                ? 'Pembayaran terverifikasi dan undangan aktif.'
                : 'Status transaksi diperbarui: '.$result->paymentStatus->value.'.',
        );
    }
}
