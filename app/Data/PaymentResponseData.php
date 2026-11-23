<?php

namespace App\Data;

final readonly class PaymentResponseData
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public string $token = '',
        public string $redirectUrl = '',
        public array $raw = [],
        public ?string $paymentType = null,
        public ?string $paymentLabel = null,
        public ?string $paymentReference = null,
        public ?string $qrString = null,
        public ?string $qrUrl = null,
        public ?string $expiryTime = null,
        public ?string $status = null,
    ) {}

    /** @param array<string, mixed> $raw */
    public static function fromMidtrans(array $raw): self
    {
        $paymentType = self::string($raw, 'payment_type');

        return new self(
            token: '',
            redirectUrl: self::string($raw, 'redirect_url'),
            raw: $raw,
            paymentType: $paymentType !== '' ? $paymentType : null,
            paymentLabel: self::label($paymentType),
            paymentReference: self::reference($raw),
            qrString: self::string($raw, 'qr_string') ?: null,
            qrUrl: self::qrUrl($raw) ?: null,
            expiryTime: self::string($raw, 'expiry_time') ?: null,
            status: self::string($raw, 'transaction_status') ?: null,
        );
    }

    public function isValid(): bool
    {
        if ($this->token !== '' && $this->redirectUrl !== '') {
            return true;
        }

        if ($this->status !== null && $this->status !== '') {
            return true;
        }

        return isset($this->raw['transaction_id']) || isset($this->raw['status_code']);
    }

    /** @param array<string, mixed> $source */
    private static function string(array $source, string $key): string
    {
        $value = $source[$key] ?? null;

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /** @param array<string, mixed> $raw */
    private static function reference(array $raw): ?string
    {
        $vaNumbers = $raw['va_numbers'] ?? null;

        if (is_array($vaNumbers)) {
            $first = reset($vaNumbers);
            if (is_array($first) && isset($first['va_number'])) {
                return (string) $first['va_number'];
            }
        }

        foreach (['permata_va_number', 'payment_code', 'bill_key'] as $key) {
            $value = self::string($raw, $key);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $raw */
    private static function qrUrl(array $raw): string
    {
        $direct = self::string($raw, 'qr_code_url');
        if ($direct !== '') {
            return $direct;
        }

        $actions = $raw['actions'] ?? null;
        if (! is_array($actions)) {
            return '';
        }

        foreach ($actions as $action) {
            if (! is_array($action)) {
                continue;
            }

            $name = strtolower((string) ($action['name'] ?? ''));
            if (str_contains($name, 'qr') && isset($action['url'])) {
                return (string) $action['url'];
            }
        }

        return '';
    }

    private static function label(string $paymentType): ?string
    {
        if ($paymentType === '') {
            return null;
        }

        return match ($paymentType) {
            'qris' => 'QRIS',
            'bank_transfer' => 'Virtual Account',
            'bca_va' => 'BCA Virtual Account',
            'bni_va' => 'BNI Virtual Account',
            'bri_va' => 'BRI Virtual Account',
            'permata_va' => 'Permata Virtual Account',
            'echannel' => 'Mandiri Bill Payment',
            'gopay' => 'GoPay',
            'cstore' => 'Gerai Retail (Indomaret)',
            default => strtoupper(str_replace('_', ' ', $paymentType)),
        };
    }
}
