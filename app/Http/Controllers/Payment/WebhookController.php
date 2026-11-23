<?php

namespace App\Http\Controllers\Payment;

use App\Exceptions\Payment\AmountMismatchException;
use App\Exceptions\Payment\InvalidSignatureException;
use App\Exceptions\Payment\PaymentException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\PaymentNotificationRequest;
use App\Services\Payment\PaymentVerificationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __invoke(
        PaymentNotificationRequest $request,
        PaymentVerificationService $verification,
    ): JsonResponse {
        try {
            $result = $verification->handleNotification($request->all());

            return response()->json([
                'status' => 'ok',
                'order_id' => $result->transaction->order_id,
                'payment_status' => $result->paymentStatus->value,
                'activated' => $result->activated,
                'stale' => $result->stale,
                'access_revoked' => $result->accessRevoked,
            ]);
        } catch (InvalidSignatureException $exception) {
            Log::warning('payment.webhook_rejected', [
                'reason' => 'invalid_signature',
                'order_id' => $this->safeOrderId($request->input('order_id')),
            ]);

            return response()->json(['status' => 'rejected', 'message' => $exception->getMessage()], 403);
        } catch (AmountMismatchException $exception) {
            Log::warning('payment.webhook_rejected', [
                'reason' => 'amount_mismatch',
                'order_id' => $this->safeOrderId($request->input('order_id')),
            ]);

            return response()->json(['status' => 'rejected', 'message' => $exception->getMessage()], 400);
        } catch (ModelNotFoundException) {
            Log::warning('payment.webhook_rejected', [
                'reason' => 'unknown_order',
                'order_id' => $this->safeOrderId($request->input('order_id')),
            ]);

            return response()->json(['status' => 'rejected', 'message' => 'Order tidak ditemukan.'], 404);
        } catch (PaymentException $exception) {
            Log::warning('payment.webhook_rejected', [
                'reason' => 'payment_exception',
                'order_id' => $this->safeOrderId($request->input('order_id')),
            ]);

            return response()->json(['status' => 'rejected', 'message' => 'Notifikasi tidak dapat diproses.'], 400);
        }
    }

    private function safeOrderId(mixed $value): string
    {
        return preg_replace('/[^A-Za-z0-9_-]/', '', is_scalar($value) ? (string) $value : '') ?? '';
    }
}
