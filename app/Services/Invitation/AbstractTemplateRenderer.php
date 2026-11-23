<?php

namespace App\Services\Invitation;

use App\Data\EventViewData;
use App\Data\InvitationViewData;
use App\Data\PersonViewData;
use App\Data\StoryViewData;
use Carbon\Carbon;

abstract class AbstractTemplateRenderer
{
    protected readonly HtmlRewriter $rewriter;

    public function __construct()
    {
        $this->rewriter = new HtmlRewriter($this->key());
    }

    abstract protected function key(): string;

    abstract protected function apply(string $html, InvitationViewData $data): string;

    /**
     * Widget data-ids grouped by feature. A disabled feature hides the whole
     * Elementor top section that contains the first matching widget.
     *
     * @return array<string, array<int, string>>
     */
    abstract protected function featureHiddenIds(): array;

    /**
     * Elementor data-ids for the four slideshow background slots.
     *
     * @return array<string, string>
     */
    abstract protected function backgroundIds(): array;

    /**
     * Elementor data-ids for the "Kirim Amplop" button and the envelope panel.
     * Real invitations replace the panel with bank cards but keep the native
     * toggle wired to these ids.
     *
     * @return array{button:string,panel:string,legacy?:array<int,string>}
     */
    abstract protected function giftWidgetIds(): array;

    /**
     * Widgets that must disappear because the customer left their field empty.
     * Unlike featureHiddenIds() these hide only the widget, never its section.
     *
     * @return array<int, string>
     */
    abstract protected function emptyWidgetIds(InvitationViewData $data): array;

    /**
     * Sample-only widgets that have no customer-owned field to overwrite.
     * They stay in the source markup but are hidden on public invitations.
     *
     * @return array<int, string>
     */
    abstract protected function removedWidgetIds(InvitationViewData $data): array;

    public function render(string $source, InvitationViewData $data): string
    {
        $html = $this->rewriter->prepare($source);
        $html = $this->clearSampleBackgrounds($html, $data);
        // Backgrounds are applied first so an explicit couple_order can bind
        // the ordered couple photos to the same native slots afterwards.
        $html = $this->applyBackgrounds($html, $data);
        $html = $this->apply($html, $data);
        $html = $this->applyCommon($html, $data);

        $hiddenWidgets = array_merge(
            $this->removedWidgetIds($data),
            $this->emptyWidgetIds($data),
        );

        return $this->rewriter->finalize(
            $html,
            $data,
            $this->hiddenIds($data),
            $hiddenWidgets,
        );
    }

    protected function e(?string $value): string
    {
        return e($value ?? '');
    }

    /**
     * Sample-only widgets are hidden, never removed. Their native markup stays
     * in the document so every template keeps the exact source structure.
     */
    private function clearSampleBackgrounds(string $html, InvitationViewData $data): string
    {
        return $data->sample ? $html : $this->rewriter->clearSampleBackgrounds($html);
    }

    protected function heading(string $html, string $id, string $value): string
    {
        return $this->rewriter->setHeading($html, $id, $value);
    }

    protected function richHeading(string $html, string $id, string $value): string
    {
        return $this->rewriter->setHeading($html, $id, $value, false);
    }

    protected function lineBreaks(?string $value): string
    {
        return nl2br($this->e($value), false);
    }

    protected function formattedDate(?string $date): string
    {
        if (! $date) {
            return '';
        }

        $parsed = Carbon::parse($date);

        return $parsed->format('d.m.Y') === $date ? $date : $parsed->format('d.m.Y');
    }

    protected function slashDate(?string $date): string
    {
        return $date ? Carbon::parse($date)->format('d/m/Y') : '';
    }

    protected function longDate(EventViewData $event): string
    {
        return ucfirst($event->dayName) . ', ' . $event->day . ' ' . $event->month . ' ' . $event->year;
    }

    protected function stackedDate(EventViewData $event): string
    {
        return ucfirst($event->dayName) . '<br>' . $event->month . '<br>' . $event->year;
    }

    protected function parentsLine(PersonViewData $person, bool $rich = false): string
    {
        $label = str_replace(' pertama', '', $person->parentsLabel);
        $parts = array_filter([$person->fatherName, $person->motherName], fn ($name) => trim($name) !== '');
        $parents = implode(' dan ', $parts);
        $plain = $label . ' ' . $parents;

        if (! $rich) {
            return $plain;
        }

        return '<b>' . $this->e($label) . '<br></b> ' . $this->e($parents);
    }

    protected function personIsEmpty(PersonViewData $person): bool
    {
        return trim($person->fullName) === ''
            && trim($person->nickname) === ''
            && ! $person->photo
            && trim($person->fatherName) === ''
            && trim($person->motherName) === ''
            && trim((string) $person->instagram) === '';
    }

    protected function personParentsEmpty(PersonViewData $person): bool
    {
        return trim($person->fatherName) === '' && trim($person->motherName) === '';
    }

    protected function instagramUrl(?string $instagram): ?string
    {
        $handle = ltrim(trim($instagram ?: ''), '@');

        return $handle !== '' ? 'https://instagram.com/' . $handle : null;
    }

    protected function applyCover(
        string $html,
        InvitationViewData $data,
        string $coupleId,
        string $dateId,
        string $guestId,
        string $messageId,
        string $quoteId,
        bool $quoteWithSource = true,
    ): string {
        $html = $this->heading($html, $coupleId, $data->coupleLabel());
        $html = $this->heading($html, $dateId, $this->formattedDate($data->saveTheDate));
        $html = $this->heading($html, $guestId, $data->guestName);
        $html = $this->heading($html, $messageId, $data->invitationMessage);

        // tema1 has a single heading for quote + source; tema2 has two separate
        // widgets and renders the source itself. Never write the source twice.
        $quote = $this->e($data->quote);
        if ($quoteWithSource && trim($data->quoteSource) !== '') {
            $quote .= '<br><br>' . $this->e($data->quoteSource);
        }
        $html = $this->richHeading($html, $quoteId, $quote);

        return $html;
    }

    /**
     * @param  array<string, string>  $ids
     */
    protected function applyCouple(
        string $html,
        InvitationViewData $data,
        array $ids,
        bool $richParents,
    ): string {
        // The keys describe visual slots in the frozen template, not genders.
        // Every template puts the first slot before the second one, so one
        // ordered view keeps names, biodata and social links in sync.
        [$first, $second] = $data->orderedPeople();

        foreach (($ids['bride_names'] ?? []) as $id) {
            $html = $this->heading($html, $id, $first->fullName);
        }
        foreach (($ids['groom_names'] ?? []) as $id) {
            $html = $this->heading($html, $id, $second->fullName);
        }
        foreach (($ids['bride_parents'] ?? []) as $id) {
            $html = $richParents
                ? $this->richHeading($html, $id, $this->parentsLine($first, true))
                : $this->heading($html, $id, $this->parentsLine($first));
        }
        foreach (($ids['groom_parents'] ?? []) as $id) {
            $html = $richParents
                ? $this->richHeading($html, $id, $this->parentsLine($second, true))
                : $this->heading($html, $id, $this->parentsLine($second));
        }

        // Couple photos are no longer used as background slideshows. The
        // Foto dan Galeri panel supplies every decorative photo through
        // applyBackgrounds(), so only names and social links are applied here.

        foreach (($ids['bride_instagram'] ?? []) as $id) {
            $html = $this->rewriter->setButtonLabel($html, $id, $first->instagram ?: 'Instagram');
            $html = $this->rewriter->setLinkHref($html, $id, $this->instagramUrl($first->instagram));
        }
        foreach (($ids['groom_instagram'] ?? []) as $id) {
            $html = $this->rewriter->setButtonLabel($html, $id, $second->instagram ?: 'Instagram');
            $html = $this->rewriter->setLinkHref($html, $id, $this->instagramUrl($second->instagram));
        }

        return $html;
    }

    /**
     * Role captions are part of the frozen template. Only an explicit
     * male_first choice swaps them; legacy and female_first keep the original
     * BRIDE/GROOM order byte-for-byte.
     *
     * @param  array{0: string, 1: string}  $ids  First visual slot, then second.
     */
    protected function applyCoupleRoleLabels(
        string $html,
        InvitationViewData $data,
        array $ids,
        string $firstLabel,
        string $secondLabel,
    ): string {
        if (! $data->coupleOrder()->isMaleFirst()) {
            return $html;
        }

        $html = $this->heading($html, $ids[0], $secondLabel);

        return $this->heading($html, $ids[1], $firstLabel);
    }

    /**
     * Bind the ordered couple photos to the two native visual photo slots.
     * A null setting keeps the legacy gallery/supporting-photo behaviour;
     * either explicit choice makes the person's own photo follow the chosen
     * order. A missing photo leaves the existing native slot untouched.
     *
     * @param  array{0: string, 1: string}  $ids  First visual slot, then second.
     * @param  'background'|'image'  $type
     */
    protected function applyCouplePhotos(
        string $html,
        InvitationViewData $data,
        array $ids,
        string $type,
    ): string {
        if ($data->sample || ! $data->hasExplicitCoupleOrder()) {
            return $html;
        }

        [$first, $second] = $data->orderedPeople();

        foreach ([[$ids[0], $first], [$ids[1], $second]] as [$id, $person]) {
            if (! $person->photo) {
                continue;
            }

            $html = $type === 'background'
                ? $this->rewriter->setBackgroundGallery($html, $id, [$person->photo])
                : $this->rewriter->setImageSource($html, $id, $person->photo);
        }

        return $html;
    }

    /**
     * @param  array<int, array<string, string>>  $slots
     */
    protected function applyEvents(string $html, InvitationViewData $data, array $slots): string
    {
        foreach ($slots as $index => $ids) {
            $event = $data->events[$index] ?? null;

            if (! $event) {
                continue;
            }

            $html = $this->heading($html, $ids['title'], $event->title);
            $html = $this->heading($html, $ids['day'], $event->dayName);
            $html = $this->rewriter->setCounter($html, $ids['counter'], (int) $event->day);
            $html = $this->heading($html, $ids['month'], $this->monthLabel($event, $ids));
            $html = $this->heading($html, $ids['time'], $event->timeLabel);

            if (isset($ids['year'])) {
                $html = $this->heading($html, $ids['year'], $event->year);
            }

            $location = ($ids['location_prefix'] ?? 'Lokasi Acara :')
                . '<br>' . $this->e($event->locationLabel);

            if (trim((string) $event->description) !== '') {
                $location .= '<br><br>' . $this->lineBreaks($event->description);
            }

            if (trim((string) $event->dressCode) !== '') {
                $location .= '<br><br>Dress code: ' . $this->e($event->dressCode);
            }

            $html = $this->richHeading($html, $ids['location'], $location);
            $html = $this->rewriter->setLinkHref($html, $ids['map'], $event->mapsUrl);

            if ($event->liveStreamUrl && isset($ids['live'])) {
                $html = $this->rewriter->setLinkHref($html, $ids['live'], $event->liveStreamUrl);
            }
        }

        // Events beyond the template's own slots have no native destination.
        // They are intentionally not rendered instead of adding a generic
        // section that would change the template's composition.

        return $html;
    }

    protected function monthLabel(EventViewData $event, array $ids): string
    {
        if (! isset($ids['with_year'])) {
            return $event->month;
        }

        return $event->month . ' ' . $event->year;
    }

    /**
     * @param  array<int, array<string, string>>  $slots
     */
    protected function applyStories(string $html, InvitationViewData $data, array $slots): string
    {
        foreach ($slots as $index => $ids) {
            $story = $data->stories[$index] ?? null;

            if (! $story) {
                continue;
            }

            $title = trim($story->storyDate . ' — ' . $story->title, ' —');
            $html = $this->heading($html, $ids['title'], $title);
            $html = $this->heading($html, $ids['description'], $story->description);

            if (isset($ids['image'])) {
                $html = $this->rewriter->setImageSource($html, $ids['image'], $story->image);
            }
        }

        // Stories beyond the native slots are not rendered. The template's own
        // story cards remain the only layout, with no injected generic section.

        return $html;
    }

    protected function applyGallery(string $html, InvitationViewData $data, string $slideshowId): string
    {
        $items = '';
        $photos = array_slice($data->galleries, 0, 8);

        foreach ($photos as $photo) {
            $url = $this->e($photo->url);
            $caption = $this->e($photo->caption);
            $items .= '<a class="e-gallery-item elementor-gallery-item elementor-animated-content"'
                . ' href="' . $url . '" data-elementor-open-lightbox="yes"'
                . ' data-elementor-lightbox-slideshow="' . $this->e($slideshowId) . '"'
                . ' data-elementor-lightbox-title="' . $caption . '">'
                . '<div class="e-gallery-image elementor-gallery-item__image e-gallery-image-loaded"'
                . ' data-thumbnail="' . $url . '"'
                . ' data-width="' . (int) $photo->width . '" data-height="' . (int) $photo->height . '"'
                . ' aria-label="' . $caption . '" role="img"'
                . ' style="background-image:url(&quot;' . $url . '&quot;)"></div>'
                . '<div class="elementor-gallery-item__overlay"></div>'
                . '</a>';
        }

        $html = $this->rewriter->replaceGalleryContainer($html, $items);

        // The mirrored Elementor sources mark optional sections with a literal
        // `hidden` class. Remove it when the customer actually has photos so
        // the section is visible even before the runtime script runs.
        if ($photos !== []) {
            $galleryIds = $this->featureHiddenIds()['gallery'] ?? [];

            if (($galleryIds[0] ?? null) !== null) {
                $html = $this->rewriter->revealElementByDataId($html, (string) $galleryIds[0]);
            }
        }

        return $html;
    }

    protected function hasSupportingPhotos(InvitationViewData $data): bool
    {
        return $data->supportingPhotos !== [];
    }

    /**
     * The decorative photo slots are owned by Foto Pendukung, which is kept
     * separate from the "Our Moments" gallery grid. Galleries may hold up to
     * eight photos; supporting photos are a separate set of at most three.
     *
     * @param  array<int, string>  $imageIds
     */
    protected function applySupportingPhotos(
        string $html,
        InvitationViewData $data,
        string $carouselId,
        array $imageIds,
    ): string {
        if (! $this->hasSupportingPhotos($data)) {
            return $html;
        }

        $urls = array_slice($data->supportingPhotos, 0, 4);

        if ($urls === []) {
            return $html;
        }

        $html = $this->rewriter->setCarouselImages($html, $carouselId, $urls);

        foreach ($imageIds as $index => $id) {
            $html = $this->rewriter->setImageSource($html, $id, $urls[$index % count($urls)]);
        }

        return $html;
    }

    /**
     * Gift data is bound to the native Elementor widgets only. Empty slots are
     * hidden; no envelope panel, bank card or toggle is injected into the
     * template because that would replace the original design.
     *
     * @param  array<int, array<string, array<int, string>|string>>  $slots
     * @param  array<int, \App\Data\GiftAccountViewData>|null  $accounts  Override the slot order when a template uses a native address block.
     */
    protected function applyGifts(
        string $html,
        InvitationViewData $data,
        array $slots,
        ?array $accounts = null,
    ): string {
        $accounts ??= $data->giftAccounts;

        if (! $data->sample) {
            foreach (($this->giftWidgetIds()['legacy'] ?? []) as $legacyId) {
                $html = $this->rewriter->setElementAttributeByDataId(
                    $html,
                    (string) $legacyId,
                    'style',
                    'display:none',
                );
            }
        }

        foreach ($slots as $index => $ids) {
            $account = $accounts[$index] ?? null;
            $slotIds = array_merge(
                $ids['display'] ?? [],
                $ids['copy'] ?? [],
                isset($ids['image']) ? [$ids['image']] : [],
            );

            if (! $account) {
                foreach ($slotIds as $id) {
                    $html = $this->rewriter->setElementAttributeByDataId(
                        $html,
                        (string) $id,
                        'style',
                        'display:none',
                    );
                }

                continue;
            }

            foreach (($ids['display'] ?? []) as $id) {
                $html = $this->richHeading($html, (string) $id, $account->displayText());
            }
            foreach (($ids['copy'] ?? []) as $id) {
                $html = $this->rewriter->setCopyValue($html, (string) $id, $account->copyValue());
            }

            if (isset($ids['image'])) {
                if ($account->qrUrl) {
                    $html = $this->rewriter->setImageSource($html, (string) $ids['image'], $account->qrUrl);
                } else {
                    $html = $this->rewriter->setElementAttributeByDataId(
                        $html,
                        (string) $ids['image'],
                        'style',
                        'display:none',
                    );
                }
            }
        }

        return $html;
    }

    protected function applyCountdown(string $html, InvitationViewData $data, string $attributePattern): string
    {
        if (! $data->countdownTarget) {
            return $html;
        }

        $target = $this->e($data->countdownTarget);

        // A callback keeps backslashes and $1 in customer data literal.
        return preg_replace_callback(
            $attributePattern,
            static fn (array $matches): string => ($matches[1] ?? '') . $target . ($matches[2] ?? ''),
            $html,
        ) ?? $html;
    }

    /**
     * Apply customer backgrounds. A real invitation never keeps the template
     * sample slideshow, so missing slots are explicitly emptied.
     */
    protected function applyBackgrounds(string $html, InvitationViewData $data): string
    {
        if ($data->sample) {
            return $html;
        }

        foreach ($this->backgroundIds() as $slot => $id) {
            $urls = $data->backgrounds[$slot] ?? [];

            if ($urls === [] && $slot === 'cover_inner') {
                $urls = $data->backgrounds['cover'] ?? [];
            }

            // A missing slot stays empty. Couple photos and stock template
            // images are never reused as decorative backgrounds.
            $html = $this->rewriter->setBackgroundGallery($html, $id, $urls);
        }

        return $html;
    }

    protected function applyCommon(string $html, InvitationViewData $data): string
    {
        $html = $this->applyGuestbook($html, $data);
        $musicUrl = ($data->features['music'] ?? false) ? $data->musicUrl : null;
        $html = $this->rewriter->setAudioSource($html, $musicUrl);
        $html = $this->rewriter->replaceGlobalText($html, $data);

        return $html;
    }

    protected function applyGuestbook(string $html, InvitationViewData $data): string
    {
        $action = $this->e($data->endpoints['greeting'] ?? '#');
        $token = $data->csrfToken
            ? '<input type="hidden" name="_token" value="' . $this->e($data->csrfToken) . '">'
            : '';
        $guestName = $data->guestName === 'Nama Tamu' ? '' : $data->guestName;

        $options = '<option value="">Konfirmasi Kehadiran</option>'
            . '<option value="HADIR">Hadir</option>'
            . '<option value="AKAN_HADIR">Akan Hadir</option>'
            . '<option value="TIDAK_HADIR">Tidak Hadir</option>';

        // Replace the WordPress form wholesale so labels, names, autocomplete
        // and the spam honeypot are explicit instead of relying on dead WP JS.
        $form = '<form id="post-guestbook-box" class="ud-guestbook-form"'
            . ' method="post" action="' . $action . '" aria-label="Form ucapan dan doa">'
            . $token
            . '<div class="guestbook-label"><label class="" for="ud-guestbook-name">Nama</label></div>'
            . '<input id="ud-guestbook-name" class="form-control" type="text" name="name"'
            . ' placeholder="Isikan Nama Anda" value="' . $this->e($guestName) . '"'
            . ' autocomplete="name" required>'
            . '<div class="guestbook-label"><label class="" for="ud-guestbook-message">Pesan</label></div>'
            . '<textarea id="ud-guestbook-message" class="form-control" rows="3" name="message"'
            . ' placeholder="Berikan Ucapan Dan Doa Restu" required></textarea>'
            . '<div class="guestbook-label"><label class="" for="ud-guestbook-attendance">Konfirmasi Kehadiran</label></div>'
            . '<select id="ud-guestbook-attendance" class="form-control" name="attendance" required>'
            . $options . '</select>'
            . '<div class="ud-honeypot" aria-hidden="true">'
            . '<label for="ud-guestbook-website">Jangan isi kolom ini</label>'
            . '<input id="ud-guestbook-website" type="text" name="website" tabindex="-1" autocomplete="off">'
            . '</div>'
            . '<div class="elementor-button-wrapper">'
            . '<button type="submit" class="elementor-button-link elementor-button elementor-size-sm">'
            . '<span class="elementor-button-text">Kirimkan Ucapan</span>'
            . '</button></div>'
            . '</form>';

        $html = preg_replace(
            '~<form\b[^>]*\bid="post-guestbook-box"[^>]*>.*?</form>~is',
            $form,
            $html,
            1,
        ) ?? $html;

        $items = '';

        foreach ($data->greetings as $greeting) {
            $initial = mb_substr($greeting->guestName, 0, 1);
            $items .= '<div class="user-guestbook">'
                . '<div><span style="display:flex;width:48px;height:48px;border-radius:50%;'
                . 'background:#e9d5c7;color:#6d4c3d;align-items:center;justify-content:center;'
                . 'font-weight:700">' . $this->e($initial) . '</span></div>'
                . '<div class="guestbook">'
                . '<a class="guestbook-name">' . $this->e($greeting->guestName) . '</a>'
                . '<span class="wdp-confirm"><i class="fas fa-check-circle"></i> '
                . $this->e($greeting->attendance) . '</span>'
                . '<div class="guestbook-message">' . $this->lineBreaks($greeting->message) . '</div>'
                . '</div></div>';
        }

        if ($items === '') {
            $items = '<p style="text-align:center;opacity:.7">Belum ada ucapan. Jadilah yang pertama memberi doa restu.</p>';
        }

        return $this->rewriter->replaceDivInnerByClass($html, 'guestbook-list', $items);
    }

    /**
     * @return array<int, string>
     */
    protected function hiddenIds(InvitationViewData $data): array
    {
        $ids = [];
        $liveStreamUrl = $data->primaryEvent()?->liveStreamUrl;
        $empty = [
            'story' => $data->stories === [],
            'gallery' => $data->galleries === [],
            'families' => $data->families === [],
            'gift' => $data->giftAccounts === [] && trim((string) $data->giftAddress) === '',
            'countdown' => ! $data->countdownTarget,
            'live_stream' => ! $liveStreamUrl,
        ];

        foreach ($this->featureHiddenIds() as $feature => $featureIds) {
            if ($feature === 'live_stream' && $liveStreamUrl) {
                continue;
            }

            // Hide an optional section when it is switched off OR when the
            // customer supplied no data for it. Missing flags fail closed.
            if (($empty[$feature] ?? false) || ! ($data->features[$feature] ?? false)) {
                $ids = array_merge($ids, $featureIds);
            }
        }

        return array_values(array_unique($ids));
    }
}
