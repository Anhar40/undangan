<?php

namespace App\Contracts;

use App\Data\PaymentRequestData;
use App\Data\PaymentResponseData;

interface PaymentGateway
{
    /** @throws \App\Exceptions\Payment\GatewayException */
    public function createTransaction(PaymentRequestData $request): PaymentResponseData;

    /** @return array<string, mixed> */
    public function fetchStatus(string $orderId): array;

    /** Compensating action when a charge succeeded but the local write failed. */
    public function cancel(string $orderId): void;
}
