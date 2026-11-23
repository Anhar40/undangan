<?php

namespace App\Http\Requests\Public;

use App\Enums\AttendanceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRsvpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'attendance' => ['required', Rule::enum(AttendanceStatus::class)],
            'guest_count' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'message' => ['sometimes', 'nullable', 'string', 'max:500'],
            'website' => ['sometimes', 'nullable', 'size:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'attendance.required' => 'Konfirmasi kehadiran wajib dipilih.',
            'website.size' => 'Input tidak valid.',
        ];
    }
}
