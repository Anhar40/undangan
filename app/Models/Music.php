<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Music extends Model
{
    use HasFactory;

    protected $table = 'musics';

    protected $fillable = [
        'title',
        'artist',
        'file_path',
        'status',
    ];

    public function invitationSettings(): HasMany
    {
        return $this->hasMany(InvitationSetting::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function url(): string
    {
        return str_starts_with($this->file_path, 'http')
            ? $this->file_path
            : asset('storage/' . ltrim($this->file_path, '/'));
    }
}
