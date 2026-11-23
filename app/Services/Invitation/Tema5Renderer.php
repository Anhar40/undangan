<?php

namespace App\Services\Invitation;

use App\Data\GiftAccountViewData;
use App\Data\InvitationViewData;

class Tema5Renderer extends AbstractTemplateRenderer
{
    protected function key(): string
    {
        return 'tema5';
    }

    protected function apply(string $html, InvitationViewData $data): string
    {
        foreach (['3434e463', '1c6048c8', 'f664efe'] as $id) {
            $html = $this->heading($html, $id, $data->coupleLabel());
        }
        $html = $this->heading($html, '7d024d2e', $data->guestName);
        $html = $this->heading($html, '7727edb5', $data->invitationMessage);

        $quote = $this->e($data->quote);
        if (trim($data->quoteSource) !== '') {
            $quote .= '<br><br>' . $this->e($data->quoteSource);
        }
        $html = $this->richHeading($html, '1c1092ad', $quote);
        $html = $this->richHeading($html, '768cf1c2', $this->e($data->quote));
        $html = $this->heading($html, '4f2c72bc', $data->quoteSource);

        $html = $this->applyCouple($html, $data, [
            'bride_names' => ['4d9a0eda'],
            'groom_names' => ['1ac8960f'],
            'bride_parents' => ['288c6a37'],
            'groom_parents' => ['15b90be7'],
            'bride_column' => '2247578f',
            'groom_column' => '1b5619e',
            'bride_instagram' => ['6162c34f'],
            'groom_instagram' => ['14560187'],
        ], false);

        // In this template the couple photos are real image widgets rather
        // than background slideshows, so they need their own slots.
        if (! $data->sample) {
            $html = $this->rewriter->setImageSource($html, '5c77959a', $data->bride->photo);
            $html = $this->rewriter->setImageSource($html, '6fa17f37', $data->groom->photo);
        }
        $html = $this->applyCouplePhotos($html, $data, ['5c77959a', '6fa17f37'], 'image');

        $html = $this->applyLongDateEvents($html, $data);

        $html = $this->applyStories($html, $data, [
            ['image' => '4ba5479f', 'title' => '512deee3', 'description' => '4814e0ec'],
            ['image' => '1f91f3aa', 'title' => 'bbf0e89', 'description' => '2be373a1'],
            ['image' => '1efe21ae', 'title' => '226d188', 'description' => '134503af'],
        ]);

        $html = $this->applyGallery($html, $data, '4be1b556');

        // The source has four native bank cards plus one native delivery
        // address card. Bind each customer value to its own Elementor widgets;
        // the address uses a pseudo account so applyGifts() can reuse the same
        // heading/copy widgets without injecting new markup.
        $giftAccounts = [];
        foreach (array_slice($data->giftAccounts, 0, 4) as $index => $account) {
            $giftAccounts[$index] = $account;
        }
        if ($data->giftAddress !== null && trim($data->giftAddress) !== '') {
            $giftAccounts[4] = new GiftAccountViewData(
                label: '',
                accountName: '',
                accountNumber: '',
                qrUrl: null,
                giftAddress: $data->giftAddress,
            );
        }

        $html = $this->applyGifts(
            $html,
            $data,
            [
                [
                    'display' => ['49f3294d'],
                    'copy' => ['2105d08f'],
                    'image' => '28795dca',
                ],
                [
                    'display' => ['460f856a'],
                    'copy' => ['5c10a35d'],
                    'image' => '1e7a5042',
                ],
                [
                    'display' => ['99cf060'],
                    'copy' => ['250a970'],
                    'image' => '3cd4ce6',
                ],
                [
                    'display' => ['27d0173'],
                    'copy' => ['6b13a8b'],
                    'image' => '2446b53',
                ],
                [
                    'display' => ['6da138f'],
                    'copy' => ['a07c3d9'],
                ],
            ],
            $giftAccounts,
        );

        $families = implode('<br>', array_map(fn ($family) => $this->e($family), $data->families));
        $html = $this->richHeading(
            $html,
            '7e52d34',
            $families !== '' ? 'Kami Yang Berbahagia,<br>' . $families : '',
        );

        if (! $data->sample) {
            $html = $this->heading($html, '29ea9bf1', 'Doa Restu & Ucapan');
        }

        return $this->applyCountdown(
            $html,
            $data,
            '~(elementor-countdown-wrapper" data-date=")[^"]*(")~i',
        );
    }

    private function applyLongDateEvents(string $html, InvitationViewData $data): string
    {
        $slots = [
            [
                'title' => '486fbadb', 'date' => '3f56fd5c', 'time' => '77bb928a',
                'location' => '71091379', 'map' => '17ad70b9',
            ],
            [
                'title' => '259ca5b2', 'date' => '7c7480f9', 'time' => '646c0c85',
                'location' => '6e4abe2a', 'map' => '551c3f5f',
            ],
        ];

        foreach ($slots as $index => $ids) {
            $event = $data->events[$index] ?? null;

            if (! $event) {
                continue;
            }

            $html = $this->heading($html, $ids['title'], $event->title);
            $html = $this->heading($html, $ids['date'], $this->longDate($event));
            $html = $this->heading($html, $ids['time'], $event->timeLabel);

            $location = 'Lokasi Acara :<br>' . $this->e($event->locationLabel);
            if (trim((string) $event->description) !== '') {
                $location .= '<br><br>' . $this->lineBreaks($event->description);
            }
            if (trim((string) $event->dressCode) !== '') {
                $location .= '<br><br>Dress code: ' . $this->e($event->dressCode);
            }

            $html = $this->richHeading($html, $ids['location'], $location);
            $html = $this->rewriter->setLinkHref($html, $ids['map'], $event->mapsUrl);
        }

        return $html;
    }

    protected function featureHiddenIds(): array
    {
        return [
            'story' => ['417f3acb'],
            'gallery' => ['2d3f5286'],
            'music' => [],
            'theme' => [],
            'rsvp' => ['1710e7ae'],
            'families' => [],
            'gift' => ['30764fe7', '8126b21', '7394cb94', 'c8e81b4'],
            'live_stream' => [],
            'countdown' => ['6aebba1b'],
            'quote' => [],
            'multiple_events' => [],
        ];
    }

    protected function backgroundIds(): array
    {
        return [
            'gate' => '90e124f',
            'cover' => '5d8c82ef',
            'groom' => '1b5619e',
            'bride' => '2247578f',
            'closing' => 'ad718c9',
        ];
    }

    /**
     * The gate section has one native slideshow column carrying stock photos.
     * clearSampleBackgrounds() empties it on real invitations, so refill that
     * existing setting from Foto Sampul/Cover (fallback footer). Samples keep
     * their original photography and no new background markup is added.
     */
    protected function applyBackgrounds(string $html, InvitationViewData $data): string
    {
        $html = parent::applyBackgrounds($html, $data);

        if ($data->sample) {
            return $html;
        }

        $cover = array_values(array_filter($data->backgrounds['cover'] ?? $data->photoGallery['cover'] ?? []));
        $footer = array_values(array_filter($data->backgrounds['closing'] ?? $data->photoGallery['footer'] ?? []));

        if ($cover !== [] || $footer !== []) {
            $html = $this->rewriter->setBackgroundGallery($html, '90e124f', $cover ?: $footer);
        }

        return $html;
    }

    protected function giftWidgetIds(): array
    {
        return [
            'button' => '4566503',
            'panel' => '467b1693',
            // The bank cards and address card are native. applyGifts() binds
            // them in place and hides each slot only when its data is empty.
            'legacy' => [],
        ];
    }

    protected function removedWidgetIds(InvitationViewData $data): array
    {
        $ids = [
            '7fa41008', // leaked sample date button
            '5c85982d', // stock decorative carousel
            '5703712a', '556100eb', // dead sample audio options
            '10f88bb0', // empty duplicate amplop section
            // Stock bank logos have no customer-owned field to bind to.
            '28795dca', '1e7a5042', '3cd4ce6', '2446b53',
        ];

        [$first, $second] = $data->orderedPeople();

        if (! $first->photo) {
            $ids[] = '5c77959a';
        }
        if (! $second->photo) {
            $ids[] = '6fa17f37';
        }

        foreach (['4ba5479f', '1f91f3aa', '1efe21ae'] as $index => $id) {
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

        // Hide each native gift card independently so an address-only gift
        // does not leave empty bank cards behind, and vice versa.
        foreach ([
            0 => '72bab81f', 1 => '4187e74', 2 => '080baa5', 3 => '9954c09',
        ] as $index => $columnId) {
            if (! isset($data->giftAccounts[$index])) {
                $ids[] = $columnId;
            }
        }
        if ($data->giftAddress === null || trim($data->giftAddress) === '') {
            $ids[] = '371ca36';
        }

        foreach (['3434e463', '1c6048c8', 'f664efe'] as $id) {
            if ($data->coupleLabel() === '') {
                $ids[] = $id;
            }
        }
        if (trim($data->guestName) === '') {
            $ids[] = '7d024d2e';
        }
        if (trim($data->invitationMessage) === '') {
            $ids[] = '7727edb5';
        }
        if (trim($data->quote) === '' && trim($data->quoteSource) === '') {
            $ids[] = '1c1092ad';
            $ids[] = '768cf1c2';
            $ids[] = '4f2c72bc';
        } elseif (trim($data->quoteSource) === '') {
            $ids[] = '4f2c72bc';
        }
        if ($data->families === []) {
            $ids[] = '7e52d34';
        }
        if (! $data->musicUrl || ! ($data->features['music'] ?? false)) {
            $ids[] = '40ac9c77';
        }

        foreach (['4d9a0eda'] as $id) {
            if (trim($bride->fullName) === '') {
                $ids[] = $id;
            }
        }
        foreach (['1ac8960f'] as $id) {
            if (trim($groom->fullName) === '') {
                $ids[] = $id;
            }
        }
        if ($this->personParentsEmpty($bride)) {
            $ids[] = '288c6a37';
        }
        if ($this->personParentsEmpty($groom)) {
            $ids[] = '15b90be7';
        }
        if (trim((string) $bride->instagram) === '') {
            $ids[] = '6162c34f';
        }
        if (trim((string) $groom->instagram) === '') {
            $ids[] = '14560187';
        }

        $eventSlots = [
            ['title' => '486fbadb', 'location' => '71091379', 'map' => '17ad70b9'],
            ['title' => '259ca5b2', 'location' => '6e4abe2a', 'map' => '551c3f5f'],
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
            ['image' => '4ba5479f', 'title' => '512deee3', 'description' => '4814e0ec'],
            ['image' => '1f91f3aa', 'title' => 'bbf0e89', 'description' => '2be373a1'],
            ['image' => '1efe21ae', 'title' => '226d188', 'description' => '134503af'],
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

        if (! $data->countdownTarget || ! ($data->features['countdown'] ?? false)) {
            $ids[] = '148d7c92';
        }

        return array_values(array_unique($ids));
    }
}
