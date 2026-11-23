<?php

namespace App\Data;

final readonly class StoryViewData
{
    public function __construct(
        public string $title,
        public string $description,
        public ?string $image,
        public ?string $storyDate = null,
    ) {
    }
}
