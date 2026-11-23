<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'PENDING';
    case Authorize = 'AUTHORIZE';
    case Challenge = 'CHALLENGE';
    case Paid = 'PAID';
    case Settlement = 'SETTLEMENT';
    case Expire = 'EXPIRE';
    case Cancel = 'CANCEL';
    case Deny = 'DENY';
    case Failure = 'FAILURE';
    case Refund = 'REFUND';
    case PartialRefund = 'PARTIAL_REFUND';
    case Chargeback = 'CHARGEBACK';
    case PartialChargeback = 'PARTIAL_CHARGEBACK';

    public function badgeClass(): string
    {
        return match ($this) {
            self::Paid, self::Settlement => 'pill-success',
            self::Refund, self::PartialRefund, self::Chargeback, self::PartialChargeback => 'pill-danger',
            self::Expire, self::Cancel, self::Deny, self::Failure => 'pill-danger',
            self::Authorize, self::Challenge => 'pill-warning',
            default => 'pill-info',
        };
    }

    /** Midtrans statuses that mean money has been received. */
    public function isSuccessful(): bool
    {
        return in_array($this, [self::Paid, self::Settlement], true);
    }

    /** Full reversals must revoke public access; partial refunds are recorded only. */
    public function revokesAccess(): bool
    {
        return in_array($this, [self::Refund, self::Chargeback], true);
    }

    /**
     * Newer notifications may replace older ones. This prevents a late
     * "pending" webhook from downgrading a settlement.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Pending => 10,
            self::Authorize => 15,
            self::Challenge => 20,
            self::Expire, self::Cancel, self::Deny, self::Failure => 30,
            self::Paid, self::Settlement => 40,
            self::PartialRefund => 50,
            self::PartialChargeback => 55,
            self::Refund => 60,
            self::Chargeback => 65,
        };
    }
}
