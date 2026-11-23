<?php

namespace App\Enums;

enum CoupleOrder: string
{
    case MaleFirst = 'male_first';
    case FemaleFirst = 'female_first';

    public function label(): string
    {
        return match ($this) {
            self::MaleFirst => 'Laki-laki dulu',
            self::FemaleFirst => 'Perempuan dulu',
        };
    }

    public function isMaleFirst(): bool
    {
        return $this === self::MaleFirst;
    }

    public function isFemaleFirst(): bool
    {
        return $this === self::FemaleFirst;
    }
}
