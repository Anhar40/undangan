<?php

namespace App\Http\Controllers\Public;

use App\Enums\InvitationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreGreetingRequest;
use App\Http\Requests\Public\StoreRsvpRequest;
use App\Models\Invitation;
use App\Services\Public\GuestResponseService;
use Illuminate\Http\RedirectResponse;

class GuestResponseController extends Controller
{
    public function greeting(
        StoreGreetingRequest $request,
        Invitation $invitation,
        GuestResponseService $service,
    ): RedirectResponse {
        $this->ensureActive($invitation);

        $greeting = $service->storeGreeting($invitation, $request->validated(), $request->ip());

        return $this->redirect($invitation, $greeting->guest_name, 'post-guestbook-box')
            ->with('public_status', 'Terima kasih atas ucapan dan doa restu Anda.');
    }

    public function rsvp(
        StoreRsvpRequest $request,
        Invitation $invitation,
        GuestResponseService $service,
    ): RedirectResponse {
        $this->ensureActive($invitation);

        if (! $this->rsvpEnabled($invitation)) {
            abort(404);
        }

        $guest = $service->storeRsvp($invitation, $request->validated(), $request->ip());

        return $this->redirect($invitation, $guest->guest_name)
            ->with('public_status', 'Konfirmasi kehadiran Anda sudah kami terima.');
    }

    private function ensureActive(Invitation $invitation): void
    {
        abort_unless($invitation->status === InvitationStatus::Active, 404);
    }

    private function rsvpEnabled(Invitation $invitation): bool
    {
        $settings = $invitation->invitationSettings;

        return (bool) ($invitation->templateFeatures()['rsvp'] ?? false)
            && ($settings === null || (bool) $settings->show_rsvp);
    }

    private function redirect(Invitation $invitation, string $guestName, string $fragment = ''): RedirectResponse
    {
        $url = route('invitation.show', $invitation->slug);

        if ($guestName !== '') {
            $url .= '?to=' . rawurlencode($guestName);
        }

        if ($fragment !== '') {
            $url .= '#' . $fragment;
        }

        return redirect()->to($url);
    }
}
