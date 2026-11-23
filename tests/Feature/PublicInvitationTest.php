<?php

namespace Tests\Feature;

use App\Enums\InvitationStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Invitation;
use App\Models\Template;
use App\Models\User;
use Database\Seeders\TemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicInvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TemplateSeeder::class);
    }

    public function test_active_invitation_renders_with_personalization_without_bottom_share_bar(): void
    {
        [$user, $invitation] = $this->makeInvitation();
        $invitation->events()->create([
            'event_type' => 'AKAD',
            'title' => 'Akad',
            'event_date' => '2027-05-20',
            'start_time' => '08:00',
            'timezone' => 'Asia/Jakarta',
            'sort_order' => 0,
        ]);
        $this->activate($invitation);

        $response = $this->get(route('invitation.show', $invitation->slug, false) . '?to=' . rawurlencode('Budi Santoso'))
            ->assertOk()
            ->assertSee('Budi Santoso')
            ->assertDontSee('Aktifkan Undangan Digital Anda!')
            ->assertSee('window.UD_CONFIG', false)
            ->assertSee('/u/' . $invitation->slug . '/greetings', false)
            ->assertDontSee('data-ud-share-bar', false);

        $html = (string) $response->getContent();

        $this->assertStringContainsString('<html lang="id">', $html);
        $this->assertSame(1, substr_count($html, 'name="viewport"'));
        $this->assertStringNotContainsString('window.alert(', $html);
        $this->assertStringContainsString('for="ud-guestbook-name"', $html);
        $this->assertStringContainsString('for="ud-guestbook-message"', $html);
        $this->assertStringContainsString('for="ud-guestbook-attendance"', $html);
        $this->assertStringContainsString('name="website"', $html);
        // These controls are created by the runtime after load; assert the
        // injected source so the accessible attributes remain covered.
        $this->assertStringContainsString("setAttribute('role', 'dialog')", $html);

        // The countdown remains visible even when live streaming is empty.
        preg_match('/window\.UD_CONFIG=(\{.*?\});/s', $html, $matches);
        $this->assertNotSame([], $matches);
        $config = json_decode($matches[1], true);
        $this->assertIsArray($config);
        $this->assertNotEmpty($config['countdown']['target'] ?? null);
        $this->assertNotContains('3adf3466', $config['hiddenIds'] ?? []);
        $this->assertNotContains('5fe8a914', $config['hiddenWidgets'] ?? []);
        $this->assertStringContainsString('elementor-countdown-wrapper" data-date="', $html);

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
    }

    public function test_non_active_and_invalid_slugs_are_not_public(): void
    {
        [, $invitation] = $this->makeInvitation();
        $this->app['auth']->forgetGuards();

        foreach ([
            InvitationStatus::Draft,
            InvitationStatus::PendingPayment,
            InvitationStatus::Expired,
            InvitationStatus::Suspended,
        ] as $status) {
            $invitation->update(['status' => $status]);

            $this->get(route('invitation.show', $invitation->slug, false))
                ->assertNotFound();
        }

        $this->get('/u/INVALID_SLUG!')->assertNotFound();
    }

    public function test_guest_greeting_is_saved_and_redirects_with_personalization(): void
    {
        [, $invitation] = $this->makeInvitation();
        $this->activate($invitation);

        $this->post(route('public.greetings.store', $invitation->slug, false), [
            'name' => 'Budi <b>Santoso</b>',
            'message' => '<script>Selamat</script> menempuh hidup baru.',
            'attendance' => 'AKAN_HADIR',
        ])->assertRedirect(
            route('invitation.show', $invitation->slug, false)
            . '?to=' . rawurlencode('Budi Santoso')
            . '#post-guestbook-box'
        );

        $this->assertDatabaseHas('invitation_greetings', [
            'invitation_id' => $invitation->id,
            'guest_name' => 'Budi Santoso',
            'message' => 'Selamat menempuh hidup baru.',
            'attendance_status' => 'AKAN_HADIR',
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('invitation_guests', [
            'invitation_id' => $invitation->id,
            'guest_name' => 'Budi Santoso',
            'attendance_status' => 'AKAN_HADIR',
            'guest_count' => 1,
        ]);
    }

    public function test_guest_response_flash_is_rendered_as_an_accessible_notice(): void
    {
        [, $invitation] = $this->makeInvitation();
        $this->activate($invitation);

        $response = $this->post(route('public.greetings.store', $invitation->slug, false), [
            'name' => 'Budi',
            'message' => 'Selamat.',
            'attendance' => 'HADIR',
        ])->assertRedirect();

        $location = (string) $response->headers->get('Location');
        $path = (string) parse_url($location, PHP_URL_PATH);
        $query = parse_url($location, PHP_URL_QUERY);

        $this->get($path . ($query ? '?' . $query : ''))
            ->assertOk()
            ->assertSee('Terima kasih atas ucapan dan doa restu Anda.')
            ->assertSee('data-ud-notice', false)
            ->assertSee('role="status"', false)
            ->assertSee('aria-label="Tutup pesan"', false);
    }

    public function test_greeting_validation_and_honeypot_reject_bad_submissions(): void
    {
        [, $invitation] = $this->makeInvitation();
        $this->activate($invitation);

        $url = route('public.greetings.store', $invitation->slug, false);

        $this->from(route('invitation.show', $invitation->slug, false))
            ->post($url, ['name' => '', 'message' => ''])
            ->assertSessionHasErrors(['name', 'message']);

        $this->post($url, [
            'name' => 'Spam Bot',
            'message' => 'Iklan',
            'website' => 'https://spam.test',
        ])->assertSessionHasErrors(['website']);

        $this->assertSame(0, $invitation->greetings()->count());
    }

    public function test_rsvp_is_saved_when_enabled_and_hidden_when_disabled(): void
    {
        [, $invitation] = $this->makeInvitation();
        $this->activate($invitation);

        $url = route('public.rsvp.store', $invitation->slug, false);

        $this->post($url, [
            'name' => 'Sari',
            'attendance' => 'TIDAK_HADIR',
            'guest_count' => 2,
            'message' => 'Maaf tidak bisa hadir.',
        ])->assertRedirect(
            route('invitation.show', $invitation->slug, false) . '?to=Sari'
        );

        $this->assertDatabaseHas('invitation_guests', [
            'invitation_id' => $invitation->id,
            'guest_name' => 'Sari',
            'attendance_status' => 'TIDAK_HADIR',
            'guest_count' => 2,
        ]);

        $invitation->invitationSettings()->updateOrCreate([], ['show_rsvp' => false]);

        $this->post($url, [
            'name' => 'Sari',
            'attendance' => 'HADIR',
        ])->assertNotFound();
    }

    public function test_guest_endpoints_reject_inactive_invitations(): void
    {
        [, $invitation] = $this->makeInvitation();

        $this->post(route('public.greetings.store', $invitation->slug, false), [
            'name' => 'Budi',
            'message' => 'Selamat.',
        ])->assertNotFound();

        $this->post(route('public.rsvp.store', $invitation->slug, false), [
            'name' => 'Budi',
            'attendance' => 'HADIR',
        ])->assertNotFound();
    }

    public function test_dashboard_shows_view_action_for_every_invitation_and_share_actions_for_active(): void
    {
        [$user, $invitation] = $this->makeInvitation();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee($invitation->publicUrl(), false)
            ->assertSee('Lihat Undangan')
            ->assertDontSee('Bagikan');

        $this->activate($invitation);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee($invitation->publicUrl(), false)
            ->assertSee('Lihat Undangan')
            ->assertSee('Bagikan')
            ->assertSee('wa.me/?text=', false);
    }

    public function test_owner_sees_invitation_with_activation_popup_before_payment(): void
    {
        [$user, $invitation] = $this->makeInvitation();

        // Freeze time: the rendered countdown and the assertion below must
        // observe the same second, otherwise a slow render makes this flaky.
        $this->travelTo(now());

        $response = $this->actingAs($user)
            ->get(route('invitation.show', $invitation->slug, false) . '?to=' . rawurlencode('Budi Santoso'))
            ->assertOk()
            ->assertSee('Budi Santoso')
            ->assertSee('Aktifkan Undangan Digital Anda!')
            ->assertSee('Sisa waktu preview')
            ->assertSee('Harga aktivasi')
            ->assertSee('data-ud-activation-gate', false)
            ->assertDontSee('<div class="ud-notice"', false)
            ->assertDontSee('facebook.com/sharer', false)
            ->assertDontSee('t.me/share', false);

        $html = (string) $response->getContent();
        $this->assertStringContainsString('href="' . route('invitations.activation', $invitation) . '"', $html);
        $this->assertStringContainsString('data-remaining-seconds="' . $invitation->previewRemainingSeconds() . '"', $html);
    }

    public function test_non_owner_cannot_view_a_draft_through_the_slug(): void
    {
        [$owner, $invitation] = $this->makeInvitation();
        $other = User::create([
            'name' => 'Other Customer',
            'email' => 'other-' . uniqid() . '@example.test',
            'google_id' => 'google-' . uniqid(),
            'role' => Role::Customer,
            'status' => UserStatus::Active,
        ]);

        $this->assertNotSame($owner->id, $other->id);

        $this->actingAs($other)
            ->get(route('invitation.show', $invitation->slug, false))
            ->assertNotFound();
    }

    /** @return array{0: User, 1: Invitation} */
    private function makeInvitation(): array
    {
        $user = User::create([
            'name' => 'Customer Public',
            'email' => 'public-' . uniqid() . '@example.test',
            'google_id' => 'google-' . uniqid(),
            'role' => Role::Customer,
            'status' => UserStatus::Active,
        ]);

        $this->actingAs($user)->post(route('templates.select', Template::firstWhere('slug', 'tema1')));

        return [$user, Invitation::where('user_id', $user->id)->firstOrFail()];
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
}
