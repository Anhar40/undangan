<?php

namespace Tests\Feature;

use App\Enums\EventType;
use App\Enums\InvitationStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Invitation;
use App\Models\InvitationGallery;
use App\Models\InvitationGiftAccount;
use App\Models\InvitationGreeting;
use App\Models\InvitationGuest;
use App\Models\Music;
use App\Models\Template;
use App\Models\User;
use App\Services\Invitation\InvitationCreationService;
use Database\Seeders\TemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TemplateFidelityAssertions;
use Tests\TestCase;

/**
 * Guarantees that the nine-section editor stays synchronized with the database
 * and the public invitation, including section-scoped saves and template swaps.
 */
class EditorSectionSyncTest extends TestCase
{
    use RefreshDatabase;
    use TemplateFidelityAssertions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TemplateSeeder::class);
        Storage::fake('public');
    }

    public function test_section_scoped_saves_never_overwrite_other_sections(): void
    {
        [$user, $invitation] = $this->makeInvitation('tema1');
        $startedAt = $invitation->preview_started_at?->toIso8601String();
        $expiresAt = $invitation->preview_expires_at?->toIso8601String();
        $defaultQuote = (string) config('invitation.default_quote');

        // A: couple data only.
        $this->patchEditor($user, $invitation, [
            'groom' => ['full_name' => 'Ahmad Section', 'nickname' => 'A'],
            'bride' => ['full_name' => 'Siti Section', 'nickname' => 'S'],
        ]);

        $this->assertSame('Ahmad Section', $invitation->fresh()->groom->full_name);
        $this->assertSame($defaultQuote, $invitation->fresh()->invitationSettings->quote);

        // J: sharing metadata and image only.
        $this->patchEditor($user, $invitation, [
            'settings' => [
                'share_title' => 'Judul Bagikan',
                'share_description' => 'Deskripsi bagikan.',
                'share_message' => 'Pesan bagikan khusus.',
            ],
            'share_image' => UploadedFile::fake()->image('share.jpg', 600, 400),
        ]);

        $settings = $invitation->fresh()->invitationSettings;
        $this->assertSame('Judul Bagikan', $settings->share_title);
        $this->assertSame('Deskripsi bagikan.', $settings->share_description);
        $this->assertSame('Pesan bagikan khusus.', $settings->whatsapp_message);
        $this->assertNotNull($settings->share_image);
        Storage::disk('public')->assertExists($settings->share_image);

        // G: music only; all sharing values must survive.
        $music = Music::create([
            'title' => 'Lagu Bagian G', 'artist' => 'Uji',
            'file_path' => 'music/bagian-g.mp3', 'status' => 'active',
        ]);
        $this->patchEditor($user, $invitation, [
            'settings' => ['music_id' => $music->id, 'music_autoplay' => true],
        ]);

        $settings = $invitation->fresh()->invitationSettings;
        $this->assertSame($music->id, $settings->music_id);
        $this->assertTrue((bool) $settings->music_autoplay);
        $this->assertSame('Judul Bagikan', $settings->share_title);
        $this->assertSame('Pesan bagikan khusus.', $settings->whatsapp_message);
        $this->assertSame($defaultQuote, $settings->quote);

        // B: events and quote only; music and sharing must survive.
        $this->patchEditor($user, $invitation, [
            'settings' => ['quote' => 'Kutipan bagian B.'],
            'events' => [[
                'event_type' => EventType::Akad->value,
                'event_date' => '2027-06-06',
                'start_time' => '09:00',
                'timezone' => 'Asia/Jakarta',
                'dress_code' => 'Formal',
                'sort_order' => 0,
            ]],
        ]);

        $invitation->refresh();
        $settings = $invitation->invitationSettings;
        $this->assertSame('Kutipan bagian B.', $settings->quote);
        $this->assertSame('Formal', $invitation->events->first()->dress_code);
        $this->assertSame($music->id, $settings->music_id);
        $this->assertSame('Judul Bagikan', $settings->share_title);
        $this->assertSame($startedAt, $invitation->preview_started_at?->toIso8601String());
        $this->assertSame($expiresAt, $invitation->preview_expires_at?->toIso8601String());
    }

    public function test_event_timezone_and_url_shortcuts_are_normalized_instead_of_failing_validation(): void
    {
        [$user, $invitation] = $this->makeInvitation('tema1');

        $this->patchEditor($user, $invitation, [
            'settings' => ['timezone' => 'WIB'],
            'events' => [[
                'event_type' => EventType::Akad->value,
                'event_date' => '2027-06-06',
                'start_time' => '09:00',
                'timezone' => 'WITA',
                'maps_url' => 'maps.google.com/place/wedding',
                'live_stream_url' => 'youtube.com/live/wedding',
                'sort_order' => 0,
            ]],
        ]);

        $invitation->refresh();
        $event = $invitation->events->first();

        $this->assertSame('Asia/Jakarta', $invitation->invitationSettings->custom_settings['timezone']);
        $this->assertSame('Asia/Makassar', $event->timezone);
        $this->assertSame('https://maps.google.com/place/wedding', $event->maps_url);
        $this->assertSame('https://youtube.com/live/wedding', $event->live_stream_url);
    }

    public function test_every_editor_add_button_has_a_matching_row_template(): void
    {
        [$user, $invitation] = $this->makeInvitation('tema1');

        $html = (string) $this->actingAs($user)
            ->get(route('invitations.edit', $invitation))
            ->getContent();

        preg_match_all('/data-add="([^"]+)"/', $html, $matches);

        $this->assertNotEmpty($matches[1]);

        foreach ($matches[1] as $key) {
            $this->assertStringContainsString('id="' . $key . '-row-template"', $html, $key);
        }
    }

    public function test_quote_is_default_editable_and_restorable_for_both_templates(): void
    {
        $defaultQuote = (string) config('invitation.default_quote');
        $defaultSource = (string) config('invitation.default_quote_source');

        foreach (['tema1', 'tema2'] as $slug) {
            [$user, $invitation] = $this->makeInvitation($slug);
            $settings = $invitation->invitationSettings;
            $this->assertSame($defaultQuote, $settings->quote);
            $this->assertSame($defaultSource, $settings->quote_source);
            $this->assertTrue((bool) $settings->quote_is_default);

            $this->patchEditor($user, $invitation, [
                'settings' => ['quote' => 'Kutipan edit ' . $slug . '.', 'quote_source' => 'Sumber edit ' . $slug . '.'],
            ]);

            $settings = $invitation->fresh()->invitationSettings;
            $this->assertSame('Kutipan edit ' . $slug . '.', $settings->quote);
            $this->assertSame('Sumber edit ' . $slug . '.', $settings->quote_source);
            $this->assertFalse((bool) $settings->quote_is_default);

            // The editor sets the restored text and the action marker together.
            $this->patchEditor($user, $invitation, [
                'settings' => [
                    'quote' => $defaultQuote,
                    'quote_source' => $defaultSource,
                    'quote_action' => 'default',
                ],
            ]);

            $settings = $invitation->fresh()->invitationSettings;
            $this->assertSame($defaultQuote, $settings->quote);
            $this->assertSame($defaultSource, $settings->quote_source);
            $this->assertTrue((bool) $settings->quote_is_default);

            $this->activate($invitation);
            $html = (string) $this->getPublic($invitation)->getContent();
            $this->assertStringContainsString(e($defaultQuote), $html, $slug);
            $this->assertStringContainsString(e($defaultSource), $html, $slug);
            $this->assertStringNotContainsString('Kutipan edit ' . $slug . '.', $html, $slug);
        }
    }

    public function test_share_metadata_and_image_are_rendered_in_public_head_and_share_page(): void
    {
        [$user, $invitation] = $this->makeInvitation('tema2');

        $this->patchEditor($user, $invitation, [
            'settings' => [
                'share_title' => 'Undangan Ahmad Siti',
                'share_description' => 'Deskripsi untuk pembagian tautan.',
                'share_message' => 'Assalamu alaikum, ini undangan kami.',
            ],
            'share_image' => UploadedFile::fake()->image('og-image.jpg', 600, 315),
        ]);

        $settings = $invitation->fresh()->invitationSettings;
        $this->assertNotNull($settings->share_image);
        $this->activate($invitation);

        $html = (string) $this->getPublic($invitation)->getContent();
        $this->assertStringContainsString('<meta property="og:title" content="Undangan Ahmad Siti">', $html);
        $this->assertStringContainsString('<meta property="og:description" content="Deskripsi untuk pembagian tautan.">', $html);
        $this->assertStringContainsString('<meta property="og:image" content="', $html);
        $this->assertStringContainsString(basename($settings->share_image), $html);
        $this->assertStringContainsString('<meta property="og:url" content="' . $invitation->publicUrl() . '">', $html);

        $this->get(route('share.index', [
            'uri' => $invitation->publicUrl(),
            'to' => 'Budi Santoso',
        ]))
            ->assertOk()
            ->assertSee('Assalamu alaikum, ini undangan kami.')
            ->assertSee('Budi Santoso');
    }

    public function test_gallery_uploads_reorder_and_deletion_are_saved_exactly(): void
    {
        [$user, $invitation] = $this->makeInvitation('tema1');

        $this->patchEditor($user, $invitation, [
            'gallery_items' => [
                ['caption' => 'Foto Satu', 'sort_order' => 1],
                ['caption' => 'Foto Dua', 'sort_order' => 0, 'is_cover' => true],
            ],
            'gallery_images' => [
                UploadedFile::fake()->image('satu.jpg', 800, 600),
                UploadedFile::fake()->image('dua.jpg', 800, 600),
            ],
            'gift_accounts' => [[
                'label' => 'BCA', 'account_name' => 'Ahmad', 'sort_order' => 0,
            ]],
        ]);

        $this->assertCount(2, $invitation->galleries);
        $this->assertSame(
            ['Foto Dua', 'Foto Satu'],
            $invitation->galleries()->orderBy('sort_order')->pluck('caption')->all(),
        );
        $gift = $invitation->giftAccounts()->firstOrFail();
        $this->assertSame('BCA', $gift->label);
        $this->assertNull($gift->qr_image);

        $first = $invitation->galleries()->where('caption', 'Foto Satu')->firstOrFail();
        $second = $invitation->galleries()->where('caption', 'Foto Dua')->firstOrFail();

        $this->patchEditor($user, $invitation, [
            'gallery_items' => [[
                'id' => $second->id,
                'caption' => 'Foto Dua Diunggah Ulang',
                'sort_order' => 2,
                'is_cover' => true,
            ]],
            'gallery_remove_image' => [$first->id => true],
            'gallery_images' => [UploadedFile::fake()->image('dua-baru.jpg', 800, 600)],
            'gift_accounts' => [[
                'id' => $gift->id,
                'label' => 'BCA',
                'account_name' => 'Ahmad',
                'sort_order' => 0,
            ]],
        ]);

        $this->assertCount(1, $invitation->galleries()->get());
        $remaining = $invitation->galleries()->firstOrFail();
        $this->assertSame('Foto Dua Diunggah Ulang', $remaining->caption);
        $this->assertSame(2, $remaining->sort_order);
        $this->assertNotSame($second->image, $remaining->image);
        Storage::disk('public')->assertExists($remaining->image);
        Storage::disk('public')->assertMissing($first->image);
        Storage::disk('public')->assertMissing($second->image);
        $this->assertSame('BCA', $invitation->giftAccounts()->firstOrFail()->label);
        $this->assertNull($invitation->giftAccounts()->firstOrFail()->qr_image);
    }

    public function test_music_can_be_selected_and_removed_on_both_templates(): void
    {
        $music = Music::create([
            'title' => 'Lagu Uji', 'artist' => 'Artis',
            'file_path' => 'music/uji.mp3', 'status' => 'active',
        ]);

        foreach (['tema1', 'tema2'] as $slug) {
            [$user, $invitation] = $this->makeInvitation($slug);

            $this->patchEditor($user, $invitation, [
                'settings' => ['music_id' => $music->id, 'music_autoplay' => true],
            ]);
            $this->activate($invitation);
            $html = (string) $this->getPublic($invitation)->getContent();
            $this->assertStringContainsString('music/uji.mp3', $html, $slug);
            $this->assertStringContainsString('<audio', $html, $slug);

            $invitation->update(['status' => InvitationStatus::Preview]);
            $this->patchEditor($user, $invitation, [
                'settings' => ['music_id' => '', 'music_autoplay' => false],
            ]);
            $this->assertNull($invitation->fresh()->invitationSettings->music_id);

            $this->activate($invitation);
            $html = (string) $this->getPublic($invitation)->getContent();
            $this->assertStringNotContainsString('music/uji.mp3', $html, $slug);
            $this->assertNativeAudioHidden($html, $slug);
        }
    }

    public function test_guest_and_greeting_edits_are_scoped_to_the_owned_invitation(): void
    {
        [$ownerA, $invitationA] = $this->makeInvitation('tema1');
        [$ownerB, $invitationB] = $this->makeInvitation('tema2');

        $this->patchEditor($ownerA, $invitationA, [
            'guests' => [[
                'guest_name' => 'Tamu Pemilik A',
                'attendance_status' => 'HADIR',
                'guest_count' => 2,
            ]],
        ]);
        $guestA = $invitationA->guests()->firstOrFail();
        $greetingA = $invitationA->greetings()->create([
            'guest_name' => 'Tamu Pemilik A',
            'message' => 'Ucapan milik A.',
            'attendance_status' => 'HADIR',
            'status' => 'approved',
            'ip_address' => '127.0.0.1',
        ]);
        $guestB = $invitationB->guests()->create([
            'guest_name' => 'Tamu Pemilik B',
            'attendance_status' => 'HADIR',
            'guest_count' => 1,
        ]);

        // Owner B tries to edit and delete A's guest and greeting by id.
        $this->patchEditor($ownerB, $invitationB, [
            'guests' => [[
                'id' => $guestA->id,
                'guest_name' => 'Tamu Disusupi',
                'attendance_status' => 'TIDAK_HADIR',
                'guest_count' => 5,
            ]],
            'greetings' => [['id' => $greetingA->id, 'status' => 'hidden']],
            'guest_remove' => [$guestA->id => true],
            'greeting_remove' => [$greetingA->id => true],
        ]);

        $this->assertSame('Tamu Pemilik A', $guestA->fresh()->guest_name);
        $this->assertSame(2, $guestA->fresh()->guest_count);
        $this->assertSame('approved', $greetingA->fresh()->status);
        $this->assertSame(1, $invitationA->guests()->count());
        $this->assertSame(1, $invitationA->greetings()->count());

        // B's submitted collection replaces its own guest with the injected row;
        // A's records are untouched because the foreign id was never trusted.
        $this->assertSame(1, $invitationB->guests()->count());
        $this->assertSame('Tamu Disusupi', $invitationB->guests()->first()->guest_name);
        $this->assertSame(5, $invitationB->guests()->first()->guest_count);
        $this->assertSame(0, $invitationB->greetings()->count());
    }

    public function test_template_switch_preserves_data_and_ignores_unsupported_fields(): void
    {
        [$user, $invitation] = $this->makeInvitation('tema1');

        $this->patchEditor($user, $invitation, [
            'events' => [[
                'event_type' => EventType::Akad->value,
                'event_date' => '2027-08-08',
                'start_time' => '08:30',
                'live_stream_url' => 'https://youtube.com/live/didukung',
                'dress_code' => 'Adat',
                'sort_order' => 0,
            ]],
            'stories' => [[
                'title' => 'Cerita Tetap', 'description' => 'Tetap setelah pindah template.', 'sort_order' => 0,
            ]],
            'families' => [['name' => 'Keluarga Tetap', 'sort_order' => 0]],
            'gift_accounts' => [[
                'label' => 'Amplop Tetap', 'account_name' => 'Siti', 'account_number' => '999', 'sort_order' => 0,
            ]],
            'settings' => [
                'quote' => 'Kutipan tetap.',
                'share_title' => 'Judul tetap',
            ],
            'backgrounds' => ['cover' => [UploadedFile::fake()->image('latar.jpg', 800, 600)]],
        ]);

        $this->actingAs($user)
            ->post(route('invitations.templates.switch', $invitation), [
                'template_id' => Template::firstWhere('slug', 'tema2')->id,
            ])
            ->assertRedirect(route('invitations.edit', $invitation));

        $invitation->refresh();
        $this->assertSame('tema2', $invitation->template_snapshot['renderer']);
        $this->assertSame('Kutipan tetap.', $invitation->invitationSettings->quote);
        $this->assertSame('Judul tetap', $invitation->invitationSettings->share_title);
        $this->assertSame('Cerita Tetap', $invitation->stories->first()->title);
        $this->assertSame('Keluarga Tetap', $invitation->families->first()->name);
        $this->assertSame('Amplop Tetap', $invitation->giftAccounts->first()->label);
        $this->assertSame('Adat', $invitation->events->first()->dress_code);
        $this->assertSame('https://youtube.com/live/didukung', $invitation->events->first()->live_stream_url);

        // tema2 has no live-stream slot, so the submitted value is ignored.
        $this->patchEditor($user, $invitation, [
            'events' => [[
                'id' => $invitation->events->first()->id,
                'event_type' => EventType::Akad->value,
                'event_date' => '2027-08-08',
                'start_time' => '08:30',
                'live_stream_url' => 'https://youtube.com/live/tidak-didukung',
                'sort_order' => 0,
            ]],
        ]);
        $this->assertNull($invitation->fresh()->events->first()->live_stream_url);

        // Unknown template slots are rejected instead of being stored blindly.
        $this->actingAs($user)
            ->patchJson(route('invitations.update', $invitation), [
                'title' => $invitation->title,
                'backgrounds' => ['slot-palsu' => [UploadedFile::fake()->image('palsu.jpg')]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('backgrounds');
    }

    /** @return array{0: User, 1: Invitation} */
    private function makeInvitation(string $templateSlug): array
    {
        $user = $this->customer('sync-' . $templateSlug . '-' . uniqid() . '@example.test');
        $invitation = app(InvitationCreationService::class)
            ->create(Template::firstWhere('slug', $templateSlug), $user);

        return [$user, $invitation];
    }

    /** @param array<string, mixed> $payload */
    private function patchEditor(User $user, Invitation $invitation, array $payload): void
    {
        $payload['title'] = $payload['title'] ?? $invitation->title;

        $this->actingAs($user)
            ->patchJson(route('invitations.update', $invitation), $payload)
            ->assertOk();
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

    private function getPublic(Invitation $invitation)
    {
        return $this->get(route('invitation.show', $invitation->slug, false) . '?to=Budi%20Santoso');
    }

    private function customer(string $email): User
    {
        return User::create([
            'name' => 'Customer Sync',
            'email' => $email,
            'google_id' => 'google-' . uniqid(),
            'role' => Role::Customer,
            'status' => UserStatus::Active,
        ]);
    }
}
