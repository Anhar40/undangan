<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\UpdateInvitationRequest;
use App\Models\ActivityLog;
use App\Models\Invitation;
use App\Models\Music;
use App\Models\Template;
use App\Services\Invitation\InvitationEditorService;
use App\Services\Invitation\InvitationSlugService;
use App\Services\Invitation\TemplateSwitchService;
use App\Services\Invitation\WeddingQuoteService;
use App\Support\IndonesianBanks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InvitationController extends Controller
{
    public function edit(Request $request, Invitation $invitation, WeddingQuoteService $quotes): View
    {
        $this->authorize('view', $invitation);

        $invitation->loadMissing([
            'template',
            'groom',
            'bride',
            'events',
            'stories',
            'families',
            'galleries',
            'giftAccounts',
            'guests',
            'greetings',
            'invitationSettings.music',
        ]);

        if ($request->user()->isSuperAdmin() && $invitation->user_id !== $request->user()->id) {
            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => 'invitation.viewed',
                'subject_type' => 'invitation',
                'subject_id' => $invitation->id,
                'description' => 'Super Admin melihat editor undangan ' . $invitation->slug,
                'ip_address' => $request->ip(),
            ]);
        }

        return view('invitations.edit', [
            'invitation' => $invitation,
            'canUpdate' => $request->user()->can('update', $invitation),
            'canSwitchTemplate' => $request->user()->can('switchTemplate', $invitation),
            'features' => $invitation->templateFeatures(),
            'banks' => IndonesianBanks::grouped(),
            'musics' => Music::active()->orderBy('title')->get(),
            'defaultQuote' => $quotes->default(),
            'publicUrl' => $invitation->publicUrl(),
            'guestName' => trim((string) $request->query('to', '')),
            'slugCheckUrl' => route('invitations.slug.check', $invitation),
            'templateSelectUrl' => route('invitations.templates.index', $invitation),
            'shareUrl' => route('share.index', ['uri' => $invitation->publicUrl()]),
        ]);
    }

    public function templates(Request $request, Invitation $invitation): View
    {
        $this->authorize('view', $invitation);

        return view('invitations.templates', [
            'invitation' => $invitation,
            'templates' => Template::active()->orderBy('category')->orderBy('name')->get(),
            'canSwitchTemplate' => $request->user()->can('switchTemplate', $invitation),
        ]);
    }

    public function switchTemplate(
        Request $request,
        Invitation $invitation,
        TemplateSwitchService $service,
    ): RedirectResponse {
        $this->authorize('switchTemplate', $invitation);

        $data = $request->validate([
            'template_id' => ['required', 'integer', Rule::exists('templates', 'id')->where('status', 'active')],
        ], [
            'template_id.required' => 'Pilih template terlebih dahulu.',
            'template_id.exists' => 'Template yang dipilih tidak tersedia.',
        ]);

        $template = Template::findOrFail((int) $data['template_id']);
        $service->switch($invitation, $template, $request->user());

        return redirect()
            ->route('invitations.edit', $invitation)
            ->with('status', 'Template undangan berhasil diperbarui menjadi ' . $template->name . '.');
    }

    public function checkSlug(
        Request $request,
        Invitation $invitation,
        InvitationSlugService $service,
    ): JsonResponse {
        $this->authorize('checkSlug', $invitation);

        $data = $request->validate([
            'slug' => ['required', 'string', 'max:80'],
        ]);

        $normalized = $service->normalize($data['slug']);
        $available = $normalized !== '' && $service->isAvailable($invitation, $normalized);

        return response()->json([
            'slug' => $normalized,
            'available' => $available,
            'message' => $available
                ? 'Slug tersedia.'
                : 'Slug tidak tersedia atau menggunakan format yang tidak diizinkan.',
            'url' => $available ? route('invitation.show', $normalized) : null,
        ]);
    }

    public function update(
        UpdateInvitationRequest $request,
        Invitation $invitation,
        InvitationEditorService $service,
        InvitationSlugService $slugs,
    ): JsonResponse|RedirectResponse {
        $data = $request->validated();
        $requestedSlug = isset($data['slug']) && is_string($data['slug']) ? trim($data['slug']) : null;
        unset($data['slug']);

        $invitation = $service->save($invitation, $data);

        if ($requestedSlug !== null && $requestedSlug !== '') {
            $slugs->change($invitation, $requestedSlug);
        } else {
            // Once nicknames are saved, replace the old template-generated URL
            // with /u/{groom}-dan-{bride}. Explicit custom slugs stay untouched.
            $slugs->syncFromCouple($invitation);
        }

        $invitation->refresh();

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'invitation.updated',
            'subject_type' => 'invitation',
            'subject_id' => $invitation->id,
            'description' => 'Editor menyimpan perubahan pada undangan ' . $invitation->slug,
            'ip_address' => $request->ip(),
        ]);

        $message = 'Perubahan tersimpan.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'saved_at' => $invitation->updated_at?->toIso8601String(),
                'public_url' => $invitation->publicUrl(),
                'share_url' => route('share.index', ['uri' => $invitation->publicUrl()]),
                'slug' => $invitation->slug,
            ]);
        }

        return back()->with('status', $message);
    }

    public function destroy(Request $request, Invitation $invitation): RedirectResponse
    {
        $this->authorize('delete', $invitation);

        $title = $invitation->title;
        $invitation->delete();

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'invitation.deleted',
            'description' => 'Customer menghapus draft undangan ' . $title,
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('dashboard')->with('status', 'Draft undangan berhasil dihapus.');
    }
}
