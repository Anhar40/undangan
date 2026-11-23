<?php

namespace Tests\Feature;

use App\Data\PaymentRequestData;
use App\Exceptions\Payment\GatewayException;
use App\Services\Payment\MidtransGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Midtrans\Config;
use ReflectionMethod;
use Tests\TestCase;

class MidtransGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.midtrans.server_key', 'test-server-key');
        config()->set('services.midtrans.client_key', 'test-client-key');
        config()->set('services.midtrans.is_production', false);
        config()->set('services.midtrans.notification_url', 'https://app.test/payment/notification');
    }

    public function test_request_payload_uses_core_api_fields_for_every_payment_method(): void
    {
        $methods = [
            'qris' => ['gateway' => 'qris', 'detail' => null],
            'bca_va' => ['gateway' => 'bank_transfer', 'detail' => ['bank_transfer' => ['bank' => 'bca']]],
            'bni_va' => ['gateway' => 'bank_transfer', 'detail' => ['bank_transfer' => ['bank' => 'bni']]],
            'bri_va' => ['gateway' => 'bank_transfer', 'detail' => ['bank_transfer' => ['bank' => 'bri']]],
            'permata_va' => ['gateway' => 'bank_transfer', 'detail' => ['bank_transfer' => ['bank' => 'permata']]],
            'echannel' => ['gateway' => 'echannel', 'detail' => ['echannel' => ['bill_info1' => 'Pembayaran']]],
            'gopay' => ['gateway' => 'gopay', 'detail' => ['gopay' => ['enable_callback' => true]]],
            'cstore' => ['gateway' => 'cstore', 'detail' => ['cstore' => ['store' => 'Indomaret']]],
        ];

        foreach ($methods as $type => $expectation) {
            $request = $this->request($type);
            $payload = $request->toMidtransPayload();

            $this->assertSame($type, $request->paymentType, 'Canonical method must stay customer-facing.');
            $this->assertSame($expectation['gateway'], $payload['payment_type']);
            $this->assertArrayNotHasKey('va_numbers', $payload, 'Snap-style VA fields are rejected by Core API.');
            $this->assertSame('UD-42', $payload['transaction_details']['order_id']);
            $this->assertSame(150000, $payload['transaction_details']['gross_amount']);
            $this->assertSame('activation-7', $payload['item_details'][0]['id']);
            $this->assertSame('Customer Test', $payload['customer_details']['first_name']);
            $this->assertSame('customer@example.test', $payload['customer_details']['email']);
            $this->assertSame('https://app.test/finish', $payload['callbacks']['finish']);
            $this->assertSame('https://app.test/payment/notification', $payload['callbacks']['notification']);
            $this->assertSame('7', $payload['custom_field1']);
            $this->assertSame('activation', $payload['custom_field2']);
            $this->assertSame('minute', $payload['expiry']['unit']);
            $this->assertSame(45, $payload['expiry']['duration']);
            $this->assertMatchesRegularExpression(
                '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
                $payload['expiry']['start_time'],
            );

            if ($expectation['detail'] !== null) {
                foreach ($expectation['detail'] as $key => $fragment) {
                    $this->assertArrayHasKey($key, $payload);
                    $this->assertSame(
                        $fragment,
                        array_intersect_key($payload[$key], $fragment),
                    );
                }
            }
        }
    }

    public function test_gateway_configures_core_api_with_idempotency_and_public_notification_url(): void
    {
        $this->configure('UD-42');

        $this->assertSame('test-server-key', Config::$serverKey);
        $this->assertSame('test-client-key', Config::$clientKey);
        $this->assertFalse(Config::$isProduction);
        $this->assertTrue(Config::$isSanitized);
        $this->assertFalse(Config::$is3ds);
        $this->assertSame('https://app.test/payment/notification', Config::$overrideNotifUrl);
        $this->assertNull(Config::$appendNotifUrl);
        $this->assertSame('UD-42', Config::$paymentIdempotencyKey);
        $this->assertSame([CURLOPT_HTTPHEADER => []], Config::$curlOptions);
    }

    public function test_local_notification_url_is_never_sent_to_midtrans(): void
    {
        config()->set('services.midtrans.notification_url', 'http://localhost/payment/notification');

        $this->configure('UD-43');

        $this->assertNull(Config::$overrideNotifUrl);
        $this->assertSame('UD-43', Config::$paymentIdempotencyKey);
    }

    public function test_curl_headers_are_reset_between_requests(): void
    {
        $this->configure('UD-44');
        Config::$curlOptions[CURLOPT_HTTPHEADER][] = 'X-Stale: header';

        $this->configure('UD-45');

        $this->assertSame([CURLOPT_HTTPHEADER => []], Config::$curlOptions);
        $this->assertSame('UD-45', Config::$paymentIdempotencyKey);
    }

    public function test_missing_server_key_is_rejected_before_any_charge(): void
    {
        config()->set('services.midtrans.server_key', '');

        $this->expectException(GatewayException::class);

        $this->configure('UD-46');
    }

    private function configure(string $orderId): void
    {
        $method = new ReflectionMethod(MidtransGateway::class, 'configure');
        $method->invoke(new MidtransGateway(), $orderId);
    }

    private function request(string $type): PaymentRequestData
    {
        return new PaymentRequestData(
            orderId: 'UD-42',
            grossAmount: 150000,
            customerName: 'Customer Test',
            customerEmail: 'customer@example.test',
            itemName: 'Aktivasi Contoh',
            finishUrl: 'https://app.test/finish',
            notificationUrl: 'https://app.test/payment/notification',
            invitationId: 7,
            paymentType: $type,
            expiryMinutes: 45,
        );
    }
}
