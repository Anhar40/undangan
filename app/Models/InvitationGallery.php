<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvitationGallery extends Model
{
    use HasFactory;

    protected $table = 'invitation_galleries';

    protected $fillable = [
        'invitation_id',
        'image',
        'caption',
        'is_cover',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_cover' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }

    public function url(): string
    {
        return str_starts_with($this->image, 'http')
            ? $this->image
            : asset('storage/' . ltrim($this->image, '/'));
    }
}
