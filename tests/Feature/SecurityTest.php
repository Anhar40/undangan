<?php

namespace Tests\Feature;

use App\Enums\InvitationStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Invitation;
use App\Models\Setting;
use App\Models\Template;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Invitation\InvitationCreationService;
use Database\Seeders\TemplateSeeder;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TemplateSeeder::class);
        Cache::flush();
    }

    public function test_csrf_protection_blocks_state_changing_requests_but_exempts_signed_webhook(): void
    {
        config()->set('services.midtrans.server_key', 'test-server-key');

        $user = $this->customer('csrf-owner@example.test');
        $invitation = $this->createInvitation($user);
        $transaction = $this->transaction($invitation, 'UD-CSRF-1');

        $this->app->instance(ValidateCsrfToken::class, new StrictValidateCsrfToken(
            $this->app,
            $this->app->make(Encrypter::class),
        ));

        $this->actingAs($user)
            ->post(route('invitations.payment.start', $invitation))
            ->assertStatus(419);

        $this->postJson(route('payment.notification'), $this->signedPayload($transaction, 'settlement'))
            ->assertOk()
            ->assertJsonPath('activated', true);

        $this->assertSame(InvitationStatus::Active, $invitation->fresh()->status);

        $this->post(route('public.greetings.store', $invitation->slug, false), [
            'name' => 'Tanpa Token',
            'message' => 'Seharusnya ditolak.',
        ])->assertStatus(419);

        $this->assertSame(0, $invitation->greetings()->count());
    }

    public function test_role_and_ownership_middleware_protect_customer_and_admin_routes(): void
    {
        $owner = $this->customer('security-owner@example.test');
        $other = $this->customer('security-other@example.test');
        $admin = $this->admin();
        $invitation = $this->createInvitation($owner);

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));

        $this->actingAs($other)
            ->get(route('admin.dashboard'))
            ->assertForbidden();

        foreach ([
            ['get', route('invitations.edit', $invitation)],
            ['patch', route('invitations.update', $invitation)],
            ['get', route('invitations.activation', $invitation)],
            ['post', route('invitations.payment.start', $invitation)],
            ['get', route('invitations.payment.status', $invitation)],
            ['post', route('invitations.payment.check', $invitation)],
            ['delete', route('invitations.destroy', $invitation)],
        ] as [$method, $url]) {
            $this->actingAs($other)->{$method}($url)->assertForbidden();
        }

        foreach ([
            ['patch', route('invitations.update', $invitation)],
            ['post', route('invitations.payment.start', $invitation)],
            ['delete', route('invitations.destroy', $invitation)],
        ] as [$method, $url]) {
            $this->actingAs($admin)->{$method}($url)->assertForbidden();
        }

        $this->assertSame($invitation->title, $invitation->fresh()->title);
        $this->assertSame(0, $invitation->transactions()->count());
        $this->assertNotSoftDeleted('invitations', ['id' => $invitation->id]);
    }

    public function test_google_callback_requires_verified_email_before_creating_a_session(): void
    {
        config()->set('services.google.client_id', 'google-client');
        config()->set('services.google.client_secret', 'google-secret');

        $this->fakeGoogleUser([
            'id' => 'google-unverified',
            'name' => 'Belum Terverifikasi',
            'email' => 'unverified@example.test',
            'user' => ['verified_email' => false],
        ]);

        $this->get(route('google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Email Google belum terverifikasi.');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_google_callback_creates_a_verified_customer_and_logs_the_login(): void
    {
        config()->set('services.google.client_id', 'google-client');
        config()->set('services.google.client_secret', 'google-secret');

        $this->fakeGoogleUser([
            'id' => 'google-customer-oauth',
            'name' => 'Pelanggan OAuth',
            'email' => 'oauth-customer@example.test',
            'avatar' => 'https://google.test/avatar.png',
            'user' => ['verified_email' => true],
        ]);

        $this->get(route('google.callback'))->assertRedirect(route('dashboard'));

        $user = User::where('email', 'oauth-customer@example.test')->firstOrFail();

        $this->assertSame(Role::Customer, $user->role);
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertSame('google-customer-oauth', $user->google_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'login',
        ]);
    }

    public function test_super_admin_role_is_promoted_only_from_configured_email(): void
    {
        config()->set('services.google.client_id', 'google-client');
        config()->set('services.google.client_secret', 'google-secret');
        Setting::set('super_admin_email', 'promoted-admin@example.test');

        $existing = User::create([
            'name' => 'Calon Admin',
            'email' => 'promoted-admin@example.test',
            'google_id' => 'google-old-id',
            'role' => Role::Customer,
            'status' => UserStatus::Active,
        ]);

        $this->fakeGoogleUser([
            'id' => 'google-promoted-admin',
            'name' => 'Admin Terverifikasi',
            'email' => 'promoted-admin@example.test',
            'user' => ['verified_email' => true],
        ]);

        $this->get(route('google.callback'))->assertRedirect(route('dashboard'));

        $this->assertSame(Role::SuperAdmin, $existing->fresh()->role);
        $this->assertSame('google-promoted-admin', $existing->fresh()->google_id);
        $this->assertAuthenticatedAs($existing->fresh());
    }

    public function test_public_rendering_escapes_user_supplied_html_and_script(): void
    {
        $payload = '<script>window.__XSS_PROBE__=1</script>';
        $user = $this->customer('xss-owner@example.test');
        $invitation = $this->createInvitation($user);

        $invitation->update(['title' => 'Undangan ' . $payload]);
        $invitation->groom()->create(['full_name' => 'Groom ' . $payload]);
        $invitation->invitationSettings()->updateOrCreate([], [
            'custom_settings' => ['invitation_message' => 'Pesan ' . $payload],
        ]);
        $this->activate($invitation);

        $this->get(route('invitation.show', $invitation->slug, false) . '?to=' . rawurlencode($payload))
            ->assertOk()
            ->assertDontSee($payload, false)
            ->assertSee('&lt;script&gt;window.__XSS_PROBE__=1&lt;/script&gt;', false);
    }

    public function test_malformed_slugs_and_sql_like_input_cannot_reach_invitation_queries(): void
    {
        $this->get('/u/' . rawurlencode("' OR 1=1 --"))->assertNotFound();
        $this->get('/u/' . rawurlencode('<script>alert(1)</script>'))->assertNotFound();

        $this->post('/u/' . rawurlencode("' OR 1=1 --") . '/greetings', [
            'name' => 'Penyusup',
            'message' => 'Seharusnya tidak sampai.',
        ])->assertNotFound();

        $user = $this->customer('sql-owner@example.test');
        $invitation = $this->createInvitation($user);
        $this->activate($invitation);

        $sqlLikeName = "'); DROP TABLE invitations; --";

        $this->post(route('public.greetings.store', $invitation->slug, false), [
            'name' => $sqlLikeName,
            'message' => 'Tetap tersimpan sebagai teks.',
        ])->assertRedirect();

        $this->assertDatabaseHas('invitation_greetings', [
            'invitation_id' => $invitation->id,
            'guest_name' => $sqlLikeName,
        ]);
        $this->assertDatabaseHas('invitations', ['id' => $invitation->id]);
    }

    public function test_public_response_endpoints_are_rate_limited(): void
    {
        Cache::flush();

        $user = $this->customer('rate-owner@example.test');
        $invitation = $this->createInvitation($user);
        $this->activate($invitation);

        $url = route('public.greetings.store', $invitation->slug, false);

        for ($i = 0; $i < 20; $i++) {
            $this->post($url, [
                'name' => 'Tamu ' . $i,
                'message' => 'Ucapan ke-' . $i,
            ])->assertRedirect();
        }

        $this->post($url, [
            'name' => 'Tamu ke-21',
            'message' => 'Seharusnya ditolak.',
        ])->assertStatus(429);

        $this->assertSame(20, $invitation->greetings()->count());
    }

    public function test_upload_validation_rejects_executable_files(): void
    {
        Storage::fake('public');

        $user = $this->customer('upload-owner@example.test');
        $invitation = $this->createInvitation($user);

        $this->actingAs($user)
            ->patch(route('invitations.update', $invitation), [
                'title' => 'Undangan Upload Aman',
                'photos' => [
                    'groom' => UploadedFile::fake()->create('shell.php', 10, 'application/x-php'),
                ],
            ])
            ->assertSessionHasErrors('photos.groom');

        $this->assertSame([], Storage::disk('public')->allFiles('invitations/' . $invitation->id));
    }

    public function test_midtrans_server_key_is_never_rendered_to_the_browser(): void
    {
        $secret = 'SERVER_KEY_SANGAT_RAHASIA_9f3a7c';
        config()->set('services.midtrans.server_key', $secret);

        $user = $this->customer('secret-owner@example.test');
        $draft = $this->createInvitation($user);
        $active = $this->createInvitation($user);
        $this->activate($active);

        $this->transaction($draft, 'UD-SECRET-1');

        $this->get(route('login'))->assertOk()->assertDontSee($secret);
        $this->get(route('invitation.show', $active->slug, false))->assertOk()->assertDontSee($secret);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee($secret);

        $this->actingAs($user)
            ->get(route('invitations.edit', $draft))
            ->assertOk()
            ->assertDontSee($secret);

        $this->actingAs($user)
            ->get(route('invitations.activation', $draft))
            ->assertOk()
            ->assertDontSee($secret);

        $this->actingAs($user)
            ->get(route('invitations.payment.status', $draft))
            ->assertOk()
            ->assertDontSee($secret);
    }

    public function test_share_rejects_external_hosts_but_accepts_this_applications_url(): void
    {
        $user = $this->customer('share-owner@example.test');
        $invitation = $this->createInvitation($user);
        $invitation->groom()->create(['full_name' => 'Ahmad Rahman']);
        $invitation->bride()->create(['full_name' => 'Siti Aminah']);
        $this->activate($invitation);

        $this->get(route('share.index', [
            'uri' => 'https://evil.example/u/' . $invitation->slug,
        ]))->assertNotFound();

        $this->get(route('share.index', [
            'uri' => '//evil.example/u/' . $invitation->slug,
        ]))->assertNotFound();

        $this->get(route('share.index', ['uri' => $invitation->publicUrl()]))
            ->assertOk()
            ->assertSee('Ahmad Rahman & Siti Aminah')
            ->assertDontSee('evil.example');
    }

    public function test_font_family_is_limited_to_configured_font_stacks(): void
    {
        $user = $this->customer('font-owner@example.test');
        $invitation = $this->createInvitation($user);

        $this->actingAs($user)
            ->patchJson(route('invitations.update', $invitation), [
                'settings' => ['font_family' => '</style><script>alert(1)</script>'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('settings.font_family');

        $this->actingAs($user)
            ->patchJson(route('invitations.update', $invitation), [
                'settings' => ['font_family' => 'editorial'],
            ])
            ->assertOk();

        $this->assertSame('editorial', $invitation->fresh()->invitationSettings->font_family);

        $html = (string) $this->get(route('invitation.show', $invitation->slug, false))->getContent();

        // Template typography is authoritative: a valid theme value is stored
        // for the editor but never injected as a global CSS override.
        $this->assertStringNotContainsString("font-family:'Cormorant Garamond', Georgia, serif", $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function test_webhook_does_not_store_signature_and_accepts_settlement_with_non_200_code(): void
    {
        config()->set('services.midtrans.server_key', 'test-server-key');

        $user = $this->customer('webhook-owner@example.test');
        $invitation = $this->createInvitation($user);
        $transaction = $this->transaction($invitation, 'UD-WEBHOOK-CODE');

        $payload = $this->signedPayload($transaction, 'settlement');
        $payload['status_code'] = '201';
        $payload['signature_key'] = hash('sha512', implode('', [
            $payload['order_id'],
            '201',
            $payload['gross_amount'],
            'test-server-key',
        ]));

        $this->postJson(route('payment.notification'), $payload)
            ->assertOk()
            ->assertJsonPath('activated', true)
            ->assertJsonPath('payment_status', 'SETTLEMENT');

        $transaction->refresh();

        $this->assertArrayNotHasKey('signature_key', $transaction->midtrans_response ?? []);
        $this->assertSame(InvitationStatus::Active, $invitation->fresh()->status);
    }

    public function test_admin_invitation_detail_access_is_audited_with_a_consistent_subject(): void
    {
        $admin = $this->admin();
        $invitation = $this->createInvitation($this->customer('audit-owner@example.test'));

        $this->actingAs($admin)
            ->get(route('admin.invitations.show', $invitation))
            ->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'action' => 'invitation.viewed_by_admin',
            'subject_type' => 'invitation',
            'subject_id' => $invitation->id,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function fakeGoogleUser(array $attributes): void
    {
        config()->set('services.google.client_id', 'google-client');
        config()->set('services.google.client_secret', 'google-secret');

        $googleUser = (new SocialiteUser())->map($attributes);
        $provider = \Mockery::mock();
        $provider->shouldReceive('user')->once()->andReturn($googleUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    private function customer(string $email): User
    {
        return User::create([
            'name' => 'Customer Security',
            'email' => $email,
            'google_id' => 'google-' . $email,
            'role' => Role::Customer,
            'status' => UserStatus::Active,
        ]);
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Super Admin Security',
            'email' => 'security-admin@example.test',
            'google_id' => 'google-security-admin',
            'role' => Role::SuperAdmin,
            'status' => UserStatus::Active,
        ]);
    }

    private function createInvitation(User $user): Invitation
    {
        return app(InvitationCreationService::class)
            ->create(Template::firstWhere('slug', 'tema1'), $user);
    }

    private function activate(Invitation $invitation): void
    {
        $invitation->update([
            'status' => InvitationStatus::Active,
            'payment_status' => PaymentStatus::Settlement,
            'activated_at' => now(),
            'published_at' => now(),
        ]);
    }

    private function transaction(Invitation $invitation, string $orderId): Transaction
    {
        return Transaction::create([
            'user_id' => $invitation->user_id,
            'invitation_id' => $invitation->id,
            'order_id' => $orderId,
            'gross_amount' => (int) $invitation->price,
            'payment_type' => 'qris',
            'transaction_status' => PaymentStatus::Pending,
            'transaction_time' => now(),
        ]);
    }

    /** @return array<string, string> */
    private function signedPayload(
        Transaction $transaction,
        string $status,
        ?string $fraudStatus = 'accept',
        ?string $serverKey = null,
    ): array {
        $grossAmount = (string) $transaction->gross_amount;
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
            'transaction_id' => 'midtrans-security-' . $transaction->id,
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
}

class StrictValidateCsrfToken extends ValidateCsrfToken
{
    protected function runningUnitTests(): bool
    {
        return false;
    }
}
