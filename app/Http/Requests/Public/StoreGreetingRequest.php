<?php

namespace App\Http\Requests\Public;

use App\Enums\AttendanceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGreetingRequest extends FormRequest
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
            'message' => ['required', 'string', 'max:1000'],
            'attendance' => ['sometimes', 'nullable', Rule::enum(AttendanceStatus::class)],
            'website' => ['sometimes', 'nullable', 'size:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'message.required' => 'Ucapan wajib diisi.',
            'website.size' => 'Input tidak valid.',
        ];
    }
}
