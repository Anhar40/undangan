<?php

namespace App\Enums;

enum Role: string
{
    case Customer = 'customer';
    case SuperAdmin = 'super_admin';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Customer',
            self::SuperAdmin => 'Super Admin',
        };
    }
}
