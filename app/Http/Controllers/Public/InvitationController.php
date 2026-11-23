<?php

namespace App\Http\Controllers\Public;

use App\Enums\InvitationStatus;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\InvitationSlugRedirect;
use App\Services\Invitation\TemplateRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InvitationController extends Controller
{
    public function show(
        Request $request,
        string $slug,
        TemplateRenderer $renderer,
    ): Response|RedirectResponse {
        $invitation = Invitation::where('slug', $slug)->first();

        if (! $invitation) {
            $redirect = InvitationSlugRedirect::with('invitation')->where('old_slug', $slug)->first();

            if (! $redirect?->invitation) {
                abort(404);
            }

            $query = $request->getQueryString();
            $target = route('invitation.show', $redirect->invitation->slug)
                . ($query ? '?' . $query : '');

            return redirect()->to($target, 301);
        }

        $isActive = $invitation->status === InvitationStatus::Active
            && ! $invitation->isActiveExpired();
        $user = $request->user();
        $canViewInactive = $user !== null
            && $invitation->status !== InvitationStatus::Suspended
            && ($user->isSuperAdmin() || $invitation->user_id === $user->id);

        // Draft/pending invitations stay private. Only the owner (or a super
        // admin) may open them through the same slug link used for the live view.
        abort_unless($isActive || $canViewInactive, 404);

        $publicStatus = $request->session()->pull('public_status');
        $activationPrompt = null;

        if (! $isActive) {
            $activeExpired = $invitation->isActiveExpired()
                || ($invitation->status === InvitationStatus::Expired && $invitation->hasBeenActivated());
            $expired = $activeExpired
                || $invitation->isPreviewExpired()
                || $invitation->status === InvitationStatus::Expired;
            $remainingSeconds = $activeExpired ? 0 : $invitation->previewRemainingSeconds();
            $expiredMessage = $activeExpired
                ? 'Masa aktif undangan 1 tahun sudah berakhir. Aktifkan kembali agar undangan dapat dilihat dan dibagikan.'
                : 'Masa pratinjau sudah berakhir. Aktifkan undangan agar dapat dilihat kembali dan dibagikan.';

            $activationPrompt = [
                'title' => $activeExpired ? 'Masa Aktif Undangan Berakhir' : 'Aktifkan Undangan Digital Anda!',
                'message' => $expired
                    ? $expiredMessage
                    : 'Undangan Anda sudah siap dipublikasikan. Segera lakukan aktivasi agar undangan dapat dibagikan kepada keluarga dan kerabat.',
                'expired_message' => $expiredMessage,
                'activation_url' => route('invitations.activation', $invitation),
                'dashboard_url' => route('dashboard'),
                'price' => 'Rp' . number_format((int) $invitation->price, 0, ',', '.'),
                'remaining_seconds' => $remainingSeconds,
                'remaining_label' => gmdate('H:i:s', $remainingSeconds),
                'expired' => $expired,
            ];
        }

        $html = $renderer->renderInvitation(
            $invitation,
            $request->query('to'),
            preview: ! $isActive,
            csrfToken: csrf_token(),
            publicStatus: is_string($publicStatus) ? $publicStatus : null,
            activationPrompt: $activationPrompt,
        );

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
