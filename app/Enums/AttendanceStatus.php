<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Hadir = 'HADIR';
    case AkanHadir = 'AKAN_HADIR';
    case TidakHadir = 'TIDAK_HADIR';

    public function label(): string
    {
        return match ($this) {
            self::Hadir => 'Hadir',
            self::AkanHadir => 'Akan Hadir',
            self::TidakHadir => 'Tidak Hadir',
        };
    }
}
