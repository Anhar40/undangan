<?php

namespace App\Data;

use App\Models\Transaction;
use Carbon\Carbon;

final readonly class PaymentInstructionData
{
    /** @param array<int, string> $steps */
    public function __construct(
        public string $label,
        public ?string $reference,
        public ?string $qrUrl,
        public ?string $qrString,
        public ?string $expiryTime,
        public array $steps,
    ) {}

    public static function fromResponse(PaymentResponseData $response, ?string $paymentType = null): self
    {
        $selected = strtolower((string) ($paymentType ?? ''));
        $providerType = strtolower((string) ($response->paymentType ?? ''));

        // Core API returns "bank_transfer" for every VA. The customer-selected
        // method is authoritative for labels and instructions.
        $useSelected = PaymentRequestData::isSupported($selected);
        $type = $useSelected ? $selected : $providerType;
        $label = $useSelected
            ? PaymentRequestData::labels()[$type]
            : ($response->paymentLabel ?? self::labelFor($type));

        return new self(
            label: $label,
            reference: $response->paymentReference,
            qrUrl: $response->qrUrl,
            qrString: $response->qrString,
            expiryTime: $response->expiryTime,
            steps: self::stepsFor($type),
        );
    }

    public static function fromTransaction(Transaction $transaction): self
    {
        $stored = is_array($transaction->payment_instructions) ? $transaction->payment_instructions : [];
        $type = strtolower((string) ($transaction->payment_type ?? ''));

        if ($stored !== []) {
            $steps = $stored['steps'] ?? self::stepsFor($type);

            return new self(
                label: (string) ($stored['label'] ?? self::labelFor($type)),
                reference: $transaction->payment_reference,
                qrUrl: isset($stored['qr_url']) ? (string) $stored['qr_url'] : null,
                qrString: isset($stored['qr_string']) ? (string) $stored['qr_string'] : null,
                expiryTime: $transaction->payment_expires_at?->toIso8601String(),
                steps: is_array($steps) ? array_values(array_map('strval', $steps)) : self::stepsFor($type),
            );
        }

        $raw = is_array($transaction->midtrans_response) ? $transaction->midtrans_response : [];

        return new self(
            label: self::labelFor($type),
            reference: $transaction->payment_reference,
            qrUrl: isset($raw['qr_code_url']) ? (string) $raw['qr_code_url'] : null,
            qrString: isset($raw['qr_string']) ? (string) $raw['qr_string'] : null,
            expiryTime: $transaction->payment_expires_at?->toIso8601String()
                ?? (isset($raw['expiry_time']) ? (string) $raw['expiry_time'] : null),
            steps: self::stepsFor($type),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'reference' => $this->reference,
            'qr_url' => $this->qrUrl,
            'qr_string' => $this->qrString,
            'expiry_time' => $this->expiryTime,
            'steps' => $this->steps,
        ];
    }

    public function formattedExpiry(): ?string
    {
        if ($this->expiryTime === null || $this->expiryTime === '') {
            return null;
        }

        try {
            return Carbon::parse($this->expiryTime)
                ->timezone(config('app.timezone', 'Asia/Makassar'))
                ->translatedFormat('d M Y H:i');
        } catch (\Throwable) {
            return $this->expiryTime;
        }
    }

    private static function labelFor(string $type): string
    {
        return match ($type) {
            'qris' => 'QRIS',
            'bca_va' => 'BCA Virtual Account',
            'bni_va' => 'BNI Virtual Account',
            'bri_va' => 'BRI Virtual Account',
            'permata_va' => 'Permata Virtual Account',
            'echannel' => 'Mandiri Bill Payment',
            'gopay' => 'GoPay',
            'cstore' => 'Gerai Retail (Indomaret)',
            '' => 'Metode pembayaran',
            default => strtoupper(str_replace('_', ' ', $type)),
        };
    }

    /** @return array<int, string> */
    private static function stepsFor(string $type): array
    {
        if (str_contains($type, 'va')) {
            return [
                'Buka aplikasi mobile banking atau internet banking Anda.',
                'Pilih menu Transfer / Virtual Account.',
                'Masukkan nomor Virtual Account di bawah ini lalu kirim.',
                'Simpan bukti transaksi. Undangan aktif otomatis setelah notifikasi terverifikasi.',
            ];
        }

        if ($type === 'echannel' || $type === 'cstore') {
            return [
                'Buka aplikasi bank atau datang ke gerai retail terdekat.',
                'Pilih menu pembayaran dan cantumkan kode pembayaran di bawah ini.',
                'Periksa nominal lalu konfirmasi pembayaran.',
                'Undangan aktif otomatis setelah notifikasi pembayaran terverifikasi.',
            ];
        }

        return [
            'Buka aplikasi pembayaran atau e-wallet Anda.',
            'Pindai kode QR / buka halaman pembayaran yang disediakan.',
            'Periksa nama undangan dan nominal yang harus dibayar.',
            'Selesaikan pembayaran. Undangan aktif otomatis setelah notifikasi terverifikasi.',
        ];
    }
}
