<?php

namespace App\Services\Storage;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class MediaStorageService
{
    /**
     * Upload a file to Cloudinary when configured, otherwise fall back to the
     * local public disk. Returns a stored value suitable for DB persistence:
     * - Cloudinary: secure URL (and we embed public_id in path-style value is not needed; we store URL)
     * - Local: relative path like "invitations/1/cover/file.jpg"
     */
    public function upload(UploadedFile $file, string $folder, string $publicDisk = 'public'): array
    {
        if ($this->shouldUseCloudinary()) {
            return $this->uploadToCloudinary($file, $folder);
        }

        $path = $file->store($folder, $publicDisk);

        return [
            'path' => $path,
            'url' => asset('storage/'.ltrim($path, '/')),
            'provider' => 'local',
            'public_id' => null,
        ];
    }

    /** Delete a stored media value (URL or local relative path). */
    public function delete(?string $value, string $publicDisk = 'public'): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        if ($this->isCloudinaryUrl($value)) {
            $publicId = $this->publicIdFromUrl($value);
            if ($publicId) {
                $this->deleteFromCloudinary($publicId, $this->resourceTypeFromUrl($value));
            }

            return;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return;
        }

        Storage::disk($publicDisk)->delete($value);
    }

    /** Resolve a stored value to a displayable URL. */
    public function url(?string $value, string $publicDisk = 'public'): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        if ($this->shouldUseCloudinary()) {
            // Legacy local path but Cloudinary is active - best effort local URL.
            // This should be rare after migration.
            return asset('storage/'.ltrim($value, '/'));
        }

        return asset('storage/'.ltrim($value, '/'));
    }

    public function shouldUseCloudinary(): bool
    {
        return (string) config('filesystems.default') === 'cloudinary'
            || (bool) config('filesystems.disks.cloudinary.url');
    }

    private function uploadToCloudinary(UploadedFile $file, string $folder): array
    {
        $config = $this->cloudinaryConfig();
        $timestamp = time();
        $folder = trim($folder, '/');
        $publicId = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $publicId = ($publicId !== '' ? $publicId : 'upload').'-'.Str::lower(Str::random(8));

        $paramsToSign = [
            'folder' => $folder,
            'public_id' => $publicId,
            'timestamp' => $timestamp,
        ];
        ksort($paramsToSign);
        $signatureBase = collect($paramsToSign)
            ->map(fn ($v, $k) => $k.'='.$v)
            ->implode('&');
        $signature = sha1($signatureBase.$config['secret']);

        $resourceType = $this->resourceTypeForFile($file);
        $endpoint = "https://api.cloudinary.com/v1_1/{$config['cloud']}/{$resourceType}/upload";

        $response = Http::asMultipart()
            ->timeout(60)
            ->post($endpoint, [
                ['name' => 'file', 'contents' => fopen($file->getRealPath(), 'r'), 'filename' => $file->getClientOriginalName()],
                ['name' => 'api_key', 'contents' => $config['key']],
                ['name' => 'timestamp', 'contents' => (string) $timestamp],
                ['name' => 'signature', 'contents' => $signature],
                ['name' => 'folder', 'contents' => $folder],
                ['name' => 'public_id', 'contents' => $publicId],
            ]);

        if (! $response->successful()) {
            Log::warning('cloudinary.upload_failed', ['status' => $response->status(), 'body' => $response->json('error')]);
            throw new RuntimeException('Gagal mengunggah file ke Cloudinary.');
        }

        $url = (string) $response->json('secure_url', $response->json('url', ''));

        if ($url === '') {
            throw new RuntimeException('Respons Cloudinary tidak valid.');
        }

        return [
            'path' => $url,
            'url' => $url,
            'provider' => 'cloudinary',
            'public_id' => (string) $response->json('public_id', $folder.'/'.$publicId),
            'resource_type' => $resourceType,
        ];
    }

    private function deleteFromCloudinary(string $publicId, string $resourceType = 'image'): void
    {
        try {
            $config = $this->cloudinaryConfig();
            $timestamp = time();
            $paramsToSign = [
                'public_id' => $publicId,
                'timestamp' => $timestamp,
            ];
            ksort($paramsToSign);
            $signatureBase = collect($paramsToSign)->map(fn ($v, $k) => $k.'='.$v)->implode('&');
            $signature = sha1($signatureBase.$config['secret']);

            $endpoint = "https://api.cloudinary.com/v1_1/{$config['cloud']}/{$resourceType}/destroy";
            Http::asForm()->timeout(30)->post($endpoint, [
                'public_id' => $publicId,
                'api_key' => $config['key'],
                'timestamp' => $timestamp,
                'signature' => $signature,
            ]);
        } catch (\Throwable $e) {
            Log::warning('cloudinary.delete_failed', ['public_id' => $publicId, 'error' => $e->getMessage()]);
        }
    }

    private function cloudinaryConfig(): array
    {
        $url = (string) config('filesystems.disks.cloudinary.url', env('CLOUDINARY_URL', ''));
        if ($url !== '') {
            $parts = parse_url($url);
            if (is_array($parts) && ($parts['scheme'] ?? '') === 'cloudinary') {
                return [
                    'cloud' => $parts['host'] ?? '',
                    'key' => $parts['user'] ?? '',
                    'secret' => $parts['pass'] ?? '',
                ];
            }
        }

        $config = [
            'cloud' => (string) config('filesystems.disks.cloudinary.cloud', ''),
            'key' => (string) config('filesystems.disks.cloudinary.key', ''),
            'secret' => (string) config('filesystems.disks.cloudinary.secret', ''),
        ];

        if ($config['cloud'] === '' || $config['key'] === '' || $config['secret'] === '') {
            throw new RuntimeException('Konfigurasi Cloudinary tidak lengkap.');
        }

        return $config;
    }

    private function isCloudinaryUrl(string $value): bool
    {
        return str_contains($value, 'res.cloudinary.com/') || str_contains($value, 'cloudinary.com/');
    }

    private function publicIdFromUrl(string $url): ?string
    {
        // Typical: https://res.cloudinary.com/<cloud>/image/upload/v123/folder/name.ext
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }
        if (! preg_match('#/(?:image|video|raw)/upload/(?:v\d+/)?(.+)$#', $path, $m)) {
            return null;
        }
        $publicId = $m[1];
        // remove extension
        $publicId = preg_replace('/\.[A-Za-z0-9]+$/', '', $publicId);

        return $publicId ?: null;
    }

    private function resourceTypeFromUrl(string $url): string
    {
        if (str_contains($url, '/video/upload/')) {
            return 'video';
        }
        if (str_contains($url, '/raw/upload/')) {
            return 'raw';
        }

        return 'image';
    }

    private function resourceTypeForFile(UploadedFile $file): string
    {
        $mime = (string) $file->getMimeType();
        if (str_starts_with($mime, 'video/')) {
            return 'video';
        }
        if (str_starts_with($mime, 'audio/')) {
            return 'video';
        } // cloudinary treats audio as video resource type
        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }

        return 'raw';
    }
}
