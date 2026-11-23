<?php

namespace App\Services\Admin;

use App\Models\Template;
use App\Services\Storage\MediaStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TemplateManagementService
{
    public function __construct(private readonly MediaStorageService $media) {}

    public function create(array $data, ?UploadedFile $thumbnail, ?UploadedFile $html): Template
    {
        $payload = $this->payload($data);
        $payload['template_path'] = $html
            ? $this->storeHtml($data['slug'], $data['version'], $html)
            : $this->validateSource($data['template_path'] ?? null);
        $payload['thumbnail'] = $thumbnail ? $this->storeThumbnail($thumbnail) : null;

        return DB::transaction(fn (): Template => Template::create($payload));
    }

    public function update(Template $template, array $data, ?UploadedFile $thumbnail, ?UploadedFile $html): Template
    {
        $payload = $this->payload($data);
        $oldHtml = $template->template_path;
        $oldThumbnail = $template->thumbnail;

        if ($html) {
            $payload['template_path'] = $this->storeHtml($data['slug'], $data['version'], $html);
        }

        if ($thumbnail) {
            $payload['thumbnail'] = $this->storeThumbnail($thumbnail);
        }

        DB::transaction(fn () => $template->update($payload));

        $fresh = $template->refresh();

        if ($fresh->template_path !== $oldHtml) {
            $this->deleteManagedHtml($oldHtml);
        }

        if ($fresh->thumbnail !== $oldThumbnail) {
            $this->deleteManagedThumbnail($oldThumbnail);
        }

        return $fresh;
    }

    public function toggleStatus(Template $template): Template
    {
        $template->update([
            'status' => $template->isActive() ? 'inactive' : 'active',
        ]);

        return $template->refresh();
    }

    public function delete(Template $template): void
    {
        if ($template->invitations()->exists()) {
            throw ValidationException::withMessages([
                'template' => 'Template sudah digunakan undangan dan tidak dapat dihapus.',
            ]);
        }

        $html = $template->template_path;
        $thumbnail = $template->thumbnail;

        $template->delete();

        $this->deleteManagedHtml($html);
        $this->deleteManagedThumbnail($thumbnail);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function payload(array $data): array
    {
        $features = [];

        foreach (Template::FEATURES as $feature) {
            $features[$feature] = (bool) ($data['features'][$feature] ?? false);
        }

        return [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?: null,
            'category' => $data['category'] ?: null,
            'price' => (int) $data['price'],
            'version' => $data['version'],
            'status' => $data['status'],
            'features' => $features,
            'config' => [
                'renderer' => $data['renderer'],
                'save_the_date_format' => 'd.m.Y',
                'countdown_selector' => Template::countdownIsTimestamp($data['renderer'])
                    ? '.elementor-countdown-wrapper'
                    : '.wpkoi-elements-countdown-items',
                'countdown_format' => Template::countdownIsTimestamp($data['renderer']) ? 'timestamp' : 'text',
                'guest_label' => Template::guestLabel($data['renderer']),
            ],
        ];
    }

    private function validateSource(?string $path): string
    {
        if (! $path || ! File::exists(base_path($path))) {
            throw ValidationException::withMessages([
                'template_path' => 'Pilih file template HTML yang valid.',
            ]);
        }

        return $path;
    }

    private function storeHtml(string $slug, string $version, UploadedFile $file): string
    {
        $contents = File::get($file->getRealPath());

        if (! str_contains($contents, '<html') && ! str_contains($contents, '<!DOCTYPE html')) {
            throw ValidationException::withMessages([
                'template_html' => 'File harus berupa dokumen HTML.',
            ]);
        }

        $hash = substr(hash('sha256', $contents), 0, 10);
        $filename = sprintf('%s-%s-%s.html', Str::slug($slug), Str::slug($version), $hash);

        if ($this->media->shouldUseCloudinary()) {
            $tmp = tempnam(sys_get_temp_dir(), 'tpl');
            file_put_contents($tmp, $contents);
            try {
                $upload = new UploadedFile($tmp, $filename, 'text/html', null, true);
                $result = $this->media->upload($upload, 'templates/html');

                return $result['path'];
            } finally {
                @unlink($tmp);
            }
        }

        $relative = 'templates/'.$filename;
        Storage::disk('local')->put($relative, $contents);

        return 'storage/'.$relative;
    }

    private function deleteManagedHtml(?string $path): void
    {
        if (! $path) {
            return;
        }

        if (Template::where('template_path', $path)->exists()) {
            return;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $this->media->delete($path);

            return;
        }

        if (! str_starts_with($path, 'storage/templates/')) {
            return;
        }

        Storage::disk('local')->delete(Str::after($path, 'storage/'));
    }

    private function deleteManagedThumbnail(?string $path): void
    {
        if (! $path) {
            return;
        }

        if (Template::where('thumbnail', $path)->exists()) {
            return;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $this->media->delete($path);

            return;
        }

        if (str_starts_with($path, '/uploads/templates/')) {
            @unlink(public_path(ltrim($path, '/')));

            return;
        }

        if (! str_starts_with($path, '/')) {
            $this->media->delete($path);
        }
    }

    private function storeThumbnail(UploadedFile $file): string
    {
        $extension = match ($file->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw ValidationException::withMessages([
                'thumbnail' => 'Format gambar tidak didukung.',
            ]),
        };

        if ($this->media->shouldUseCloudinary()) {
            $result = $this->media->upload($file, 'templates/thumbnails');

            return $result['path'];
        }

        $directory = public_path('uploads/templates');
        File::ensureDirectoryExists($directory, 0755);
        $filename = Str::random(20).'.'.$extension;
        $file->move($directory, $filename);

        return '/uploads/templates/'.$filename;
    }
}
