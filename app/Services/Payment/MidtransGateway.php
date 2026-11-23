<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Data\PaymentRequestData;
use App\Data\PaymentResponseData;
use App\Exceptions\Payment\GatewayException;
use Illuminate\Support\Facades\Log;
use Midtrans\Config;
use Midtrans\CoreApi;
use Midtrans\Transaction;
use Throwable;

class MidtransGateway implements PaymentGateway
{
    public function createTransaction(PaymentRequestData $request): PaymentResponseData
    {
        $this->configure($request->orderId);

        try {
            $response = CoreApi::charge($request->toMidtransPayload());
            $raw = $this->toArray($response);

            if ($raw === []) {
                throw new GatewayException('Respons sistem pembayaran tidak valid.');
            }

            return PaymentResponseData::fromMidtrans($raw);
        } catch (GatewayException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('midtrans.charge_failed', [
                'order_id' => preg_replace('/[^A-Za-z0-9_-]/', '', $request->orderId),
                'payment_type' => $request->paymentType,
                'message' => $exception->getMessage(),
            ]);

            throw new GatewayException('Gagal membuat transaksi pembayaran.', 0, $exception);
        }
    }

    public function fetchStatus(string $orderId): array
    {
        $this->configure();

        try {
            return $this->toArray(Transaction::status($orderId));
        } catch (Throwable $exception) {
            Log::error('midtrans.fetch_status_failed', [
                'order_id' => preg_replace('/[^A-Za-z0-9_-]/', '', $orderId),
                'message' => $exception->getMessage(),
            ]);

            throw new GatewayException('Gagal memeriksa status transaksi.', 0, $exception);
        }
    }

    public function cancel(string $orderId): void
    {
        $this->configure($orderId);

        try {
            Transaction::cancel($orderId);
        } catch (Throwable $exception) {
            Log::error('midtrans.cancel_failed', [
                'order_id' => preg_replace('/[^A-Za-z0-9_-]/', '', $orderId),
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function configure(string $idempotencyKey = ''): void
    {
        $serverKey = (string) config('services.midtrans.server_key', '');

        if ($serverKey === '') {
            throw new GatewayException('Kunci server pembayaran belum dikonfigurasi.');
        }

        Config::$serverKey = $serverKey;
        Config::$clientKey = (string) config('services.midtrans.client_key', '');
        Config::$isProduction = (bool) config('services.midtrans.is_production', false);
        Config::$isSanitized = true;
        Config::$is3ds = false;

        // The vendor appends headers to this static array on every request.
        // Reset it so long-lived workers do not accumulate duplicate headers.
        Config::$curlOptions = [CURLOPT_HTTPHEADER => []];

        $notificationUrl = (string) config('services.midtrans.notification_url', '');
        Config::$appendNotifUrl = null;
        Config::$overrideNotifUrl = $this->isPublicUrl($notificationUrl) ? $notificationUrl : null;
        Config::$paymentIdempotencyKey = $idempotencyKey !== '' ? $idempotencyKey : null;
    }

    private function isPublicUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (($parts['scheme'] ?? '') !== 'https') {
            return false;
        }

        return ! in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }

    /** @param mixed $response @return array<string, mixed> */
    private function toArray(mixed $response): array
    {
        $encoded = json_encode($response);
        $decoded = is_string($encoded) ? json_decode($encoded, true) : null;

        return is_array($decoded) ? $decoded : [];
    }
}
