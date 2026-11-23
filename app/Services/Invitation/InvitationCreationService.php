<?php

namespace App\Services\Invitation;

use App\Enums\InvitationStatus;
use App\Enums\PaymentStatus;
use App\Models\Invitation;
use App\Models\Template;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvitationCreationService
{
    public function __construct(
        private readonly TemplateSnapshotService $snapshots,
        private readonly WeddingQuoteService $quotes,
    ) {
    }

    /**
     * Create a draft from an active template and freeze everything the renderer
     * needs. The ten-hour clock starts here and is never reset by later edits.
     */
    public function create(Template $template, User $user): Invitation
    {
        if (! $template->isActive()) {
            throw ValidationException::withMessages([
                'template' => 'Template ini tidak tersedia.',
            ]);
        }

        $snapshot = $this->snapshots->make($template);
        $now = now();
        $hours = max(1, (int) config('invitation.preview_hours', 10));

        return DB::transaction(function () use ($template, $user, $snapshot, $now, $hours): Invitation {
            $invitation = Invitation::create([
                'user_id' => $user->id,
                'template_id' => $template->id,
                'template_name' => $template->name,
                'template_version' => $template->version,
                'template_path' => $template->template_path,
                'template_snapshot' => $snapshot,
                'title' => 'Undangan Pernikahan ' . $user->name,
                'slug' => $this->uniqueSlug($template),
                'status' => InvitationStatus::Draft,
                'payment_status' => PaymentStatus::Pending,
                'price' => $template->price,
                'preview_started_at' => $now,
                'preview_expires_at' => $now->copy()->addHours($hours),
            ]);

            $default = $this->quotes->default();
            $invitation->invitationSettings()->create([
                'quote' => $default['quote'],
                'quote_source' => $default['source'],
                'quote_is_default' => true,
                'custom_settings' => [
                    'quote' => $default['quote'],
                    'quote_source' => $default['source'],
                ],
            ]);

            return $invitation;
        });
    }

    private function uniqueSlug(Template $template): string
    {
        $base = Str::slug($template->name ?: 'undangan') ?: 'undangan';

        do {
            $slug = $base . '-' . strtolower(Str::random(8));
        } while (Invitation::withTrashed()->where('slug', $slug)->exists());

        return $slug;
    }
}
