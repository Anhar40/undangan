<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Template extends Model
{
    use HasFactory;

    public const FEATURES = [
        'story', 'gallery', 'music', 'theme', 'rsvp',
        'families', 'gift', 'live_stream', 'countdown',
        'quote', 'multiple_events',
    ];

    /** Renderer keys are shared by the renderer, editor validation and seed data. */
    public const RENDERERS = [
        'tema1' => 'Thema 38 (tema1)',
        'tema2' => 'Thema 40 (tema2)',
        'tema3' => 'Thema 39 (tema3)',
        'tema4' => 'Thema 42 (tema4)',
        'tema5' => 'Thema 41 (tema5)',
    ];

    protected $fillable = [
        'name',
        'slug',
        'description',
        'category',
        'thumbnail',
        'template_path',
        'price',
        'version',
        'status',
        'features',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'config' => 'array',
            'price' => 'decimal:2',
        ];
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** Feature flags that drive editor menus (PRD 15). */
    public function features(): array
    {
        $defaults = array_fill_keys(self::FEATURES, false);

        return array_merge($defaults, $this->features ?? []);
    }

    public function supports(string $feature): bool
    {
        return (bool) ($this->features()[$feature] ?? false);
    }

    public function absolutePath(): string
    {
        $path = (string) $this->template_path;

        if (str_starts_with($path, 'storage/')) {
            return Storage::disk('local')->path(Str::after($path, 'storage/'));
        }

        return realpath(base_path($path)) ?: base_path($path);
    }

    public function contents(): string
    {
        $path = (string) $this->template_path;

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $response = Http::timeout(20)->get($path);

            if ($response->successful()) {
                return (string) $response->body();
            }

            throw new \RuntimeException("Gagal membaca template dari URL: {$path}");
        }

        $absolute = $this->absolutePath();

        if (File::exists($absolute)) {
            return File::get($absolute);
        }

        throw new \RuntimeException("File template tidak ditemukan: {$absolute}");
    }

    public function thumbnailUrl(): ?string
    {
        $thumbnail = $this->thumbnail;

        if (! $thumbnail) {
            return null;
        }

        // Str::startsWith() accepts an array of needles; PHP's str_starts_with()
        // only accepts a single string and throws a TypeError on PHP 8.2.
        if (Str::startsWith($thumbnail, ['http://', 'https://', '/'])) {
            return $thumbnail;
        }

        return asset('storage/'.ltrim($thumbnail, '/'));
    }

    public function coverUrl(): string
    {
        if ($this->thumbnailUrl() !== null) {
            return (string) $this->thumbnailUrl();
        }

        $art = $this->slug === 'tema2' ? 'catalog-modern.svg' : 'catalog-classic.svg';

        return asset('assets/site/img/'.$art);
    }

    /** @return array<string, string> */
    public static function rendererOptions(): array
    {
        return self::RENDERERS;
    }

    public static function hasRenderer(?string $key): bool
    {
        return is_string($key) && array_key_exists($key, self::RENDERERS);
    }

    /** Elementor timestamp countdowns are used by tema1 and tema5. */
    public static function countdownIsTimestamp(string $key): bool
    {
        return in_array($key, ['tema1', 'tema5'], true);
    }

    public static function guestLabel(string $key): string
    {
        return self::countdownIsTimestamp($key) && $key === 'tema1'
            ? 'Kepada yth:'
            : 'DEAR TO';
    }

    /** @return array<string, string> */
    public static function sourceOptions(): array
    {
        $options = [];

        foreach (File::files(config('invitation.template_path')) as $file) {
            if (strtolower($file->getExtension()) === 'html') {
                $relative = 'template/'.$file->getFilename();
                $options[$relative] = $file->getFilename();
            }
        }

        return $options;
    }
}
