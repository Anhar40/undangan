<?php

namespace App\Services\Invitation;

use App\Enums\InvitationStatus;
use App\Enums\PaymentStatus;
use App\Models\Invitation;
use App\Services\Storage\MediaStorageService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class InvitationExpiryService
{
    public function __construct(private readonly ?MediaStorageService $media = null) {}

    /** Mark previews whose 10-hour window has ended without payment as EXPIRED. */
    public function expireDue(?Carbon $at = null): int
    {
        $previews = $this->expirePreviewDue($at);
        $active = $this->expireActiveDue($at);

        return $previews + $active;
    }

    public function expirePreviewDue(?Carbon $at = null): int
    {
        return DB::transaction(function () use ($at): int {
            $ids = Invitation::previewExpired($at)
                ->whereNotIn('payment_status', [
                    PaymentStatus::Paid->value,
                    PaymentStatus::Settlement->value,
                ])
                ->pluck('id');

            if ($ids->isEmpty()) {
                return 0;
            }

            $count = Invitation::whereIn('id', $ids)
                ->whereNotIn('status', [
                    InvitationStatus::Active->value,
                    InvitationStatus::PendingPayment->value,
                    InvitationStatus::Suspended->value,
                ])
                ->update(['status' => InvitationStatus::Expired]);

            if ($count > 0) {
                Log::info('invitation.previews_expired', ['count' => $count]);
            }

            return $count;
        });
    }

    /** Mark active invitations whose 1-year active window has ended as EXPIRED. */
    public function expireActiveDue(?Carbon $at = null): int
    {
        return DB::transaction(function () use ($at): int {
            $ids = Invitation::activeExpired($at)->pluck('id');

            if ($ids->isEmpty()) {
                return 0;
            }

            $count = Invitation::whereIn('id', $ids)
                ->where('status', InvitationStatus::Active->value)
                ->update(['status' => InvitationStatus::Expired]);

            if ($count > 0) {
                Log::info('invitation.active_expired', ['count' => $count]);
            }

            return $count;
        });
    }

    /** Remove old EXPIRED drafts and their uploaded files. */
    public function pruneExpired(int $olderThanDays = 30): int
    {
        $cutoff = now()->subDays(max(1, $olderThanDays));

        $invitations = Invitation::with(['galleries:id,invitation_id,image', 'giftAccounts:id,invitation_id,qr_image', 'invitationSettings:id,invitation_id,music_url,share_image,custom_settings', 'groom:id,invitation_id,photo', 'bride:id,invitation_id,photo', 'stories:id,invitation_id,image'])
            ->where('status', InvitationStatus::Expired)
            ->whereNotIn('payment_status', [
                PaymentStatus::Paid->value,
                PaymentStatus::Settlement->value,
            ])
            ->where('updated_at', '<', $cutoff)
            ->get();

        if ($invitations->isEmpty()) {
            return 0;
        }

        $mediaPaths = [];
        foreach ($invitations as $invitation) {
            $mediaPaths = array_merge($mediaPaths, $this->collectInvitationMedia($invitation));
        }

        $ids = $invitations->pluck('id')->all();

        DB::transaction(fn () => Invitation::whereIn('id', $ids)->delete());

        foreach (array_unique(array_filter($mediaPaths)) as $path) {
            $this->media?->delete($path);
        }

        foreach ($ids as $id) {
            Storage::disk('public')->deleteDirectory('invitations/'.$id);
        }

        Log::info('invitation.expired_drafts_pruned', ['count' => count($ids)]);

        return count($ids);
    }

    /** @return array<int, string> */
    private function collectInvitationMedia(Invitation $invitation): array
    {
        $paths = [];
        foreach ($invitation->galleries as $gallery) {
            if (is_string($gallery->image) && $gallery->image !== '') {
                $paths[] = $gallery->image;
            }
        }
        foreach ($invitation->giftAccounts as $account) {
            if (is_string($account->qr_image) && $account->qr_image !== '') {
                $paths[] = $account->qr_image;
            }
        }
        foreach (['groom', 'bride'] as $relation) {
            $photo = $invitation->{$relation}?->photo;
            if (is_string($photo) && $photo !== '') {
                $paths[] = $photo;
            }
        }
        foreach ($invitation->stories as $story) {
            if (is_string($story->image) && $story->image !== '') {
                $paths[] = $story->image;
            }
        }
        $settings = $invitation->invitationSettings;
        if ($settings) {
            foreach (['music_url', 'share_image'] as $field) {
                $v = $settings->{$field};
                if (is_string($v) && $v !== '') {
                    $paths[] = $v;
                }
            }
            $custom = is_array($settings->custom_settings) ? $settings->custom_settings : [];
            foreach (($custom['backgrounds'] ?? []) as $bgPaths) {
                foreach ((array) $bgPaths as $p) {
                    if (is_string($p) && $p !== '') {
                        $paths[] = $p;
                    }
                }
            }
            foreach (($custom['supporting_photos'] ?? []) as $p) {
                if (is_string($p) && $p !== '') {
                    $paths[] = $p;
                }
            }
            foreach (($custom['photo_gallery'] ?? []) as $slot) {
                foreach ((array) $slot as $p) {
                    if (is_string($p) && $p !== '') {
                        $paths[] = $p;
                    }
                }
            }
        }

        return $paths;
    }
}
