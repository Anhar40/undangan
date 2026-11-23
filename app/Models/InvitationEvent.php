<?php

namespace App\Models;

use App\Enums\EventType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvitationEvent extends Model
{
    use HasFactory;

    protected $table = 'invitation_events';

    protected $fillable = [
        'invitation_id',
        'event_type',
        'title',
        'event_date',
        'start_time',
        'end_time',
        'timezone',
        'location_name',
        'address',
        'maps_url',
        'description',
        'dress_code',
        'live_stream_url',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => EventType::class,
            'event_date' => 'date:Y-m-d',
            'sort_order' => 'integer',
        ];
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }
}
