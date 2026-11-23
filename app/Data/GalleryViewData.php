<?php

namespace App\Data;

final readonly class GalleryViewData
{
    public function __construct(
        public string $url,
        public string $caption,
        public bool $isCover = false,
        public int $width = 853,
        public int $height = 1280,
    ) {
    }
}
