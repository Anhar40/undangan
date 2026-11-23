<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MusicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'file_url' => trim((string) $this->input('file_url', '')),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $music = $this->route('music');
        $creating = $this->isMethod('post');
        $hasExistingFile = $music && is_string($music->file_path) && $music->file_path !== '';

        return [
            'title' => ['required', 'string', 'max:120'],
            'artist' => ['nullable', 'string', 'max:120'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'file_url' => ['nullable', 'url', 'max:2048'],
            'audio_file' => [
                ($creating && $this->filled('file_url') === false) || (! $creating && ! $hasExistingFile && $this->filled('file_url') === false)
                    ? 'required_without:file_url'
                    : 'nullable',
                'file',
                'mimetypes:audio/mpeg,audio/mp3,audio/wav,audio/x-wav,audio/ogg,audio/mp4,audio/aac,audio/x-m4a,application/ogg',
                'max:10240',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'audio_file.required_without' => 'Unggah file musik atau isi URL audio.',
            'audio_file.mimetypes' => 'File musik harus berformat MP3, WAV, OGG, M4A, atau AAC.',
            'audio_file.max' => 'Ukuran file musik maksimal 10 MB.',
            'file_url.url' => 'URL musik harus berupa URL yang valid.',
        ];
    }
}
