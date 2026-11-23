<?php

namespace App\Http\Controllers\Customer;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function index(Request $request): View
    {
        $status = trim((string) $request->query('status', ''));
        $validStatus = in_array($status, array_column(PaymentStatus::cases(), 'value'), true) ? $status : '';

        $transactions = Transaction::query()
            ->where('user_id', $request->user()->id)
            ->with('invitation')
            ->when($validStatus !== '', fn ($query) => $query->where('transaction_status', $validStatus))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('transactions.index', [
            'transactions' => $transactions,
            'statuses' => PaymentStatus::cases(),
            'activeStatus' => $validStatus,
        ]);
    }
}
