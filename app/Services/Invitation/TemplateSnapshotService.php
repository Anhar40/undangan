<?php

namespace App\Services\Invitation;

use App\Models\Invitation;
use App\Models\Template;
use App\Services\Storage\MediaStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class TemplateSnapshotService
{
    private const DISK = 'local';

    private const DIRECTORY = 'template-snapshots';

    public function __construct(private readonly ?MediaStorageService $media = null) {}

    /**
     * Freeze the exact HTML and capabilities used by a new invitation.
     * Later admin edits to the template can never affect this invitation.
     *
     * @return array<string, mixed>
     */
    public function make(Template $template): array
    {
        $html = $template->contents();
        $hash = hash('sha256', $html);
        $filename = sprintf(
            '%s-%s-%s.html',
            Str::slug($template->slug),
            Str::slug($template->version),
            substr($hash, 0, 12),
        );
        $path = self::DIRECTORY.'/'.$filename;

        if ($this->media?->shouldUseCloudinary()) {
            $path = $this->storeCloudinarySnapshot($path, $html, $filename);
        } else {
            Storage::disk(self::DISK)->makeDirectory(self::DIRECTORY);
            if (! Storage::disk(self::DISK)->exists($path)) {
                Storage::disk(self::DISK)->put($path, $html);
            }
        }

        return [
            'path' => $path,
            'filename' => $filename,
            'hash' => $hash,
            'name' => $template->name,
            'version' => $template->version,
            'template_path' => $template->template_path,
            'renderer' => $template->config['renderer'] ?? $template->slug,
            'features' => $template->features(),
            'config' => $template->config ?? [],
            'created_at' => now()->toIso8601String(),
        ];
    }

    public function htmlFor(Invitation $invitation): string
    {
        $snapshot = $invitation->template_snapshot ?? [];
        $path = $snapshot['path'] ?? null;

        if (is_string($path) && (str_starts_with($path, 'http://') || str_starts_with($path, 'https://'))) {
            $tmpDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'invite-template-snapshots';
            $tmpFile = $tmpDir.DIRECTORY_SEPARATOR.sha1($path).'.html';
            if (is_file($tmpFile)) {
                $cached = @file_get_contents($tmpFile);
                if (is_string($cached) && $cached !== '') {
                    return $cached;
                }
            }

            $response = Http::timeout(15)->connectTimeout(5)->get($path);
            if ($response->successful()) {
                $html = (string) $response->body();
                if (! is_dir($tmpDir)) {
                    @mkdir($tmpDir, 0777, true);
                }
                @file_put_contents($tmpFile, $html, LOCK_EX);

                return $html;
            }

            if (is_file($tmpFile)) {
                $cached = @file_get_contents($tmpFile);
                if (is_string($cached) && $cached !== '') {
                    return $cached;
                }
            }
        }

        if (is_string($path) && Storage::disk(self::DISK)->exists($path)) {
            return Storage::disk(self::DISK)->get($path);
        }

        // Legacy invitations created before snapshots: fall back to the file
        // recorded on the invitation rather than the current admin-managed one.
        $fallback = $invitation->template_path ? base_path($invitation->template_path) : null;

        if ($fallback && File::exists($fallback)) {
            return File::get($fallback);
        }

        // Never silently render the current admin-managed template for an
        // existing invitation: that would break snapshot immutability.
        throw new RuntimeException('Salinan template untuk undangan ini tidak ditemukan.');
    }

    private function storeCloudinarySnapshot(string $path, string $html, string $filename): string
    {
        // Upload raw snapshot through Cloudinary; store secure URL.
        $tmp = tempnam(sys_get_temp_dir(), 'tplsnap');
        file_put_contents($tmp, $html);
        try {
            $file = new UploadedFile($tmp, $filename, 'text/html', null, true);
            $result = $this->media->upload($file, self::DIRECTORY);

            return $result['path'];
        } finally {
            @unlink($tmp);
        }
    }
}
