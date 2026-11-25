<?php

namespace App\Http\Controllers\Customer;

use App\Contracts\PaymentGateway;
use App\Data\PaymentInstructionData;
use App\Data\PaymentRequestData;
use App\Enums\PaymentStatus;
use App\Exceptions\Payment\PaymentException;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\Voucher;
use App\Services\Payment\PaymentService;
use App\Services\Payment\PaymentVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function activation(Request $request, Invitation $invitation): View
    {
        $this->authorize('activate', $invitation);

        $pendingTransaction = $invitation->transactions()
            ->where('transaction_status', PaymentStatus::Pending->value)
            ->where(function ($query) {
                $query->whereNull('payment_expires_at')
                    ->orWhere('payment_expires_at', '>', now());
            })
            ->latest()
            ->first();

        $voucherCode = strtoupper(trim((string) $request->query('voucher', old('voucher_code', ''))));
        $voucher = null;
        $voucherError = null;
        $discount = 0;
        $price = (int) $invitation->price;

        if ($voucherCode !== '') {
            $voucher = Voucher::where('code', $voucherCode)->first();
            if (! $voucher) {
                $voucherError = 'Kode voucher tidak ditemukan.';
            } elseif (! $voucher->isAvailableForAmount($price)) {
                $voucherError = 'Voucher tidak dapat digunakan untuk transaksi ini.';
            } else {
                $discount = $voucher->discountFor($price);
                if ($discount <= 0) {
                    $voucherError = 'Voucher tidak memberikan diskon untuk transaksi ini.';
                    $voucher = null;
                }
            }
        }

        return view('payments.activation', [
            'invitation' => $invitation,
            'price' => $price,
            'discount' => $discount,
            'finalPrice' => max(0, $price - $discount),
            'voucherCode' => $voucherCode,
            'voucher' => $voucherError ? null : $voucher,
            'voucherError' => $voucherError,
            'paymentMethods' => PaymentRequestData::labels(),
            'defaultPaymentType' => (string) config('services.midtrans.default_payment_type', 'qris'),
            'pendingTransaction' => $pendingTransaction,
        ]);
    }

    public function start(
        Request $request,
        Invitation $invitation,
        PaymentService $service,
    ): RedirectResponse {
        $this->authorize('activate', $invitation);

        try {
            $paymentType = (string) $request->input(
                'payment_method',
                config('services.midtrans.default_payment_type', 'qris'),
            );
            $voucherCode = strtoupper(trim((string) $request->input('voucher_code', '')));
            $transaction = $service->begin($invitation, $request->user(), $paymentType, $voucherCode !== '' ? $voucherCode : null);

            if ($transaction->isSuccessful()) {
                return redirect()->route('invitations.payment.status', $invitation)
                    ->with('status', 'Voucher berhasil diklaim. Undangan Anda sudah aktif.');
            }

            return redirect()->route('invitations.payment.status', $invitation)
                ->with('status', 'Silakan selesaikan pembayaran untuk mengaktifkan undangan.');
        } catch (PaymentException $exception) {
            return back()
                ->withInput($request->except('_token'))
                ->with('error', $exception->getMessage());
        }
    }

    public function status(Invitation $invitation): View|RedirectResponse
    {
        $this->authorize('view', $invitation);

        $transaction = $invitation->transactions()->latest()->first();

        if ($transaction === null) {
            return redirect()->route('invitations.activation', $invitation);
        }

        return view('payments.status', [
            'invitation' => $invitation,
            'transaction' => $transaction,
            'instructions' => PaymentInstructionData::fromTransaction($transaction),
        ]);
    }

    public function check(
        Request $request,
        Invitation $invitation,
        PaymentGateway $gateway,
        PaymentVerificationService $verification,
    ): RedirectResponse {
        $this->authorize('activate', $invitation);

        $transaction = $invitation->transactions()->latest()->first();

        if ($transaction === null) {
            return redirect()->route('invitations.activation', $invitation)
                ->with('error', 'Belum ada transaksi yang dapat diperiksa.');
        }

        try {
            $payload = $gateway->fetchStatus($transaction->order_id);
            $result = $verification->handleProviderStatus($transaction, $payload);

            return back()->with(
                'status',
                $result->activated
                    ? 'Pembayaran terverifikasi. Undangan Anda sudah aktif.'
                    : 'Status pembayaran diperbarui: ' . $result->paymentStatus->value . '.',
            );
        } catch (PaymentException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }
}
