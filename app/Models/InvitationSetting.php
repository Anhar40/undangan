<?php

namespace App\Models;

use App\Enums\CoupleOrder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvitationSetting extends Model
{
    use HasFactory;

    protected $table = 'invitation_settings';

    protected $fillable = [
        'invitation_id',
        'theme',
        'primary_color',
        'secondary_color',
        'font_family',
        'couple_order',
        'music_id',
        'music_url',
        'music_autoplay',
        'whatsapp_number',
        'whatsapp_display_name',
        'whatsapp_message',
        'quote',
        'quote_source',
        'quote_is_default',
        'share_title',
        'share_description',
        'share_image',
        'show_stories',
        'show_gallery',
        'show_families',
        'show_gift',
        'show_rsvp',
        'custom_settings',
    ];

    protected function casts(): array
    {
        return [
            'couple_order' => CoupleOrder::class,
            'music_autoplay' => 'boolean',
            'quote_is_default' => 'boolean',
            'show_stories' => 'boolean',
            'show_gallery' => 'boolean',
            'show_families' => 'boolean',
            'show_gift' => 'boolean',
            'show_rsvp' => 'boolean',
            'custom_settings' => 'array',
        ];
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }

    public function music(): BelongsTo
    {
        return $this->belongsTo(Music::class);
    }

    public function resolvedMusicUrl(): ?string
    {
        if ($this->music_url) {
            return str_starts_with($this->music_url, 'http')
                ? $this->music_url
                : asset('storage/' . ltrim($this->music_url, '/'));
        }

        return $this->music?->url();
    }
}
