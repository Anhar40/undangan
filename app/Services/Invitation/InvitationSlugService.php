<?php

namespace App\Services\Invitation;

use App\Models\Invitation;
use App\Models\InvitationSlugRedirect;
use App\Models\Template;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvitationSlugService
{
    public function normalize(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        return Str::slug(trim((string) $value), '-');
    }

    public function isAvailable(Invitation $invitation, string $slug): bool
    {
        if ($slug === '' || in_array($slug, config('invitation.reserved_slugs', []), true)) {
            return false;
        }

        $taken = Invitation::withTrashed()
            ->where('slug', $slug)
            ->whereKeyNot($invitation->id)
            ->exists();

        if ($taken) {
            return false;
        }

        return ! InvitationSlugRedirect::where('old_slug', $slug)->exists();
    }

    /** Change the slug and keep the previous URL alive through a 301 redirect. */
    public function change(Invitation $invitation, mixed $value): bool
    {
        $slug = $this->normalize($value);

        if ($slug === '' || $slug === $invitation->slug) {
            return false;
        }

        if (! $this->isAvailable($invitation, $slug)) {
            throw ValidationException::withMessages([
                'slug' => 'Slug tidak tersedia. Gunakan slug lain.',
            ]);
        }

        return DB::transaction(function () use ($invitation, $slug): bool {
            InvitationSlugRedirect::updateOrCreate([
                'old_slug' => $invitation->slug,
            ], [
                'invitation_id' => $invitation->id,
            ]);

            $invitation->update(['slug' => $slug]);

            return true;
        });
    }

    /**
     * Base URL from both nicknames, e.g. "anhar-dan-ika". Full names are only
     * a fallback when a nickname has not been filled yet.
     */
    public function nicknameSlugBase(Invitation $invitation): ?string
    {
        $invitation->loadMissing(['groom', 'bride']);

        $groom = $this->nameForSlug($invitation->groom?->nickname, $invitation->groom?->full_name);
        $bride = $this->nameForSlug($invitation->bride?->nickname, $invitation->bride?->full_name);

        if ($groom === null || $bride === null) {
            return null;
        }

        $base = $this->normalize($groom . '-dan-' . $bride);

        if ($base === '' || in_array($base, config('invitation.reserved_slugs', []), true)) {
            return null;
        }

        return rtrim(Str::substr($base, 0, 80), '-');
    }

    /** True for slugs generated from a template name plus an 8-character token. */
    public function isTemplateGeneratedSlug(Invitation $invitation): bool
    {
        if (! preg_match('/^(.+)-([a-z0-9]{8})$/', (string) $invitation->slug, $matches)) {
            return false;
        }

        $snapshot = is_array($invitation->template_snapshot) ? $invitation->template_snapshot : [];

        // The invitation may have been re-skinned after creation, so the
        // original generator name can differ from the current snapshot name.
        $bases = collect([
            $snapshot['name'] ?? null,
            $invitation->template_name,
        ])
            ->merge(Template::query()->pluck('name'))
            ->merge(Template::query()->pluck('slug'))
            ->map(fn (mixed $name): string => $this->normalize($name))
            ->filter()
            ->unique();

        return $bases->contains($matches[1]);
    }

    /**
     * Use the couple's nickname slug when the current URL is still the old
     * template-generated one. A customer-chosen slug is never overwritten
     * unless --force is used by the backfill command.
     */
    public function syncFromCouple(Invitation $invitation, bool $force = false): bool
    {
        if (! $force && ! $this->isTemplateGeneratedSlug($invitation)) {
            return false;
        }

        $base = $this->nicknameSlugBase($invitation);

        if ($base === null) {
            return false;
        }

        $slug = $this->availableNicknameSlug($invitation, $base);

        if ($slug === $invitation->slug) {
            return false;
        }

        return $this->change($invitation, $slug);
    }

    private function nameForSlug(mixed $nickname, mixed $fullName): ?string
    {
        $preferred = is_scalar($nickname) ? trim((string) $nickname) : '';

        if ($preferred === '') {
            $preferred = is_scalar($fullName) ? trim((string) $fullName) : '';
        }

        $slug = $this->normalize($preferred);

        return $slug === '' ? null : $slug;
    }

    private function availableNicknameSlug(Invitation $invitation, string $base): string
    {
        $candidate = $base;

        for ($suffix = 2; ! $this->isAvailable($invitation, $candidate); $suffix++) {
            $tail = '-' . $suffix;
            $candidate = rtrim(Str::substr($base, 0, 80 - strlen($tail)), '-') . $tail;
        }

        return $candidate;
    }
}
