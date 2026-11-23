<?php

namespace App\Services\Invitation;

use App\Data\GiftAccountViewData;
use App\Data\InvitationViewData;

class Tema2Renderer extends AbstractTemplateRenderer
{
    protected function key(): string
    {
        return 'tema2';
    }

    protected function apply(string $html, InvitationViewData $data): string
    {
        $html = $this->applyCover(
            $html,
            $data,
            coupleId: '4f987902',
            dateId: '5a814787',
            guestId: '1e07ef3f',
            messageId: '3afb4993',
            quoteId: '1752312c',
            quoteWithSource: false,
        );
        $html = $this->heading($html, '3ba1c8f4', $data->quoteSource);

        $html = $this->applyCouple($html, $data, [
            'bride_names' => ['6c936581', '26afe6f4'],
            'groom_names' => ['446761ce', '40228ba6'],
            'bride_parents' => ['7158d3b2'],
            'groom_parents' => ['39e1866d'],
            'bride_column' => '1a8ab2c4',
            'groom_column' => '76dba0dd',
            'bride_instagram' => ['7368c603'],
            'groom_instagram' => ['636f3f63'],
        ], true);

        $html = $this->applyCoupleRoleLabels($html, $data, ['a982656', '3ba075a1'], 'BRIDE', 'GROOM');
        $html = $this->applyCouplePhotos($html, $data, ['1a8ab2c4', '76dba0dd'], 'background');

        $html = $this->applyEvents($html, $data, [
            [
                'title' => '627ffbff',
                'day' => '7df69d59',
                'counter' => '4f8a1ec1',
                'month' => '6f815f3f',
                'year' => '134262a4',
                'time' => '6b100790',
                'location' => '76fd03e5',
                'location_prefix' => '',
                'map' => '28f5d021',
            ],
            [
                'title' => '4f74390e',
                'day' => '66444592',
                'counter' => '3208afb2',
                'month' => '776a2d4d',
                'year' => '3fa1af01',
                'time' => '6a31072c',
                'location' => '3ffcf0f2',
                'location_prefix' => '',
                'map' => '2e74f9d6',
            ],
        ]);

        $html = $this->applyStories($html, $data, [
            ['image' => '55dab7a2', 'title' => '167a0beb', 'description' => '6a846cbf'],
            ['image' => '783cf6b0', 'title' => '1ddb084a', 'description' => '3ff8d429'],
            ['image' => '725487cb', 'title' => '464015fa', 'description' => '30d817a3'],
        ]);

        $html = $this->applyGallery($html, $data, '1a5fc17a');

        $html = $this->applySupportingPhotos(
            $html,
            $data,
            carouselId: '3fba8d42',
            imageIds: ['64d9be4f'],
        );

        // The source has two native gift blocks: the bank account and the
        // delivery address. The second slot is filled from settings.gift_address
        // so the address appears in its own Elementor widgets instead of being
        // hidden as an empty account slot.
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
                    'display' => ['7cf88fd6', '265ec209'],
                    'copy' => ['6d98f144', '7eb8f639'],
                    'image' => '19b4f468',
                ],
                [
                    'display' => ['3c3bc048', '4d72bbb8'],
                    'copy' => ['55fdfd25', '7a9e4961'],
                ],
            ],
            $giftAccounts,
        );

        $families = implode('<br>', array_map(fn ($family) => $this->e($family), $data->families));
        $html = $this->richHeading(
            $html,
            '74116615',
            $families !== '' ? 'Kami Yang Berbahagia,<br>' . $families : '',
        );
        $html = $this->heading($html, '5a5b4f7e', $data->coupleLabel());

        // Replace the template's English sample heading with a neutral,
        // customer-agnostic label on real invitations.
        if (! $data->sample) {
            $html = $this->heading($html, '1bbe5ed5', 'Doa Restu & Ucapan');
        }

        return $this->applyCountdown(
            $html,
            $data,
            '~(wpkoi-elements-countdown-items" data-date=")[^"]*(")~i',
        );
    }

    protected function featureHiddenIds(): array
    {
        return [
            'story' => ['781761c7', '167a0beb', '1ddb084a', '464015fa'],
            'gallery' => ['71779c60', '25729427', '1a5fc17a'],
            'music' => [],
            'theme' => [],
            'rsvp' => ['74f522e5', '37dc99e7'],
            // Family/couple labels live inside the closing section that also
            // carries the footer background. Hide only the labels via
            // emptyWidgetIds(); hiding the parent section would hide the photo.
            // The whole gift top section is independent of the closing photo,
            // so it can be hidden cleanly when no gift data was supplied.
            'gift' => ['111523f1'],
            'live_stream' => [],
            // Countdown widgets live inside the cover section in tema2. Hiding
            // their parent section would also hide the footer background, so
            // they are treated as ordinary widgets in emptyWidgetIds() instead.
        ];
    }

    protected function backgroundIds(): array
    {
        return [
            'cover' => '56efded2',
            'groom' => '1a8ab2c4',
            'bride' => '76dba0dd',
            'closing' => 'a09598',
        ];
    }

    /**
     * The sub-cover section (1bc32d64) lives inside the cover section, and its
     * photo is the background of column 5e3f969e. In the stock template that
     * column shares the opening cover slideshow, which made the sub-cover look
     * like it was taking the cover upload. Real invitations must instead show
     * the customer's footer upload there; the opening cover keeps its own
     * cover photo. There is no separate sub-cover upload: Foto Pendukung feeds
     * the couple backgrounds, while this section follows the footer slot.
     * Native markup and samples are left untouched.
     */
    protected function applyBackgrounds(string $html, InvitationViewData $data): string
    {
        $html = parent::applyBackgrounds($html, $data);

        if ($data->sample) {
            return $html;
        }

        $cover = array_values(array_filter($data->backgrounds['cover'] ?? $data->photoGallery['cover'] ?? []));
        $footer = array_values(array_filter($data->backgrounds['closing'] ?? $data->photoGallery['footer'] ?? []));

        // The "THE WEDDING OF" section has one native slideshow column with a
        // stock photo in the source. clearSampleBackgrounds() empties it on real
        // invitations, so refill that existing setting from Foto Sampul/Cover.
        if ($cover !== [] || $footer !== []) {
            $html = $this->rewriter->setBackgroundGallery(
                $html,
                '17b7bb78',
                $cover ?: $footer,
            );
        }

        return $this->rewriter->setBackgroundGallery(
            $html,
            '5e3f969e',
            $data->photoGallery['footer'] ?? [],
        );
    }

    protected function giftWidgetIds(): array
    {
        return [
            'button' => '25441996',
            'panel' => '18363c3b',
            // The address block is native. applyGifts() binds it in place and
            // hides it only when the customer supplied no delivery address.
            'legacy' => [],
        ];
    }

    protected function removedWidgetIds(InvitationViewData $data): array
    {
        // The closing paragraph and greeting (5713d21a / 1ff6157c) are part of
        // the template's editorial copy, not customer-owned placeholders. Keep
        // them in real invitations; native template files stay untouched.
        $ids = [];

        // With customer gallery photos these slots are rewritten in place,
        // keeping the native carousel and image widget markup intact.
        if (! $this->hasSupportingPhotos($data)) {
            $ids[] = '3fba8d42'; // sample couple-photo carousel
            $ids[] = '64d9be4f'; // decorative sample photo
        }

        foreach (['55dab7a2', '783cf6b0', '725487cb'] as $index => $id) {
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
            $ids[] = '18363c3b';
        }
        if ($data->giftAddress === null || trim($data->giftAddress) === '') {
            $ids[] = '61f9301f';
        }

        if ($data->families === [] || ! ($data->features['families'] ?? false)) {
            $ids[] = '74116615';
        }

        if ($data->coupleLabel() === '') {
            $ids[] = '4f987902';
        }
        if ($data->saveTheDate === '') {
            $ids[] = '5a814787';
        }
        if (trim($data->guestName) === '') {
            $ids[] = '1e07ef3f';
        }
        if (trim($data->invitationMessage) === '') {
            $ids[] = '3afb4993';
        }
        if (trim($data->quote) === '' && trim($data->quoteSource) === '') {
            $ids[] = '1752312c';
            $ids[] = '3ba1c8f4';
        } elseif (trim($data->quoteSource) === '') {
            $ids[] = '3ba1c8f4';
        }
        if (! $data->musicUrl || ! ($data->features['music'] ?? false)) {
            $ids[] = '35cb1f';
        }

        foreach (['6c936581', '26afe6f4'] as $id) {
            if (trim($bride->fullName) === '') {
                $ids[] = $id;
            }
        }
        foreach (['446761ce', '40228ba6'] as $id) {
            if (trim($groom->fullName) === '') {
                $ids[] = $id;
            }
        }
        if ($this->personParentsEmpty($bride)) {
            $ids[] = '7158d3b2';
        }
        if ($this->personParentsEmpty($groom)) {
            $ids[] = '39e1866d';
        }
        // Couple backgrounds come from the Foto dan Galeri slots, so they no
        // longer depend on the person records being filled.

        if (trim((string) $bride->instagram) === '') {
            $ids[] = '7368c603';
        }
        if (trim((string) $groom->instagram) === '') {
            $ids[] = '636f3f63';
        }

        $eventSlots = [
            [
                'title' => '627ffbff', 'day' => '7df69d59', 'counter' => '4f8a1ec1',
                'month' => '6f815f3f', 'year' => '134262a4', 'time' => '6b100790',
                'location' => '76fd03e5', 'map' => '28f5d021',
            ],
            [
                'title' => '4f74390e', 'day' => '66444592', 'counter' => '3208afb2',
                'month' => '776a2d4d', 'year' => '3fa1af01', 'time' => '6a31072c',
                'location' => '3ffcf0f2', 'map' => '2e74f9d6',
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
            ['image' => '55dab7a2', 'title' => '167a0beb', 'description' => '6a846cbf'],
            ['image' => '783cf6b0', 'title' => '1ddb084a', 'description' => '3ff8d429'],
            ['image' => '725487cb', 'title' => '464015fa', 'description' => '30d817a3'],
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
            $ids = array_merge($ids, ['27f8d37b', '30d71a57', '5e524221']);
        }

        return array_values(array_unique($ids));
    }
}
