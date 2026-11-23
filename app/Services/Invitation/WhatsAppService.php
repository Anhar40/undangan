<?php

namespace App\Services\Invitation;

use InvalidArgumentException;

class WhatsAppService
{
    /**
     * Normalize a customer-entered number to the digits used by wa.me.
     * Accepts local (08xx), international (+62/62) and spaced formats.
     */
    public function normalize(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62' . $digits;
        }

        if (! str_starts_with($digits, '62')) {
            throw new InvalidArgumentException('Nomor WhatsApp harus menggunakan kode Indonesia (+62).');
        }

        if (strlen($digits) < 10 || strlen($digits) > 15) {
            throw new InvalidArgumentException('Nomor WhatsApp tidak valid.');
        }

        return $digits;
    }

    public function contactUrl(?string $number, ?string $message = null): ?string
    {
        $normalized = $this->normalize($number ?? '');

        if ($normalized === null) {
            return null;
        }

        $url = 'https://wa.me/' . $normalized;

        if ($message !== null && trim($message) !== '') {
            $url .= '?text=' . rawurlencode(trim($message));
        }

        return $url;
    }
}
