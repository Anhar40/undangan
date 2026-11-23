<?php

namespace App\Services\Public;

use App\Models\Invitation;
use App\Models\InvitationGreeting;
use App\Models\InvitationGuest;
use Illuminate\Support\Facades\DB;

class GuestResponseService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function storeGreeting(Invitation $invitation, array $data, ?string $ipAddress): InvitationGreeting
    {
        return DB::transaction(function () use ($invitation, $data, $ipAddress): InvitationGreeting {
            $greeting = $invitation->greetings()->create([
                'guest_name' => $this->clean($data['name'] ?? ''),
                'message' => $this->clean($data['message'] ?? ''),
                'attendance_status' => $data['attendance'] ?? null,
                'status' => 'approved',
                'ip_address' => $ipAddress,
            ]);

            if (! empty($data['attendance'])) {
                $this->storeGuest($invitation, $data, $ipAddress);
            }

            return $greeting;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function storeRsvp(Invitation $invitation, array $data, ?string $ipAddress): InvitationGuest
    {
        return DB::transaction(fn (): InvitationGuest => $this->storeGuest($invitation, $data, $ipAddress));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function storeGuest(Invitation $invitation, array $data, ?string $ipAddress): InvitationGuest
    {
        return $invitation->guests()->create([
            'guest_name' => $this->clean($data['name'] ?? ''),
            'attendance_status' => $data['attendance'] ?? 'HADIR',
            'guest_count' => max(1, min(20, (int) ($data['guest_count'] ?? 1))),
            'message' => isset($data['message']) ? $this->clean($data['message']) : null,
            'ip_address' => $ipAddress,
        ]);
    }

    private function clean(mixed $value): string
    {
        return trim(strip_tags((string) $value));
    }
}
