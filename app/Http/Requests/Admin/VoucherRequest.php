<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $code = $this->input('code');
        $this->merge([
            'code' => is_string($code) ? strtoupper(trim($code)) : $code,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $voucher = $this->route('voucher');
        $type = (string) $this->input('type', 'fixed');

        return [
            'code' => [
                'required', 'string', 'max:50', 'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('vouchers', 'code')->ignore($voucher?->id),
            ],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', Rule::in(['fixed', 'percent'])],
            'value' => [
                'required', 'numeric', 'min:0.01',
                ...($type === 'percent' ? ['max:100'] : ['max:9999999999999.99']),
            ],
            'min_purchase' => ['nullable', 'numeric', 'min:0'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.regex' => 'Kode voucher hanya boleh huruf besar, angka, strip, atau underscore.',
            'expires_at.after_or_equal' => 'Tanggal berakhir harus sama atau setelah tanggal mulai.',
        ];
    }
}
