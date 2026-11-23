<?php

namespace App\Data;

final readonly class GreetingViewData
{
    public function __construct(
        public string $guestName,
        public string $message,
        public string $attendance,
        public string $createdAt,
    ) {
    }
}
