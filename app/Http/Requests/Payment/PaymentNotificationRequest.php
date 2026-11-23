<?php

namespace App\Http\Requests\Payment;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PaymentNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'string', 'max:120'],
            'status_code' => ['required', 'string', 'max:10'],
            'gross_amount' => ['required', 'numeric'],
            'transaction_status' => ['required', 'string', 'max:30'],
            'signature_key' => ['required', 'string', 'size:128'],
            'fraud_status' => ['sometimes', 'nullable', 'string', 'max:30'],
            'transaction_id' => ['sometimes', 'nullable', 'string', 'max:120'],
            'payment_type' => ['sometimes', 'nullable', 'string', 'max:60'],
            'transaction_time' => ['sometimes', 'nullable', 'string', 'max:60'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        Log::warning('payment.webhook_rejected', [
            'reason' => 'invalid_payload',
            'order_id' => $this->safeOrderId(),
            'fields' => array_keys($validator->errors()->toArray()),
        ]);

        throw (new ValidationException($validator))
            ->errorBag($this->errorBag)
            ->redirectTo($this->getRedirectUrl());
    }

    private function safeOrderId(): string
    {
        $value = $this->input('order_id');

        return preg_replace('/[^A-Za-z0-9_-]/', '', is_scalar($value) ? (string) $value : '') ?? '';
    }
}
