<?php

namespace App\Http\Requests\Customer;

use App\Enums\AttendanceStatus;
use App\Enums\CoupleOrder;
use App\Enums\EventType;
use App\Models\Invitation;
use App\Services\Invitation\InvitationSlugService;
use App\Services\Invitation\WhatsAppService;
use App\Support\IndonesianBanks;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class UpdateInvitationRequest extends FormRequest
{
    private const MAX_GALLERY_PHOTOS = 8;

    private const MAX_SUPPORTING_PHOTOS = 3;

    private const COLLECTIONS = [
        'events',
        'stories',
        'families',
        'gallery_items',
        'gift_accounts',
        'guests',
        'greetings',
    ];

    public function authorize(): bool
    {
        $invitation = $this->route('invitation');

        return $invitation instanceof Invitation
            && $this->user()?->can('update', $invitation) === true;
    }

    protected function prepareForValidation(): void
    {
        // Hidden markers let the form intentionally submit an empty collection
        // (for example after the customer deletes every gallery photo).
        // Never synthesize a collection when its panel was not submitted:
        // the service treats presence as "replace this collection" and would
        // otherwise delete data owned by another section.
        foreach (self::COLLECTIONS as $key) {
            if ($this->has($key . '_present') && ! $this->exists($key)) {
                $this->merge([$key => []]);
            }
        }

        if ($this->exists('settings')) {
            $settings = $this->input('settings');

            if (is_array($settings)) {
                // Only normalize flags that were actually posted. A missing
                // checkbox means "no change", not "false".
                if (array_key_exists('music_autoplay', $settings)) {
                    $settings['music_autoplay'] = $this->boolean('settings.music_autoplay');
                }

                if (array_key_exists('timezone', $settings)) {
                    $settings['timezone'] = $this->normalizeTimezone($settings['timezone']);

                    if ($settings['timezone'] === null) {
                        unset($settings['timezone']);
                    }
                }

                $this->merge(['settings' => $settings]);
            }
        }

        // The Acara panel used free-text timezone and URL inputs. Normalize the
        // common shortcuts and scheme-less links here so a valid customer edit
        // is not rejected with a 422 that the browser cannot explain.
        if ($this->exists('events')) {
            $events = $this->input('events');

            if (is_array($events)) {
                foreach ($events as $index => $event) {
                    if (! is_array($event)) {
                        continue;
                    }

                    if (array_key_exists('timezone', $event)) {
                        $events[$index]['timezone'] = $this->normalizeTimezone($event['timezone']);
                    }

                    foreach (['maps_url', 'live_stream_url'] as $urlKey) {
                        if (array_key_exists($urlKey, $event)) {
                            $events[$index][$urlKey] = $this->normalizeUrl($event[$urlKey]);
                        }
                    }
                }

                $this->merge(['events' => $events]);
            }
        }

        if ($this->exists('gallery_items')) {
            $gallery = $this->input('gallery_items');

            if (is_array($gallery)) {
                foreach ($gallery as $index => $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $gallery[$index]['is_cover'] = filter_var(
                        $item['is_cover'] ?? false,
                        FILTER_VALIDATE_BOOLEAN,
                    );
                }

                $this->merge(['gallery_items' => $gallery]);
            }
        }

        if ($this->exists('gift_accounts')) {
            $accounts = $this->input('gift_accounts');

            if (is_array($accounts)) {
                foreach ($accounts as $index => $account) {
                    if (! is_array($account) || ! array_key_exists('label', $account)) {
                        continue;
                    }

                    $bank = IndonesianBanks::find(is_scalar($account['label']) ? (string) $account['label'] : null);
                    if ($bank !== null) {
                        $accounts[$index]['label'] = $bank['name'];
                    }
                }

                $this->merge(['gift_accounts' => $accounts]);
            }
        }
    }

    /** Validate invitation-scoped values that depend on the frozen template. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $invitation = $this->route('invitation');

            if (! $invitation instanceof Invitation) {
                return;
            }

            $slug = trim((string) $this->input('slug', ''));

            if ($slug !== '') {
                $service = app(InvitationSlugService::class);
                $normalized = $service->normalize($slug);

                if ($normalized !== $slug) {
                    $validator->errors()->add('slug', 'Gunakan huruf kecil, angka, dan tanda hubung.');
                } elseif (! $service->isAvailable($invitation, $slug)) {
                    $validator->errors()->add('slug', 'Slug ini sudah digunakan atau dipesan sistem.');
                }
            }

            $backgrounds = is_array($this->input('backgrounds')) ? array_keys($this->input('backgrounds')) : [];
            $removals = is_array($this->input('background_remove')) ? array_keys($this->input('background_remove')) : [];
            $supported = array_keys((array) ($invitation->templateConfig()['backgrounds'] ?? []));
            $unknown = array_diff(array_unique([...$backgrounds, ...$removals]), $supported);

            if ($unknown !== []) {
                $validator->errors()->add('backgrounds', 'Template ini tidak mendukung latar tersebut.');
            }

            $galleryRows = is_array($this->input('gallery_items')) ? $this->input('gallery_items') : [];
            $galleryUploads = is_array($this->file('gallery_new')) ? $this->file('gallery_new') : [];
            $keptGallery = 0;

            foreach ($galleryRows as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }

                $currentUpload = $this->file('gallery_images.' . $index);
                $isRemoved = filter_var($this->input('gallery_remove_image.' . $index, false), FILTER_VALIDATE_BOOLEAN);
                $hasImage = $currentUpload instanceof UploadedFile
                    || (! $isRemoved && $invitation->galleries()->whereKey($row['id'] ?? null)->exists());

                if ($hasImage) {
                    $keptGallery++;
                }
            }

            $keptGallery += count(array_filter(
                $galleryUploads,
                static fn ($file): bool => $file instanceof UploadedFile,
            ));

            // Foto Galeri is optional and may hold up to eight photos. Foto
            // Pendukung is a separate, smaller set used only for decorative
            // slots, so it must never be counted as gallery content.
            if ($keptGallery > self::MAX_GALLERY_PHOTOS) {
                $validator->errors()->add('gallery_items', 'Galeri maksimal berisi 8 foto.');
            }

            // Only the Foto dan Galeri panel sends this marker. Other panels
            // must keep their existing data without being forced to re-upload
            // every photo slot.
            if (! $this->has('photo_gallery_present')) {
                return;
            }

            $settings = $invitation->invitationSettings;
            $customSettings = is_array($settings?->custom_settings) ? $settings->custom_settings : [];
            $storedSlots = is_array($customSettings['photo_gallery'] ?? null)
                ? $customSettings['photo_gallery']
                : [];

            $storedSupporting = is_array($customSettings['supporting_photos'] ?? null)
                ? $customSettings['supporting_photos']
                : [];
            $removedSupporting = 0;

            foreach ((array) $this->input('supporting_photos_remove') as $remove) {
                if (filter_var($remove, FILTER_VALIDATE_BOOLEAN)) {
                    $removedSupporting++;
                }
            }

            $keptSupporting = max(0, count($storedSupporting) - min($removedSupporting, count($storedSupporting)));
            $supportingUploads = $this->file('supporting_photos_new');
            $supportingUploads = is_array($supportingUploads) ? $supportingUploads : [];
            $keptSupporting += count(array_filter(
                $supportingUploads,
                static fn ($file): bool => $file instanceof UploadedFile,
            ));

            if ($keptSupporting > self::MAX_SUPPORTING_PHOTOS) {
                $validator->errors()->add('supporting_photos_new', 'Foto pendukung maksimal 3 foto.');
            }

            if (! $this->hasFile('photo_gallery.cover') && ($storedSlots['cover'] ?? []) === []) {
                $validator->errors()->add('photo_gallery.cover', 'Foto cover wajib diunggah.');
            }
            if (! $this->hasFile('photo_gallery.footer') && ($storedSlots['footer'] ?? []) === []) {
                $validator->errors()->add('photo_gallery.footer', 'Foto footer wajib diunggah.');
            }
        });
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $image = [
            'nullable',
            'image',
            'mimes:jpg,jpeg,png,webp',
            'max:' . (int) config('invitation.upload.max_kb', 5120),
            'dimensions:max_width=' . (int) config('invitation.upload.max_dimension', 1600)
                . ',max_height=' . (int) config('invitation.upload.max_dimension', 1600),
        ];

        $backgroundRules = [];
        $invitation = $this->route('invitation');
        $maxEvents = 10;

        if ($invitation instanceof Invitation) {
            $backgroundRules['background_remove'] = ['sometimes', 'array'];

            foreach (array_keys((array) ($invitation->templateConfig()['backgrounds'] ?? [])) as $slot) {
                $backgroundRules['backgrounds.' . $slot] = ['sometimes', 'array', 'max:5'];
                $backgroundRules['backgrounds.' . $slot . '.*'] = $image;
                $backgroundRules['background_remove.' . $slot] = ['nullable', 'boolean'];
            }
        }

        return array_merge([
            'title' => ['sometimes', 'required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],

            'groom' => ['sometimes', 'array'],
            'groom.full_name' => ['nullable', 'string', 'max:120'],
            'groom.nickname' => ['nullable', 'string', 'max:80'],
            'groom.father_name' => ['nullable', 'string', 'max:120'],
            'groom.mother_name' => ['nullable', 'string', 'max:120'],
            'groom.instagram' => ['nullable', 'string', 'max:120'],
            'groom.social_links' => ['nullable', 'string', 'max:1000'],

            'bride' => ['sometimes', 'array'],
            'bride.full_name' => ['nullable', 'string', 'max:120'],
            'bride.nickname' => ['nullable', 'string', 'max:80'],
            'bride.father_name' => ['nullable', 'string', 'max:120'],
            'bride.mother_name' => ['nullable', 'string', 'max:120'],
            'bride.instagram' => ['nullable', 'string', 'max:120'],
            'bride.social_links' => ['nullable', 'string', 'max:1000'],

            'photos' => ['sometimes', 'array'],
            'photos.cover' => $image,
            'photos.groom' => $image,
            'photos.bride' => $image,
            'remove_photo' => ['sometimes', 'array'],
            'remove_photo.cover' => ['nullable', 'boolean'],
            'remove_photo.groom' => ['nullable', 'boolean'],
            'remove_photo.bride' => ['nullable', 'boolean'],

            'photo_gallery_present' => ['nullable', 'boolean'],
            'photo_gallery' => ['sometimes', 'array'],
            'photo_gallery.cover' => ['sometimes', 'array', 'max:' . (int) config('invitation.upload.max_cover_footer_photos', 2)],
            'photo_gallery.cover.*' => $image,
            'photo_gallery.footer' => ['sometimes', 'array', 'max:' . (int) config('invitation.upload.max_cover_footer_photos', 2)],
            'photo_gallery.footer.*' => $image,

            'supporting_photos_new' => ['sometimes', 'array', 'max:' . self::MAX_SUPPORTING_PHOTOS],
            'supporting_photos_new.*' => $image,
            'supporting_photos_remove' => ['sometimes', 'array', 'max:' . self::MAX_SUPPORTING_PHOTOS],
            'supporting_photos_remove.*' => ['nullable', 'boolean'],

            'events' => ['sometimes', 'array', 'max:' . $maxEvents],
            'events.*.id' => ['nullable', 'integer'],
            'events.*.event_type' => ['required', Rule::enum(EventType::class)],
            'events.*.title' => ['nullable', 'string', 'max:120'],
            'events.*.event_date' => ['required', 'date'],
            'events.*.start_time' => ['required', 'date_format:H:i'],
            'events.*.end_time' => ['nullable', 'date_format:H:i'],
            'events.*.timezone' => ['nullable', 'timezone'],
            'events.*.location_name' => ['nullable', 'string', 'max:150'],
            'events.*.address' => ['nullable', 'string', 'max:500'],
            'events.*.maps_url' => ['nullable', 'url', 'max:2048'],
            'events.*.description' => ['nullable', 'string', 'max:2000'],
            'events.*.dress_code' => ['nullable', 'string', 'max:120'],
            'events.*.live_stream_url' => ['nullable', 'url', 'max:2048'],
            'events.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],

            'stories' => ['sometimes', 'array', 'max:12'],
            'stories.*.id' => ['nullable', 'integer'],
            'stories.*.title' => ['nullable', 'string', 'max:120'],
            'stories.*.description' => ['nullable', 'string', 'max:5000'],
            'stories.*.story_date' => ['nullable', 'string', 'max:50'],
            'stories.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'stories_images' => ['sometimes', 'array', 'max:12'],
            'stories_images.*' => $image,
            'stories_remove_image' => ['sometimes', 'array', 'max:12'],
            'stories_remove_image.*' => ['nullable', 'boolean'],

            'families' => ['sometimes', 'array', 'max:12'],
            'families.*.id' => ['nullable', 'integer'],
            'families.*.name' => ['nullable', 'string', 'max:120'],
            'families.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],

            'gallery_items' => ['sometimes', 'array', 'max:' . self::MAX_GALLERY_PHOTOS],
            'gallery_items.*.id' => ['nullable', 'integer'],
            'gallery_items.*.caption' => ['nullable', 'string', 'max:150'],
            'gallery_items.*.is_cover' => ['nullable', 'boolean'],
            'gallery_items.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'gallery_images' => ['sometimes', 'array', 'max:' . self::MAX_GALLERY_PHOTOS],
            'gallery_images.*' => $image,
            'gallery_new' => ['sometimes', 'array', 'max:' . self::MAX_GALLERY_PHOTOS],
            'gallery_new.*' => $image,
            'gallery_remove_image' => ['sometimes', 'array', 'max:' . self::MAX_GALLERY_PHOTOS],
            'gallery_remove_image.*' => ['nullable', 'boolean'],

            'gift_accounts' => ['sometimes', 'array', 'max:10'],
            'gift_accounts.*.id' => ['nullable', 'integer'],
            'gift_accounts.*.label' => ['nullable', 'string', 'max:120'],
            'gift_accounts.*.account_name' => ['nullable', 'string', 'max:120'],
            'gift_accounts.*.account_number' => ['nullable', 'string', 'max:100'],
            'gift_accounts.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],

            'guests' => ['sometimes', 'array', 'max:100'],
            'guests.*.id' => ['nullable', 'integer'],
            'guests.*.guest_name' => ['required', 'string', 'max:120'],
            'guests.*.attendance_status' => ['nullable', Rule::enum(AttendanceStatus::class)],
            'guests.*.guest_count' => ['nullable', 'integer', 'min:1', 'max:50'],
            'guests.*.message' => ['nullable', 'string', 'max:2000'],
            'guest_remove' => ['sometimes', 'array', 'max:100'],
            'guest_remove.*' => ['nullable', 'boolean'],

            'greetings' => ['sometimes', 'array', 'max:100'],
            'greetings.*.id' => ['nullable', 'integer'],
            'greetings.*.status' => ['nullable', Rule::in(['approved', 'hidden'])],
            'greeting_remove' => ['sometimes', 'array', 'max:100'],
            'greeting_remove.*' => ['nullable', 'boolean'],

            'share_image' => $image,
            'share_image_remove' => ['nullable', 'boolean'],

            'music_file' => [
                'nullable',
                'file',
                'mimetypes:audio/mpeg,audio/mp3,audio/wav,audio/x-wav,audio/ogg,audio/mp4,audio/aac,audio/x-m4a,application/ogg',
                'max:10240',
            ],
            'music_file_remove' => ['nullable', 'boolean'],

            'settings' => ['sometimes', 'array'],
            'settings.theme' => ['nullable', 'string', 'max:50'],
            'settings.couple_order' => [
                'nullable',
                Rule::in(array_map(static fn (CoupleOrder $order): string => $order->value, CoupleOrder::cases())),
            ],
            'settings.primary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'settings.secondary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'settings.font_family' => [
                'nullable',
                Rule::in(array_merge(
                    [''],
                    array_keys(config('invitation.fonts', [])),
                    array_values(config('invitation.fonts', [])),
                )),
            ],
            'settings.music_id' => ['nullable', 'integer', 'exists:musics,id'],
            'settings.music_autoplay' => ['nullable', 'boolean'],
            'settings.whatsapp_number' => [
                'nullable',
                'string',
                'max:30',
                function (string $attribute, mixed $value, callable $fail): void {
                    try {
                        app(WhatsAppService::class)->normalize($value);
                    } catch (InvalidArgumentException $exception) {
                        $fail($exception->getMessage());
                    }
                },
            ],
            'settings.whatsapp_display_name' => ['nullable', 'string', 'max:120'],
            'settings.whatsapp_message' => ['nullable', 'string', 'max:2000'],
            'settings.show_stories' => ['nullable', 'boolean'],
            'settings.show_gallery' => ['nullable', 'boolean'],
            'settings.show_families' => ['nullable', 'boolean'],
            'settings.show_gift' => ['nullable', 'boolean'],
            'settings.show_rsvp' => ['nullable', 'boolean'],
            'settings.invitation_message' => ['nullable', 'string', 'max:2000'],
            'settings.gift_address' => ['nullable', 'string', 'max:500'],
            'settings.quote' => ['nullable', 'string', 'max:2000'],
            'settings.quote_source' => ['nullable', 'string', 'max:200'],
            'settings.quote_action' => ['nullable', Rule::in(['default'])],
            'settings.share_title' => ['nullable', 'string', 'max:120'],
            'settings.share_description' => ['nullable', 'string', 'max:300'],
            'settings.share_message' => ['nullable', 'string', 'max:2000'],
            'settings.timezone' => ['nullable', 'timezone'],
            'settings.gallery_limit' => ['nullable', 'integer', 'min:0', 'max:' . self::MAX_GALLERY_PHOTOS],
        ], $backgroundRules);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'title.required' => 'Judul undangan wajib diisi.',
            'title.max' => 'Judul undangan maksimal 120 karakter.',
            'slug.regex' => 'Slug hanya boleh berisi huruf kecil, angka, dan tanda hubung.',
            'slug.max' => 'Slug maksimal 80 karakter.',
            'events.*.event_date.required' => 'Tanggal acara wajib diisi.',
            'events.*.start_time.required' => 'Jam mulai acara wajib diisi.',
            'gallery_items.max' => 'Galeri maksimal berisi 8 foto.',
            'gallery_new.max' => 'Anda hanya dapat menambahkan 8 foto sekaligus.',
            'gallery_new.*.image' => 'Berkas galeri harus berupa gambar.',
            'gallery_new.*.mimes' => 'Gambar galeri harus berformat JPG, PNG, atau WEBP.',
            'gallery_new.*.max' => 'Ukuran gambar galeri terlalu besar.',
            'gallery_new.*.dimensions' => 'Dimensi gambar galeri terlalu besar.',
            'photos.cover.image' => 'Foto sampul harus berupa gambar.',
            'photos.groom.image' => 'Foto mempelai pria harus berupa gambar.',
            'photos.bride.image' => 'Foto mempelai wanita harus berupa gambar.',
            'photo_gallery.cover.max' => 'Foto cover maksimal 2 foto.',
            'photo_gallery.cover.*.image' => 'Foto cover harus berupa gambar.',
            'photo_gallery.cover.*.mimes' => 'Foto cover harus berformat JPG, PNG, atau WEBP.',
            'photo_gallery.cover.*.max' => 'Ukuran foto cover terlalu besar.',
            'photo_gallery.cover.*.dimensions' => 'Dimensi foto cover terlalu besar.',
            'photo_gallery.footer.max' => 'Foto footer maksimal 2 foto.',
            'photo_gallery.footer.*.image' => 'Foto footer harus berupa gambar.',
            'photo_gallery.footer.*.mimes' => 'Foto footer harus berformat JPG, PNG, atau WEBP.',
            'photo_gallery.footer.*.max' => 'Ukuran foto footer terlalu besar.',
            'photo_gallery.footer.*.dimensions' => 'Dimensi foto footer terlalu besar.',
            'supporting_photos_new.max' => 'Anda hanya dapat menambahkan 3 foto pendukung sekaligus.',
            'supporting_photos_new.*.image' => 'Foto pendukung harus berupa gambar.',
            'supporting_photos_new.*.mimes' => 'Foto pendukung harus berformat JPG, PNG, atau WEBP.',
            'supporting_photos_new.*.max' => 'Ukuran foto pendukung terlalu besar.',
            'supporting_photos_new.*.dimensions' => 'Dimensi foto pendukung terlalu besar.',
            'settings.whatsapp_number.max' => 'Nomor WhatsApp terlalu panjang.',
            'settings.whatsapp_message.max' => 'Pesan WhatsApp maksimal 2000 karakter.',
        ];
    }

    private function normalizeTimezone(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = trim((string) $value);

        $aliases = [
            'WIB' => 'Asia/Jakarta',
            'WITA' => 'Asia/Makassar',
            'WIT' => 'Asia/Jayapura',
            'UTC' => 'UTC',
            'GMT' => 'UTC',
        ];

        return $aliases[strtoupper($value)] ?? $value;
    }

    private function normalizeUrl(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('~^https?://~i', $value) === 1) {
            return $value;
        }

        if (str_starts_with($value, '//')) {
            return 'https:' . $value;
        }

        return 'https://' . $value;
    }
}
