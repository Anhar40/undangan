<?php

namespace Tests\Feature;

use App\Contracts\PaymentGateway;
use App\Data\PaymentRequestData;
use App\Data\PaymentResponseData;
use App\Enums\InvitationStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Exceptions\Payment\GatewayException;
use App\Models\ActivityLog;
use App\Models\Invitation;
use App\Models\Template;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Invitation\InvitationCreationService;
use Database\Seeders\TemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentEdgeCaseTest extends TestCase
{
    use RefreshDatabase;

    private EdgeCasePaymentGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TemplateSeeder::class);

        config()->set('services.midtrans.server_key', 'test-server-key');
        config()->set('services.midtrans.client_key', 'test-client-key');
        config()->set('services.midtrans.notification_url', 'https://app.test/payment/notification');

        $this->gateway = new EdgeCasePaymentGateway();
        $this->app->instance(PaymentGateway::class, $this->gateway);
    }

    public function test_all_midtrans_statuses_are_mapped_and_only_successful_ones_activate(): void
    {
        $cases = [
            ['settlement', 'accept', PaymentStatus::Settlement, true],
            ['capture', 'accept', PaymentStatus::Paid, true],
            ['capture', 'challenge', PaymentStatus::Pending, false],
            ['expire', null, PaymentStatus::Expire, false],
            ['cancel', null, PaymentStatus::Cancel, false],
            ['deny', null, PaymentStatus::Deny, false],
            ['refund', null, PaymentStatus::Refund, false],
            ['status_yang_tidak_dikenal', null, PaymentStatus::Pending, false],
        ];

        foreach ($cases as $index => [$midtransStatus, $fraudStatus, $expected, $shouldActivate]) {
            $user = $this->customer('edge-map-' . $index . '@example.test');
            $invitation = $this->createInvitation($user);
            $transaction = $this->startPayment($user, $invitation);

            $this->postJson(
                route('payment.notification'),
                $this->signedPayload($transaction, $midtransStatus, $fraudStatus),
            )
                ->assertOk()
                ->assertJsonPath('payment_status', $expected->value)
                ->assertJsonPath('activated', $shouldActivate);

            $fresh = $invitation->fresh();

            $this->assertSame($expected, $fresh->payment_status, $midtransStatus);
            $this->assertSame(
                $shouldActivate ? InvitationStatus::Active : InvitationStatus::PendingPayment,
                $fresh->status,
                $midtransStatus,
            );
            $this->assertSame(1, $transaction->fresh()->notification_count, $midtransStatus);
        }
    }

    public function test_missing_server_key_rejects_a_correctly_shaped_notification(): void
    {
        config()->set('services.midtrans.server_key', '');

        $user = $this->customer('edge-no-key@example.test');
        $invitation = $this->createInvitation($user);
        $transaction = $this->startPayment($user, $invitation);

        $payload = $this->signedPayload($transaction, 'settlement', 'accept', 'server-key-lama');

        $this->postJson(route('payment.notification'), $payload)->assertForbidden();

        $this->assertSame(InvitationStatus::PendingPayment, $invitation->fresh()->status);
        $this->assertSame(PaymentStatus::Pending, $invitation->fresh()->payment_status);
        $this->assertSame(0, $transaction->fresh()->notification_count);
    }

    public function test_gateway_failures_never_create_a_transaction_or_change_invitation_status(): void
    {
        $user = $this->customer('edge-gateway@example.test');
        $invitation = $this->createInvitation($user);
        $url = route('invitations.payment.start', $invitation);
        $back = route('invitations.activation', $invitation);

        $this->app->instance(PaymentGateway::class, new EdgeCasePaymentGateway('throw-gateway'));

        $this->actingAs($user)
            ->from($back)
            ->post($url)
            ->assertRedirect($back)
            ->assertSessionHas('error', 'Sistem pembayaran sedang tidak dapat dihubungi.');

        $this->assertSame(0, $invitation->transactions()->count());
        $this->assertSame(InvitationStatus::Draft, $invitation->fresh()->status);

        $this->app->instance(PaymentGateway::class, new EdgeCasePaymentGateway('empty'));

        $this->actingAs($user)
            ->from($back)
            ->post($url)
            ->assertRedirect($back)
            ->assertSessionHas('error', 'Respons sistem pembayaran tidak valid.');

        $this->app->instance(PaymentGateway::class, new EdgeCasePaymentGateway('throw-runtime'));

        $this->actingAs($user)
            ->from($back)
            ->post($url)
            ->assertRedirect($back)
            ->assertSessionHas('error', 'Gagal membuat transaksi pembayaran.');

        $this->assertSame(0, $invitation->transactions()->count());
        $this->assertSame(InvitationStatus::Draft, $invitation->fresh()->status);
        $this->assertSame(PaymentStatus::Pending, $invitation->fresh()->payment_status);
    }

    public function test_recheck_rejects_gateway_failure_and_amount_mismatch_without_activation(): void
    {
        $user = $this->customer('edge-recheck@example.test');
        $invitation = $this->createInvitation($user);
        $transaction = $this->startPayment($user, $invitation);
        $url = route('invitations.payment.check', $invitation);
        $back = route('invitations.payment.status', $invitation);

        $this->app->instance(PaymentGateway::class, new EdgeCasePaymentGateway('status-fail'));

        $this->actingAs($user)
            ->from($back)
            ->post($url)
            ->assertRedirect($back)
            ->assertSessionHas('error', 'Gagal memeriksa status transaksi.');

        $this->assertSame(InvitationStatus::PendingPayment, $invitation->fresh()->status);

        $this->app->instance(PaymentGateway::class, $this->gateway);
        $this->gateway->statusResponses[$transaction->order_id] = [
            'gross_amount' => 999999,
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
        ];

        $this->actingAs($user)
            ->from($back)
            ->post($url)
            ->assertRedirect($back)
            ->assertSessionHas('error', 'Nominal pembayaran tidak sesuai.');

        $this->assertSame(InvitationStatus::PendingPayment, $invitation->fresh()->status);
        $this->assertSame(PaymentStatus::Pending, $invitation->fresh()->payment_status);
        $this->assertSame(0, ActivityLog::where('action', 'invitation.activated')->count());
    }

    public function test_success_redirect_parameters_never_activate_an_invitation(): void
    {
        $user = $this->customer('edge-redirect@example.test');
        $invitation = $this->createInvitation($user);
        $transaction = $this->startPayment($user, $invitation);

        $query = http_build_query([
            'transaction_status' => 'settlement',
            'status_code' => '200',
            'gross_amount' => $transaction->gross_amount,
            'order_id' => $transaction->order_id,
            'signature_key' => str_repeat('a', 128),
        ]);

        $this->actingAs($user)
            ->get(route('invitations.payment.status', $invitation) . '?' . $query)
            ->assertOk()
            ->assertSee($transaction->order_id);

        $this->assertSame(InvitationStatus::PendingPayment, $invitation->fresh()->status);
        $this->assertSame(PaymentStatus::Pending, $invitation->fresh()->payment_status);
        $this->assertSame(0, ActivityLog::where('action', 'invitation.activated')->count());
    }

    public function test_payment_history_is_recorded_and_repeated_notifications_stay_idempotent(): void
    {
        $user = $this->customer('edge-history@example.test');
        $invitation = $this->createInvitation($user);
        $transaction = $this->startPayment($user, $invitation);

        $payload = $this->signedPayload($transaction, 'settlement');
        $payload['transaction_id'] = 'midtrans-edge-history';
        $payload['payment_type'] = 'qris';

        $this->postJson(route('payment.notification'), $payload)
            ->assertOk()
            ->assertJsonPath('activated', true);

        $transaction->refresh();

        $this->assertSame(PaymentStatus::Settlement, $transaction->transaction_status);
        $this->assertSame('midtrans-edge-history', $transaction->midtrans_transaction_id);
        $this->assertSame('qris', $transaction->payment_type);
        $this->assertNotNull($transaction->paid_at);
        $this->assertNotNull($transaction->verified_at);
        $this->assertSame($payload['order_id'], $transaction->midtrans_response['order_id']);
        $this->assertSame(1, $transaction->notification_count);

        $paidAt = $transaction->paid_at?->toIso8601String();
        $activatedAt = $invitation->fresh()->activated_at?->toIso8601String();

        $this->travel(5)->minutes();

        $this->postJson(route('payment.notification'), $payload)
            ->assertOk()
            ->assertJsonPath('activated', false);

        $this->assertSame($paidAt, $transaction->fresh()->paid_at?->toIso8601String());
        $this->assertSame($activatedAt, $invitation->fresh()->activated_at?->toIso8601String());
        $this->assertSame(2, $transaction->fresh()->notification_count);
        $this->assertSame(1, ActivityLog::where('action', 'invitation.activated')->count());
    }

    public function test_late_pending_notification_does_not_downgrade_a_settlement(): void
    {
        $user = $this->customer('edge-stale@example.test');
        $invitation = $this->createInvitation($user);
        $transaction = $this->startPayment($user, $invitation);

        $this->postJson(route('payment.notification'), $this->signedPayload($transaction, 'settlement'))
            ->assertOk()
            ->assertJsonPath('activated', true)
            ->assertJsonPath('stale', false);

        $this->postJson(route('payment.notification'), $this->signedPayload($transaction, 'pending'))
            ->assertOk()
            ->assertJsonPath('payment_status', PaymentStatus::Settlement->value)
            ->assertJsonPath('activated', false)
            ->assertJsonPath('stale', true);

        $this->assertSame(InvitationStatus::Active, $invitation->fresh()->status);
        $this->assertSame(PaymentStatus::Settlement, $invitation->fresh()->payment_status);
        $this->assertSame(2, $transaction->fresh()->notification_count);
    }

    public function test_refund_suspends_public_access_and_later_settlement_cannot_reactivate(): void
    {
        $user = $this->customer('edge-refund@example.test');
        $invitation = $this->createInvitation($user);
        $transaction = $this->startPayment($user, $invitation);

        $this->postJson(route('payment.notification'), $this->signedPayload($transaction, 'settlement'))
            ->assertOk()
            ->assertJsonPath('activated', true);

        $this->postJson(route('payment.notification'), $this->signedPayload($transaction, 'refund', null))
            ->assertOk()
            ->assertJsonPath('payment_status', PaymentStatus::Refund->value)
            ->assertJsonPath('access_revoked', true);

        $this->assertSame(InvitationStatus::Suspended, $invitation->fresh()->status);
        $this->assertSame(PaymentStatus::Refund, $invitation->fresh()->payment_status);

        $this->postJson(route('payment.notification'), $this->signedPayload($transaction, 'settlement'))
            ->assertOk()
            ->assertJsonPath('activated', false)
            ->assertJsonPath('stale', true);

        $this->assertSame(InvitationStatus::Suspended, $invitation->fresh()->status);
        $this->assertSame(PaymentStatus::Refund, $invitation->fresh()->payment_status);
    }

    public function test_suspended_invitation_is_not_reactivated_by_a_new_settlement_notification(): void
    {
        $user = $this->customer('edge-suspended@example.test');
        $invitation = $this->createInvitation($user);
        $transaction = $this->startPayment($user, $invitation);

        $invitation->update(['status' => InvitationStatus::Suspended]);

        $this->postJson(route('payment.notification'), $this->signedPayload($transaction, 'settlement'))
            ->assertOk()
            ->assertJsonPath('payment_status', PaymentStatus::Settlement->value)
            ->assertJsonPath('activated', false);

        $this->assertSame(InvitationStatus::Suspended, $invitation->fresh()->status);
        $this->assertSame(1, ActivityLog::where('action', 'payment.suspended_not_reactivated')->count());
    }

    public function test_amount_comparison_accepts_decimal_zero_cents_and_rejects_fractional_cents(): void
    {
        $user = $this->customer('edge-decimal@example.test');
        $invitation = $this->createInvitation($user);
        $transaction = $this->startPayment($user, $invitation);

        $this->postJson(
            route('payment.notification'),
            $this->signedPayload($transaction, 'settlement', 'accept', null, bcadd((string) $transaction->gross_amount, '0.00', 2)),
        )
            ->assertOk()
            ->assertJsonPath('activated', true);

        $otherUser = $this->customer('edge-decimal-bad@example.test');
        $otherInvitation = $this->createInvitation($otherUser);
        $otherTransaction = $this->startPayment($otherUser, $otherInvitation);

        $this->postJson(
            route('payment.notification'),
            $this->signedPayload($otherTransaction, 'settlement', 'accept', null, bcadd((string) $otherTransaction->gross_amount, '0.01', 2)),
        )->assertBadRequest();

        $this->assertSame(InvitationStatus::PendingPayment, $otherInvitation->fresh()->status);
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
        ?string $serverKey = null,
        ?string $amount = null,
    ): array {
        $grossAmount = $amount ?? (string) $transaction->gross_amount;
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
            'transaction_id' => 'midtrans-edge-' . $transaction->id,
            'payment_type' => 'qris',
        ];

        if ($fraudStatus !== null) {
            $payload['fraud_status'] = $fraudStatus;
        }

        $payload['signature_key'] = hash('sha512', implode('', [
            $payload['order_id'],
            $payload['status_code'],
            $grossAmount,
            $serverKey ?? 'test-server-key',
        ]));

        return $payload;
    }

    private function createInvitation(User $user): Invitation
    {
        $invitation = app(InvitationCreationService::class)
            ->create(Template::firstWhere('slug', 'tema1'), $user);

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

    private function customer(string $email): User
    {
        return User::create([
            'name' => 'Customer Edge Case',
            'email' => $email,
            'google_id' => 'google-' . $email,
            'role' => Role::Customer,
            'status' => UserStatus::Active,
        ]);
    }
}

class EdgeCasePaymentGateway implements PaymentGateway
{
    /** @var array<string, array<string, mixed>> */
    public array $statusResponses = [];

    public function __construct(private readonly string $mode = 'ok') {}

    public function createTransaction(PaymentRequestData $request): PaymentResponseData
    {
        if ($this->mode === 'throw-gateway') {
            throw new GatewayException('Sistem pembayaran sedang tidak dapat dihubungi.');
        }

        if ($this->mode === 'throw-runtime') {
            throw new \RuntimeException('Koneksi sistem pembayaran terputus.');
        }

        if ($this->mode === 'empty') {
            return new PaymentResponseData(token: '', redirectUrl: '', raw: []);
        }

        return new PaymentResponseData(
            token: 'edge-token-' . $request->orderId,
            redirectUrl: 'https://midtrans.test/' . $request->orderId,
            raw: ['token' => 'edge-token-' . $request->orderId],
        );
    }

    public function fetchStatus(string $orderId): array
    {
        if ($this->mode === 'status-fail') {
            throw new GatewayException('Gagal memeriksa status transaksi.');
        }

        return $this->statusResponses[$orderId] ?? [
            'order_id' => $orderId,
            'gross_amount' => 150000,
            'transaction_status' => 'pending',
        ];
    }

    public function cancel(string $orderId): void
    {
        // No external call in the fake gateway.
    }
}
