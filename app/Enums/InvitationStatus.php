<?php

namespace App\Enums;

enum InvitationStatus: string
{
    case Draft = 'DRAFT';
    case Preview = 'PREVIEW';
    case Expired = 'EXPIRED';
    case PendingPayment = 'PENDING_PAYMENT';
    case Active = 'ACTIVE';
    case Suspended = 'SUSPENDED';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Preview => 'Preview',
            self::Expired => 'Kedaluwarsa',
            self::PendingPayment => 'Menunggu Pembayaran',
            self::Active => 'Aktif',
            self::Suspended => 'Dinonaktifkan',
        };
    }

    /** Statuses in which the customer may still edit the invitation. */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Preview, self::PendingPayment], true);
    }
}
