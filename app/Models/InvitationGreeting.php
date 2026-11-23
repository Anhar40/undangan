<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvitationGreeting extends Model
{
    use HasFactory;

    protected $table = 'invitation_greetings';

    protected $fillable = [
        'invitation_id',
        'guest_name',
        'message',
        'attendance_status',
        'status',
        'ip_address',
    ];

    protected function casts(): array
    {
        return ['attendance_status' => AttendanceStatus::class];
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
}
