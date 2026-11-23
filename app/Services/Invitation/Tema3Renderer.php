<?php

namespace App\Services\Invitation;

use App\Data\GiftAccountViewData;
use App\Data\InvitationViewData;

class Tema3Renderer extends AbstractTemplateRenderer
{
    protected function key(): string
    {
        return 'tema3';
    }

    protected function apply(string $html, InvitationViewData $data): string
    {
        $html = $this->applyCover(
            $html,
            $data,
            coupleId: '36c0d7e',
            dateId: '48f5cd1d',
            guestId: '11067841',
            messageId: '60fddeae',
            quoteId: '45b7bff5',
            quoteWithSource: false,
        );
        $html = $this->heading($html, '705132cd', $data->coupleLabel());
        $html = $this->heading($html, '1c6d5369', $data->quoteSource);

        $html = $this->applyCouple($html, $data, [
            'bride_names' => ['48290d3'],
            'groom_names' => ['7c52ae4f'],
            'bride_parents' => ['58dbf87'],
            'groom_parents' => ['64770940'],
            'bride_column' => '171b4b90',
            'groom_column' => '7ffa566e',
            'bride_instagram' => ['1abe6d75'],
            'groom_instagram' => ['1ff27945'],
        ], true);

        $html = $this->applyCoupleRoleLabels($html, $data, ['79ee9ee0', '58509836'], 'BRIDE', 'GROOM');
        $html = $this->applyCouplePhotos($html, $data, ['171b4b90', '7ffa566e'], 'background');

        $html = $this->applyPlainDateEvents($html, $data);

        $html = $this->applyStories($html, $data, [
            ['image' => '442e8e9', 'title' => 'af79f95', 'description' => 'd98c0a6'],
            ['image' => 'f23fa97', 'title' => '761e7b8', 'description' => '021a570'],
            ['image' => '3cad5bb', 'title' => 'cf2c15e', 'description' => '57242d4'],
        ]);

        $html = $this->applyGallery($html, $data, '313e6f45');
        $html = $this->applySupportingPhotos(
            $html,
            $data,
            carouselId: '8050a98',
            imageIds: [],
        );

        // The source has two native gift blocks: a bank account and a gift
        // delivery address. Bind each one to its own Elementor widgets so the
        // customer's data appears in the original layout without adding markup.
        $giftAccounts = [];
        if (isset($data->giftAccounts[0])) {
            $giftAccounts[0] = $data->giftAccounts[0];
        }
        if ($data->giftAddress !== null && trim($data->giftAddress) !== '') {
            $giftAccounts[1] = new GiftAccountViewData(
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
                    'display' => ['18f5bfce'],
                    'copy' => ['680ac935'],
                    'image' => '3c928698',
                ],
                [
                    'display' => ['7fd7436'],
                    'copy' => ['20ea64a'],
                ],
            ],
            $giftAccounts,
        );

        $families = implode('<br>', array_map(fn ($family) => $this->e($family), $data->families));
        $html = $this->richHeading(
            $html,
            '206f9fe3',
            $families !== '' ? 'Kami Yang Berbahagia,<br>' . $families : '',
        );
        $html = $this->heading($html, '56adced9', $data->coupleLabel());

        if (! $data->sample) {
            $html = $this->heading($html, '167f290f', 'Doa Restu & Ucapan');
        }

        return $this->applyCountdown(
            $html,
            $data,
            '~(wpkoi-elements-countdown-items" data-date=")[^"]*(")~i',
        );
    }

    private function applyPlainDateEvents(string $html, InvitationViewData $data): string
    {
        $slots = [
            [
                'title' => '2ba219ef', 'day' => '76b28b05', 'date' => '61e2a7b6',
                'time' => '472cfa', 'location' => '1a258e4a', 'map' => '2a12b46e',
            ],
            [
                'title' => '113c03a0', 'day' => '2af5641c', 'date' => '43c0408b',
                'time' => '605ee72d', 'location' => '2ed15e0a', 'map' => 'fedcd31',
            ],
        ];

        foreach ($slots as $index => $ids) {
            $event = $data->events[$index] ?? null;

            if (! $event) {
                continue;
            }

            $html = $this->heading($html, $ids['title'], $event->title);
            $html = $this->heading($html, $ids['day'], $event->dayName);
            $html = $this->heading($html, $ids['date'], $this->slashDate($event->date));
            $html = $this->heading($html, $ids['time'], $event->timeLabel);

            $location = $this->e($event->locationLabel);
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
            'story' => ['b60f23d'],
            'gallery' => ['7cb92d5b'],
            'music' => [],
            'theme' => [],
            'rsvp' => ['1bc769a4'],
            'families' => [],
            'gift' => ['3b4f3c4e'],
            'live_stream' => [],
            'countdown' => ['57eecfab'],
            'quote' => [],
            'multiple_events' => [],
        ];
    }

    protected function backgroundIds(): array
    {
        return [
            'cover' => '6f09d350',
            'cover_inner' => '5e2fd3d8',
            'groom' => '7ffa566e',
            'bride' => '171b4b90',
            'closing' => '58ed48e',
        ];
    }

    protected function applyBackgrounds(string $html, InvitationViewData $data): string
    {
        $html = parent::applyBackgrounds($html, $data);

        if ($data->sample) {
            return $html;
        }

        $cover = array_values(array_filter($data->backgrounds['cover'] ?? $data->photoGallery['cover'] ?? []));
        $footer = array_values(array_filter($data->backgrounds['closing'] ?? $data->photoGallery['footer'] ?? []));

        // Only these three columns carry a stock gallery in the original
        // Thema 39 source. clearSampleBackgrounds() empties them on real
        // invitations, so refill the existing settings with the customer's
        // cover/footer uploads. The two sibling columns (4c421c4f and
        // 37c0b978) are intentionally empty in the template and stay empty.
        $eventSlideshows = [
            '4684d4b4' => $cover ?: $footer,
            'b9055e3' => $cover ?: $footer,
            '15865fca' => $footer ?: $cover,
        ];

        foreach ($eventSlideshows as $id => $urls) {
            if ($urls === []) {
                continue;
            }

            $html = $this->rewriter->setBackgroundGallery($html, $id, $urls);
        }

        // The inner cover follows the footer upload, not the opening slideshow.
        return $this->rewriter->setBackgroundGallery(
            $html,
            '5e2fd3d8',
            $data->photoGallery['footer'] ?? [],
        );
    }

    protected function giftWidgetIds(): array
    {
        return [
            'button' => 'udgiftbutton',
            'panel' => 'udgiftpanel',
            // The gift sections and widgets are native. They are filled in
            // place by applyGifts() and hidden only when their own data is
            // empty; none of them may be cleared wholesale.
            'legacy' => [],
        ];
    }

    protected function removedWidgetIds(InvitationViewData $data): array
    {
        // The stock bank logo has no customer-owned field, so it stays hidden.
        // The account/address headings, values and copy buttons are native
        // slots and are hidden by applyGifts() only when that slot is empty.
        $ids = [
            '3c928698',
        ];

        if (! $this->hasSupportingPhotos($data)) {
            $ids[] = '8050a98'; // stock couple-photo carousel
        }

        foreach (['442e8e9', 'f23fa97', '3cad5bb'] as $index => $id) {
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

        // Hide each native gift block independently when its own data is
        // missing. With both missing the whole top section is hidden by
        // hiddenIds(), but an address-only gift must not leave an empty bank
        // block behind (and vice versa).
        if (! isset($data->giftAccounts[0])) {
            $ids[] = '12489c64';
        }
        if ($data->giftAddress === null || trim($data->giftAddress) === '') {
            $ids[] = '39b55ef';
        }

        foreach (['36c0d7e', '705132cd'] as $id) {
            if ($data->coupleLabel() === '') {
                $ids[] = $id;
            }
        }
        if ($data->saveTheDate === '') {
            $ids[] = '48f5cd1d';
        }
        if (trim($data->guestName) === '') {
            $ids[] = '11067841';
        }
        if (trim($data->invitationMessage) === '') {
            $ids[] = '60fddeae';
        }
        if (trim($data->quote) === '' && trim($data->quoteSource) === '') {
            $ids[] = '45b7bff5';
            $ids[] = '1c6d5369';
        } elseif (trim($data->quoteSource) === '') {
            $ids[] = '1c6d5369';
        }
        if ($data->families === []) {
            $ids[] = '206f9fe3';
        }
        if ($data->coupleLabel() === '') {
            $ids[] = '56adced9';
        }
        if (! $data->musicUrl || ! ($data->features['music'] ?? false)) {
            $ids[] = '412f60f3';
        }

        foreach (['48290d3'] as $id) {
            if (trim($bride->fullName) === '') {
                $ids[] = $id;
            }
        }
        foreach (['7c52ae4f'] as $id) {
            if (trim($groom->fullName) === '') {
                $ids[] = $id;
            }
        }
        if ($this->personParentsEmpty($bride)) {
            $ids[] = '58dbf87';
        }
        if ($this->personParentsEmpty($groom)) {
            $ids[] = '64770940';
        }
        if (trim((string) $bride->instagram) === '') {
            $ids[] = '1abe6d75';
        }
        if (trim((string) $groom->instagram) === '') {
            $ids[] = '1ff27945';
        }

        $eventSlots = [
            ['title' => '2ba219ef', 'location' => '1a258e4a', 'map' => '2a12b46e'],
            ['title' => '113c03a0', 'location' => '2ed15e0a', 'map' => 'fedcd31'],
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
            ['image' => '442e8e9', 'title' => 'af79f95', 'description' => 'd98c0a6'],
            ['image' => 'f23fa97', 'title' => '761e7b8', 'description' => '021a570'],
            ['image' => '3cad5bb', 'title' => 'cf2c15e', 'description' => '57242d4'],
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
            $ids[] = '1614e940';
        }

        return array_values(array_unique($ids));
    }
}
