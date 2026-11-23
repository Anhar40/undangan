<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvitationGiftAccount extends Model
{
    use HasFactory;

    protected $table = 'invitation_gift_accounts';

    protected $fillable = [
        'invitation_id',
        'label',
        'account_name',
        'account_number',
        'qr_image',
        'gift_address',
        'sort_order',
    ];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }

    public function qrUrl(): ?string
    {
        if (! $this->qr_image) {
            return null;
        }

        return str_starts_with($this->qr_image, 'http')
            ? $this->qr_image
            : asset('storage/' . ltrim($this->qr_image, '/'));
    }
}
