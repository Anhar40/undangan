<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function get(string $key, ?string $default = null): ?string
    {
        return Cache::rememberForever("setting:{$key}", function () use ($key, $default) {
            try {
                if (! Schema::hasTable((new static())->getTable())) {
                    return $default;
                }

                $value = static::query()->where('key', $key)->value('value');

                return $value === null ? $default : (string) $value;
            } catch (QueryException) {
                // Allow pages to render before migrations are run (e.g. fresh installs).
                return $default;
            }
        });
    }

    public static function set(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("setting:{$key}");
    }
}
