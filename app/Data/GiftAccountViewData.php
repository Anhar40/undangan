<?php

namespace App\Data;

final readonly class GiftAccountViewData
{
    public function __construct(
        public string $label,
        public string $accountName,
        public string $accountNumber,
        public ?string $qrUrl,
        public ?string $giftAddress,
    ) {
    }

    public function displayText(): string
    {
        $lines = [];

        if ($this->label !== '') {
            $lines[] = e($this->label);
        }
        if ($this->accountNumber !== '') {
            $lines[] = e($this->accountNumber);
        } elseif ($this->giftAddress) {
            $lines[] = e($this->giftAddress);
        }
        if ($this->accountName !== '') {
            $lines[] = 'a.n ' . e($this->accountName);
        }

        return implode('<br>', $lines);
    }

    public function copyValue(): string
    {
        if ($this->giftAddress && ! $this->accountNumber) {
            return $this->giftAddress . ($this->accountName ? ' a.n ' . $this->accountName : '');
        }

        return $this->accountNumber;
    }
}
