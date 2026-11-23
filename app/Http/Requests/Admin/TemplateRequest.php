<?php

namespace App\Http\Requests\Admin;

use App\Models\Template;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $features = [];

        foreach (Template::FEATURES as $feature) {
            $features[$feature] = $this->boolean("features.{$feature}");
        }

        $price = $this->input('price');

        // The price column is DECIMAL(15,2) and the model casts it to "150000.00".
        // Accept that rendered value as whole rupiah, but keep rejecting real
        // fractions so the service never silently truncates a submitted amount.
        if (is_string($price) && preg_match('/^(-?\d+)\.0+$/', $price, $matches) === 1) {
            $price = $matches[1];
        }

        $this->merge([
            'features' => $features,
            'price' => $price,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $template = $this->route('template');
        $creating = $this->isMethod('post');
        $sources = array_keys(Template::sourceOptions());
        $slugRule = Rule::unique('templates', 'slug');

        if ($template) {
            $slugRule->ignore($template->id);
        }

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'required', 'string', 'max:50', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                $slugRule,
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'category' => ['nullable', 'string', 'max:50'],
            'price' => ['required', 'integer', 'min:0', 'max:100000000'],
            'version' => ['required', 'string', 'max:20'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'renderer' => ['required', Rule::in(array_keys(Template::rendererOptions()))],
            'template_path' => [
                $creating ? 'required_without:template_html' : 'nullable',
                'string',
                Rule::in($sources),
            ],
            'template_html' => [
                'nullable', 'file', 'max:10240',
                'mimetypes:text/html,text/plain,application/octet-stream',
            ],
            'thumbnail' => [
                'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120',
            ],
            'features' => ['array'],
            'features.*' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'price.required' => 'Harga wajib diisi.',
            'price.integer' => 'Harga harus berupa bilangan bulat rupiah.',
            'price.min' => 'Harga tidak boleh negatif.',
            'price.max' => 'Harga tidak boleh lebih dari :max.',
        ];
    }
}
