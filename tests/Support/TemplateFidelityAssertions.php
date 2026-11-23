<?php

namespace Tests\Support;

trait TemplateFidelityAssertions
{
    /** @return array<string, mixed> */
    protected function fidelityConfig(string $html): array
    {
        if (! preg_match('/window\.UD_CONFIG=(\{.*?\});/s', $html, $matches)) {
            return [];
        }

        $config = json_decode($matches[1], true);

        return is_array($config) ? $config : [];
    }

    /**
     * Markup with runtime scripts removed, so injected nodes can be compared
     * against the source without matching strings inside the runtime itself.
     */
    protected function markupWithoutScripts(string $html): string
    {
        $dom = $this->fidelityDom($html);
        $xpath = new \DOMXPath($dom);

        foreach ($xpath->query('//script|//noscript|//template') as $node) {
            $node->parentNode?->removeChild($node);
        }

        return (string) $dom->saveHTML();
    }

    /** Markup that a visitor can actually perceive, with hidden nodes removed. */
    protected function visibleMarkup(string $html): string
    {
        $dom = $this->fidelityDom($html);
        $xpath = new \DOMXPath($dom);

        foreach ($xpath->query('//script|//style|//noscript|//template') as $node) {
            $node->parentNode?->removeChild($node);
        }

        $hiddenIds = $this->hiddenDataIds($html);
        $remove = [];

        foreach ($xpath->query('//*') as $node) {
            $classes = preg_split('~\s+~', trim($node->getAttribute('class'))) ?: [];
            $style = strtolower(str_replace(' ', '', $node->getAttribute('style')));

            if (
                in_array($node->getAttribute('data-id'), $hiddenIds, true)
                || $node->hasAttribute('hidden')
                || in_array('elementor-hidden', $classes, true)
                || str_contains($style, 'display:none')
            ) {
                $remove[] = $node;
            }
        }

        foreach ($remove as $node) {
            $node->parentNode?->removeChild($node);
        }

        return (string) $dom->saveHTML();
    }

    /** @param iterable<string> $markers */
    protected function assertNoVisibleSample(string $html, iterable $markers, string $context = ''): void
    {
        $visible = $this->visibleMarkup($html);

        foreach ($markers as $marker) {
            $this->assertStringNotContainsString(
                $marker,
                $visible,
                trim($context . ' sample marker must stay hidden: ' . $marker),
            );
        }
    }

    /** @return array<string, int> */
    protected function dataIdCounts(string $html): array
    {
        $counts = [];

        foreach ($this->fidelityXPath($html)->query('//*[@data-id]') as $node) {
            $id = $node->getAttribute('data-id');
            $counts[$id] = ($counts[$id] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }

    /** @return array<int, string> */
    protected function topSectionIds(string $html): array
    {
        $ids = [];
        $query = '//section[contains(concat(" ", normalize-space(@class), " "), " elementor-top-section ") and @data-id]';

        foreach ($this->fidelityXPath($html)->query($query) as $node) {
            $ids[] = $node->getAttribute('data-id');
        }

        return $ids;
    }

    protected function assertNativeStructurePreserved(string $source, string $rendered, string $context = ''): void
    {
        $this->assertSame(
            $this->topSectionIds($source),
            $this->topSectionIds($rendered),
            $context . ' top-level Elementor section order changed',
        );
        $this->assertSame(
            $this->dataIdCounts($source),
            $this->dataIdCounts($rendered),
            $context . ' native Elementor data-id elements changed',
        );
    }

    /**
     * Reject markup added by the old renderer. Comparison is done on scriptless
     * DOM so strings inside the runtime script are not false positives.
     *
     * @param iterable<string> $markers
     */
    protected function assertNoInjectedMarkup(string $source, string $rendered, iterable $markers, string $context = ''): void
    {
        $sourceMarkup = $this->markupWithoutScripts($source);
        $renderedMarkup = $this->markupWithoutScripts($rendered);

        foreach ($markers as $marker) {
            $this->assertLessThanOrEqual(
                substr_count($sourceMarkup, $marker),
                substr_count($renderedMarkup, $marker),
                $context . ' injected unsupported markup: ' . $marker,
            );
        }
    }

    protected function assertNativeAudioHidden(string $html, string $context = ''): void
    {
        $xpath = $this->fidelityXPath($html);
        $audio = $xpath->query('//audio[@id="song"]')->item(0);
        $this->assertNotNull($audio, $context . ' native audio element must stay in the source');

        $style = strtolower(str_replace(' ', '', $audio->getAttribute('style')));
        $this->assertStringContainsString('display:none', $style, $context . ' native audio must be hidden');

        $source = $xpath->query('.//source', $audio)->item(0);
        if ($source !== null) {
            $this->assertSame('', $source->getAttribute('src'), $context . ' sample audio source must be empty');
        }

        $config = $this->fidelityConfig($html);
        $this->assertFalse($config['audio']['enabled'] ?? true, $context . ' audio feature must be disabled');
    }

    protected function fidelityDom(string $html): \DOMDocument
    {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $dom;
    }

    protected function fidelityXPath(string $html): \DOMXPath
    {
        return new \DOMXPath($this->fidelityDom($html));
    }

    /** @return array<int, string> */
    private function hiddenDataIds(string $html): array
    {
        $config = $this->fidelityConfig($html);
        $ids = array_merge(
            $config['hiddenIds'] ?? [],
            $config['hiddenWidgets'] ?? [],
        );

        preg_match_all(
            '~\[data-id="([^"]+)"\]\s*\{[^}]*display\s*:\s*none[^}]*\}~i',
            $html,
            $matches,
        );

        return array_values(array_unique(array_merge($ids, $matches[1] ?? [])));
    }
}
