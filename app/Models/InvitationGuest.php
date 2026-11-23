<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvitationGuest extends Model
{
    use HasFactory;

    protected $table = 'invitation_guests';

    protected $fillable = [
        'invitation_id',
        'guest_name',
        'attendance_status',
        'guest_count',
        'message',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'attendance_status' => AttendanceStatus::class,
            'guest_count' => 'integer',
        ];
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }
}
