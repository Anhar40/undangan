<?php

namespace App\Services\Invitation;

use App\Data\EventViewData;
use App\Data\GalleryViewData;
use App\Data\GiftAccountViewData;
use App\Data\GreetingViewData;
use App\Data\InvitationViewData;
use App\Data\PersonViewData;
use App\Data\StoryViewData;
use App\Enums\AttendanceStatus;
use App\Enums\CoupleOrder;
use App\Models\Invitation;
use App\Models\Template;
use Carbon\Carbon;

class InvitationDataService
{
    private const MAX_GALLERY_PHOTOS = 8;

    private const DAYS = [
        'Sunday' => 'Minggu',
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu',
    ];

    private const MONTHS = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    public function forSample(Template $template, ?string $guestName = null, ?string $csrfToken = null): InvitationViewData
    {
        $key = $this->rendererKey($template->config['renderer'] ?? null, $template->slug);
        $sample = $this->sampleData($key);
        $guestName = trim($guestName ?: '');
        $galleries = array_slice($sample['galleries'], 0, self::MAX_GALLERY_PHOTOS);

        return new InvitationViewData(
            key: $key,
            templateName: $template->name,
            templateVersion: $template->version,
            title: 'Contoh '.$template->name,
            bride: $sample['bride'],
            groom: $sample['groom'],
            events: $sample['events'],
            stories: $sample['stories'],
            galleries: $galleries,
            supportingPhotos: array_map(
                static fn (GalleryViewData $photo): string => $photo->url,
                $galleries,
            ),
            giftAccounts: $sample['giftAccounts'],
            giftAddress: $sample['giftAddress'] ?? null,
            families: $sample['families'],
            greetings: $sample['greetings'],
            features: $template->features(),
            theme: $sample['theme'],
            musicUrl: $sample['musicUrl'],
            // Demo pages must behave like the real invitation once a visitor
            // opens it. The runtime retries on the first gesture when the
            // browser blocks audible autoplay.
            musicAutoplay: true,
            guestName: $guestName !== '' ? $guestName : 'Nama Tamu',
            saveTheDate: $sample['saveTheDate'],
            countdownTarget: $sample['countdownTarget'],
            invitationMessage: $sample['invitationMessage'],
            quote: $sample['quote'],
            quoteSource: $sample['quoteSource'],
            endpoints: [
                'greeting' => '#',
                'rsvp' => '#',
                'public' => '#',
            ],
            csrfToken: $csrfToken,
            sample: true,
            preview: true,
        );
    }

    public function forInvitation(
        Invitation $invitation,
        ?string $guestName = null,
        bool $preview = false,
        ?string $csrfToken = null,
        ?string $publicStatus = null,
        ?array $activationPrompt = null,
    ): InvitationViewData {
        $invitation->loadMissing([
            'template',
            'groom',
            'bride',
            'events',
            'stories',
            'families',
            'galleries',
            'giftAccounts',
            'invitationSettings.music',
            'greetings' => fn ($query) => $query->approved()->latest()->limit(12),
        ]);

        $renderer = $invitation->template_snapshot['renderer']
            ?? $invitation->template?->config['renderer']
            ?? $invitation->template?->slug
            ?? 'tema1';
        $key = $this->rendererKey($renderer, $invitation->template?->slug ?? 'tema1');
        $settings = $invitation->invitationSettings;

        $person = fn ($model, string $label): PersonViewData => new PersonViewData(
            fullName: trim((string) ($model?->full_name ?? '')),
            nickname: trim((string) ($model?->nickname ?? '')),
            photo: $this->assetUrl($model?->photo),
            fatherName: trim((string) ($model?->father_name ?? '')),
            motherName: trim((string) ($model?->mother_name ?? '')),
            instagram: ($handle = trim((string) ($model?->instagram ?? ''))) !== '' ? $handle : null,
            parentsLabel: $label,
            socialLinks: $this->socialLinks($model?->social_links),
        );

        $events = [];
        foreach ($invitation->events as $event) {
            if (! $event->event_date) {
                continue;
            }

            $date = Carbon::parse($event->event_date);
            $startTime = $event->start_time ?: '08:00:00';
            $timezone = $event->timezone ?: 'Asia/Jakarta';
            $type = $event->event_type?->value ?: (string) $event->event_type;

            $events[] = $this->makeEvent(
                type: $type,
                title: trim((string) $event->title) ?: match ($type) {
                    'AKAD' => 'Akad Nikah',
                    'RESEPSI' => 'Resepsi',
                    default => 'Acara',
                },
                date: $date,
                startTime: substr($startTime, 0, 5),
                endTime: $event->end_time ? substr((string) $event->end_time, 0, 5) : null,
                timezone: $timezone,
                locationName: trim((string) $event->location_name),
                address: trim((string) $event->address),
                mapsUrl: $event->maps_url,
                liveStreamUrl: $event->live_stream_url,
                description: trim((string) ($event->description ?? '')),
                key: $key,
                dressCode: $this->nullableTrim($event->dress_code ?? null),
            );
        }

        $stories = $invitation->stories
            ->filter(fn ($story) => trim((string) $story->title) !== ''
                || trim((string) $story->description) !== ''
                || $story->image)
            ->map(fn ($story) => new StoryViewData(
                title: trim((string) $story->title),
                description: trim((string) $story->description),
                image: $this->assetUrl($story->image),
                storyDate: trim((string) ($story->story_date ?? '')) ?: null,
            ))->values()->all();

        $galleries = $invitation->galleries
            ->filter(fn ($photo) => filled($photo->image))
            ->sortBy(fn ($photo) => (int) $photo->sort_order)
            ->sortByDesc(fn ($photo) => (bool) $photo->is_cover)
            ->take(self::MAX_GALLERY_PHOTOS)
            ->map(function ($photo): GalleryViewData {
                [$width, $height] = $this->galleryDimensions((string) $photo->image);

                return new GalleryViewData(
                    url: $photo->url(),
                    caption: trim((string) $photo->caption),
                    isCover: (bool) $photo->is_cover,
                    width: $width,
                    height: $height,
                );
            })->values()->all();

        $custom = is_array($settings?->custom_settings) ? $settings->custom_settings : [];
        $giftAddress = trim((string) ($custom['gift_address'] ?? ''));

        $supportingPhotos = [];
        $storedSupporting = is_array($custom['supporting_photos'] ?? null) ? $custom['supporting_photos'] : [];

        foreach ($storedSupporting as $path) {
            $url = $this->assetUrl(is_string($path) ? $path : null);

            if ($url) {
                $supportingPhotos[] = $url;
            }
        }

        // QR and per-row address columns are legacy-only. The public envelope
        // uses bank cards plus one shared delivery address from settings.
        $giftAccounts = $invitation->giftAccounts
            ->filter(fn ($account) => trim((string) $account->label) !== ''
                || trim((string) $account->account_name) !== ''
                || trim((string) $account->account_number) !== '')
            ->map(fn ($account) => new GiftAccountViewData(
                label: trim((string) $account->label),
                accountName: trim((string) $account->account_name),
                accountNumber: trim((string) $account->account_number),
                qrUrl: null,
                giftAddress: null,
            ))->values()->all();

        $greetings = $invitation->greetings->map(fn ($greeting) => new GreetingViewData(
            guestName: $greeting->guest_name,
            message: $greeting->message,
            attendance: $greeting->attendance_status instanceof AttendanceStatus
                ? $greeting->attendance_status->label()
                : (string) $greeting->attendance_status,
            createdAt: $greeting->created_at?->translatedFormat('d M Y') ?? '',
        ))->all();

        $slug = $invitation->slug;
        $features = $invitation->templateFeatures();

        // Visibility follows the customer's data, not legacy show_* toggles:
        // an optional section is rendered only when the frozen template supports
        // it AND the customer actually supplied content for it.
        $visibility = [
            'story' => $stories === [],
            'gallery' => $galleries === [] && $supportingPhotos === [],
            'families' => $invitation->families->isEmpty(),
            'gift' => $giftAccounts === [] && $giftAddress === '',
        ];

        foreach ($visibility as $feature => $isEmpty) {
            if ($isEmpty) {
                $features[$feature] = false;
            }
        }

        $quote = trim((string) ($settings->quote ?? $custom['quote'] ?? ''));
        $quoteSource = trim((string) ($settings->quote_source ?? $custom['quote_source'] ?? ''));

        if (! ($features['quote'] ?? false)) {
            $quote = '';
            $quoteSource = '';
        }

        $musicUrl = $settings?->resolvedMusicUrl();

        if (! $musicUrl) {
            $features['music'] = false;
        }

        $countdownTarget = $events !== []
            ? $this->countdownTarget($events[0], $custom['timezone'] ?? $events[0]->timezone)
            : null;

        if ($countdownTarget === null) {
            $features['countdown'] = false;
        }

        $photoGallery = [];
        $storedPhotoGallery = is_array($custom['photo_gallery'] ?? null) ? $custom['photo_gallery'] : [];
        foreach (['cover', 'footer'] as $slot) {
            $paths = is_array($storedPhotoGallery[$slot] ?? null) ? $storedPhotoGallery[$slot] : [];
            $paths = array_slice($paths, 0, (int) config('invitation.upload.max_cover_footer_photos', 2));
            $resolved = [];

            foreach ($paths as $path) {
                $url = $this->assetUrl(is_string($path) ? $path : null);

                if ($url) {
                    $resolved[] = $url;
                }
            }

            if ($resolved !== []) {
                $photoGallery[$slot] = $resolved;
            }
        }

        $backgrounds = $this->backgroundUrls($custom, $photoGallery, $supportingPhotos);
        $guestName = trim($guestName ?: '');

        $whatsappNumber = trim((string) ($settings?->whatsapp_number ?? ''));
        $whatsappMessage = trim((string) ($settings?->whatsapp_message ?? ''));
        $whatsapp = $whatsappNumber !== '' ? [
            'number' => $whatsappNumber,
            'display_name' => trim((string) ($settings?->whatsapp_display_name ?? '')),
            'message' => $whatsappMessage,
            'url' => 'https://wa.me/'.$whatsappNumber
                .($whatsappMessage !== '' ? '?text='.rawurlencode($whatsappMessage) : ''),
        ] : null;

        return new InvitationViewData(
            key: $key,
            templateName: $invitation->template_name,
            templateVersion: $invitation->template_version,
            title: $invitation->title,
            bride: $person($invitation->bride, 'Putri dari'),
            groom: $person($invitation->groom, 'Putra dari'),
            events: $events,
            stories: $stories,
            galleries: $galleries,
            supportingPhotos: $supportingPhotos,
            giftAccounts: $giftAccounts,
            giftAddress: $giftAddress !== '' ? $giftAddress : null,
            families: $invitation->families->pluck('name')->filter()->values()->all(),
            greetings: $greetings,
            features: $features,
            theme: [
                'primary_color' => $settings?->primary_color,
                'secondary_color' => $settings?->secondary_color,
                'font_family' => $this->fontStack($settings?->font_family),
                'theme' => $settings?->theme ?: 'default',
            ],
            musicUrl: $musicUrl,
            musicAutoplay: (bool) ($settings?->music_autoplay ?? false),
            guestName: $guestName,
            saveTheDate: $events[0]->date ?? '',
            countdownTarget: $countdownTarget,
            invitationMessage: trim((string) ($custom['invitation_message'] ?? '')),
            quote: $quote,
            quoteSource: $quoteSource,
            endpoints: [
                'greeting' => route('public.greetings.store', $slug),
                'rsvp' => route('public.rsvp.store', $slug),
                'public' => route('invitation.show', $slug),
            ],
            csrfToken: $csrfToken,
            sample: false,
            preview: $preview,
            publicStatus: $publicStatus,
            activationPrompt: $activationPrompt,
            backgrounds: $backgrounds,
            photoGallery: $photoGallery,
            whatsapp: $whatsapp,
            shareTitle: $this->nullableTrim($settings->share_title ?? null),
            shareDescription: $this->nullableTrim($settings->share_description ?? null),
            shareImage: $this->assetUrl($this->nullableTrim($settings->share_image ?? null)),
            coupleOrder: $settings?->couple_order instanceof CoupleOrder
                ? $settings->couple_order
                : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleData(string $key): array
    {
        $isFirst = $key === 'tema1';
        $eventDate = match ($key) {
            'tema1' => '2023-12-12',
            'tema4' => '2023-05-22',
            'tema5' => '2023-09-10',
            default => '2023-07-09',
        };
        $assets = '/assets/templates/'.$key.'/wp-content/uploads/2024/07/';

        // Sample media must point at files mirrored into this template's own
        // public folder; never fall back to another template's remote URLs.
        $files = [
            'tema1' => [
                'bride' => 'pexels-azra-tuba-demir-12124451.jpg',
                'groom' => 'pexels-azra-tuba-demir-12124421.jpg',
                'stories' => ['pexels-azra-tuba-demir-12124342.jpg', 'pexels-azra-tuba-demir-12124474.jpg', null],
                'gallery' => ['pexels-azra-tuba-demir-12124342.jpg', 'pexels-azra-tuba-demir-12124416.jpg', 'pexels-azra-tuba-demir-12124474.jpg', 'pexels-azra-tuba-demir-12124451.jpg', 'pexels-azra-tuba-demir-12124421.jpg', 'pexels-azra-tuba-demir-12124419.jpg'],
                'music' => '/assets/templates/tema1/music/music-1755739436735-110611525.mp3',
            ],
            'tema2' => [
                'bride' => 'pexels-photo-8526254.webp',
                'groom' => 'pexels-photo-8526247.jpeg',
                'stories' => ['pexels-photo-8526250.webp', 'pexels-photo-8526257.webp', null],
                'gallery' => ['pexels-photo-8526254.webp', 'pexels-photo-8526250.webp', 'pexels-photo-8526247.jpeg', 'pexels-photo-8526257.webp', 'pexels-photo-8526246.jpeg', 'pexels-photo-8526252.webp'],
                'music' => '/assets/templates/tema2/wp-content/uploads/2024/07/arvian-dwi-ft-tri-suaka-melepas-1.mp3',
            ],
            'tema3' => [
                'bride' => 'pexels-artem-podrez-7987090.jpg',
                'groom' => 'pexels-artem-podrez-7987087-scaled-1.jpg',
                'stories' => ['pexels-artem-podrez-7987098-576x1024.jpg', 'pexels-artem-podrez-7987087-scaled-1-576x1024.jpg', 'pexels-artem-podrez-7987082-576x1024.jpg'],
                'gallery' => ['pexels-artem-podrez-7987098.jpg', 'pexels-artem-podrez-7987087-scaled-1.jpg', 'pexels-artem-podrez-7987097.jpg', 'pexels-artem-podrez-7987090.jpg', 'pexels-artem-podrez-7987082.jpg', 'pexels-artem-podrez-7987083.jpg', 'pexels-artem-podrez-7986955-scaled-1.jpg', 'pexels-artem-podrez-7987080.jpg'],
                'music' => $assets.'Lirik-Lagu-Wanita-Yang-Kau-Pilih.mp3',
            ],
            'tema4' => [
                'bride' => 'IMG-20230508-WA0021-682x1024.jpg',
                'groom' => 'IMG-20230508-WA0020-683x1024.jpg',
                'stories' => ['pexels-photo-8526252.webp', 'pexels-photo-8526257.webp', 'pexels-photo-8526257.webp'],
                'gallery' => ['IMG-20230508-WA0017.jpg', 'IMG-20230508-WA0016.jpg', 'IMG-20230508-WA0015.jpg', 'IMG-20230508-WA0022.jpg', 'IMG-20230508-WA0019.jpg', 'IMG-20230508-WA0023.jpg', 'IMG-20230508-WA0014.jpg', 'IMG-20230508-WA0018.jpg'],
                'music' => $assets.'Payung-Teduh-Akad-Official-Music-Video.mp3',
            ],
            'tema5' => [
                'bride' => 'pexels-willy-wft-9915173-scaled-1-683x1024.jpg',
                'groom' => 'pexels-willy-wft-9915172-scaled-1-683x1024.jpg',
                'stories' => ['pexels-willy-wft-9915162-scaled-1.jpg', 'pexels-willy-wft-9915177-scaled-1.jpg', 'pexels-willy-wft-9915166-scaled-1.jpg'],
                'gallery' => ['pexels-willy-wft-9915165-scaled-1.jpg', 'pexels-willy-wft-9717093-scaled-1.jpg', 'pexels-willy-wft-9915177-scaled-1.jpg', 'pexels-willy-wft-9717092-scaled-1.jpg', 'pexels-willy-wft-9915174-scaled-1.jpg', 'pexels-willy-wft-9915162-scaled-1.jpg', 'pexels-willy-wft-9915173-scaled-1.jpg', 'pexels-willy-wft-9915172-scaled-1.jpg', 'pexels-willy-wft-9915166-scaled-1.jpg', 'pexels-willy-wft-9915178-scaled-1.jpg', 'pexels-willy-wft-9915169-scaled-1.jpg'],
                'music' => $assets.'Nuca-Mahalini-Janji-Kita-Liri-ge.mp3',
            ],
        ];
        $sample = $files[$key] ?? $files['tema2'];

        $bride = new PersonViewData(
            fullName: 'Putri Pratiwi',
            nickname: 'Putri',
            photo: $assets.$sample['bride'],
            fatherName: 'Bapak Fulan',
            motherName: 'Ibu Fulanah',
            instagram: '@putri',
            parentsLabel: 'Putri pertama dari',
        );

        $groom = new PersonViewData(
            fullName: 'Putra Pratama',
            nickname: 'Putra',
            photo: $assets.$sample['groom'],
            fatherName: 'Bapak Fulan',
            motherName: 'Ibu Fulanah',
            instagram: '@putra',
            parentsLabel: 'Putra pertama dari',
        );

        $events = [
            $this->makeEvent('AKAD', 'Akad Nikah', Carbon::parse($eventDate), '08:00', 'Asia/Jakarta',
                'Mesjid Makmur Nunang', 'Jl. Pajagalan No. 12', 'https://maps.google.com', null, null, $key),
            $this->makeEvent('RESEPSI', 'Resepsi', Carbon::parse($eventDate), '10:00', 'Asia/Jakarta',
                'Gedung Pernikahan', 'Jl. Pajagalan No. 12', 'https://maps.google.com', null, null, $key),
        ];

        $stories = [];
        foreach (['Awal Ketemu', 'Komitmen', 'Lamaran'] as $index => $title) {
            $image = $sample['stories'][$index] ?? null;
            $stories[] = new StoryViewData(
                $title,
                [
                    'Awal mula kami bertemu dan mulai saling mengenal.',
                    'Seiring waktu kami memutuskan untuk bersama.',
                    'Kami mempertemukan kedua keluarga dan merencanakan hari bahagia.',
                ][$index],
                $image ? $assets.$image : null,
            );
        }

        $galleries = array_map(function ($file) use ($assets): GalleryViewData {
            [$width, $height] = $this->galleryDimensions(public_path(ltrim($assets.$file, '/')));

            return new GalleryViewData($assets.$file, '', false, $width, $height);
        }, $sample['gallery']);
        $giftAccounts = [
            new GiftAccountViewData('Amplop Digital', 'Putri Pratiwi', $isFirst ? '0123 456 789' : 'xxxx-xxxx-xxxx-xxx', null, null),
            new GiftAccountViewData('Alamat Pengiriman Kado', 'Putri Pratiwi', '', null, 'Jl Bukit Indah No 183'),
        ];

        if ($isFirst) {
            $quote = '" Dan di antara tanda-tanda kekuasaan-Nya diciptakan-Nya untukmu pasangan hidup dari jenismu sendiri supaya kamu dapat ketenangan hati dan dijadikannya kasih sayang di antara kamu. "';
            $quoteSource = '- Q.S. Ar-Rum: 21 -';
        } else {
            $quote = '"Dan di antara ayat-ayat-Nya ialah Dia menciptakan untukmu istri-istri dari jenismu sendiri, supaya kamu merasa nyaman kepadanya, dan dijadikan-Nya di antaramu mawadah dan rahmah."';
            $quoteSource = '- Ar-Rum : 21 -';
        }

        return [
            'bride' => $bride,
            'groom' => $groom,
            'events' => $events,
            'stories' => $stories,
            'galleries' => $galleries,
            'giftAccounts' => $giftAccounts,
            'giftAddress' => null,
            'families' => ['Keluarga Besar Putri', 'Keluarga Besar Putra'],
            'greetings' => [
                new GreetingViewData('Tari & Aziz', 'Selamat menempuh hidup baru. Semoga menjadi keluarga yang sehat dan sejahtera.', 'Hadir', '12 Des 2023'),
                new GreetingViewData('Rizky', 'Semoga hari bahagia ini menjadi awal yang indah.', 'Hadir', '12 Des 2023'),
            ],
            'theme' => [
                'primary_color' => '#8b5cf6',
                'secondary_color' => '#d946ef',
                'font_family' => 'Poppins, sans-serif',
            ],
            'musicUrl' => $sample['music'],
            'saveTheDate' => $eventDate,
            'countdownTarget' => Carbon::parse($eventDate.' 08:00', 'Asia/Jakarta')->toIso8601String(),
            'invitationMessage' => 'Tanpa mengurangi rasa hormat, kami bermaksud mengundang Bapak/Ibu/Saudara/I untuk menghadiri acara pernikahan kami.',
            'quote' => $quote,
            'quoteSource' => $quoteSource,
        ];
    }

    private function makeEvent(
        string $type,
        string $title,
        Carbon $date,
        string $startTime,
        string $timezone,
        string $locationName,
        string $address,
        ?string $mapsUrl,
        ?string $liveStreamUrl,
        ?string $description,
        string $key,
        ?string $endTime = null,
        ?string $dressCode = null,
    ): EventViewData {
        $zone = $this->timezoneLabel($timezone);
        $time = $startTime.' - '.($endTime ?: 'Selesai');
        $timeLabel = (Template::countdownIsTimestamp($key) ? 'Pukul : ' : 'Pukul ').$time.' '.$zone;
        $location = $locationName.($address !== '' ? "\n".$address : '');

        return new EventViewData(
            type: $type,
            title: $title,
            date: $date->format('Y-m-d'),
            dayName: self::DAYS[$date->format('l')] ?? $date->format('l'),
            day: $date->format('j'),
            month: self::MONTHS[(int) $date->format('n')] ?? $date->format('F'),
            year: $date->format('Y'),
            timeLabel: $timeLabel,
            locationLabel: $location,
            mapsUrl: $mapsUrl,
            liveStreamUrl: $liveStreamUrl,
            description: $description,
            address: $address !== '' ? $address : null,
            endTime: $endTime,
            timezone: $timezone,
            dressCode: $dressCode,
        );
    }

    /**
     * Elementor's masonry gallery reads data-width/data-height from the markup
     * when lazyload is enabled. Without them it computes NaN heights and the
     * photos collapse to zero pixels.
     *
     * @return array{int,int}
     */
    private function galleryDimensions(string $path): array
    {
        static $cache = [];

        if (isset($cache[$path])) {
            return $cache[$path];
        }

        $candidate = $path;
        if (! str_starts_with($path, 'http://') && ! str_starts_with($path, 'https://')) {
            $localPublic = storage_path('app/public/'.ltrim($path, '/'));
            $candidate = is_file($localPublic) ? $localPublic : $path;
        }

        if (is_file($candidate) || str_starts_with($candidate, 'http://') || str_starts_with($candidate, 'https://')) {
            $size = @getimagesize($candidate);

            if (is_array($size) && ($size[0] ?? 0) > 0 && ($size[1] ?? 0) > 0) {
                return $cache[$path] = [(int) $size[0], (int) $size[1]];
            }
        }

        return $cache[$path] = [853, 1280];
    }

    private function timezoneLabel(string $timezone): string
    {
        return match ($timezone) {
            'Asia/Jakarta' => 'WIB',
            'Asia/Makassar' => 'WITA',
            'Asia/Jayapura' => 'WIT',
            'Asia/Shanghai' => 'WCS',
            default => $timezone,
        };
    }

    /**
     * @return list<array{label:string,url:string}>
     */
    private function socialLinks(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $links = [];

        foreach ($value as $link) {
            if (! is_array($link)) {
                continue;
            }

            $url = trim((string) ($link['url'] ?? ''));
            if ($url === '') {
                continue;
            }

            if (! preg_match('~^https?://~i', $url)) {
                if (! preg_match('~^[\w.-]+\.[a-z]{2,}~i', $url)) {
                    continue;
                }
                $url = 'https://'.$url;
            }

            $links[] = [
                'label' => trim((string) ($link['label'] ?? '')) ?: 'Link',
                'url' => $url,
            ];
        }

        return $links;
    }

    private function countdownTarget(EventViewData $event, ?string $timezone = null): string
    {
        $time = preg_match('/(\d{2}:\d{2})/', $event->timeLabel, $matches) ? $matches[1] : '08:00';

        return Carbon::parse($event->date.' '.$time, $timezone ?: 'Asia/Jakarta')->toIso8601String();
    }

    /**
     * @param  array<string, mixed>  $custom
     * @param  array<string, array<int, string>>  $photoGallery
     * @param  array<int, string>  $supportingPhotos
     * @return array<string, array<int, string>>
     */
    private function backgroundUrls(array $custom, array $photoGallery = [], array $supportingPhotos = []): array
    {
        $stored = is_array($custom['backgrounds'] ?? null) ? $custom['backgrounds'] : [];
        $urls = [];

        // The Foto dan Galeri panel is the primary source. Cover and footer
        // are slots of at most two photos; Foto Pendukung replaces the old
        // sub-cover group as the source for the couple backgrounds. tema2
        // keeps its own override so the sub-cover section still follows the
        // footer photos.
        foreach (['cover', 'cover_inner'] as $slot) {
            if (($photoGallery['cover'] ?? []) !== []) {
                $urls[$slot] = $photoGallery['cover'];
            }
        }
        foreach (['groom', 'bride'] as $index => $slot) {
            if (($supportingPhotos[$index] ?? null) !== null) {
                $urls[$slot] = [$supportingPhotos[$index]];
            }
        }
        if (($photoGallery['footer'] ?? []) !== []) {
            $urls['closing'] = $photoGallery['footer'];
        }

        foreach ($stored as $slot => $paths) {
            if (isset($urls[$slot])) {
                continue;
            }
            if (! is_string($slot)) {
                continue;
            }

            $paths = is_array($paths) ? $paths : [$paths];
            $resolved = [];

            foreach ($paths as $path) {
                $url = $this->assetUrl(is_string($path) ? $path : null);

                if ($url) {
                    $resolved[] = $url;
                }
            }

            if ($resolved !== []) {
                $urls[$slot] = $resolved;
            }
        }

        // Cover and closing images now come only from the Foto dan Galeri
        // settings (or an already-frozen template background slot). Legacy
        // cover_photo / "foto utama" gallery data is deliberately not reused.

        return $urls;
    }

    private function nullableTrim(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function assetUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http') || str_starts_with($path, '/')) {
            return $path;
        }

        return asset('storage/'.ltrim($path, '/'));
    }

    private function fontStack(mixed $value): ?string
    {
        $key = is_string($value) ? trim($value) : '';

        if ($key === '') {
            return null;
        }

        $fonts = config('invitation.fonts', []);

        return is_array($fonts) ? ($fonts[$key] ?? (in_array($key, $fonts, true) ? $key : null)) : null;
    }

    private function rendererKey(mixed $renderer, string $fallback): string
    {
        $key = is_string($renderer) && $renderer !== '' ? $renderer : $fallback;

        return Template::hasRenderer($key) ? (string) $key : 'tema1';
    }
}
