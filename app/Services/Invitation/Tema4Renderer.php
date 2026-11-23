<?php

namespace App\Services\Invitation;

use App\Data\GiftAccountViewData;
use App\Data\InvitationViewData;

class Tema4Renderer extends AbstractTemplateRenderer
{
    protected function key(): string
    {
        return 'tema4';
    }

    protected function apply(string $html, InvitationViewData $data): string
    {
        $html = $this->heading($html, '75662565', $data->coupleLabel());
        $html = $this->heading($html, '74cb798d', $data->coupleLabel());
        $html = $this->heading($html, '447ff81a', $this->formattedDate($data->saveTheDate));
        $html = $this->heading($html, '124d60bc', $data->guestName);
        $html = $this->richHeading($html, '34aa1951', $this->e($data->quote));
        $html = $this->heading($html, '1850aef6', $data->quoteSource);

        $html = $this->applyCouple($html, $data, [
            'bride_names' => ['5650d75c'],
            'groom_names' => ['977dd53'],
            'bride_parents' => ['18876561'],
            'groom_parents' => ['51431a41'],
            'bride_instagram' => ['72bbb22'],
            'groom_instagram' => ['86eaba3'],
        ], true);

        $html = $this->applyCoupleRoleLabels($html, $data, ['5ecfbd74', '70d52ef9'], 'THE BRIDE', 'THE GROOM');

        $html = $this->applyCounterEvents($html, $data);

        $html = $this->applyStories($html, $data, [
            ['image' => '238fb039', 'title' => '7d978b20', 'description' => '56e1e592'],
            ['image' => '5bac9c65', 'title' => 'aeb52d2', 'description' => '3b31a6ce'],
            ['image' => '79a74673', 'title' => 'cda6f5', 'description' => '6b518047'],
        ]);

        $html = $this->applyGallery($html, $data, '34fc0486');

        if ($this->hasSupportingPhotos($data)) {
            foreach (['200aee0e', '5bcef27b'] as $index => $id) {
                $html = $this->rewriter->setImageSource(
                    $html,
                    $id,
                    $data->supportingPhotos[$index % count($data->supportingPhotos)] ?? null,
                );
            }
        }

        $html = $this->applyCouplePhotos($html, $data, ['200aee0e', '5bcef27b'], 'image');

        // The source has two native gift blocks: the bank account and the
        // delivery address. Keep each customer value in its own Elementor
        // widgets; no envelope panel or new markup is introduced.
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
                    'display' => ['8716a95'],
                    'copy' => ['a7e0da'],
                    'image' => '1e1170bb',
                ],
                [
                    'display' => ['1d424bd1'],
                    'copy' => ['9ed974b'],
                ],
            ],
            $giftAccounts,
        );

        $families = implode('<br>', array_map(fn ($family) => $this->e($family), $data->families));
        $html = $this->richHeading(
            $html,
            '6beac9b3',
            $families !== '' ? 'Kami Yang Berbahagia,<br>' . $families : '',
        );
        $html = $this->heading($html, '58e67324', $data->coupleLabel());

        if (! $data->sample) {
            $html = $this->heading($html, '5ae714e8', 'Doa Restu & Ucapan');
        }

        return $this->applyCountdown(
            $html,
            $data,
            '~(wpkoi-elements-countdown-items" data-date=")[^"]*(")~i',
        );
    }

    private function applyCounterEvents(string $html, InvitationViewData $data): string
    {
        $slots = [
            [
                'title' => 'eac3ab0', 'counter' => '31e0839', 'date' => '2fa3867b',
                'time' => '4eec1488', 'location' => '6eeae72e', 'map' => '3b3e1875',
            ],
            [
                'title' => 'da1361', 'counter' => '624cd45b', 'date' => '111b63ab',
                'time' => '16b96ebe', 'location' => '66a89aa5', 'map' => '68112076',
            ],
        ];

        foreach ($slots as $index => $ids) {
            $event = $data->events[$index] ?? null;

            if (! $event) {
                continue;
            }

            $html = $this->heading($html, $ids['title'], $event->title);
            $html = $this->rewriter->setCounter($html, $ids['counter'], (int) $event->day);
            $html = $this->richHeading($html, $ids['date'], $this->stackedDate($event));
            $html = $this->rewriter->setIconListText($html, $ids['time'], $event->timeLabel);

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
            'story' => ['17810ae7'],
            'gallery' => ['51bf5d5a'],
            'music' => [],
            'theme' => [],
            'rsvp' => ['679f63b6', '7950cddb'],
            'families' => [],
            'gift' => ['565b39c8'],
            'live_stream' => [],
            // Both countdown widgets live in the cover section in tema4.
            'countdown' => [],
            'quote' => [],
            'multiple_events' => [],
        ];
    }

    protected function backgroundIds(): array
    {
        return [
            'cover' => '4e4391bb',
            'cover_inner' => 'b86eaa9',
            'groom' => '5e892ecd',
            'bride' => '5204381e',
            'closing' => '5c3c2729',
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
        $eventPhotos = $cover ?: $footer;

        // Only these three columns carry a stock gallery in the original
        // Thema 42 source. clearSampleBackgrounds() empties them on real
        // invitations, so refill the existing settings from Foto Sampul/Cover.
        // The two sibling columns (565f4543, 3d6059f4) are intentionally empty
        // in the template and must stay empty.
        foreach (['528b3f36', '5e892ecd', '5204381e'] as $id) {
            if ($eventPhotos === []) {
                continue;
            }

            $html = $this->rewriter->setBackgroundGallery($html, $id, $eventPhotos);
        }

        return $this->rewriter->setBackgroundGallery(
            $html,
            'b86eaa9',
            $data->photoGallery['footer'] ?? [],
        );
    }

    protected function giftWidgetIds(): array
    {
        return [
            'button' => 'udgiftbutton',
            'panel' => 'udgiftpanel',
            // The gift sections and widgets are native. They are bound in place
            // by applyGifts() and hidden only when their own data is empty.
            'legacy' => [],
        ];
    }

    protected function removedWidgetIds(InvitationViewData $data): array
    {
        // The stock bank logo has no customer-owned field, so it stays hidden.
        // Account/address values and copy buttons are native slots; applyGifts()
        // hides them only when that slot has no data.
        $ids = [
            '1e1170bb',
        ];

        if (! $this->hasSupportingPhotos($data)) {
            $ids[] = '200aee0e';
            $ids[] = '5bcef27b';
        }

        foreach (['238fb039', '5bac9c65', '79a74673'] as $index => $id) {
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

        // Hide each native gift block independently. An address-only gift must
        // not leave an empty bank block behind, and a bank-only gift must not
        // leave an empty address block behind.
        if (! isset($data->giftAccounts[0])) {
            $ids[] = '50a93844';
        }
        if ($data->giftAddress === null || trim($data->giftAddress) === '') {
            $ids[] = '54dafe21';
        }

        foreach (['75662565', '74cb798d', '58e67324'] as $id) {
            if ($data->coupleLabel() === '') {
                $ids[] = $id;
            }
        }
        if ($data->saveTheDate === '') {
            $ids[] = '447ff81a';
        }
        if (trim($data->guestName) === '') {
            $ids[] = '124d60bc';
        }
        if (trim($data->quote) === '' && trim($data->quoteSource) === '') {
            $ids[] = '34aa1951';
            $ids[] = '1850aef6';
        } elseif (trim($data->quoteSource) === '') {
            $ids[] = '1850aef6';
        }
        if ($data->families === []) {
            $ids[] = '6beac9b3';
        }
        if (! $data->musicUrl || ! ($data->features['music'] ?? false)) {
            $ids[] = '4bc0a3c2';
        }

        foreach (['5650d75c'] as $id) {
            if (trim($bride->fullName) === '') {
                $ids[] = $id;
            }
        }
        foreach (['977dd53'] as $id) {
            if (trim($groom->fullName) === '') {
                $ids[] = $id;
            }
        }
        if ($this->personParentsEmpty($bride)) {
            $ids[] = '18876561';
        }
        if ($this->personParentsEmpty($groom)) {
            $ids[] = '51431a41';
        }
        if (trim((string) $bride->instagram) === '') {
            $ids[] = '72bbb22';
        }
        if (trim((string) $groom->instagram) === '') {
            $ids[] = '86eaba3';
        }

        $eventSlots = [
            [
                'title' => 'eac3ab0', 'counter' => '31e0839', 'date' => '2fa3867b',
                'time' => '4eec1488', 'location' => '6eeae72e', 'map' => '3b3e1875',
            ],
            [
                'title' => 'da1361', 'counter' => '624cd45b', 'date' => '111b63ab',
                'time' => '16b96ebe', 'location' => '66a89aa5', 'map' => '68112076',
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
            ['image' => '238fb039', 'title' => '7d978b20', 'description' => '56e1e592'],
            ['image' => '5bac9c65', 'title' => 'aeb52d2', 'description' => '3b31a6ce'],
            ['image' => '79a74673', 'title' => 'cda6f5', 'description' => '6b518047'],
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
            $ids[] = '26cc5a0c';
            $ids[] = '7ef71e6f';
        }

        return array_values(array_unique($ids));
    }
}
