<?php

namespace App\Data;

final readonly class EventViewData
{
    public function __construct(
        public string $type,
        public string $title,
        public string $date,
        public string $dayName,
        public string $day,
        public string $month,
        public string $year,
        public string $timeLabel,
        public string $locationLabel,
        public ?string $mapsUrl,
        public ?string $liveStreamUrl,
        public ?string $description,
        public ?string $address = null,
        public ?string $endTime = null,
        public string $timezone = 'Asia/Jakarta',
        public ?string $dressCode = null,
    ) {
    }
}
