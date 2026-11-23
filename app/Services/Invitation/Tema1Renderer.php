<?php

namespace App\Services\Invitation;

use App\Data\InvitationViewData;

class Tema1Renderer extends AbstractTemplateRenderer
{
    protected function key(): string
    {
        return 'tema1';
    }

    protected function apply(string $html, InvitationViewData $data): string
    {
        $html = $this->applyCover(
            $html,
            $data,
            coupleId: '3058ec24',
            dateId: '1a06cffd',
            guestId: '7fac8a76',
            messageId: '7e5d7ae6',
            quoteId: '4a41ce2f',
        );

        $html = $this->applyCouple($html, $data, [
            'bride_names' => ['7d5344c4', '469f4f2'],
            'groom_names' => ['5de56291', '2233fd63'],
            'bride_parents' => ['7d466437'],
            'groom_parents' => ['1aeac5e5'],
            'bride_column' => '5d7ac283',
            'groom_column' => '5c22588c',
            'bride_instagram' => ['3ecedd8e'],
            'groom_instagram' => ['190cf58c'],
        ], false);

        $html = $this->applyCoupleRoleLabels($html, $data, ['32936610', '7df01bdf'], 'BRIDE', 'GROOM');
        $html = $this->applyCouplePhotos($html, $data, ['5d7ac283', '5c22588c'], 'background');

        $html = $this->applyEvents($html, $data, [
            [
                'title' => '7acb9878',
                'day' => '4905ecf2',
                'counter' => '7a456843',
                'month' => '2fa44f42',
                'with_year' => true,
                'time' => '4c13b865',
                'location' => '6e2ca33',
                'map' => '3dfc7066',
                'live_append' => '650f0858',
            ],
            [
                'title' => 'efd8c41',
                'day' => '727f740',
                'counter' => '48f69865',
                'month' => '30d2831f',
                'with_year' => true,
                'time' => '1fc47a50',
                'location' => '4b406f6c',
                'map' => '1786d3be',
            ],
        ]);

        $html = $this->applyStories($html, $data, [
            ['image' => '7fe5e8be', 'title' => '79a78ccc', 'description' => '669f1889'],
            ['image' => '6ccd076a', 'title' => '129b7a4d', 'description' => '6d811651'],
            ['image' => '30ca2aab', 'title' => 'e4e8146', 'description' => '5fa78dd2'],
        ]);

        $html = $this->applyGallery($html, $data, '45a07475');

        $html = $this->applySupportingPhotos(
            $html,
            $data,
            carouselId: '542f3a1f',
            imageIds: ['5a32762e', '7b6cf76', '3d7facad'],
        );

        $html = $this->applyGifts($html, $data, [
            [
                'display' => ['7c0478bd'],
                'copy' => ['3c4d725e'],
                'image' => '10a2d3a',
            ],
            [
                'display' => ['70990778'],
                'copy' => ['7bbcff2d'],
                'image' => '60d7dd04',
            ],
        ]);

        $families = implode('<br>', array_map(fn ($family) => $this->e($family), $data->families));
        $html = $this->richHeading(
            $html,
            '5bca0080',
            $families !== '' ? 'Kami Yang Berbahagia,<br>' . $families : '',
        );

        return $this->applyCountdown(
            $html,
            $data,
            '~(elementor-countdown-wrapper" data-date=")[^"]*(")~i',
        );
    }

    protected function featureHiddenIds(): array
    {
        return [
            'story' => ['6ff5471e', '79a78ccc', '129b7a4d', 'e4e8146'],
            'gallery' => ['1ff62bc5', '7decd091', '45a07475'],
            'music' => [],
            'theme' => [],
            'rsvp' => ['393379a3', '71e33806'],
            // The family line shares the closing section with the thank-you
            // message and couple names. emptyWidgetIds() hides only that line,
            // so the whole closing section must stay visible.
            'families' => [],
            'gift' => ['5b39afec', '6dc1dfc5', '3799206a'],
            'live_stream' => [],
            'countdown' => ['5df715d2', '5fe8a914'],
        ];
    }

    protected function backgroundIds(): array
    {
        return [
            'cover' => '3fe3ed95',
            // The cover has a second, inner slideshow in the stock template.
            // It shares the customer's cover photos so the native animation
            // stays identical to the sample instead of showing stock images.
            'cover_inner' => '30eed981',
            'groom' => '5c22588c',
            'bride' => '5d7ac283',
            'closing' => '27998b08',
        ];
    }

    protected function giftWidgetIds(): array
    {
        return [
            'button' => '13ccc6ab',
            'panel' => '41e2fa24',
            'legacy' => ['c182eda', '6654c100'],
        ];
    }

    protected function removedWidgetIds(InvitationViewData $data): array
    {
        // These widgets contain only hard-coded template sample content. They
        // are kept for the sample preview but never shipped to real guests.
        $ids = [
            '61e9402c', // third gift block (address) has no customer-owned slot
        ];

        // When Foto dan Galeri has customer photos these slots are rewritten
        // instead of removed, so keep their native Elementor structure.
        if (! $this->hasSupportingPhotos($data)) {
            $ids[] = '542f3a1f'; // sample couple-photo carousel
            $ids[] = '5a32762e'; // decorative sample photo
            $ids[] = '1d6ce7b6'; // decorative sample photo
            $ids[] = '3d7facad'; // decorative sample photo
        }

        foreach (['7fe5e8be', '6ccd076a', '30ca2aab'] as $index => $id) {
            if (! ($data->stories[$index]?->image ?? null)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    protected function emptyWidgetIds(InvitationViewData $data): array
    {
        $ids = [];
        [$bride, $groom] = $data->orderedPeople();

        if ($data->families === []) {
            $ids[] = '5bca0080';
        }

        if ($data->coupleLabel() === '') {
            $ids[] = '3058ec24';
        }
        if ($data->saveTheDate === '') {
            $ids[] = '1a06cffd';
        }
        if (trim($data->guestName) === '') {
            $ids[] = '7fac8a76';
        }
        if (trim($data->invitationMessage) === '') {
            $ids[] = '7e5d7ae6';
        }
        if (trim($data->quote) === '' && trim($data->quoteSource) === '') {
            $ids[] = '4a41ce2f';
        }
        if (! $data->musicUrl || ! ($data->features['music'] ?? false)) {
            $ids[] = '67c4eb6';
        }

        foreach (['7d5344c4', '469f4f2'] as $id) {
            if (trim($bride->fullName) === '') {
                $ids[] = $id;
            }
        }
        foreach (['5de56291', '2233fd63'] as $id) {
            if (trim($groom->fullName) === '') {
                $ids[] = $id;
            }
        }
        if ($this->personParentsEmpty($bride)) {
            $ids[] = '7d466437';
        }
        if ($this->personParentsEmpty($groom)) {
            $ids[] = '1aeac5e5';
        }
        // The couple columns are driven by the Foto dan Galeri slots, not by
        // person records, so they must stay visible when photos are uploaded.

        if (trim((string) $bride->instagram) === '') {
            $ids[] = '3ecedd8e';
        }
        if (trim((string) $groom->instagram) === '') {
            $ids[] = '190cf58c';
        }

        $eventSlots = [
            [
                'title' => '7acb9878', 'day' => '4905ecf2', 'counter' => '7a456843',
                'month' => '2fa44f42', 'time' => '4c13b865', 'location' => '6e2ca33',
                'map' => '3dfc7066',
            ],
            [
                'title' => 'efd8c41', 'day' => '727f740', 'counter' => '48f69865',
                'month' => '30d2831f', 'time' => '1fc47a50', 'location' => '4b406f6c',
                'map' => '1786d3be',
            ],
        ];

        foreach ($eventSlots as $index => $slot) {
            $event = $data->events[$index] ?? null;

            if (! $event) {
                $ids = array_merge($ids, array_values($slot));
                continue;
            }

            if (trim($event->locationLabel) === '') {
                $ids[] = $slot['location'];
            }
            if (! $event->mapsUrl) {
                $ids[] = $slot['map'];
            }
        }

        $storySlots = [
            ['image' => '7fe5e8be', 'title' => '79a78ccc', 'description' => '669f1889'],
            ['image' => '6ccd076a', 'title' => '129b7a4d', 'description' => '6d811651'],
            ['image' => '30ca2aab', 'title' => 'e4e8146', 'description' => '5fa78dd2'],
        ];

        foreach ($storySlots as $index => $slot) {
            $story = $data->stories[$index] ?? null;

            if (! $story) {
                $ids = array_merge($ids, array_values($slot));
                continue;
            }

            if (! $story->image) {
                $ids[] = $slot['image'];
            }
            if (trim($story->title) === '') {
                $ids[] = $slot['title'];
            }
            if (trim($story->description) === '') {
                $ids[] = $slot['description'];
            }
        }

        // Live-stream widgets share the countdown section. Hide only the
        // widgets, never the whole section, so the countdown remains visible.
        if (! $data->primaryEvent()?->liveStreamUrl) {
            $ids[] = '459ad270';
            $ids[] = '650f0858';
        }

        return array_values(array_unique($ids));
    }
}
