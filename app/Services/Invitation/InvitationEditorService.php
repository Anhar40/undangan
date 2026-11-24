<?php

namespace App\Services\Invitation;

use App\Enums\CoupleOrder;
use App\Enums\EventType;
use App\Models\Invitation;
use App\Models\InvitationBride;
use App\Models\InvitationFamily;
use App\Models\InvitationGallery;
use App\Models\InvitationGiftAccount;
use App\Models\InvitationGreeting;
use App\Models\InvitationGroom;
use App\Models\InvitationGuest;
use App\Models\InvitationSetting;
use App\Models\InvitationStory;
use App\Services\Storage\MediaStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class InvitationEditorService
{
    private ?string $optimizedTempPath = null;

    public function __construct(
        private readonly WhatsAppService $whatsapp,
        private readonly WeddingQuoteService $quotes,
        private readonly MediaStorageService $media,
    ) {}

    /**
     * Persist one editor save atomically. The invitation's preview timestamps
     * are deliberately never touched (PRD section 9.2).
     *
     * @param  array<string, mixed>  $data
     */
    public function save(Invitation $invitation, array $data): Invitation
    {
        /** @var array<int, string> $stored */
        $stored = [];
        /** @var array<int, string|null> $obsolete */
        $obsolete = [];

        try {
            DB::transaction(function () use ($invitation, $data, &$stored, &$obsolete): void {
                $this->saveBasics($invitation, $data);
                $this->saveCoverPhoto($invitation, $data, $stored, $obsolete);
                $this->savePerson($invitation, 'groom', InvitationGroom::class, $data, $stored, $obsolete);
                $this->savePerson($invitation, 'bride', InvitationBride::class, $data, $stored, $obsolete);

                $features = $invitation->templateFeatures();

                if (array_key_exists('events', $data)) {
                    $this->saveEvents(
                        $invitation,
                        $data['events'],
                        (bool) ($features['live_stream'] ?? false),
                        true,
                    );
                }

                if (($features['story'] ?? false) && array_key_exists('stories', $data)) {
                    $this->saveStories($invitation, $data, $stored, $obsolete);
                }

                if (($features['families'] ?? false) && array_key_exists('families', $data)) {
                    $this->saveFamilies($invitation, $data['families']);
                }

                if (($features['gallery'] ?? false) && array_key_exists('gallery_items', $data)) {
                    $this->saveGallery($invitation, $data, $stored, $obsolete);
                }

                if (($features['gallery'] ?? false) && array_key_exists('photo_gallery', $data)) {
                    $this->savePhotoGallery($invitation, $data, $stored, $obsolete);
                }

                if (($features['gallery'] ?? false)
                    && (array_key_exists('supporting_photos_new', $data)
                        || array_key_exists('supporting_photos_remove', $data))
                ) {
                    $this->saveSupportingPhotos($invitation, $data, $stored, $obsolete);
                }

                if (($features['gift'] ?? false) && array_key_exists('gift_accounts', $data)) {
                    $this->saveGiftAccounts($invitation, $data, $stored, $obsolete);
                }

                if (array_key_exists('guests', $data) || array_key_exists('guest_remove', $data)) {
                    $this->saveGuests($invitation, $data);
                }

                if (array_key_exists('greetings', $data) || array_key_exists('greeting_remove', $data)) {
                    $this->saveGreetings($invitation, $data);
                }

                $this->saveSettings($invitation, $data, $features, $obsolete);
                $this->saveShareImage($invitation, $data, $stored, $obsolete);
                $this->saveMusicFile($invitation, $data, $stored, $obsolete);
                $this->saveBackgrounds($invitation, $data, $stored, $obsolete);
            });
        } catch (Throwable $exception) {
            foreach ($stored as $path) {
                $this->media->delete($path);
            }

            throw $exception;
        }

        // Backfilled photo-gallery slots can reuse gallery/background files.
        // Never delete a file that another live field or row still references.
        $referenced = $this->referencedPaths($invitation);

        foreach (array_unique(array_filter($obsolete)) as $path) {
            if (! isset($referenced[$path])) {
                $this->media->delete($path);
            }
        }

        return $invitation->refresh();
    }

    /** @param array<string, mixed> $data */
    private function saveBasics(Invitation $invitation, array $data): void
    {
        if (array_key_exists('title', $data)) {
            $invitation->title = trim((string) $data['title']) ?: $invitation->title;
            $invitation->save();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $stored
     * @param  array<int, string|null>  $obsolete
     */
    private function saveCoverPhoto(Invitation $invitation, array $data, array &$stored, array &$obsolete): void
    {
        $upload = $data['photos']['cover'] ?? null;
        $remove = (bool) ($data['remove_photo']['cover'] ?? false);

        if (! $upload instanceof UploadedFile && ! $remove) {
            return;
        }

        $photo = $invitation->cover_photo;

        if ($upload instanceof UploadedFile) {
            $photo = $this->store($upload, $invitation, 'cover', $stored);
            if ($invitation->cover_photo) {
                $obsolete[] = $invitation->cover_photo;
            }
        } elseif ($remove) {
            if ($invitation->cover_photo) {
                $obsolete[] = $invitation->cover_photo;
            }
            $photo = null;
        }

        $invitation->update(['cover_photo' => $photo]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $stored
     * @param  array<int, string|null>  $obsolete
     */
    private function savePerson(
        Invitation $invitation,
        string $role,
        string $modelClass,
        array $data,
        array &$stored,
        array &$obsolete,
    ): void {
        if (! array_key_exists($role, $data)) {
            return;
        }

        $input = is_array($data[$role]) ? $data[$role] : [];
        $person = $modelClass::firstOrNew(['invitation_id' => $invitation->id]);
        $photo = $person->photo;
        $upload = $data['photos'][$role] ?? null;
        $remove = (bool) ($data['remove_photo'][$role] ?? false);

        if ($upload instanceof UploadedFile) {
            $photo = $this->store($upload, $invitation, $role, $stored);
            if ($person->photo) {
                $obsolete[] = $person->photo;
            }
        } elseif ($remove) {
            if ($person->photo) {
                $obsolete[] = $person->photo;
            }
            $photo = null;
        }

        // A section save may contain only part of the person form. Only touch
        // fields that were actually submitted so sibling values survive.
        $attributes = [
            'invitation_id' => $invitation->id,
            'photo' => $photo,
        ];

        $textFields = ['full_name', 'nickname', 'father_name', 'mother_name', 'instagram'];

        foreach ($textFields as $field) {
            if (array_key_exists($field, $input)) {
                $attributes[$field] = $this->nullableString($input[$field]);
            }
        }

        if (array_key_exists('social_links', $input)) {
            $attributes['social_links'] = $this->socialLinks($input['social_links']);
        }

        $person->fill($attributes)->save();
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function saveEvents(
        Invitation $invitation,
        array $rows,
        bool $withLiveStream,
        bool $multipleEvents = true,
    ): void {
        $invitation->events()->delete();

        if (! $multipleEvents) {
            $rows = array_slice($rows, 0, 1);
        }

        foreach (array_values($rows) as $index => $row) {
            $row = is_array($row) ? $row : [];

            if (blank($row['title'] ?? null) && blank($row['event_date'] ?? null)) {
                continue;
            }

            $invitation->events()->create([
                'event_type' => EventType::tryFrom((string) ($row['event_type'] ?? '')) ?? EventType::Akad,
                'title' => $this->nullableString($row['title'] ?? null),
                'event_date' => $row['event_date'] ?? null,
                'start_time' => $row['start_time'] ?? '08:00',
                'end_time' => $this->nullableString($row['end_time'] ?? null),
                'timezone' => $this->nullableString($row['timezone'] ?? null) ?: 'Asia/Jakarta',
                'location_name' => $this->nullableString($row['location_name'] ?? null),
                'address' => $this->nullableString($row['address'] ?? null),
                'maps_url' => $this->nullableString($row['maps_url'] ?? null),
                'description' => $this->nullableString($row['description'] ?? null),
                'dress_code' => $this->nullableString($row['dress_code'] ?? null),
                'live_stream_url' => $withLiveStream
                    ? $this->nullableString($row['live_stream_url'] ?? null)
                    : null,
                'sort_order' => (int) ($row['sort_order'] ?? $index),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $stored
     * @param  array<int, string|null>  $obsolete
     */
    private function saveStories(Invitation $invitation, array $data, array &$stored, array &$obsolete): void
    {
        // Keep the submitted keys: upload inputs are keyed by the same index.
        $rows = is_array($data['stories'] ?? null) ? $data['stories'] : [];
        $keep = [];

        foreach ($rows as $index => $row) {
            $row = is_array($row) ? $row : [];
            $existing = $this->findOwned(InvitationStory::class, $invitation, $row['id'] ?? null);
            $image = $existing?->image;
            $upload = $data['stories_images'][$index] ?? null;
            $remove = (bool) ($data['stories_remove_image'][$index] ?? false);

            if ($upload instanceof UploadedFile) {
                $image = $this->store($upload, $invitation, 'story-'.$index, $stored);
                if ($existing?->image) {
                    $obsolete[] = $existing->image;
                }
            } elseif ($remove) {
                if ($existing?->image) {
                    $obsolete[] = $existing->image;
                }
                $image = null;
            }

            if ($this->rowIsBlank($row, ['title', 'description', 'story_date']) && ! $image) {
                continue;
            }

            $story = $existing ?? new InvitationStory(['invitation_id' => $invitation->id]);
            $story->fill([
                'invitation_id' => $invitation->id,
                'title' => $this->nullableString($row['title'] ?? null),
                'description' => $this->nullableString($row['description'] ?? null),
                'story_date' => $this->nullableString($row['story_date'] ?? null),
                'image' => $image,
                'sort_order' => (int) ($row['sort_order'] ?? $index),
            ])->save();

            $keep[] = $story->id;
        }

        $this->deleteMissingFiles(InvitationStory::class, $invitation, $keep, $obsolete, 'image');
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function saveFamilies(Invitation $invitation, array $rows): void
    {
        $keep = [];

        foreach (array_values($rows) as $index => $row) {
            $row = is_array($row) ? $row : [];
            $name = $this->nullableString($row['name'] ?? null);

            if ($name === null) {
                continue;
            }

            $family = $this->findOwned(InvitationFamily::class, $invitation, $row['id'] ?? null)
                ?? new InvitationFamily(['invitation_id' => $invitation->id]);

            $family->fill([
                'invitation_id' => $invitation->id,
                'name' => $name,
                'sort_order' => (int) ($row['sort_order'] ?? $index),
            ])->save();

            $keep[] = $family->id;
        }

        $this->deleteMissing(InvitationFamily::class, $invitation, $keep);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $stored
     * @param  array<int, string|null>  $obsolete
     */
    private function saveGallery(Invitation $invitation, array $data, array &$stored, array &$obsolete): void
    {
        // Keep the submitted keys: upload inputs are keyed by the same index.
        $rows = is_array($data['gallery_items'] ?? null) ? $data['gallery_items'] : [];
        $keep = [];
        $coverId = null;

        foreach ($rows as $index => $row) {
            $row = is_array($row) ? $row : [];
            $existing = $this->findOwned(InvitationGallery::class, $invitation, $row['id'] ?? null);
            $image = $existing?->image;
            $upload = $data['gallery_images'][$index] ?? null;
            $remove = (bool) ($data['gallery_remove_image'][$index] ?? false);

            if ($upload instanceof UploadedFile) {
                $image = $this->store($upload, $invitation, 'gallery-'.$index, $stored);
                if ($existing?->image) {
                    $obsolete[] = $existing->image;
                }
            } elseif ($remove) {
                if ($existing?->image) {
                    $obsolete[] = $existing->image;
                }
                $image = null;
            }

            if (! $image) {
                continue;
            }

            $isCover = (bool) ($row['is_cover'] ?? false);
            $photo = $existing ?? new InvitationGallery(['invitation_id' => $invitation->id]);
            $photo->fill([
                'invitation_id' => $invitation->id,
                'image' => $image,
                'caption' => $this->nullableString($row['caption'] ?? null),
                'is_cover' => $isCover,
                'sort_order' => (int) ($row['sort_order'] ?? $index),
            ])->save();

            if ($isCover) {
                $coverId = $photo->id;
            }
            $keep[] = $photo->id;
        }

        $newUploads = is_array($data['gallery_new'] ?? null) ? $data['gallery_new'] : [];
        $sortOrders = array_map(
            fn ($row): int => (int) (is_array($row) ? ($row['sort_order'] ?? 0) : 0),
            $rows,
        );
        $nextSort = max([-1, ...$sortOrders]) + 1;

        foreach ($newUploads as $upload) {
            if (! $upload instanceof UploadedFile) {
                continue;
            }

            $image = $this->store($upload, $invitation, 'gallery', $stored);
            $photo = $invitation->galleries()->create([
                'image' => $image,
                'caption' => null,
                'is_cover' => $coverId === null && count($keep) === 0,
                'sort_order' => $nextSort++,
            ]);

            $keep[] = $photo->id;
        }

        if ($coverId !== null) {
            InvitationGallery::where('invitation_id', $invitation->id)
                ->where('id', '!=', $coverId)
                ->update(['is_cover' => false]);
        }

        $this->deleteMissingFiles(InvitationGallery::class, $invitation, $keep, $obsolete, 'image');
    }

    /**
     * Cover and footer photos are template-level settings rather than couple
     * photos. Each slot holds at most two photos; uploading a slot replaces
     * that slot's whole set. Sub-cover is no longer an editable slot: the
     * sub-cover section of tema2 follows the footer photo in the renderer.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $stored
     * @param  array<int, string|null>  $obsolete
     */
    private function savePhotoGallery(Invitation $invitation, array $data, array &$stored, array &$obsolete): void
    {
        $input = is_array($data['photo_gallery'] ?? null) ? $data['photo_gallery'] : [];
        $settings = InvitationSetting::firstOrNew(['invitation_id' => $invitation->id]);
        $custom = is_array($settings->custom_settings) ? $settings->custom_settings : [];
        $current = is_array($custom['photo_gallery'] ?? null) ? $custom['photo_gallery'] : [];
        $limit = (int) config('invitation.upload.max_cover_footer_photos', 2);
        $changed = false;

        foreach (['cover', 'footer'] as $slot) {
            $uploads = is_array($input[$slot] ?? null) ? $input[$slot] : [];
            $uploads = array_values(array_filter(
                $uploads,
                static fn ($file): bool => $file instanceof UploadedFile,
            ));

            if ($uploads !== []) {
                foreach ((array) ($current[$slot] ?? []) as $old) {
                    if (is_string($old)) {
                        $obsolete[] = $old;
                    }
                }

                $paths = [];
                foreach (array_slice($uploads, 0, $limit) as $upload) {
                    $paths[] = $this->store($upload, $invitation, 'photo-'.$slot, $stored);
                }

                $current[$slot] = $paths;
                $changed = true;

                continue;
            }

            // Legacy rows may hold more photos than the current limit. Trim
            // them so every saved slot obeys the same two-photo cap.
            $paths = array_values(array_filter(
                (array) ($current[$slot] ?? []),
                static fn ($path): bool => is_string($path) && $path !== '',
            ));
            $trimmed = array_slice($paths, 0, $limit);

            if ($trimmed !== (array) ($current[$slot] ?? [])) {
                foreach (array_slice($paths, $limit) as $old) {
                    $obsolete[] = $old;
                }

                $current[$slot] = $trimmed;
                $changed = true;
            }
        }

        // Drop any legacy sub-cover slot so old payloads cannot resurrect it.
        if (array_key_exists('sub_cover', $current)) {
            unset($current['sub_cover']);
            $changed = true;
        }

        if ($changed) {
            $custom['photo_gallery'] = $current;
            $settings->update(['custom_settings' => $custom]);
        }
    }

    /**
     * Foto Pendukung is a small, separate set of decorative photos. It is not
     * part of the gallery grid and never reuses gallery captions or cover flags.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $stored
     * @param  array<int, string|null>  $obsolete
     */
    private function saveSupportingPhotos(Invitation $invitation, array $data, array &$stored, array &$obsolete): void
    {
        $settings = InvitationSetting::firstOrNew(['invitation_id' => $invitation->id]);
        $custom = is_array($settings->custom_settings) ? $settings->custom_settings : [];
        $current = array_values(array_filter(
            (array) ($custom['supporting_photos'] ?? []),
            static fn ($path): bool => is_string($path) && $path !== '',
        ));
        $removals = is_array($data['supporting_photos_remove'] ?? null)
            ? $data['supporting_photos_remove']
            : [];
        $kept = [];

        foreach ($current as $index => $path) {
            if (filter_var($removals[$index] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $obsolete[] = $path;

                continue;
            }

            $kept[] = $path;
        }

        $uploads = is_array($data['supporting_photos_new'] ?? null) ? $data['supporting_photos_new'] : [];

        foreach ($uploads as $upload) {
            if ($upload instanceof UploadedFile) {
                $kept[] = $this->store($upload, $invitation, 'photo-supporting', $stored);
            }
        }

        if ($kept === $current && ! array_key_exists('supporting_photos_new', $data)
            && ! array_key_exists('supporting_photos_remove', $data)
        ) {
            return;
        }

        $custom['supporting_photos'] = array_values($kept);
        $settings->update(['custom_settings' => $custom]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $stored
     * @param  array<int, string|null>  $obsolete
     */
    private function saveGiftAccounts(Invitation $invitation, array $data, array &$stored, array &$obsolete): void
    {
        $rows = is_array($data['gift_accounts'] ?? null) ? $data['gift_accounts'] : [];
        $keep = [];

        foreach ($rows as $index => $row) {
            $row = is_array($row) ? $row : [];
            $existing = $this->findOwned(InvitationGiftAccount::class, $invitation, $row['id'] ?? null);
            $fields = ['label', 'account_name', 'account_number'];

            if ($this->rowIsBlank($row, $fields)) {
                continue;
            }

            $account = $existing ?? new InvitationGiftAccount(['invitation_id' => $invitation->id]);
            $account->fill([
                'invitation_id' => $invitation->id,
                'label' => $this->nullableString($row['label'] ?? null),
                'account_name' => $this->nullableString($row['account_name'] ?? null),
                'account_number' => $this->nullableString($row['account_number'] ?? null),
                'sort_order' => (int) ($row['sort_order'] ?? $index),
            ])->save();

            $keep[] = $account->id;
        }

        $this->deleteMissing(InvitationGiftAccount::class, $invitation, $keep);
    }

    /** @param array<string, mixed> $data */
    private function saveGuests(Invitation $invitation, array $data): void
    {
        $rows = is_array($data['guests'] ?? null) ? $data['guests'] : [];
        $removals = is_array($data['guest_remove'] ?? null) ? $data['guest_remove'] : [];
        $keep = [];

        foreach ($rows as $row) {
            $row = is_array($row) ? $row : [];
            $name = $this->nullableString($row['guest_name'] ?? null);

            if ($name === null) {
                continue;
            }

            $guest = $this->findOwned(InvitationGuest::class, $invitation, $row['id'] ?? null)
                ?? new InvitationGuest(['invitation_id' => $invitation->id]);
            $attendance = $row['attendance_status'] ?? null;
            $attendance = is_string($attendance) && $attendance !== '' ? $attendance : 'HADIR';

            $guest->fill([
                'invitation_id' => $invitation->id,
                'guest_name' => $name,
                'attendance_status' => $attendance,
                'guest_count' => max(1, min(50, (int) ($row['guest_count'] ?? 1))),
                'message' => $this->nullableString($row['message'] ?? null),
            ])->save();

            $keep[] = $guest->id;
        }

        foreach ($removals as $id => $remove) {
            if (! filter_var($remove, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }

            $guest = $this->findOwned(InvitationGuest::class, $invitation, $id);
            $guest?->delete();
        }

        // A payload that only contains guest_remove must not be treated as a
        // full replacement of the guest list.
        if (array_key_exists('guests', $data)) {
            $this->deleteMissing(InvitationGuest::class, $invitation, $keep);
        }
    }

    /** @param array<string, mixed> $data */
    private function saveGreetings(Invitation $invitation, array $data): void
    {
        $rows = is_array($data['greetings'] ?? null) ? $data['greetings'] : [];
        $removals = is_array($data['greeting_remove'] ?? null) ? $data['greeting_remove'] : [];

        foreach ($rows as $row) {
            $row = is_array($row) ? $row : [];
            $greeting = $this->findOwned(InvitationGreeting::class, $invitation, $row['id'] ?? null);

            if (! $greeting) {
                continue;
            }

            $status = $row['status'] ?? null;
            $greeting->update([
                'status' => in_array($status, ['approved', 'hidden'], true) ? $status : 'approved',
            ]);
        }

        foreach ($removals as $id => $remove) {
            if (! filter_var($remove, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }

            $greeting = $this->findOwned(InvitationGreeting::class, $invitation, $id);
            $greeting?->delete();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $stored
     * @param  array<int, string|null>  $obsolete
     */
    private function saveShareImage(Invitation $invitation, array $data, array &$stored, array &$obsolete): void
    {
        $upload = $data['share_image'] ?? null;
        $remove = filter_var($data['share_image_remove'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (! $upload instanceof UploadedFile && ! $remove) {
            return;
        }

        $settings = InvitationSetting::firstOrNew(['invitation_id' => $invitation->id]);
        $path = $settings->share_image;

        if ($upload instanceof UploadedFile) {
            $path = $this->store($upload, $invitation, 'share', $stored);
            if ($settings->share_image) {
                $obsolete[] = $settings->share_image;
            }
        } elseif ($remove) {
            if ($settings->share_image) {
                $obsolete[] = $settings->share_image;
            }
            $path = null;
        }

        $settings->update(['share_image' => $path]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string|null>  $stored
     * @param  array<int, string|null>  $obsolete
     */
    private function saveMusicFile(Invitation $invitation, array $data, array &$stored, array &$obsolete): void
    {
        $upload = $data['music_file'] ?? null;
        $remove = filter_var($data['music_file_remove'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (! $upload instanceof UploadedFile && ! $remove) {
            return;
        }

        $settings = InvitationSetting::firstOrNew(['invitation_id' => $invitation->id]);
        $path = $settings->music_url;

        if ($upload instanceof UploadedFile) {
            $path = $this->store($upload, $invitation, 'music', $stored);
            if ($settings->music_url && ! str_starts_with($settings->music_url, 'http')) {
                $obsolete[] = $settings->music_url;
            }
            // Custom upload takes precedence over library selection.
            $settings->music_id = null;
        } elseif ($remove) {
            if ($settings->music_url && ! str_starts_with($settings->music_url, 'http')) {
                $obsolete[] = $settings->music_url;
            }
            $path = null;
        }

        $settings->music_url = $path;
        $settings->save();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, bool>  $features
     * @param  array<int, string|null>  $obsolete
     */
    private function saveSettings(Invitation $invitation, array $data, array $features, array &$obsolete = []): void
    {
        $input = is_array($data['settings'] ?? null) ? $data['settings'] : [];
        $settings = InvitationSetting::firstOrNew(['invitation_id' => $invitation->id]);
        $custom = is_array($settings->custom_settings) ? $settings->custom_settings : [];
        $default = $this->quotes->default();

        foreach (['invitation_message', 'timezone', 'gallery_limit', 'gift_address'] as $key) {
            if (array_key_exists($key, $input)) {
                $custom[$key] = $this->nullableString($input[$key]);
            }
        }

        $quote = (string) ($settings->quote ?? '');
        $quoteSource = (string) ($settings->quote_source ?? '');
        $quoteIsDefault = (bool) ($settings->quote_is_default ?? false);

        if (($input['quote_action'] ?? null) === 'default') {
            $quote = $default['quote'];
            $quoteSource = $default['source'];
            $quoteIsDefault = true;
        } else {
            if (array_key_exists('quote', $input)) {
                $quote = (string) ($this->nullableString($input['quote']) ?? '');
            }
            if (array_key_exists('quote_source', $input)) {
                $quoteSource = (string) ($this->nullableString($input['quote_source']) ?? '');
            }
            if (array_key_exists('quote', $input) || array_key_exists('quote_source', $input)) {
                $quoteIsDefault = $quote === $default['quote'] && $quoteSource === $default['source'];
            }
        }

        if (! ($features['quote'] ?? false)) {
            $quote = '';
            $quoteSource = '';
            $quoteIsDefault = false;
        }

        $custom['quote'] = $quote !== '' ? $quote : null;
        $custom['quote_source'] = $quoteSource !== '' ? $quoteSource : null;

        $usesTheme = (bool) ($features['theme'] ?? false);
        $usesMusic = (bool) ($features['music'] ?? false);

        $whatsappNumber = $settings->whatsapp_number;
        if (array_key_exists('whatsapp_number', $input)) {
            try {
                $whatsappNumber = $this->whatsapp->normalize($input['whatsapp_number']);
            } catch (InvalidArgumentException) {
                $whatsappNumber = null;
            }
        }

        $whatsappDisplayName = $settings->whatsapp_display_name;
        if (array_key_exists('whatsapp_display_name', $input)) {
            $whatsappDisplayName = $this->nullableString($input['whatsapp_display_name']);
        }

        $shareMessage = $input['share_message'] ?? $input['whatsapp_message'] ?? null;

        // Section-by-section saves only send the settings owned by that panel.
        // Every untouched value must survive unchanged.
        $themeFields = [];
        $coupleOrderFields = [];

        if (array_key_exists('couple_order', $input)) {
            $order = CoupleOrder::tryFrom((string) $input['couple_order']);
            $coupleOrderFields['couple_order'] = $order;
        }

        foreach (['theme', 'primary_color', 'secondary_color', 'font_family'] as $key) {
            if ($usesTheme && array_key_exists($key, $input)) {
                $themeFields[$key] = $this->nullableString($input[$key]);
            }
        }

        $musicFields = [];

        if ($usesMusic) {
            if (array_key_exists('music_id', $input)) {
                $selectedMusicId = ! empty($input['music_id']) ? (int) $input['music_id'] : null;
                $musicFields['music_id'] = $selectedMusicId;
                if ($selectedMusicId !== null && $settings->music_url && ! str_starts_with($settings->music_url, 'http')) {
                    $obsolete[] = $settings->music_url;
                    $musicFields['music_url'] = null;
                } elseif ($selectedMusicId !== null) {
                    $musicFields['music_url'] = null;
                }
            }
            if (array_key_exists('music_autoplay', $input)) {
                $musicFields['music_autoplay'] = (bool) $input['music_autoplay'];
            }
        }

        $settings->fill(array_merge([
            'invitation_id' => $invitation->id,
            'whatsapp_number' => $whatsappNumber,
            'whatsapp_display_name' => $whatsappDisplayName,
            'whatsapp_message' => array_key_exists('share_message', $input) || array_key_exists('whatsapp_message', $input)
                ? $this->nullableString($shareMessage)
                : $settings->whatsapp_message,
            'quote' => $quote !== '' ? $quote : null,
            'quote_source' => $quoteSource !== '' ? $quoteSource : null,
            'quote_is_default' => $quoteIsDefault,
            'share_title' => array_key_exists('share_title', $input)
                ? $this->nullableString($input['share_title'])
                : $settings->share_title,
            'share_description' => array_key_exists('share_description', $input)
                ? $this->nullableString($input['share_description'])
                : $settings->share_description,
            'show_stories' => (bool) ($features['story'] ?? false)
                && InvitationStory::where('invitation_id', $invitation->id)->exists(),
            'show_gallery' => (bool) ($features['gallery'] ?? false)
                && InvitationGallery::where('invitation_id', $invitation->id)->exists(),
            'show_families' => (bool) ($features['families'] ?? false)
                && InvitationFamily::where('invitation_id', $invitation->id)->exists(),
            'show_gift' => (bool) ($features['gift'] ?? false)
                && (
                    InvitationGiftAccount::where('invitation_id', $invitation->id)->exists()
                    || trim((string) ($custom['gift_address'] ?? '')) !== ''
                ),
            'show_rsvp' => (bool) ($features['rsvp'] ?? false),
            'custom_settings' => $custom,
        ], $themeFields, $musicFields, $coupleOrderFields))->save();
    }

    /**
     * Background slots are template-specific. Only the slots declared in the
     * frozen template config are accepted, so unsupported uploads are ignored.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $stored
     * @param  array<int, string|null>  $obsolete
     */
    private function saveBackgrounds(Invitation $invitation, array $data, array &$stored, array &$obsolete): void
    {
        $slots = $invitation->templateConfig()['backgrounds'] ?? [];
        $uploads = is_array($data['backgrounds'] ?? null) ? $data['backgrounds'] : [];
        $removals = is_array($data['background_remove'] ?? null) ? $data['background_remove'] : [];

        if (! is_array($slots) || $slots === []) {
            return;
        }

        $settings = InvitationSetting::firstOrNew(['invitation_id' => $invitation->id]);
        $custom = is_array($settings->custom_settings) ? $settings->custom_settings : [];
        $current = is_array($custom['backgrounds'] ?? null) ? $custom['backgrounds'] : [];
        $changed = false;

        foreach (array_keys($slots) as $slot) {
            $slotUploads = $uploads[$slot] ?? [];

            if ($slotUploads instanceof UploadedFile) {
                $slotUploads = [$slotUploads];
            }

            $slotUploads = is_array($slotUploads) ? array_values($slotUploads) : [];

            if ($slotUploads !== []) {
                $paths = [];

                foreach ($slotUploads as $upload) {
                    if ($upload instanceof UploadedFile) {
                        $paths[] = $this->store($upload, $invitation, 'background-'.$slot, $stored);
                    }
                }

                if ($paths !== []) {
                    foreach ($current[$slot] ?? [] as $old) {
                        $obsolete[] = $old;
                    }
                    $current[$slot] = $paths;
                    $changed = true;
                }

                continue;
            }

            if (filter_var($removals[$slot] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                foreach ($current[$slot] ?? [] as $old) {
                    $obsolete[] = $old;
                }
                unset($current[$slot]);
                $changed = true;
            }
        }

        if ($changed) {
            $custom['backgrounds'] = $current;
            $settings->update(['custom_settings' => $custom]);
        }
    }

    /**
     * All image paths still owned by the invitation after the save. Some
     * photo-gallery slots intentionally reuse gallery/background uploads, so
     * removing one slot must not remove a file another slot still displays.
     *
     * @return array<string, true>
     */
    private function referencedPaths(Invitation $invitation): array
    {
        $paths = [];
        $add = static function (mixed $value) use (&$paths): void {
            foreach ((array) $value as $path) {
                if (is_string($path) && $path !== '') {
                    $paths[$path] = true;
                }
            }
        };

        $fresh = $invitation->fresh();

        if ($fresh !== null) {
            $add([$fresh->cover_photo, $fresh->bride?->photo, $fresh->groom?->photo]);
        }

        $settings = InvitationSetting::where('invitation_id', $invitation->id)->first();

        if ($settings !== null) {
            $add($settings->share_image);
            $custom = is_array($settings->custom_settings) ? $settings->custom_settings : [];

            foreach (['backgrounds', 'photo_gallery', 'supporting_photos'] as $group) {
                foreach ((array) ($custom[$group] ?? []) as $slotPaths) {
                    $add($slotPaths);
                }
            }
        }

        $add(InvitationGallery::where('invitation_id', $invitation->id)->pluck('image')->all());
        $add(InvitationStory::where('invitation_id', $invitation->id)->pluck('image')->all());

        return $paths;
    }

    /** @param class-string $modelClass */
    private function findOwned(string $modelClass, Invitation $invitation, mixed $id): ?object
    {
        if (! is_numeric($id) || (int) $id <= 0) {
            return null;
        }

        return $modelClass::where('id', (int) $id)
            ->where('invitation_id', $invitation->id)
            ->first();
    }

    /** @param class-string $modelClass */
    private function deleteMissing(string $modelClass, Invitation $invitation, array $keepIds): void
    {
        $query = $modelClass::where('invitation_id', $invitation->id);

        if ($keepIds !== []) {
            $query->whereNotIn('id', $keepIds);
        }

        $query->delete();
    }

    /**
     * Delete rows that were omitted from a submitted collection and collect
     * their files so the transaction cleanup can remove them from storage.
     *
     * @param  class-string  $modelClass
     * @param  array<int, string|null>  $obsolete
     */
    private function deleteMissingFiles(
        string $modelClass,
        Invitation $invitation,
        array $keepIds,
        array &$obsolete,
        string $fileColumn,
    ): void {
        $query = $modelClass::where('invitation_id', $invitation->id);

        if ($keepIds !== []) {
            $query->whereNotIn('id', $keepIds);
        }

        foreach ($query->get(['id', $fileColumn]) as $row) {
            $path = $row->{$fileColumn};

            if ($path) {
                $obsolete[] = $path;
            }
        }

        $query->delete();
    }

    /**
     * @param  array<int, string>  $stored
     */
    private function store(UploadedFile $file, Invitation $invitation, string $prefix, array &$stored): string
    {
        $this->optimizedTempPath = null;
        $file = $this->optimize($file);

        try {
            $result = $this->media->upload($file, 'invitations/'.$invitation->id.'/'.$prefix);
            $path = $result['path'];
        } finally {
            if ($this->optimizedTempPath !== null) {
                @unlink($this->optimizedTempPath);
                $this->optimizedTempPath = null;
            }
        }

        $stored[] = $path;

        return $path;
    }

    /**
     * Downscale oversized uploads before storage. GD is optional: when it is
     * unavailable or fails (memory), the validated original is kept.
     */
    private function optimize(UploadedFile $file): UploadedFile
    {
        if (! extension_loaded('gd')) {
            return $file;
        }

        $source = $file->getRealPath();
        $info = $source ? @getimagesize($source) : false;
        if (! is_array($info)) {
            return $file;
        }

        $max = max(1, (int) config('invitation.upload.max_dimension', 1600));
        $width = (int) ($info[0] ?? 0);
        $height = (int) ($info[1] ?? 0);
        $type = (int) ($info[2] ?? 0);

        if ($width < 1 || $height < 1) {
            return $file;
        }

        $needsResize = $width > $max || $height > $max;
        // Re-encode large originals even if within dimension limit to reduce payload.
        $needsReencode = $file->getSize() !== false && $file->getSize() > (900 * 1024);
        if (! $needsResize && ! $needsReencode) {
            return $file;
        }

        $prevLimit = ini_get('memory_limit');
        @ini_set('memory_limit', '512M');

        try {
            $ratio = $needsResize ? min($max / $width, $max / $height) : 1;
            $newWidth = max(1, (int) floor($width * $ratio));
            $newHeight = max(1, (int) floor($height * $ratio));

            $original = match ($type) {
                IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
                IMAGETYPE_PNG => @imagecreatefrompng($source),
                IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : false,
                default => false,
            };

            if ($original === false) {
                return $file;
            }

            $resized = @imagecreatetruecolor($newWidth, $newHeight);
            if ($resized === false) {
                imagedestroy($original);
                return $file;
            }

            // Preserve PNG alpha; fill white for JPEG to avoid black background.
            if ($type === IMAGETYPE_PNG) {
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
                imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);
            } elseif ($type === IMAGETYPE_WEBP) {
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
            } else {
                $white = imagecolorallocate($resized, 255, 255, 255);
                imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $white);
            }

            imagecopyresampled($resized, $original, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            $temporary = tempnam(sys_get_temp_dir(), 'ud-img-');
            if ($temporary === false) {
                imagedestroy($original);
                imagedestroy($resized);
                return $file;
            }

            $mime = 'image/jpeg';
            $ok = false;
            switch ($type) {
                case IMAGETYPE_PNG:
                    $ok = imagepng($resized, $temporary, 6);
                    $mime = 'image/png';
                    break;
                case IMAGETYPE_WEBP:
                    if (function_exists('imagewebp')) {
                        $ok = imagewebp($resized, $temporary, 82);
                        $mime = 'image/webp';
                    } else {
                        $ok = imagejpeg($resized, $temporary, 82);
                        $mime = 'image/jpeg';
                    }
                    break;
                default:
                    $ok = imagejpeg($resized, $temporary, 82);
                    $mime = 'image/jpeg';
                    break;
            }

            imagedestroy($original);
            imagedestroy($resized);

            if (! $ok || ! is_file($temporary) || filesize($temporary) === 0) {
                @unlink($temporary);
                return $file;
            }

            // Only use optimized file if it's smaller or we had to resize.
            $optimizedSize = filesize($temporary);
            $originalSize = $file->getSize();
            if (! $needsResize && $originalSize !== false && $optimizedSize !== false && $optimizedSize >= $originalSize) {
                @unlink($temporary);
                return $file;
            }

            $this->optimizedTempPath = $temporary;

            return new UploadedFile(
                $temporary,
                $file->getClientOriginalName(),
                $mime,
                null,
                true,
            );
        } catch (\Throwable) {
            return $file;
        } finally {
            if (is_string($prevLimit) && $prevLimit !== '') {
                @ini_set('memory_limit', $prevLimit);
            }
        }
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $fields
     */
    private function rowIsBlank(array $row, array $fields): bool
    {
        foreach ($fields as $field) {
            if (! blank($row[$field] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private function socialLinks(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, 'is_scalar'));
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $links = [];

        foreach (preg_split('/\R+/', $value) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', explode('|', $line, 2));
            $links[] = [
                'label' => $parts[0] ?? 'Link',
                'url' => $parts[1] ?? ($parts[0] ?? ''),
            ];
        }

        return $links;
    }
}
