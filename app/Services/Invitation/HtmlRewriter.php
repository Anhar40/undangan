<?php

namespace App\Services\Invitation;

use App\Data\InvitationViewData;
use App\Models\Setting;
use App\Models\Template;
use Carbon\Carbon;

class HtmlRewriter
{
    private const DIV_TAG = '~</?div\b~i';

    public function __construct(private readonly string $key) {}

    public function e(?string $value): string
    {
        return e($value ?? '');
    }

    /** Remove WordPress-only markup and point every mirrored asset at local files. */
    public function prepare(string $html): string
    {
        $html = $this->stripWordPressBoilerplate($html);

        // Some mirrored templates ship an invalid inline player script where a
        // function declaration immediately follows an assignment without a
        // separator. Repair it once here so the runtime does not throw.
        $html = str_replace(
            'var x=document.getElementById("song");document.getElementById("wdp-button-wrapper").onclick=function(){playAudio()}function playAudio(){x.play()}function pauseAudio(){x.pause()}',
            'var x=document.getElementById("song"),b=document.getElementById("wdp-button-wrapper");if(b){b.onclick=function(){playAudio()}}function playAudio(){if(x){x.play()}}function pauseAudio(){if(x){x.pause()}}',
            $html,
        );

        return $this->rewriteAssetUrls($html);
    }

    /** Inject document metadata, theme overrides, and the invitation runtime. */
    public function finalize(
        string $html,
        InvitationViewData $data,
        array $hiddenIds = [],
        array $hiddenWidgets = [],
    ): string {
        // An expired invitation must never leak its content, even to a JS-less
        // browser: return only the locked activation popup from the server.
        if (($data->activationPrompt['expired'] ?? false) === true) {
            return $this->activationExpiredPage($data);
        }

        $html = $this->injectHead($html, $data, $hiddenIds, $hiddenWidgets);

        // The native gallery container is part of the source template. When it
        // has no customer photos it is hidden with the gallery section, never
        // removed from the markup.

        return $this->injectRuntime($html, $data, $hiddenIds, $hiddenWidgets);
    }

    public function widgetInner(string $html, string $dataId): ?string
    {
        $bounds = $this->widgetBounds($html, $dataId);

        if ($bounds === null) {
            return null;
        }

        return substr($html, $bounds['innerStart'], $bounds['innerEnd'] - $bounds['innerStart']);
    }

    public function replaceWidgetInner(string $html, string $dataId, string $inner): string
    {
        $bounds = $this->widgetBounds($html, $dataId);

        if ($bounds === null) {
            return $html;
        }

        return substr_replace($html, $inner, $bounds['innerStart'], $bounds['innerEnd'] - $bounds['innerStart']);
    }

    /** Replace the whole inner content of the section/div carrying a data-id. */
    public function replaceElementInnerByDataId(string $html, string $dataId, string $inner): string
    {
        $bounds = $this->elementBoundsByDataId($html, $dataId);

        if ($bounds === null) {
            return $html;
        }

        return substr_replace($html, $inner, $bounds['innerStart'], $bounds['innerEnd'] - $bounds['innerStart']);
    }

    /** Add or overwrite one attribute on the section/div carrying a data-id. */
    public function setElementAttributeByDataId(string $html, string $dataId, string $attribute, string $value): string
    {
        $bounds = $this->elementBoundsByDataId($html, $dataId);

        if ($bounds === null) {
            return $html;
        }

        $length = $bounds['openEnd'] + 1 - $bounds['start'];
        $openTag = substr($html, $bounds['start'], $length);
        $quotedAttribute = preg_quote($attribute, '~');
        $replacement = ' '.$attribute.'="'.$this->e($value).'"';
        $count = 0;
        $newOpenTag = preg_replace_callback(
            '~\s'.$quotedAttribute.'\s*=\s*(["\']).*?\1~is',
            static fn (array $matches): string => $replacement,
            $openTag,
            1,
            $count,
        );

        if ($newOpenTag === null) {
            return $html;
        }

        if ($count === 0) {
            $closing = strrpos($openTag, '>');
            if ($closing === false) {
                return $html;
            }
            $newOpenTag = substr($openTag, 0, $closing).$replacement.substr($openTag, $closing);
        }

        return substr_replace($html, $newOpenTag, $bounds['start'], $length);
    }

    /**
     * Remove the mirrored template's literal `hidden` class from a section
     * that now holds customer data. The runtime's hiddenIds still controls
     * sections that must stay hidden.
     */
    public function revealElementByDataId(string $html, string $dataId): string
    {
        $bounds = $this->elementBoundsByDataId($html, $dataId);

        if ($bounds === null) {
            return $html;
        }

        $length = $bounds['openEnd'] + 1 - $bounds['start'];
        $openTag = substr($html, $bounds['start'], $length);
        $count = 0;
        $updated = preg_replace_callback(
            '~\sclass="([^"]*)"~i',
            static function (array $matches): string {
                $classes = preg_split('~\s+~', trim($matches[1])) ?: [];
                $classes = array_values(array_filter(
                    $classes,
                    static fn (string $class): bool => $class !== 'hidden',
                ));

                return ' class="'.implode(' ', $classes).'"';
            },
            $openTag,
            1,
            $count,
        );

        if ($updated === null || $count === 0 || $updated === $openTag) {
            return $html;
        }

        return substr_replace($html, $updated, $bounds['start'], $length);
    }

    /**
     * Empty every sample slideshow gallery in Elementor settings. Customer
     * backgrounds are applied afterwards, so real invitations never keep the
     * template's stock photos in data-* attributes.
     */
    public function clearSampleBackgrounds(string $html): string
    {
        $html = preg_replace(
            '~&quot;background_slideshow_gallery&quot;:\[.*?\]~is',
            '&quot;background_slideshow_gallery&quot;:[]',
            $html,
        ) ?? $html;

        return preg_replace(
            '~"background_slideshow_gallery":\[.*?\]~is',
            '"background_slideshow_gallery":[]',
            $html,
        ) ?? $html;
    }

    /** Remove a whole Elementor widget (including its wrapper) from the HTML. */
    public function removeWidget(string $html, string $dataId): string
    {
        $bounds = $this->widgetBounds($html, $dataId);

        if ($bounds === null) {
            return $html;
        }

        // widgetBounds() points at the inner widget container. Closing there
        // leaves the wrapper's own </div> behind, which prematurely closes the
        // outer .elementor container and breaks Elementor's CSS/JS. Close the
        // wrapper itself instead.
        $closeStart = $this->matchingDivClose($html, $bounds['openEnd'] + 1);

        if ($closeStart === null) {
            return $html;
        }

        $closeEnd = strpos($html, '>', $closeStart);

        return $closeEnd === false
            ? $html
            : substr_replace($html, '', $bounds['start'], $closeEnd + 1 - $bounds['start']);
    }

    public function setHeading(string $html, string $dataId, string $content, bool $escaped = true): string
    {
        $inner = $this->widgetInner($html, $dataId);

        if ($inner === null) {
            return $html;
        }

        $content = $escaped ? $this->e($content) : $content;
        $pattern = '~^(<([ph1-6]|span)\b[^>]*class="[^"]*elementor-heading-title[^"]*"[^>]*>)(.*?)(</\2>)~is';

        if (preg_match($pattern, trim($inner), $matches)) {
            $leading = substr($inner, 0, strpos($inner, '<'));
            $newInner = $leading.$matches[1].$content.$matches[4];
        } else {
            // preg_replace_callback avoids "$1" being parsed as "$12" when the
            // replacement content starts with a digit (e.g. a date like 12.12.2026).
            $newInner = preg_replace_callback(
                '~(<[^>]+>)([^<]+)(</[^>]+>)~is',
                static fn (array $matches): string => $matches[1].$content.$matches[3],
                $inner,
                1,
            ) ?? $inner;
        }

        return $this->replaceWidgetInner($html, $dataId, $newInner);
    }

    /** Replace the text of an Elementor icon-list widget (used for event times). */
    public function setIconListText(string $html, string $dataId, string $value): string
    {
        $inner = $this->widgetInner($html, $dataId);

        if ($inner === null) {
            return $html;
        }

        $value = $this->e($value);
        $inner = preg_replace_callback(
            '~(<span class="elementor-icon-list-text"[^>]*>).*?(</span>)~is',
            static fn (array $matches): string => $matches[1].$value.$matches[2],
            $inner,
            1,
        ) ?? $inner;

        return $this->replaceWidgetInner($html, $dataId, $inner);
    }

    public function setButtonLabel(string $html, string $dataId, string $label): string
    {
        $inner = $this->widgetInner($html, $dataId);

        if ($inner === null) {
            return $html;
        }

        $label = $this->e($label);
        $inner = preg_replace_callback(
            '~(<span class="elementor-button-text"[^>]*>).*?(</span>)~is',
            static fn (array $matches): string => $matches[1].$label.$matches[2],
            $inner,
            1,
        ) ?? $inner;

        return $this->replaceWidgetInner($html, $dataId, $inner);
    }

    public function setCopyValue(string $html, string $dataId, string $value): string
    {
        $inner = $this->widgetInner($html, $dataId);

        if ($inner === null) {
            return $html;
        }

        $value = $this->e($value);
        $inner = preg_replace_callback(
            '~(<div class="copy-content spancontent"[^>]*>).*?(</div>)~is',
            static fn (array $matches): string => $matches[1].$value.$matches[2],
            $inner,
            1,
        ) ?? $inner;

        return $this->replaceWidgetInner($html, $dataId, $inner);
    }

    public function replaceInWidget(string $html, string $dataId, string $search, string $replace): string
    {
        $inner = $this->widgetInner($html, $dataId);

        if ($inner === null) {
            return $html;
        }

        return $this->replaceWidgetInner($html, $dataId, str_replace($search, $replace, $inner));
    }

    public function replaceDivInnerByClass(string $html, string $className, string $inner): string
    {
        $pattern = '~<div\b[^>]*\bclass="[^"]*\b'.preg_quote($className, '~').'\b[^"]*"[^>]*>~i';

        if (! preg_match($pattern, $html, $matches, PREG_OFFSET_CAPTURE)) {
            return $html;
        }

        $innerStart = $matches[0][1] + strlen($matches[0][0]);
        $innerEnd = $this->matchingDivClose($html, $innerStart);

        if ($innerEnd === null) {
            return $html;
        }

        return substr_replace($html, $inner, $innerStart, $innerEnd - $innerStart);
    }

    public function appendAfterWidget(string $html, string $dataId, string $markup): string
    {
        $bounds = $this->widgetBounds($html, $dataId);

        if ($bounds === null) {
            return $html;
        }

        $depth = 1;
        $position = $bounds['innerStart'];

        while (preg_match(self::DIV_TAG, $html, $matches, PREG_OFFSET_CAPTURE, $position)) {
            $tagStart = $matches[0][1];
            $depth += strtolower($matches[0][0]) === '</div' ? -1 : 1;
            $position = $tagStart + strlen($matches[0][0]);

            if ($depth === 0) {
                $closeEnd = strpos($html, '>', $position);

                return $closeEnd === false
                    ? $html
                    : substr_replace($html, $markup, $closeEnd + 1, 0);
            }
        }

        return $html;
    }

    public function setCounter(string $html, string $dataId, int $value): string
    {
        $inner = $this->widgetInner($html, $dataId);

        if ($inner === null) {
            return $html;
        }

        $inner = preg_replace('~data-to-value="\d+"~', 'data-to-value="'.$value.'"', $inner) ?? $inner;
        $inner = preg_replace(
            '~(<span class="elementor-counter-number"[^>]*>)\s*\d*\s*(</span>)~i',
            '${1}'.$value.'${2}',
            $inner,
        ) ?? $inner;

        return $this->replaceWidgetInner($html, $dataId, $inner);
    }

    public function setLinkHref(string $html, string $dataId, ?string $url): string
    {
        $inner = $this->widgetInner($html, $dataId);

        if ($inner === null || ! $url) {
            return $html;
        }

        $url = $this->e($url);

        if (preg_match('~href="[^"]*"~i', $inner)) {
            $inner = preg_replace('~href="[^"]*"~i', 'href="'.$url.'"', $inner, 1);
        } else {
            $inner = preg_replace('~(<a\b[^>]*)(>)~i', '$1 href="'.$url.'"$2', $inner, 1);
        }

        return $this->replaceWidgetInner($html, $dataId, $inner ?? '');
    }

    /**
     * Replace an Elementor slideshow background. An empty list removes the
     * slideshow completely so template sample photos never leak into a real
     * invitation that has no uploaded background.
     *
     * @param  array<int, string>|string|null  $urls
     */
    public function setBackgroundGallery(string $html, string $dataId, array|string|null $urls): string
    {
        $urls = is_array($urls) ? array_values(array_filter($urls)) : ($urls ? [$urls] : []);
        $needle = 'data-id="'.$dataId.'"';
        $position = strpos($html, $needle);

        if ($position === false) {
            return $html;
        }

        $openStart = strrpos(substr($html, 0, $position), '<');

        if ($openStart === false) {
            return $html;
        }

        $openEnd = strpos($html, '>', $position);

        if ($openEnd === false) {
            return $html;
        }

        $items = [];

        foreach ($urls as $index => $url) {
            $jsonUrl = json_encode(
                (string) $url,
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            );
            $items[] = '&quot;id&quot;:'.$index.',&quot;url&quot;:'.e($jsonUrl);
        }

        $encoded = 'background_slideshow_gallery&quot;:'.($items !== [] ? '[{'.implode('},{', $items).'}]' : '[]');
        $openingTag = substr($html, $openStart, $openEnd - $openStart + 1);
        $updatedTag = preg_replace('~background_slideshow_gallery&quot;:\[.*?\]~s', $encoded, $openingTag, 1);

        if (! $updatedTag || $updatedTag === $openingTag) {
            return $html;
        }

        if ($urls === []) {
            $updatedTag = preg_replace(
                '~background_background&quot;:&quot;slideshow&quot;~',
                'background_background&quot;:&quot;none&quot;',
                $updatedTag,
                1,
            ) ?? $updatedTag;
        }

        return substr_replace($html, $updatedTag, $openStart, $openEnd - $openStart + 1);
    }

    public function replaceGalleryContainer(string $html, string $items): string
    {
        $position = strpos($html, '<div class="elementor-gallery__container">');

        if ($position === false) {
            return $html;
        }

        $innerStart = strpos($html, '>', $position) + 1;
        $innerEnd = $this->matchingDivClose($html, $innerStart);

        if ($innerEnd === null) {
            return $html;
        }

        return substr_replace($html, $items, $innerStart, $innerEnd - $innerStart);
    }

    public function setImageSource(string $html, string $dataId, ?string $url): string
    {
        $inner = $this->widgetInner($html, $dataId);

        if ($inner === null || ! $url) {
            return $html;
        }

        $image = '<img decoding="async" src="'.$this->e($url).'" alt="">';
        $inner = preg_replace('~<img\b[^>]*>~i', $image, $inner, 1) ?? $inner;

        return $this->replaceWidgetInner($html, $dataId, $inner);
    }

    /**
     * Replace an Elementor media carousel's sample slides with customer photos.
     * Swiper still initializes from the native data-settings, so loop,
     * autoplay and duplication behave exactly like the template sample.
     *
     * @param  array<int, string>  $urls
     */
    public function setCarouselImages(string $html, string $dataId, array $urls): string
    {
        $inner = $this->widgetInner($html, $dataId);

        if ($inner === null || $urls === []) {
            return $html;
        }

        $needle = '<div class="swiper-wrapper">';
        $wrapperStart = strpos($inner, $needle);

        if ($wrapperStart === false) {
            return $html;
        }

        $openEnd = strpos($inner, '>', $wrapperStart);

        if ($openEnd === false) {
            return $html;
        }

        $closeStart = $this->matchingDivClose($inner, $openEnd + 1);

        if ($closeStart === null) {
            return $html;
        }

        $slides = '';
        foreach (array_values($urls) as $index => $url) {
            $slides .= '<div class="swiper-slide">'
                .'<div class="elementor-carousel-image" role="img"'
                .' aria-label="Foto '.($index + 1).'"'
                .' style="background-image: url('.$this->e($url).')"></div>'
                .'</div>';
        }

        $inner = substr_replace($inner, $slides, $openEnd + 1, $closeStart - $openEnd - 1);

        return $this->replaceWidgetInner($html, $dataId, $inner);
    }

    public function setAudioSource(string $html, ?string $url): string
    {
        $audioPattern = '~(<audio\b[^>]*id=["\']song["\'][^>]*>)(.*?)(</audio>)~is';

        if (! $url) {
            // Keep the native media markup intact. Hide it instead so the
            // template structure stays identical and no sample audio plays.
            $html = $this->addInlineStyle($html, '<audio', 'id="song"', 'display:none!important');
            $html = $this->addInlineStyle($html, '<div', 'id="audio-container"', 'display:none!important');

            return preg_replace_callback(
                $audioPattern,
                static fn (array $matches): string => $matches[1]
                    .(preg_replace(
                        '~(<source\b[^>]*\bsrc=)["\'][^"\']*["\']~i',
                        '$1""',
                        $matches[2],
                        1,
                    ) ?? $matches[2])
                    .$matches[3],
                $html,
                1,
            ) ?? $html;
        }

        if (preg_match($audioPattern, $html, $matches)) {
            $body = preg_replace(
                '~<source\b[^>]*>~i',
                '<source src="'.$this->e($url).'" type="audio/mpeg">',
                $matches[2],
                1,
            ) ?? $matches[2];

            return str_replace($matches[0], $matches[1].$body.$matches[3], $html);
        }

        $player = '<audio id="song" preload="none"><source src="'.$this->e($url).'" type="audio/mpeg"></audio>';

        return str_replace('</body>', $player.'</body>', $html);
    }

    /** Replace sample text everywhere it survived the targeted widget edits. */
    public function replaceGlobalText(string $html, InvitationViewData $data): string
    {
        $couple = $this->e($data->coupleLabel());
        $first = $this->e($data->firstPerson()->displayName());
        $second = $this->e($data->secondPerson()->displayName());
        $date = $this->e($data->saveTheDate);

        $map = [
            'Putri &amp; Putra' => $couple,
            'Putri &#038; Putra' => $couple,
            'Putri & Putra' => $couple,
            'Putra  &amp; Putri' => $couple,
            'Putra &amp; Putri' => $couple,
            'Putra & Putri' => $couple,
            'Putri & Putrra' => $couple,
            'Putri Putrra' => $couple,
            'Putri Pratiwi' => $first,
            'Putri Pratiwiwi' => $first,
            'Putra Pratama' => $second,
            'Love Stroy' => 'Cerita Cinta',
            '12.12.2023' => $date,
            '09.07.2023' => $date,
            'Nama Tamu' => $this->e($data->guestName),
        ];

        return str_replace(array_keys($map), array_values($map), $html);
    }

    private function stripWordPressBoilerplate(string $html): string
    {
        // The dumps are English WordPress exports. Normalize the document
        // language and keep a single viewport declaration for mobile layout.
        $html = preg_replace('~<html\b[^>]*>~i', '<html lang="id">', $html, 1) ?? $html;
        $html = preg_replace('~<meta\b[^>]*\bname=["\']viewport["\'][^>]*>~i', '', $html) ?? $html;

        $html = preg_replace('~<!--\s*This site is optimized with the Yoast SEO plugin.*?-->~is', '', $html) ?? $html;
        $html = preg_replace('~<script\b[^>]*type=["\']application/ld\+json["\'][^>]*>.*?</script>~is', '', $html) ?? $html;

        // Scripts that only talk to WordPress/WeddingPress AJAX endpoints.
        $html = preg_replace_callback('~<script\b[^>]*>.*?</script>~is', function (array $match): string {
            $script = $match[0];
            $patterns = [
                '_wpemojiSettings',
                'skipLink',
                'WDP_WP',
                'cevar',
                '\.countdown\(\)',
                'disableScrolling',
                'lazyloadRunObserver',
                'wdp_script',
            ];

            foreach ($patterns as $pattern) {
                if (preg_match('~'.$pattern.'~i', $script)) {
                    return '';
                }
            }

            if (preg_match('~<script\b[^>]*\b(?:src|id)=["\'][^"\']*(?:wdp_|weddingpress-|kirim-kit|comment-reply|wp-embed|wp-emoji|exad-scripts)[^"\']*["\']~i', $script)) {
                return '';
            }

            return $script;
        }, $html) ?? $html;

        $html = preg_replace('~<link\b[^>]*\b(?:rel|href|title)=["\'][^"\']*(?:api\.w\.org|wp-json|oembed|EditURI|xmlrpc|shortlink)[^"\']*["\'][^>]*>~is', '', $html) ?? $html;
        $html = preg_replace('~<meta\b[^>]*\b(?:property|name)=["\'](?:og:|robots|description)[^"\']*["\'][^>]*>~is', '', $html) ?? $html;
        $html = preg_replace('~<title\b[^>]*>.*?</title>~is', '', $html) ?? $html;
        $html = preg_replace('~<div\b[^>]*id=["\']wpadminbar["\'][^>]*>.*?</div>~is', '', $html) ?? $html;

        // Original envelope toggle assumes both nodes exist after every feature edit.
        $html = str_replace('btnAmplop.onclick = function() {', 'if (btnAmplop) btnAmplop.onclick = function() {', $html);

        return $html;
    }

    private function rewriteAssetUrls(string $html): string
    {
        // Escaped URLs inside Elementor's JSON data-settings attributes.
        $html = str_replace(
            [
                'https:\\/\\/api.sewaundangan.com\\/music\\/',
                'http:\\/\\/api.sewaundangan.com\\/music\\/',
                'https:\\/\\/bukaundangan.com\\/',
                'http:\\/\\/bukaundangan.com\\/',
            ],
            [
                '/assets/templates/tema1/music/',
                '/assets/templates/tema1/music/',
                '/assets/templates/'.$this->key.'/',
                '/assets/templates/'.$this->key.'/',
            ],
            $html,
        );

        // Plain URLs in CSS, src, href and inline scripts.
        $html = preg_replace('~https?://api\.sewaundangan\.com/music/~i', '/assets/templates/tema1/music/', $html) ?? $html;
        $html = preg_replace('~https?://(?:www\.)?bukaundangan\.com/~i', '/assets/templates/'.$this->key.'/', $html) ?? $html;

        // Mirrored files were saved without their WordPress cache-busting query.
        $html = preg_replace('~(/assets/templates/[^"\'<>\s\)]+)\?[^"\'<>\s\)]*~', '$1', $html) ?? $html;

        return $html;
    }

    private function injectHead(
        string $html,
        InvitationViewData $data,
        array $hiddenIds = [],
        array $hiddenWidgets = [],
    ): string {
        if (preg_match('/<html\b[^>]*\blang=/i', $html)) {
            $html = preg_replace('/(<html\b[^>]*\blang=")[^"]*"/i', '$1id"', $html, 1) ?? $html;
        } else {
            $html = preg_replace('/<html\b/i', '<html lang="id"', $html, 1) ?? $html;
        }

        $shareTitle = trim((string) ($data->shareTitle ?? ''));
        $shareDescription = trim((string) ($data->shareDescription ?? ''));
        $shareImage = trim((string) ($data->shareImage ?? ''));
        $shareUrl = trim((string) ($data->endpoints['public'] ?? ''));

        $rawTitle = $shareTitle !== '' ? $shareTitle : $data->title.' - '.$data->coupleLabel();
        $rawDescription = $shareDescription !== '' ? $shareDescription : trim((string) $data->invitationMessage);
        if ($rawDescription === '') {
            $rawDescription = 'Undangan pernikahan digital '.$data->coupleLabel().'. Buka undangan untuk melihat detail acara, lokasi, dan informasi lengkap.';
        }
        $rawDescription = preg_replace('/\s+/u', ' ', strip_tags($rawDescription)) ?: $rawDescription;
        if (mb_strlen($rawDescription) > 160) {
            $rawDescription = rtrim(mb_substr($rawDescription, 0, 157)).'...';
        }

        $siteName = Setting::get('app_name', config('app.name', 'INVITE'));
        $canonical = ($shareUrl !== '' && $shareUrl !== '#') ? $this->absoluteUrl($shareUrl) : null;
        if (! $canonical && ! app()->runningInConsole()) {
            $canonical = $this->absoluteUrl(request()->url());
        }

        $fallbackCover = '';
        foreach ((array) ($data->photoGallery['cover'] ?? []) as $img) {
            if (is_string($img) && trim($img) !== '') {
                $fallbackCover = trim($img);
                break;
            }
        }
        if ($fallbackCover === '') {
            foreach ((array) ($data->galleries ?? []) as $g) {
                $u = trim((string) ($g->url ?? ''));
                if ($u !== '') {
                    $fallbackCover = $u;
                    break;
                }
            }
        }
        $ogImage = $shareImage !== '' ? $this->absoluteUrl($shareImage)
            : ($fallbackCover !== '' ? $this->absoluteUrl($fallbackCover) : $this->absoluteUrl('/assets/site/img/og-default.png'));
        $ogImage = $this->optimizeOgImageUrl($ogImage);
        $ogImageSecure = str_starts_with($ogImage, 'https://') ? $ogImage : (str_starts_with($ogImage, 'http://') ? preg_replace('/^http:\/\//i', 'https://', $ogImage) : $ogImage);
        $ogImageType = str_ends_with(strtolower(parse_url($ogImage, PHP_URL_PATH) ?? ''), '.png') ? 'image/png'
            : (str_ends_with(strtolower(parse_url($ogImage, PHP_URL_PATH) ?? ''), '.svg') ? 'image/svg+xml' : 'image/jpeg');

        $isIndexable = ! $data->sample && ! $data->preview;
        $robots = $isIndexable ? 'index, follow, max-image-preview:large' : 'noindex, nofollow, noarchive';

        $title = $this->e($rawTitle);
        $description = $this->e($rawDescription);
        $siteNameEsc = $this->e($siteName);

        $head = '<meta name="viewport" content="width=device-width, initial-scale=1">'."\n"
            .'<meta name="theme-color" content="#B5935A">'."\n"
            .'<meta name="robots" content="'.$this->e($robots).'">'."\n"
            .'<link rel="icon" href="/assets/site/img/favicon.svg" type="image/svg+xml">'."\n"
            ."<title>{$title}</title>\n"
            .'<meta name="description" content="'.$description.'">'."\n"
            .'<meta property="og:type" content="website">'."\n"
            .'<meta property="og:site_name" content="'.$siteNameEsc.'">'."\n"
            .'<meta property="og:locale" content="id_ID">'."\n"
            .'<meta property="og:title" content="'.$title.'">'."\n"
            .'<meta property="og:description" content="'.$description.'">'."\n"
            .'<meta property="og:image" content="'.$this->e($ogImage).'">'."\n"
            .'<meta property="og:image:secure_url" content="'.$this->e($ogImageSecure).'">'."\n"
            .'<meta property="og:image:type" content="'.$this->e($ogImageType).'">'."\n";

        if ($ogImageType !== 'image/svg+xml') {
            $head .= '<meta property="og:image:width" content="1200">'."\n"
                .'<meta property="og:image:height" content="630">'."\n";
        }

        $head .= '<meta name="twitter:card" content="summary_large_image">'."\n"
            .'<meta name="twitter:title" content="'.$title.'">'."\n"
            .'<meta name="twitter:description" content="'.$description.'">'."\n"
            .'<meta name="twitter:image" content="'.$this->e($ogImage).'">'."\n";

        if ($canonical) {
            $head .= '<link rel="canonical" href="'.$this->e($canonical).'">'."\n"
                .'<meta property="og:url" content="'.$this->e($canonical).'">'."\n";
        }

        $jsonLd = $this->buildJsonLd($data, $rawTitle, $rawDescription, $canonical, $ogImage, $siteName, $isIndexable);
        if ($jsonLd !== null) {
            $head .= '<script type="application/ld+json">'.$jsonLd.'</script>'."\n";
        }

        $head .= '<style>'
            .'.ud-honeypot{position:absolute!important;left:-9999px!important;width:1px!important;height:1px!important;overflow:hidden!important}'
            .'.ud-notice,.ud-toast{position:fixed;left:50%;transform:translateX(-50%);z-index:100001;display:flex;align-items:center;gap:14px;'
            .'max-width:calc(100% - 32px);padding:12px 16px;border-radius:14px;background:#fdfbf8;color:#2a2520;'
            .'box-shadow:0 16px 44px rgba(43,32,21,.24);border:1px solid #e9dfd2;'
            .'font:600 13px/1.5 Jost,system-ui,sans-serif}'
            .'.ud-notice{top:16px;border-left:4px solid #b5935a}'
            .'.ud-toast{bottom:84px;border-left:4px solid #2f7d5b}'
            .'.ud-toast[hidden]{display:none}'
            .'.ud-notice button,.ud-lightbox-close{border:0;background:rgba(42,37,32,.08);color:#2a2520;'
            .'width:28px;height:28px;border-radius:50%;cursor:pointer;font-size:18px;line-height:1}'
            .'.ud-notice button:hover,.ud-lightbox-close:hover{background:rgba(42,37,32,.16)}'
            .'.ud-lightbox-close{position:absolute;top:16px;right:16px;width:40px;height:40px;background:#fff;color:#171411;font-size:26px}'
            .'.ud-lightbox figure{margin:0;display:flex;flex-direction:column;align-items:center;gap:12px;max-width:100%;max-height:100%}'
            .'.ud-lightbox img{display:block;max-width:100%;max-height:calc(100vh - 72px);object-fit:contain;border-radius:10px}'
            .'.ud-lightbox figcaption{color:#fff;font:500 13px/1.4 Jost,system-ui,sans-serif;text-align:center}'
            .'[data-ud-notice] button:focus-visible,.ud-lightbox-close:focus-visible{outline:3px solid #b5935a;outline-offset:3px}'
            .'@media(max-width:600px){.ud-notice{top:8px}.ud-toast{bottom:72px}.ud-lightbox-close{top:10px;right:10px}}'
            .'</style>'."\n";

        // Template colours, fonts and spacing are authoritative. Customer
        // theme settings never inject global CSS over the original design.

        // Apply hiding server-side as well: sample sections/widgets must never
        // flash before the runtime script executes, and JS-disabled clients see
        // the same clean invitation.
        $hideCss = $this->hiddenElementCss($html, $hiddenIds, $hiddenWidgets);
        if ($hideCss !== '') {
            $head .= '<style>'.$hideCss.'</style>'."\n";
        }

        if ($data->activationPrompt !== null && ($data->activationPrompt['expired'] ?? false) !== true) {
            $head .= '<style>'.$this->activationGateCss().'</style>'."\n";
        }

        if (str_contains($html, '</head>')) {
            return str_replace('</head>', $head.'</head>', $html);
        }

        return preg_replace('~<html[^>]*>~i', '$0<head>'.$head.'</head>', $html, 1) ?? $html;
    }

    private function injectRuntime(string $html, InvitationViewData $data, array $hiddenIds, array $hiddenWidgets = []): string
    {
        $publicUrl = $data->endpoints['public'] ?? '';
        $share = (! $data->sample && ! $data->preview && $publicUrl !== '' && $publicUrl !== '#')
            ? [
                'url' => $publicUrl,
                'whatsapp' => 'https://wa.me/?text='.rawurlencode($data->title.' — '.$publicUrl),
                'facebook' => 'https://www.facebook.com/sharer/sharer.php?u='.rawurlencode($publicUrl),
                'telegram' => 'https://t.me/share/url?url='.rawurlencode($publicUrl)
                    .'&text='.rawurlencode($data->title),
            ]
            : null;

        $config = [
            'key' => $data->key,
            'sample' => $data->sample,
            'preview' => $data->preview,
            'guestName' => $data->guestName,
            'features' => $data->features,
            'hiddenIds' => array_values(array_unique($hiddenIds)),
            'hiddenWidgets' => array_values(array_unique($hiddenWidgets)),
            'share' => $share,
            'whatsapp' => $share['whatsapp'] ?? null,
            'whatsappContact' => $data->whatsapp,
            'audio' => [
                'url' => $data->musicUrl,
                'autoplay' => $data->musicAutoplay,
                'enabled' => (bool) ($data->features['music'] ?? false),
            ],
            'countdown' => [
                'target' => $data->countdownTarget,
                'selectors' => Template::countdownIsTimestamp($data->key)
                    ? ['.elementor-countdown-wrapper[data-date]']
                    : ['.wpkoi-elements-countdown-items[data-date]'],
            ],
            'endpoints' => $data->endpoints,
        ];

        if (! $data->sample && ! $data->preview && $data->publicStatus !== null && trim($data->publicStatus) !== '') {
            $notice = '<div class="ud-notice" data-ud-notice role="status" aria-live="polite">'
                .'<span>'.$this->e($data->publicStatus).'</span>'
                .'<button type="button" data-ud-notice-close aria-label="Tutup pesan">×</button>'
                .'</div>';

            $html = str_contains($html, '</body>')
                ? str_replace('</body>', $notice.'</body>', $html)
                : $html.$notice;
        }

        if ($data->activationPrompt !== null && ($data->activationPrompt['expired'] ?? false) !== true) {
            $gate = $this->activationGateMarkup($data->activationPrompt);

            $html = str_contains($html, '</body>')
                ? str_replace('</body>', $gate.'</body>', $html)
                : $html.$gate;
        }

        $json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $script = '<script>window.UD_CONFIG='.$json.';'.$this->runtimeScript().'</script>';

        if (str_contains($html, '</body>')) {
            return str_replace('</body>', $script.'</body>', $html);
        }

        return $html.$script;
    }

    private function activationGateCss(): string
    {
        return '.ud-activation-backdrop{position:fixed;inset:0;z-index:100002;display:flex;align-items:center;'
            .'justify-content:center;padding:20px;background:rgba(23,20,17,.62);backdrop-filter:blur(5px)}'
            .'body.ud-activation-open{overflow:hidden}'
            .'body.ud-activation-locked > *:not([data-ud-activation-gate]){display:none!important}'
            .'.ud-activation-card{position:relative;width:min(520px,100%);max-height:calc(100vh - 40px);overflow:auto;'
            .'padding:30px;border:1px solid #e9dfd2;border-radius:20px;background:#fdfbf8;color:#2a2520;'
            .'box-shadow:0 28px 80px rgba(23,20,17,.32);font:500 14px/1.65 Jost,system-ui,sans-serif}'
            .'.ud-activation-kicker{display:block;color:#8a6b3d;font:700 11px/1.4 Jost,system-ui,sans-serif;'
            .'letter-spacing:.16em;text-transform:uppercase}'
            .'.ud-activation-card h2{margin:8px 0 10px;color:#2a2520;font:600 1.75rem/1.2 Georgia,serif}'
            .'.ud-activation-card p{margin:0;color:#6b6258;line-height:1.7}'
            .'.ud-activation-close{position:absolute;top:14px;right:16px;width:34px;height:34px;border:0;border-radius:50%;'
            .'background:rgba(42,37,32,.08);color:#2a2520;cursor:pointer;font-size:20px;line-height:1}'
            .'.ud-activation-close:hover{background:rgba(42,37,32,.16)}'
            .'.ud-activation-meta{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin:20px 0 22px;'
            .'padding:14px 16px;border-radius:12px;background:#f4ede3;color:#6b6258;font-size:12px}'
            .'.ud-activation-meta strong{display:block;margin-top:4px;color:#2a2520;font:700 15px/1.3 Jost,system-ui,sans-serif}'
            .'.ud-activation-actions{display:flex;flex-wrap:wrap;gap:10px}'
            .'.ud-activation-actions a,.ud-activation-actions button{flex:1 1 180px;display:inline-flex;align-items:center;'
            .'justify-content:center;min-height:44px;padding:10px 16px;border-radius:10px;text-decoration:none;'
            .'font:700 13px/1.2 Jost,system-ui,sans-serif;cursor:pointer}'
            .'.ud-activation-primary{border:1px solid #8a6b3d;background:#8a6b3d;color:#fff}'
            .'.ud-activation-primary:hover{border-color:#6f5430;background:#6f5430}'
            .'.ud-activation-secondary{border:1px solid #d8ccbd;background:transparent;color:#2a2520}'
            .'.ud-activation-secondary:hover{background:rgba(42,37,32,.06)}'
            .'.ud-activation-backdrop :focus-visible{outline:3px solid #b5935a;outline-offset:3px}'
            .'@media(max-width:600px){.ud-activation-backdrop{padding:12px}.ud-activation-card{padding:24px 20px}'
            .'.ud-activation-card h2{font-size:1.45rem}.ud-activation-meta{grid-template-columns:1fr}}'
            .'@media(prefers-reduced-motion:reduce){.ud-activation-card{scroll-behavior:auto}}';
    }

    /** @param  array<string, mixed>  $prompt */
    private function activationGateMarkup(array $prompt): string
    {
        $title = $this->e((string) ($prompt['title'] ?? 'Aktifkan Undangan Digital Anda!'));
        $message = $this->e((string) ($prompt['message'] ?? ''));
        $expiredMessage = $this->e((string) ($prompt['expired_message'] ?? 'Masa pratinjau sudah berakhir.'));
        $price = $this->e((string) ($prompt['price'] ?? ''));
        $remainingLabel = $this->e((string) ($prompt['remaining_label'] ?? '00:00:00'));
        $remainingSeconds = (string) max(0, (int) ($prompt['remaining_seconds'] ?? 0));
        $activationUrl = $this->e((string) ($prompt['activation_url'] ?? '#'));

        return '<div class="ud-activation-backdrop" data-ud-activation-gate'
            .' data-remaining-seconds="'.$this->e($remainingSeconds).'"'
            .' data-expired-message="'.$expiredMessage.'"'
            .' role="dialog" aria-modal="true" aria-labelledby="ud-activation-title"'
            .' aria-describedby="ud-activation-message">'
            .'<div class="ud-activation-card">'
            .'<button type="button" class="ud-activation-close" data-ud-activation-close aria-label="Tutup pengingat">&times;</button>'
            .'<span class="ud-activation-kicker">Undangan Anda</span>'
            .'<h2 id="ud-activation-title">'.$title.'</h2>'
            .'<p id="ud-activation-message" data-ud-activation-message>'.$message.'</p>'
            .'<div class="ud-activation-meta">'
            .'<span>Sisa waktu preview<strong data-ud-activation-status><span data-ud-activation-remaining>'.$remainingLabel.'</span></strong></span>'
            .'<span>Harga aktivasi<strong>'.$price.'</strong></span>'
            .'</div>'
            .'<div class="ud-activation-actions">'
            .'<a class="ud-activation-primary" href="'.$activationUrl.'">Aktifkan Sekarang</a>'
            .'<button type="button" class="ud-activation-secondary" data-ud-activation-close>Nanti Saja</button>'
            .'</div>'
            .'</div>'
            .'</div>';
    }

    private function activationExpiredPage(InvitationViewData $data): string
    {
        $prompt = $data->activationPrompt ?? [];
        $title = $this->e((string) ($prompt['title'] ?? 'Aktifkan Undangan Digital Anda!'));
        $message = $this->e((string) ($prompt['message'] ?? 'Masa pratinjau sudah berakhir.'));
        $price = $this->e((string) ($prompt['price'] ?? ''));
        $activationUrl = $this->e((string) ($prompt['activation_url'] ?? '#'));
        $dashboardUrl = $this->e((string) ($prompt['dashboard_url'] ?? route('dashboard')));

        return '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width, initial-scale=1">'
            .'<meta name="theme-color" content="#B5935A">'
            .'<link rel="icon" href="/assets/site/img/favicon.svg" type="image/svg+xml">'
            .'<title>Aktivasi Diperlukan — '.$this->e($data->title).'</title>'
            .'<style>'
            .'*{box-sizing:border-box}html,body{min-height:100%}body{margin:0;background:#171411;color:#2a2520;'
            .'font:500 15px/1.7 Jost,system-ui,sans-serif}'
            .'.ud-gate{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;'
            .'background:radial-gradient(circle at 50% 0%,#4a4035 0%,#171411 62%)}'
            .'.ud-gate-card{width:min(540px,100%);padding:36px;border:1px solid #e9dfd2;border-radius:22px;'
            .'background:#fdfbf8;box-shadow:0 32px 90px rgba(0,0,0,.45);text-align:center}'
            .'.ud-gate-kicker{display:block;color:#8a6b3d;font:700 11px/1.4 Jost,system-ui,sans-serif;'
            .'letter-spacing:.18em;text-transform:uppercase}'
            .'.ud-gate-card h1{margin:10px 0 12px;color:#2a2520;font:600 2rem/1.18 Georgia,serif}'
            .'.ud-gate-card p{margin:0;color:#6b6258}'
            .'.ud-gate-meta{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin:24px 0;'
            .'padding:16px;border-radius:14px;background:#f4ede3;color:#6b6258;font-size:12px}'
            .'.ud-gate-meta strong{display:block;margin-top:5px;color:#2a2520;font:700 16px/1.3 Jost,system-ui,sans-serif}'
            .'.ud-gate-actions{display:flex;flex-wrap:wrap;gap:10px}'
            .'.ud-gate-actions a{flex:1 1 190px;display:inline-flex;align-items:center;justify-content:center;'
            .'min-height:46px;padding:11px 16px;border-radius:10px;text-decoration:none;'
            .'font:700 13px/1.2 Jost,system-ui,sans-serif}'
            .'.ud-gate-primary{border:1px solid #8a6b3d;background:#8a6b3d;color:#fff}'
            .'.ud-gate-secondary{border:1px solid #d8ccbd;background:transparent;color:#2a2520}'
            .'.ud-gate-card a:focus-visible{outline:3px solid #b5935a;outline-offset:3px}'
            .'@media(max-width:600px){.ud-gate{padding:12px}.ud-gate-card{padding:26px 20px}'
            .'.ud-gate-card h1{font-size:1.55rem}.ud-gate-meta{grid-template-columns:1fr}}'
            .'</style></head><body>'
            .'<main class="ud-gate" data-ud-activation-gate data-ud-expired'
            .' role="dialog" aria-modal="true" aria-labelledby="ud-gate-title" aria-describedby="ud-gate-message">'
            .'<div class="ud-gate-card">'
            .'<span class="ud-gate-kicker">Undangan Digital</span>'
            .'<h1 id="ud-gate-title">'.$title.'</h1>'
            .'<p id="ud-gate-message">'.$message.'</p>'
            .'<div class="ud-gate-meta">'
            .'<span>Status<strong>Masa pratinjau habis</strong></span>'
            .'<span>Biaya aktivasi<strong>'.$price.'</strong></span>'
            .'</div>'
            .'<div class="ud-gate-actions">'
            .'<a class="ud-gate-primary" href="'.$activationUrl.'">Aktifkan Sekarang</a>'
            .'<a class="ud-gate-secondary" href="'.$dashboardUrl.'">Kembali ke Dashboard</a>'
            .'</div>'
            .'</div>'
            .'</main>'
            .'</body></html>';
    }

    private function runtimeScript(): string
    {
        return <<<'JS'
(function () {
    const config = window.UD_CONFIG || {};
    document.documentElement.setAttribute('data-invitation', config.key || 'tema1');

    function showToast(message) {
        let toast = document.querySelector('[data-ud-toast]');
        if (!toast) {
            toast = document.createElement('div');
            toast.className = 'ud-toast';
            toast.setAttribute('data-ud-toast', '');
            toast.setAttribute('role', 'status');
            toast.setAttribute('aria-live', 'polite');
            document.body.appendChild(toast);
        }
        toast.textContent = message;
        toast.hidden = false;
        if (toast._hideTimer) clearTimeout(toast._hideTimer);
        toast._hideTimer = setTimeout(function () { toast.hidden = true; }, 4000);
    }

    const notice = document.querySelector('[data-ud-notice]');
    if (notice) {
        const closeNotice = notice.querySelector('[data-ud-notice-close]');
        if (closeNotice) closeNotice.addEventListener('click', function () { notice.remove(); });
    }

    const activationGate = document.querySelector('[data-ud-activation-gate]');
    if (activationGate) {
        const remainingLabel = activationGate.querySelector('[data-ud-activation-remaining]');
        const expirationMessage = activationGate.getAttribute('data-expired-message')
            || 'Masa pratinjau sudah berakhir.';
        let remainingSeconds = Number(activationGate.getAttribute('data-remaining-seconds') || 0);

        function lockActivationGate() {
            document.body.classList.add('ud-activation-open', 'ud-activation-locked');
            activationGate.classList.add('is-expired');
            activationGate.querySelectorAll('[data-ud-activation-close]').forEach(function (button) {
                button.remove();
            });
            const status = activationGate.querySelector('[data-ud-activation-status]');
            if (status) status.textContent = 'Waktu habis';
            if (remainingLabel) remainingLabel.textContent = 'Waktu habis';
            const message = activationGate.querySelector('[data-ud-activation-message]');
            if (message) message.textContent = expirationMessage;
            if (!activationGate.isConnected) document.body.appendChild(activationGate);
        }

        activationGate.querySelectorAll('[data-ud-activation-close]').forEach(function (button) {
            button.addEventListener('click', function () {
                activationGate.remove();
                document.body.classList.remove('ud-activation-open');
            });
        });

        document.body.classList.add('ud-activation-open');

        if (remainingSeconds <= 0) {
            lockActivationGate();
        } else {
            const activationTimer = window.setInterval(function () {
                remainingSeconds -= 1;
                const safeSeconds = Math.max(0, remainingSeconds);
                if (remainingLabel) {
                    remainingLabel.textContent = [
                        String(Math.floor(safeSeconds / 3600)).padStart(2, '0'),
                        String(Math.floor((safeSeconds % 3600) / 60)).padStart(2, '0'),
                        String(safeSeconds % 60).padStart(2, '0'),
                    ].join(':');
                }
                if (safeSeconds <= 0) {
                    window.clearInterval(activationTimer);
                    lockActivationGate();
                }
            }, 1000);
        }
    }

    (config.hiddenIds || []).forEach(function (id) {
        const node = document.querySelector('[data-id="' + id + '"]');
        if (!node) return;
        const section = node.closest('section.elementor-top-section') || node.closest('section') || node;
        section.style.setProperty('display', 'none', 'important');
    });

    (config.hiddenWidgets || []).forEach(function (id) {
        const node = document.querySelector('[data-id="' + id + '"]');
        if (!node) return;
        node.style.setProperty('display', 'none', 'important');
    });

    const musicEnabled = !!(config.features && config.features.music === true);
    if (!musicEnabled) {
        document.querySelectorAll('audio#song, [data-ud-music], #audio-container').forEach(function (node) {
            node.hidden = true;
            node.style.setProperty('display', 'none', 'important');
        });
    }

    function flashCopied(button) {
        const label = button.querySelector('.elementor-button-text') || button;
        const original = label.textContent;
        label.textContent = 'Berhasil disalin';
        setTimeout(function () { label.textContent = original; }, 1800);
    }

    window.copyText = function (element) {
        const content = element.parentElement.querySelector('.copy-content');
        if (!content || !navigator.clipboard) return;
        const value = content.textContent.replace(/\s+/g, ' ').trim();
        navigator.clipboard.writeText(value).then(function () { flashCopied(element); });
    };

    document.querySelectorAll('[data-ud-copy-address]').forEach(function (button) {
        button.addEventListener('click', function () {
            const value = button.getAttribute('data-copy') || '';
            if (!value || !navigator.clipboard) return;
            navigator.clipboard.writeText(value).then(function () { flashCopied(button); });
        });
    });

    const audio = musicEnabled ? document.getElementById('song') : null;
    const openButton = document.getElementById('tombol-buka');
    let musicStarted = false;
    let gestureFallbackBound = false;

    function bindGestureFallback() {
        if (gestureFallbackBound) return;
        gestureFallbackBound = true;
        const retry = function () { playMusic(); };
        document.addEventListener('pointerdown', retry, { once: true });
        document.addEventListener('keydown', retry, { once: true });
    }

    function playMusic() {
        if (!audio) return Promise.resolve(false);
        const attempt = audio.play();
        if (attempt && typeof attempt.then === 'function') {
            return attempt.then(function () {
                musicStarted = true;
                return true;
            }).catch(function () {
                // Browsers block audible autoplay until a user gesture.
                bindGestureFallback();
                return false;
            });
        }
        musicStarted = true;
        return Promise.resolve(true);
    }

    if (openButton) {
        document.body.style.overflowY = 'hidden';
        openButton.addEventListener('click', function () {
            document.body.style.overflowY = '';
            openButton.style.visibility = 'hidden';
            if (musicEnabled && audio) playMusic();
        });
    }

    if (audio && config.audio && config.audio.autoplay) {
        audio.addEventListener('playing', function () { musicStarted = true; });

        function attemptAutoplay() {
            if (musicStarted || !audio.paused) return;
            playMusic();
        }

        window.addEventListener('load', function () { setTimeout(attemptAutoplay, 300); });
        // The runtime is injected just before </body>; on cached pages load
        // may already have fired by the time this script executes.
        if (document.readyState === 'complete') setTimeout(attemptAutoplay, 300);
    }

    function parseTarget(value) {
        if (!value) return null;
        if (/^\d+$/.test(String(value))) return new Date(parseInt(value, 10) * 1000);
        const normalized = String(value).trim().replace(' ', 'T');
        const parsed = new Date(normalized);
        return isNaN(parsed.getTime()) ? null : parsed;
    }

    const target = parseTarget(config.countdown && config.countdown.target);
    const selectors = (config.countdown && config.countdown.selectors) || [];

    function setText(root, selector, value) {
        const node = root.querySelector(selector);
        if (node) node.textContent = value;
    }

    function updateCountdown() {
        if (!target) return;
        let difference = Math.max(0, Math.floor((target.getTime() - Date.now()) / 1000));
        const days = Math.floor(difference / 86400); difference %= 86400;
        const hours = Math.floor(difference / 3600); difference %= 3600;
        const minutes = Math.floor(difference / 60);
        const seconds = difference % 60;
        const pad = function (number) { return String(number).padStart(2, '0'); };

        selectors.forEach(function (selector) {
            document.querySelectorAll(selector).forEach(function (wrapper) {
                if (wrapper.classList.contains('elementor-countdown-wrapper')) {
                    setText(wrapper, '.elementor-countdown-days', String(days));
                    setText(wrapper, '.elementor-countdown-hours', pad(hours));
                    setText(wrapper, '.elementor-countdown-minutes', pad(minutes));
                    setText(wrapper, '.elementor-countdown-seconds', pad(seconds));
                    wrapper.setAttribute('data-date', String(Math.floor(target.getTime() / 1000)));
                } else {
                    setText(wrapper, '[data-days]', String(days));
                    setText(wrapper, '[data-hours]', pad(hours));
                    setText(wrapper, '[data-minutes]', pad(minutes));
                    setText(wrapper, '[data-seconds]', pad(seconds));
                }
            });
        });
    }

    updateCountdown();
    if (target) setInterval(updateCountdown, 1000);

    document.querySelectorAll('form.ud-guestbook-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (config.sample || config.preview) {
                event.preventDefault();
                showToast('Mode preview: ucapan tidak disimpan.');
            }
        });
    });

    let lastFocusedElement = null;

    function openLightbox(source, caption) {
        if (document.querySelector('[data-ud-lightbox]')) return;
        lastFocusedElement = document.activeElement;

        const overlay = document.createElement('div');
        overlay.className = 'ud-lightbox';
        overlay.setAttribute('data-ud-lightbox', '');
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', 'Pratinjau foto');
        overlay.style.cssText = 'position:fixed;inset:0;z-index:100000;background:rgba(0,0,0,.92);display:flex;align-items:center;justify-content:center;padding:24px';

        const closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 'ud-lightbox-close';
        closeButton.textContent = '×';
        closeButton.setAttribute('aria-label', 'Tutup pratinjau foto');

        const figure = document.createElement('figure');
        const preview = document.createElement('img');
        preview.src = source;
        preview.alt = caption || 'Foto galeri undangan';
        figure.appendChild(preview);

        if (caption) {
            const captionNode = document.createElement('figcaption');
            captionNode.textContent = caption;
            figure.appendChild(captionNode);
        }

        function closeLightbox() {
            overlay.remove();
            document.removeEventListener('keydown', onKeydown);
            if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
                lastFocusedElement.focus();
            }
        }

        function onKeydown(event) {
            if (event.key === 'Escape') closeLightbox();
        }

        closeButton.addEventListener('click', closeLightbox);
        overlay.addEventListener('click', function (event) {
            if (event.target === overlay) closeLightbox();
        });
        document.addEventListener('keydown', onKeydown);

        overlay.appendChild(closeButton);
        overlay.appendChild(figure);
        document.body.appendChild(overlay);
        closeButton.focus();
    }

    document.querySelectorAll('.e-gallery-item').forEach(function (item) {
        item.addEventListener('click', function (event) {
            event.preventDefault();
            const image = item.querySelector('.e-gallery-image');
            const source = item.getAttribute('href') || (image && image.getAttribute('data-thumbnail'));
            if (!source) return;
            openLightbox(source, item.getAttribute('data-elementor-lightbox-title') || '');
        });
    });

    document.querySelectorAll('[data-ud-share]').forEach(function (button) {
        button.addEventListener('click', function () {
            const url = (config.share && config.share.url) || window.location.href;
            if (navigator.share) {
                navigator.share({ title: document.title, url: url }).catch(function () {});
            } else if (navigator.clipboard) {
                navigator.clipboard.writeText(url)
                    .then(function () { showToast('Tautan undangan disalin.'); })
                    .catch(function () { showToast('Salin tautan secara manual dari bilah alamat.'); });
            } else {
                showToast('Salin tautan undangan dari bilah alamat.');
            }
        });
    });

    // The fixed share bar was intentionally removed from public invitations.
    // Sharing stays available from the dashboard and the dedicated /share page.

    // No floating contact button is injected: the template's own elements stay
    // authoritative. WhatsApp sharing remains available from the dashboard.
})();
JS;
    }

    /**
     * @return array{start:int, openEnd:int, innerStart:int, innerEnd:int}|null
     */
    private function elementBoundsByDataId(string $html, string $dataId): ?array
    {
        $position = strpos($html, 'data-id="'.$dataId.'"');

        if ($position === false) {
            return null;
        }

        $prefix = substr($html, 0, $position);
        $start = null;
        $tag = null;

        foreach (['section', 'div'] as $candidate) {
            $candidateStart = strrpos($prefix, '<'.$candidate);
            if ($candidateStart !== false && ($start === null || $candidateStart > $start)) {
                $start = $candidateStart;
                $tag = $candidate;
            }
        }

        if ($start === null || $tag === null) {
            return null;
        }

        $openEnd = strpos($html, '>', $position);

        if ($openEnd === false) {
            return null;
        }

        $innerStart = $openEnd + 1;
        $innerEnd = $this->matchingTagClose($html, $innerStart, $tag);

        if ($innerEnd === null) {
            return null;
        }

        return [
            'start' => $start,
            'openEnd' => $openEnd,
            'innerStart' => $innerStart,
            'innerEnd' => $innerEnd,
        ];
    }

    private function matchingTagClose(string $html, int $start, string $tag): ?int
    {
        $pattern = '~</?'.preg_quote($tag, '~').'\b~i';
        $depth = 1;
        $position = $start;
        $closing = '</'.$tag;

        while (preg_match($pattern, $html, $matches, PREG_OFFSET_CAPTURE, $position)) {
            $tagStart = $matches[0][1];
            $depth += strtolower($matches[0][0]) === $closing ? -1 : 1;
            $position = $tagStart + strlen($matches[0][0]);

            if ($depth === 0) {
                return $tagStart;
            }
        }

        return null;
    }

    /**
     * @return array{start:int, openEnd:int, innerStart:int, innerEnd:int}|null
     */
    private function widgetBounds(string $html, string $dataId): ?array
    {
        $position = strpos($html, 'data-id="'.$dataId.'"');

        if ($position === false) {
            return null;
        }

        $start = strrpos(substr($html, 0, $position), '<div');

        if ($start === false) {
            return null;
        }

        $openEnd = strpos($html, '>', $position);

        if ($openEnd === false) {
            return null;
        }

        $container = stripos($html, '<div class="elementor-widget-container"', $openEnd);

        if ($container === false || $container > $openEnd + 2000) {
            return null;
        }

        $innerStart = strpos($html, '>', $container) + 1;
        $innerEnd = $this->matchingDivClose($html, $innerStart);

        if ($innerEnd === null) {
            return null;
        }

        return [
            'start' => $start,
            'openEnd' => $openEnd,
            'innerStart' => $innerStart,
            'innerEnd' => $innerEnd,
        ];
    }

    private function matchingDivClose(string $html, int $start): ?int
    {
        $depth = 1;
        $position = $start;

        while (preg_match(self::DIV_TAG, $html, $matches, PREG_OFFSET_CAPTURE, $position)) {
            $tagStart = $matches[0][1];
            $tag = strtolower($matches[0][0]);
            $depth += $tag === '</div' ? -1 : 1;
            $position = $tagStart + strlen($matches[0][0]);

            if ($depth === 0) {
                return $tagStart;
            }
        }

        return null;
    }

    /**
     * Append a visibility declaration to an existing opening tag without
     * changing the element itself.
     */
    private function addInlineStyle(string $html, string $tagPrefix, string $needle, string $declaration): string
    {
        $needlePosition = strpos($html, $needle);
        if ($needlePosition === false) {
            return $html;
        }

        $start = strrpos(substr($html, 0, $needlePosition), $tagPrefix);
        if ($start === false) {
            return $html;
        }

        $start += strlen($tagPrefix);
        $end = strpos($html, '>', $needlePosition);
        if ($end === false) {
            return $html;
        }

        $tag = substr($html, $start, $end - $start);
        if (stripos($tag, $declaration) !== false) {
            return $html;
        }

        if (preg_match('~\sstyle="([^"]*)"~i', $tag, $matches)) {
            $updated = str_replace($matches[0], ' style="'.$matches[1].';'.$declaration.'"', $tag);
        } else {
            $updated = rtrim($tag).' style="'.$declaration.'"';
        }

        return substr_replace($html, $updated, $start, $end - $start);
    }

    private function removeDivById(string $html, string $id): string
    {
        $position = strpos($html, 'id="'.$id.'"');

        if ($position === false) {
            return $html;
        }

        $openStart = strrpos(substr($html, 0, $position), '<div');

        if ($openStart === false) {
            return $html;
        }

        $openEnd = strpos($html, '>', $position);

        if ($openEnd === false) {
            return $html;
        }

        $innerEnd = $this->matchingDivClose($html, $openEnd + 1);

        if ($innerEnd === null) {
            return $html;
        }

        $closeEnd = strpos($html, '>', $innerEnd);

        return $closeEnd === false
            ? $html
            : substr_replace($html, '', $openStart, $closeEnd + 1 - $openStart);
    }

    /**
     * Build one CSS rule that hides every feature section and empty widget.
     * Feature IDs are mapped to their containing top section so no empty
     * section shell remains visible while the page is loading.
     *
     * @param  array<int, string>  $hiddenIds
     * @param  array<int, string>  $hiddenWidgets
     */
    private function hiddenElementCss(string $html, array $hiddenIds, array $hiddenWidgets): string
    {
        if ($hiddenIds === [] && $hiddenWidgets === []) {
            return '';
        }

        preg_match_all(
            '~<section\b[^>]*\bclass="[^"]*elementor-top-section[^"]*"[^>]*\bdata-id="([0-9a-fA-F]{6,8})"~i',
            $html,
            $matches,
            PREG_OFFSET_CAPTURE | PREG_SET_ORDER,
        );

        $sections = [];
        foreach ($matches as $match) {
            $sections[] = ['id' => $match[1][0], 'offset' => $match[0][1]];
        }

        $selectors = [];

        foreach ($hiddenIds as $id) {
            $safeId = preg_replace('/[^a-zA-Z0-9_-]/', '', $id) ?? '';
            if ($safeId === '') {
                continue;
            }

            $sectionId = $safeId;
            $position = strpos($html, 'data-id="'.$id.'"');

            if ($position !== false) {
                foreach ($sections as $section) {
                    if ($section['offset'] < $position) {
                        $sectionId = preg_replace('/[^a-zA-Z0-9_-]/', '', $section['id']) ?? $sectionId;
                    } else {
                        break;
                    }
                }
            }

            $selectors['[data-id="'.$sectionId.'"]'] = true;
        }

        foreach ($hiddenWidgets as $id) {
            $safeId = preg_replace('/[^a-zA-Z0-9_-]/', '', $id) ?? '';
            if ($safeId !== '') {
                $selectors['[data-id="'.$safeId.'"]'] = true;
            }
        }

        return $selectors === [] ? '' : implode(',', array_keys($selectors)).'{display:none!important}';
    }

    private function absoluteUrl(string $path): string
    {
        if ($path === '' || $path === '#') {
            return $path;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        if (str_starts_with($path, '//')) {
            return 'https:'.$path;
        }

        return url($path);
    }

    private function optimizeOgImageUrl(string $url): string
    {
        if ($url === '' || str_contains($url, '/assets/site/img/og-default')) {
            return $url;
        }
        // Cloudinary delivery URL: add OG-friendly crop/size transform.
        if (preg_match('~https?://res\.cloudinary\.com/[^/]+/image/upload/(.+)$~i', $url, $m)) {
            $rest = $m[1];
            if (str_contains($rest, 'c_fill') || str_contains($rest, 'w_1200')) {
                return $url;
            }

            return preg_replace('~/image/upload/~i', '/image/upload/c_fill,g_auto,w_1200,h_630,f_jpg,q_auto/', $url, 1) ?? $url;
        }

        return $url;
    }

    private function buildJsonLd(InvitationViewData $data, string $title, string $description, ?string $canonical, string $image, string $siteName, bool $isIndexable): ?string
    {
        $graph = [];
        $websiteId = rtrim((string) config('app.url', url('/')), '/').'#website';
        $pageId = ($canonical ?: url('/')).'#webpage';

        $graph[] = [
            '@type' => 'WebSite',
            '@id' => $websiteId,
            'url' => rtrim((string) config('app.url', url('/')), '/'),
            'name' => $siteName,
            'inLanguage' => 'id-ID',
        ];

        $graph[] = [
            '@type' => 'WebPage',
            '@id' => $pageId,
            'url' => $canonical ?: url()->current(),
            'name' => $title,
            'description' => $description,
            'isPartOf' => ['@id' => $websiteId],
            'inLanguage' => 'id-ID',
            'primaryImageOfPage' => $image,
        ];

        foreach ($data->events as $event) {
            $start = trim((string) $event->date);
            $time = '';
            if (preg_match('/(\d{2}:\d{2})/', (string) $event->timeLabel, $m)) {
                $time = $m[1];
            }
            $tz = trim((string) ($event->timezone ?? 'Asia/Jakarta')) ?: 'Asia/Jakarta';
            $startIso = null;
            if ($start !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
                try {
                    $startIso = Carbon::parse($start.' '.($time !== '' ? $time : '00:00'), $tz)->toIso8601String();
                } catch (\Throwable $e) {
                    $startIso = null;
                }
            }

            $eventNode = [
                '@type' => 'Event',
                'name' => trim((string) $event->title) !== '' ? $event->title : $title,
                'description' => $description,
                'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
                'eventStatus' => 'https://schema.org/EventScheduled',
                'image' => $image,
                'url' => $canonical ?: url()->current(),
                'inLanguage' => 'id-ID',
            ];
            if ($startIso) {
                $eventNode['startDate'] = $startIso;
            }
            $address = trim((string) ($event->address ?? ''));
            $locationLabel = trim((string) ($event->locationLabel ?? ''));
            $locationName = $locationLabel;
            if ($address !== '' && $locationName !== '') {
                $locationName = trim(str_replace($address, '', $locationName));
            }
            $locationName = trim($locationName, ' 
	 ,-');
            if ($locationName === '') {
                $locationName = $address !== '' ? $address : $locationLabel;
            }
            if ($locationName !== '' || $address !== '') {
                $eventNode['location'] = [
                    '@type' => 'Place',
                    'name' => $locationName !== '' ? $locationName : $address,
                    'address' => $address !== '' ? $address : $locationName,
                ];
            }
            $graph[] = $eventNode;
        }

        $payload = [
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ];

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: null;
    }
}
