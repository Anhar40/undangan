<?php

namespace App\Data;

use Carbon\Carbon;

final readonly class PaymentRequestData
{
    public const PAYMENT_TYPES = [
        'qris', 'bca_va', 'bni_va', 'bri_va', 'permata_va',
        'echannel', 'gopay', 'cstore',
    ];

    /**
     * Customer-facing method => Core API bank code.
     *
     * Core API does not accept Snap names such as bca_va. Every VA, including
     * Permata, is charged with payment_type "bank_transfer" plus a bank code.
     *
     * @var array<string, string>
     */
    private const BANK_CODES = [
        'bca_va' => 'bca',
        'bni_va' => 'bni',
        'bri_va' => 'bri',
        'permata_va' => 'permata',
    ];

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            'qris' => 'QRIS',
            'bca_va' => 'BCA Virtual Account',
            'bni_va' => 'BNI Virtual Account',
            'bri_va' => 'BRI Virtual Account',
            'permata_va' => 'Permata Virtual Account',
            'echannel' => 'Mandiri Bill',
            'gopay' => 'GoPay',
            'cstore' => 'Indomaret',
        ];
    }

    public static function isSupported(string $paymentType): bool
    {
        return in_array($paymentType, self::PAYMENT_TYPES, true);
    }

    public function __construct(
        public string $orderId,
        public int $grossAmount,
        public string $customerName,
        public string $customerEmail,
        public string $itemName,
        public string $finishUrl,
        public string $notificationUrl,
        public int $invitationId = 0,
        public string $paymentType = 'qris',
        public int $expiryMinutes = 30,
    ) {}

    /** @return array<string, mixed> */
    public function toMidtransPayload(): array
    {
        // Midtrans timestamps are GMT+7 even when the application runs on WITA.
        $providerTimezone = (string) config('services.midtrans.timestamp_timezone', 'Asia/Jakarta');

        $payload = [
            'payment_type' => $this->coreApiPaymentType(),
            'transaction_details' => [
                'order_id' => $this->orderId,
                'gross_amount' => $this->grossAmount,
            ],
            'item_details' => [[
                'id' => 'activation-' . $this->invitationId,
                'name' => $this->itemName,
                'price' => $this->grossAmount,
                'quantity' => 1,
            ]],
            'customer_details' => [
                'first_name' => $this->customerName,
                'email' => $this->customerEmail,
            ],
            'callbacks' => [
                'finish' => $this->finishUrl,
                'notification' => $this->notificationUrl,
            ],
            'custom_field1' => (string) $this->invitationId,
            'custom_field2' => 'activation',
            'expiry' => [
                'start_time' => Carbon::now($providerTimezone)->format('Y-m-d H:i:s'),
                'unit' => 'minute',
                'duration' => max(1, $this->expiryMinutes),
            ],
        ];

        return array_replace_recursive($payload, $this->paymentDetails());
    }

    private function coreApiPaymentType(): string
    {
        return isset(self::BANK_CODES[$this->paymentType])
            ? 'bank_transfer'
            : $this->paymentType;
    }

    /**
     * Core API needs method-specific fields only when we want to prefill them.
     * The charge response still carries the customer-facing instruction.
     *
     * @return array<string, mixed>
     */
    private function paymentDetails(): array
    {
        if (isset(self::BANK_CODES[$this->paymentType])) {
            return [
                'bank_transfer' => [
                    'bank' => self::BANK_CODES[$this->paymentType],
                ],
            ];
        }

        return match ($this->paymentType) {
            'echannel' => [
                'echannel' => [
                    'bill_info1' => 'Pembayaran',
                    'bill_info2' => $this->itemName,
                ],
            ],
            'gopay' => [
                'gopay' => [
                    'enable_callback' => true,
                    'callback_url' => $this->finishUrl,
                ],
            ],
            'cstore' => [
                'cstore' => [
                    'store' => 'Indomaret',
                    'message' => $this->itemName,
                ],
            ],
            default => [],
        };
    }
}
