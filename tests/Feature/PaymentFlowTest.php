<?php

namespace Tests\Feature;

use App\Contracts\PaymentGateway;
use App\Data\PaymentRequestData;
use App\Data\PaymentResponseData;
use App\Enums\InvitationStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Invitation;
use App\Models\Template;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\TemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    private FakePaymentGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TemplateSeeder::class);

        config()->set('services.midtrans.server_key', 'test-server-key');
        config()->set('services.midtrans.client_key', 'test-client-key');
        config()->set('services.midtrans.notification_url', 'https://app.test/payment/notification');

        $this->gateway = new FakePaymentGateway();
        $this->app->instance(PaymentGateway::class, $this->gateway);
    }

    public function test_activation_and_payment_start_are_limited_to_the_owner(): void
    {
        $owner = $this->customer('owner@example.test');
        $other = $this->customer('other@example.test');
        $invitation = $this->createInvitation($owner);
        $startedAt = $invitation->preview_started_at?->toIso8601String();
        $expiresAt = $invitation->preview_expires_at?->toIso8601String();

        $this->actingAs($other)
            ->get(route('invitations.activation', $invitation))
            ->assertForbidden();

        $this->actingAs($owner)
            ->get(route('invitations.activation', $invitation))
            ->assertOk()
            ->assertSee('Rp' . number_format((int) $invitation->price, 0, ',', '.'))
            ->assertSee('Buat Pembayaran');

        $this->actingAs($owner)
            ->post(route('invitations.payment.start', $invitation))
            ->assertRedirect(route('invitations.payment.status', $invitation));

        $transaction = $invitation->transactions()->firstOrFail();
        $this->assertSame(1, count($this->gateway->requests));
        $this->assertSame($transaction->order_id, $this->gateway->requests[0]->orderId);
        $this->assertSame((int) $invitation->price, $this->gateway->requests[0]->grossAmount);
        $this->assertNotNull($transaction->snap_token);
        $this->assertSame('https://app.test/payment/notification', $this->gateway->requests[0]->notificationUrl);

        $invitation->refresh();
        $this->assertSame(InvitationStatus::PendingPayment, $invitation->status);
        $this->assertSame(PaymentStatus::Pending, $invitation->payment_status);
        $this->assertSame($startedAt, $invitation->preview_started_at?->toIso8601String());
        $this->assertSame($expiresAt, $invitation->preview_expires_at?->toIso8601String());

        $this->actingAs($owner)
            ->get(route('invitations.payment.status', $invitation))
            ->assertOk()
            ->assertSee($transaction->order_id)
            ->assertSee('bukan bukti pembayaran')
            ->assertDontSee('test-server-key');
    }

    public function test_valid_webhook_activates_invitation_once_and_is_idempotent(): void
    {
        $user = $this->customer();
        $invitation = $this->createInvitation($user);
        $transaction = $this->startPayment($user, $invitation);
        $payload = $this->signedPayload($transaction, 'settlement');

        $this->postJson(route('payment.notification'), $payload)
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('activated', true);

        $invitation->refresh();
        $transaction->refresh();
        $this->assertSame(InvitationStatus::Active, $invitation->status);
        $this->assertSame(PaymentStatus::Settlement, $invitation->payment_status);
        $this->assertNotNull($invitation->activated_at);
        $this->assertNotNull($invitation->published_at);
        $this->assertSame(1, $transaction->notification_count);
        $this->assertSame(1, $user->activityLogs()->where('action', 'invitation.activated')->count());

        $activatedAt = $invitation->activated_at?->toIso8601String();

        $this->postJson(route('payment.notification'), $payload)
            ->assertOk()
            ->assertJsonPath('activated', false);

        $invitation->refresh();
        $this->assertSame($activatedAt, $invitation->activated_at?->toIso8601String());
        $this->assertSame(2, $transaction->fresh()->notification_count);
        $this->assertSame(1, $user->activityLogs()->where('action', 'invitation.activated')->count());
    }

    public function test_webhook_rejects_invalid_signature_and_amount_mismatch(): void
    {
        $user = $this->customer();
        $invitation = $this->createInvitation($user);
        $transaction = $this->startPayment($user, $invitation);

        $invalid = $this->signedPayload($transaction, 'settlement');
        $invalid['signature_key'] = str_repeat('0', 128);

        $this->postJson(route('payment.notification'), $invalid)->assertForbidden();

        $mismatch = $this->signedPayload($transaction, 'settlement', 'accept', 999000);

        $this->postJson(route('payment.notification'), $mismatch)->assertBadRequest();

        $this->assertSame(InvitationStatus::PendingPayment, $invitation->fresh()->status);
        $this->assertSame(PaymentStatus::Pending, $invitation->fresh()->payment_status);
    }

    public function test_capture_challenge_waits_for_accept_before_activation(): void
    {
        $user = $this->customer();
        $invitation = $this->createInvitation($user);
        $transaction = $this->startPayment($user, $invitation);

        $this->postJson(route('payment.notification'), $this->signedPayload($transaction, 'capture', 'challenge'))
            ->assertOk()
            ->assertJsonPath('activated', false);

        $this->assertSame(InvitationStatus::PendingPayment, $invitation->fresh()->status);
        $this->assertSame(PaymentStatus::Pending, $invitation->fresh()->payment_status);

        $this->postJson(route('payment.notification'), $this->signedPayload($transaction, 'capture', 'accept'))
            ->assertOk()
            ->assertJsonPath('activated', true);

        $this->assertSame(InvitationStatus::Active, $invitation->fresh()->status);
        $this->assertSame(PaymentStatus::Paid, $invitation->fresh()->payment_status);
    }

    public function test_manual_status_check_activates_invitation_but_success_page_never_does(): void
    {
        $user = $this->customer();
        $invitation = $this->createInvitation($user);
        $transaction = $this->startPayment($user, $invitation);

        $this->gateway->statusResponses[$transaction->order_id] = [
            'gross_amount' => (string) $transaction->gross_amount,
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
            'transaction_id' => 'midtrans-status-check',
            'payment_type' => 'qris',
            'transaction_time' => now()->toIso8601String(),
        ];

        $this->actingAs($user)
            ->get(route('invitations.payment.status', $invitation))
            ->assertOk();

        $this->assertSame(InvitationStatus::PendingPayment, $invitation->fresh()->status);

        $this->actingAs($user)
            ->post(route('invitations.payment.check', $invitation))
            ->assertRedirect();

        $this->assertSame(InvitationStatus::Active, $invitation->fresh()->status);
        $this->assertSame(PaymentStatus::Settlement, $invitation->fresh()->payment_status);
    }

    public function test_expired_preview_shows_only_a_locked_activation_popup(): void
    {
        $user = $this->customer();
        $invitation = $this->createInvitation($user);

        $this->travel(11)->hours();
        Cache::flush();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Masa pratinjau sudah berakhir.');

        $this->assertSame(InvitationStatus::Expired, $invitation->fresh()->status);

        $this->actingAs($user)
            ->patchJson(route('invitations.update', $invitation), ['title' => 'Terlambat'])
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('invitation.show', $invitation->slug, false))
            ->assertOk()
            ->assertSee('Aktifkan Undangan Digital Anda!')
            ->assertSee('Masa pratinjau sudah berakhir.')
            ->assertSee('data-ud-expired', false)
            ->assertDontSee('window.UD_CONFIG')
            ->assertDontSee('data-ud-share-bar', false)
            ->assertDontSee('aria-label="Tutup pengingat"', false)
            ->assertDontSee('Nanti Saja');

        $this->actingAs($user)
            ->get(route('invitations.activation', $invitation))
            ->assertOk();
    }

    public function test_activation_popup_is_shown_on_invitation_not_in_editor(): void
    {
        $user = $this->customer();
        $invitation = $this->createInvitation($user);

        $this->actingAs($user)
            ->get(route('invitations.edit', $invitation))
            ->assertOk()
            ->assertDontSee('Aktifkan Undangan Digital Anda!')
            ->assertDontSee('data-preview-url', false);

        foreach (range(1, 2) as $attempt) {
            $this->actingAs($user)
                ->get(route('invitation.show', $invitation->slug, false))
                ->assertOk()
                ->assertSee('Aktifkan Undangan Digital Anda!')
                ->assertSee('Sisa waktu preview')
                ->assertSee('Harga aktivasi')
                ->assertSee('data-ud-activation-gate', false)
                ->assertSee('window.UD_CONFIG');
        }
    }

    public function test_payment_status_page_shows_core_api_instructions_and_reference(): void
    {
        $user = $this->customer();
        $invitation = $this->createInvitation($user);

        $this->gateway->response = new PaymentResponseData(
            paymentType: 'bca_va',
            paymentReference: '1234567890123456',
            expiryTime: now('Asia/Jakarta')->addMinutes(30)->toIso8601String(),
            raw: [
                'status_code' => '201',
                'transaction_status' => 'pending',
                'va_numbers' => [['bank' => 'bca', 'va_number' => '1234567890123456']],
            ],
            status: 'pending',
        );

        $this->actingAs($user)
            ->post(route('invitations.payment.start', $invitation), ['payment_method' => 'bca_va'])
            ->assertRedirect(route('invitations.payment.status', $invitation));

        $this->actingAs($user)
            ->get(route('invitations.payment.status', $invitation))
            ->assertOk()
            ->assertSee('Instruksi Pembayaran')
            ->assertSee('BCA Virtual Account')
            ->assertSee('1234567890123456')
            ->assertSee('Berlaku sampai')
            ->assertSee('WITA')
            ->assertSee('Sisa waktu pembayaran')
            ->assertSee('data-payment-countdown', false)
            ->assertSee('Masukkan nomor Virtual Account')
            ->assertDontSee('test-server-key');
    }

    public function test_repeating_checkout_reuses_the_pending_order_instead_of_charging_twice(): void
    {
        $user = $this->customer();
        $invitation = $this->createInvitation($user);

        $first = $this->startPayment($user, $invitation);
        $second = $this->startPayment($user, $invitation);

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->order_id, $second->order_id);
        $this->assertSame(1, count($this->gateway->requests));
        $this->assertSame(1, $invitation->transactions()->count());
    }

    public function test_customer_facing_payment_pages_do_not_name_the_provider(): void
    {
        $user = $this->customer();
        $invitation = $this->createInvitation($user);

        $this->actingAs($user)
            ->get(route('invitations.activation', $invitation))
            ->assertOk()
            ->assertSee('BCA Virtual Account')
            ->assertDontSee('Midtrans');

        $this->startPayment($user, $invitation);

        $this->actingAs($user)
            ->get(route('invitations.payment.status', $invitation))
            ->assertOk()
            ->assertDontSee('Midtrans');
    }

    public function test_virtual_account_can_be_selected_on_the_first_attempt(): void
    {
        $user = $this->customer();
        $invitation = $this->createInvitation($user);

        $this->actingAs($user)
            ->post(route('invitations.payment.start', $invitation), ['payment_method' => 'bni_va'])
            ->assertRedirect(route('invitations.payment.status', $invitation));

        $transaction = $invitation->transactions()->firstOrFail();

        $this->assertSame('bni_va', $transaction->payment_type);
        $this->assertSame('bni_va', $this->gateway->requests[0]->paymentType);
        $this->assertSame('BNI Virtual Account', $transaction->payment_label);
    }

    public function test_virtual_account_charge_uses_core_api_payload_and_keeps_canonical_method(): void
    {
        $user = $this->customer();
        $invitation = $this->createInvitation($user);

        $this->actingAs($user)
            ->post(route('invitations.payment.start', $invitation), ['payment_method' => 'bca_va'])
            ->assertRedirect(route('invitations.payment.status', $invitation));

        $transaction = $invitation->transactions()->firstOrFail();
        $payload = $this->gateway->requests[0]->toMidtransPayload();

        $this->assertSame('bank_transfer', $payload['payment_type']);
        $this->assertSame(['bank' => 'bca'], $payload['bank_transfer']);
        $this->assertArrayNotHasKey('va_numbers', $payload);
        $this->assertSame('bca_va', $transaction->payment_type);

        $this->postJson(
            route('payment.notification'),
            $this->signedPayload($transaction, 'settlement', 'accept', null, 'bank_transfer'),
        )
            ->assertOk()
            ->assertJsonPath('activated', true);

        $transaction->refresh();
        $this->assertSame('bca_va', $transaction->payment_type);
        $this->assertSame('BCA Virtual Account', $transaction->payment_label);
        $this->assertSame('bank_transfer', $transaction->midtrans_response['payment_type']);
    }

    public function test_application_timezone_is_wita(): void
    {
        $this->assertSame('Asia/Makassar', config('app.timezone'));
        $this->assertSame('Asia/Makassar', now()->timezoneName);
    }

    public function test_changing_payment_method_cancels_old_order_and_creates_selected_method(): void
    {
        $user = $this->customer();
        $invitation = $this->createInvitation($user);

        $first = $this->startPayment($user, $invitation);
        $this->assertSame('qris', $first->payment_type);

        $this->actingAs($user)
            ->post(route('invitations.payment.start', $invitation), ['payment_method' => 'bca_va'])
            ->assertRedirect(route('invitations.payment.status', $invitation));

        $replacement = $invitation->transactions()->where('payment_type', 'bca_va')->firstOrFail();

        $this->assertNotSame($first->id, $replacement->id);
        $this->assertSame(PaymentStatus::Pending, $replacement->transaction_status);
        $this->assertSame('BCA Virtual Account', $replacement->payment_label);
        $this->assertCount(2, $this->gateway->requests);
        $this->assertSame('bca_va', $this->gateway->requests[1]->paymentType);
        $this->assertContains($first->order_id, $this->gateway->cancelled);
        $this->assertSame(2, $invitation->transactions()->count());

        $first->refresh();
        $this->assertSame(PaymentStatus::Cancel, $first->transaction_status);
        $this->assertSame($replacement->order_id, $first->replaced_by_order_id);
        $this->assertTrue($first->wasReplacedForMethodChange());
    }

    public function test_late_settlement_for_replaced_order_does_not_activate_invitation(): void
    {
        $user = $this->customer();
        $invitation = $this->createInvitation($user);

        $old = $this->startPayment($user, $invitation);

        $this->actingAs($user)
            ->post(route('invitations.payment.start', $invitation), ['payment_method' => 'bca_va'])
            ->assertRedirect(route('invitations.payment.status', $invitation));

        $this->postJson(route('payment.notification'), $this->signedPayload($old, 'settlement'))
            ->assertOk()
            ->assertJsonPath('activated', false)
            ->assertJsonPath('stale', true);

        $this->assertSame(InvitationStatus::PendingPayment, $invitation->fresh()->status);
        $this->assertSame(PaymentStatus::Cancel, $old->fresh()->transaction_status);
    }

    private function startPayment(User $user, Invitation $invitation): Transaction
    {
        $this->actingAs($user)
            ->post(route('invitations.payment.start', $invitation))
            ->assertRedirect(route('invitations.payment.status', $invitation));

        return $invitation->transactions()->firstOrFail();
    }

    /** @return array<string, string> */
    private function signedPayload(
        Transaction $transaction,
        string $status,
        ?string $fraudStatus = 'accept',
        ?int $amount = null,
        ?string $providerType = null,
    ): array {
        $grossAmount = (string) ($amount ?? $transaction->gross_amount);
        $statusCode = match ($status) {
            'pending' => '201',
            'deny' => '202',
            'expire', 'cancel' => '400',
            default => '200',
        };

        $payload = [
            'order_id' => $transaction->order_id,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'transaction_status' => $status,
            'transaction_time' => now()->toIso8601String(),
            'transaction_id' => 'midtrans-' . $transaction->id,
            'payment_type' => $providerType ?? 'qris',
        ];

        if ($fraudStatus !== null) {
            $payload['fraud_status'] = $fraudStatus;
        }

        $payload['signature_key'] = hash('sha512', implode('', [
            $payload['order_id'],
            $payload['status_code'],
            $grossAmount,
            'test-server-key',
        ]));

        return $payload;
    }

    private function createInvitation(User $user): Invitation
    {
        $this->actingAs($user)->post(route('templates.select', Template::firstWhere('slug', 'tema1')));

        $invitation = Invitation::where('user_id', $user->id)->latest('id')->firstOrFail();
        $invitation->groom()->create(['full_name' => 'Ahmad Fauzi']);
        $invitation->bride()->create(['full_name' => 'Siti Aisyah']);
        $invitation->events()->create([
            'event_type' => \App\Enums\EventType::Akad,
            'title' => 'Akad Nikah',
            'event_date' => now()->addDays(30)->toDateString(),
            'start_time' => '08:00:00',
        ]);

        return $invitation;
    }

    private function customer(string $email = 'customer@example.test'): User
    {
        return User::create([
            'name' => 'Customer Test',
            'email' => $email,
            'google_id' => 'google-' . $email,
            'role' => Role::Customer,
            'status' => UserStatus::Active,
        ]);
    }
}

class FakePaymentGateway implements PaymentGateway
{
    /** @var array<int, PaymentRequestData> */
    public array $requests = [];

    /** @var array<string, array<string, mixed>> */
    public array $statusResponses = [];

    /** @var array<int, string> */
    public array $cancelled = [];

    public ?PaymentResponseData $response = null;

    public function createTransaction(PaymentRequestData $request): PaymentResponseData
    {
        $this->requests[] = $request;

        if ($this->response instanceof PaymentResponseData) {
            return $this->response;
        }

        return new PaymentResponseData(
            token: 'snap-token-' . $request->orderId,
            redirectUrl: 'https://midtrans.test/' . $request->orderId,
            raw: ['token' => 'snap-token-' . $request->orderId],
        );
    }

    public function fetchStatus(string $orderId): array
    {
        return $this->statusResponses[$orderId] ?? [
            'order_id' => $orderId,
            'transaction_status' => 'pending',
        ];
    }

    public function cancel(string $orderId): void
    {
        $this->cancelled[] = $orderId;
    }
}
