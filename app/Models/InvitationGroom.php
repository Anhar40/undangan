<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvitationGroom extends Model
{
    use HasFactory;

    protected $table = 'invitation_grooms';

    protected $fillable = [
        'invitation_id',
        'full_name',
        'nickname',
        'photo',
        'father_name',
        'mother_name',
        'instagram',
        'social_links',
    ];

    protected function casts(): array
    {
        return ['social_links' => 'array'];
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }
}
