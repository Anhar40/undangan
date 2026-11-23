<?php

namespace App\Http\Requests\Admin;

use App\Enums\InvitationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvitationStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in([InvitationStatus::Active->value, InvitationStatus::Suspended->value]),
            ],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan perubahan status wajib diisi.',
            'reason.min' => 'Alasan perubahan status minimal 5 karakter.',
        ];
    }
}
