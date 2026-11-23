<?php

namespace App\Data;

use App\Enums\PaymentStatus;
use App\Models\Transaction;

final readonly class PaymentVerificationResult
{
    public function __construct(
        public Transaction $transaction,
        public PaymentStatus $paymentStatus,
        public bool $activated,
        public bool $stale = false,
        public bool $accessRevoked = false,
    ) {}
}
