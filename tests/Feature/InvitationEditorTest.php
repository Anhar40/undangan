<?php

namespace Tests\Feature;

use App\Enums\InvitationStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Invitation;
use App\Models\InvitationGallery;
use App\Models\Template;
use App\Models\User;
use Database\Seeders\TemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvitationEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TemplateSeeder::class);
    }

    public function test_customer_can_open_editor_and_save_content_without_reset_preview_clock(): void
    {
        $user = $this->customer();
        $invitation = $this->createInvitation($user);
        $startedAt = $invitation->preview_started_at?->toIso8601String();
        $expiresAt = $invitation->preview_expires_at?->toIso8601String();

        $this->travel(5)->minutes();

        $this->actingAs($user)
            ->get(route('invitations.edit', $invitation))
            ->assertOk()
            ->assertSee('A Data Pasangan')
            ->assertSee('B Acara dan Lokasi')
            ->assertSee('C Cerita Pasangan')
            ->assertSee('D Turut Mengundang')
            ->assertSee('E Foto dan Galeri')
            ->assertSee('F Musik')
            ->assertSee('G Ucapan Tamu')
            ->assertSee('H Amplop Digital')
            ->assertSee('I Tampilan saat Dibagikan')
            ->assertDontSee('Tampilan dan Media')
            ->assertDontSee('tampilan-media')
            ->assertDontSee('Pengaturan URL')
            ->assertDontSee('Pengaturan WhatsApp');

        $payload = $this->payload($invitation, [
            'title' => 'Undangan Uji Editor',
            'groom' => ['full_name' => 'Groom Unik Satu', 'nickname' => 'Gus'],
            'bride' => ['full_name' => 'Bride Unik Dua', 'nickname' => 'Bus'],
            'events' => [[
                'id' => null,
                'event_type' => 'AKAD',
                'title' => 'Akad Uji',
                'event_date' => '2027-05-20',
                'start_time' => '09:30',
                'end_time' => '11:00',
                'timezone' => 'Asia/Jakarta',
                'location_name' => 'Gedung Uji',
                'address' => 'Jalan Uji No. 1',
                'maps_url' => 'https://maps.google.com/?q=uji',
                'description' => 'Acara uji editor.',
                'live_stream_url' => 'https://youtube.com/live/uji',
                'sort_order' => 0,
            ]],
            'settings' => [
                'invitation_message' => 'Pesan undangan yang diperbarui.',
                'quote' => 'Kutipan yang diperbarui.',
                'primary_color' => '#123456',
            ],
        ]);

        $response = $this->actingAs($user)
            ->patch(route('invitations.update', $invitation), $payload, ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('message', 'Perubahan tersimpan.')
            ->assertJsonPath('public_url', route('invitation.show', 'gus-dan-bus'));

        $this->assertArrayNotHasKey('preview_url', $response->json());

        $invitation->refresh();
        $this->assertSame('Undangan Uji Editor', $invitation->title);
        $this->assertSame('Groom Unik Satu', $invitation->groom->full_name);
        $this->assertSame('Bride Unik Dua', $invitation->bride->full_name);
        $this->assertSame('Gedung Uji', $invitation->events->first()->location_name);
        $this->assertSame('https://youtube.com/live/uji', $invitation->events->first()->live_stream_url);
        $this->assertSame('Pesan undangan yang diperbarui.', $invitation->invitationSettings->custom_settings['invitation_message']);
        $this->assertSame('#123456', $invitation->invitationSettings->primary_color);
        $this->assertSame($startedAt, $invitation->preview_started_at?->toIso8601String());
        $this->assertSame($expiresAt, $invitation->preview_expires_at?->toIso8601String());
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'invitation.updated',
        ]);

        $this->actingAs($user)
            ->get(route('invitation.show', $invitation->slug, false) . '?to=Budi%20Santoso')
            ->assertOk()
            ->assertSee('Groom Unik Satu')
            ->assertSee('Bride Unik Dua')
            ->assertSee('Pesan undangan yang diperbarui.')
            ->assertSee('Budi Santoso')
            ->assertSee('Aktifkan Undangan Digital Anda!')
            ->assertSee('data-ud-activation-gate', false)
            ->assertSee('window.UD_CONFIG');
    }

    public function test_editor_has_no_payment_popup_or_preview_button(): void
    {
        $user = $this->customer();
        $invitation = $this->createInvitation($user);

        $this->actingAs($user)
            ->get(route('invitations.edit', $invitation))
            ->assertOk()
            ->assertDontSee('Aktifkan Undangan Digital Anda!')
            ->assertDontSee('id="activation-reminder"', false)
            ->assertDontSee('data-preview-url', false)
            ->assertDontSee('data-open-preview', false);

        $this->actingAs($user)
            ->get('/invitations/' . $invitation->id . '/preview')
            ->assertNotFound();
    }

    public function test_saved_event_date_remains_valid_in_editor_form(): void
    {
        $user = $this->customer();
        $invitation = $this->createInvitation($user);

        $payload = $this->payload($invitation, [
            'events' => [[
                'id' => null,
                'event_type' => 'AKAD',
                'title' => 'Akad Tanggal Lama',
                'event_date' => '2027-05-20',
                'start_time' => '09:30',
                'end_time' => '11:00',
                'timezone' => 'Asia/Jakarta',
                'location_name' => 'Gedung Uji',
                'address' => 'Jalan Uji No. 1',
                'maps_url' => 'https://maps.google.com/?q=uji',
                'description' => '',
                'live_stream_url' => '',
                'sort_order' => 0,
            ]],
        ]);

        $this->actingAs($user)
            ->patch(route('invitations.update', $invitation), $payload)
            ->assertRedirect();

        $this->actingAs($user)
            ->get(route('invitations.edit', $invitation))
            ->assertOk()
            ->assertSee('value="2027-05-20"', false)
            ->assertSee('value="09:30"', false);
    }

    public function test_another_customer_and_super_admin_cannot_update_invitation(): void
    {
        $owner = $this->customer('owner@example.test');
        $other = $this->customer('other@example.test');
        $admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@example.test',
            'google_id' => 'google-admin-editor',
            'role' => Role::SuperAdmin,
            'status' => UserStatus::Active,
        ]);
        $invitation = $this->createInvitation($owner);
        $originalTitle = $invitation->title;

        $this->actingAs($other)
            ->patchJson(route('invitations.update', $invitation), ['title' => 'Hijacked'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->patchJson(route('invitations.update', $invitation), ['title' => 'Hijacked'])
            ->assertForbidden();

        $this->assertSame($originalTitle, $invitation->fresh()->title);
    }

    public function test_gallery_upload_and_removal_are_limited_to_invitation_owner(): void
    {
        Storage::fake('public');

        $user = $this->customer();
        $invitation = $this->createInvitation($user);

        $payload = $this->payload($invitation, [
            'gallery_items' => [[
                'id' => null,
                'caption' => 'Foto pertama',
                'is_cover' => true,
                'sort_order' => 0,
            ]],
            'gallery_images' => [UploadedFile::fake()->image('foto-pertama.jpg')],
        ]);

        $this->actingAs($user)
            ->patch(route('invitations.update', $invitation), $payload)
            ->assertRedirect();

        $photo = $invitation->galleries()->firstOrFail();
        $this->assertSame('Foto pertama', $photo->caption);
        $this->assertTrue($photo->is_cover);
        $this->assertSame('invitations/' . $invitation->id . '/gallery-0', dirname($photo->image));
        Storage::disk('public')->assertExists($photo->image);

        $removePayload = $this->payload($invitation, [
            'gallery_items' => [[
                'id' => $photo->id,
                'caption' => 'Foto pertama',
                'is_cover' => true,
                'sort_order' => 0,
            ]],
            'gallery_remove_image' => [true],
        ]);

        $this->actingAs($user)
            ->patch(route('invitations.update', $invitation), $removePayload)
            ->assertRedirect();

        $this->assertSame(0, InvitationGallery::where('invitation_id', $invitation->id)->count());
        Storage::disk('public')->assertMissing($photo->image);
    }

    public function test_gallery_accepts_eight_photos_and_rejects_the_ninth(): void
    {
        Storage::fake('public');

        $user = $this->customer();
        $invitation = $this->createInvitation($user);

        $eight = [];

        for ($index = 1; $index <= 8; $index++) {
            $eight[] = UploadedFile::fake()->image('foto-' . $index . '.jpg', 800, 600);
        }

        $this->actingAs($user)
            ->patch(
                route('invitations.update', $invitation),
                $this->payload($invitation, ['gallery_new' => $eight]),
                ['Accept' => 'application/json'],
            )
            ->assertOk();

        $this->assertSame(8, $invitation->galleries()->count());

        $nine = [];

        for ($index = 1; $index <= 9; $index++) {
            $nine[] = UploadedFile::fake()->image('berlebih-' . $index . '.jpg', 800, 600);
        }

        $this->actingAs($user)
            ->patch(
                route('invitations.update', $invitation),
                $this->payload($invitation, [
                    'gallery_items' => [],
                    'gallery_new' => $nine,
                ]),
                ['Accept' => 'application/json'],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['gallery_items', 'gallery_new']);

        $this->assertSame(8, $invitation->galleries()->count());
    }

    public function test_gallery_upload_uses_remaining_space_after_existing_photos(): void
    {
        Storage::fake('public');

        $user = $this->customer();
        $invitation = $this->createInvitation($user);

        for ($index = 1; $index <= 8; $index++) {
            $invitation->galleries()->create([
                'image' => 'invitations/' . $invitation->id . '/gallery/existing-' . $index . '.jpg',
                'caption' => 'Foto ' . $index,
                'is_cover' => $index === 1,
                'sort_order' => $index - 1,
            ]);
        }

        $allRows = $invitation->galleries()->orderBy('sort_order')->get()->map(fn ($photo): array => [
            'id' => $photo->id,
            'caption' => $photo->caption,
            'is_cover' => $photo->is_cover,
            'sort_order' => $photo->sort_order,
        ])->all();

        $this->actingAs($user)
            ->patch(
                route('invitations.update', $invitation),
                $this->payload($invitation, [
                    'gallery_items' => $allRows,
                    'gallery_new' => [UploadedFile::fake()->image('kesembilan.jpg', 800, 600)],
                ]),
                ['Accept' => 'application/json'],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('gallery_items');

        $this->assertSame(8, $invitation->galleries()->count());

        $this->actingAs($user)
            ->patch(
                route('invitations.update', $invitation),
                $this->payload($invitation, [
                    'gallery_items' => array_slice($allRows, 0, 7),
                    'gallery_new' => [UploadedFile::fake()->image('pengganti.jpg', 800, 600)],
                ]),
                ['Accept' => 'application/json'],
            )
            ->assertOk();

        $this->assertSame(8, $invitation->galleries()->count());
    }

    public function test_disabled_template_features_are_hidden_and_ignored_on_save(): void
    {
        $user = $this->customer();
        $template = Template::firstWhere('slug', 'tema1');
        $template->update([
            'features' => [
                'story' => false,
                'gallery' => false,
                'music' => false,
                'theme' => false,
                'rsvp' => false,
                'families' => false,
                'gift' => false,
                'live_stream' => false,
                'countdown' => true,
            ],
        ]);

        $invitation = $this->createInvitation($user, $template);

        $this->actingAs($user)
            ->get(route('invitations.edit', $invitation))
            ->assertOk()
            ->assertDontSee('name="gallery_new[]"', false)
            ->assertDontSee('name="settings[show_stories]"', false)
            ->assertDontSee('name="settings[show_gallery]"', false)
            ->assertDontSee('name="settings[show_families]"', false)
            ->assertDontSee('name="settings[show_gift]"', false)
            ->assertDontSee('name="settings[show_rsvp]"', false)
            ->assertDontSee('name="settings[music_id]"', false)
            ->assertDontSee('name="settings[theme]"', false)
            ->assertSee('name="events_present"', false);

        $payload = $this->payload($invitation, [
            'stories' => [[
                'id' => null,
                'title' => 'Cerita terlarang',
                'description' => 'Tidak boleh tersimpan.',
                'story_date' => '2020',
                'sort_order' => 0,
            ]],
            'families' => [['id' => null, 'name' => 'Keluarga Terlarang', 'sort_order' => 0]],
            'gallery_items' => [[
                'id' => null,
                'caption' => 'Galeri terlarang',
                'is_cover' => false,
                'sort_order' => 0,
            ]],
            'gallery_images' => [UploadedFile::fake()->image('terlarang.jpg')],
        ]);

        $this->actingAs($user)
            ->patch(route('invitations.update', $invitation), $payload)
            ->assertRedirect();

        $this->assertSame(0, $invitation->stories()->count());
        $this->assertSame(0, $invitation->families()->count());
        $this->assertSame(0, $invitation->galleries()->count());
    }

    public function test_saved_data_renders_on_both_templates(): void
    {
        Storage::fake('public');

        $user = $this->customer();

        foreach (['tema1', 'tema2'] as $slug) {
            $invitation = $this->createInvitation($user, Template::firstWhere('slug', $slug));

            $payload = $this->payload($invitation, [
                'title' => 'Undangan ' . $slug,
                'groom' => ['full_name' => 'Pria ' . $slug, 'nickname' => 'P' . $slug],
                'bride' => ['full_name' => 'Wanita ' . $slug, 'nickname' => 'W' . $slug],
                'settings' => ['invitation_message' => 'Pesan khusus untuk ' . $slug . '.'],
            ]);

            $this->actingAs($user)
                ->patch(route('invitations.update', $invitation), $payload, ['Accept' => 'application/json'])
                ->assertOk();

            $invitation->refresh();

            $response = $this->actingAs($user)
                ->get(route('invitation.show', $invitation->slug, false) . '?to=Tamu%20' . $slug);
            $response->assertOk()
                ->assertSee('Pria ' . $slug)
                ->assertSee('Wanita ' . $slug)
                ->assertSee('Pesan khusus untuk ' . $slug . '.')
                ->assertSee('Tamu ' . $slug)
                ->assertSee('Aktifkan Undangan Digital Anda!')
                ->assertDontSee('https://bukaundangan.com/wp-content/');
        }
    }

    /** @param array<string, mixed> $overrides */
    private function payload(Invitation $invitation, array $overrides = []): array
    {
        $base = [
            'title' => $invitation->title,
            'groom' => [
                'full_name' => 'Groom Default',
                'nickname' => 'Groom',
                'father_name' => 'Ayah Groom',
                'mother_name' => 'Ibu Groom',
                'instagram' => '@groom',
                'social_links' => '',
            ],
            'bride' => [
                'full_name' => 'Bride Default',
                'nickname' => 'Bride',
                'father_name' => 'Ayah Bride',
                'mother_name' => 'Ibu Bride',
                'instagram' => '@bride',
                'social_links' => '',
            ],
            'events' => [[
                'id' => null,
                'event_type' => 'AKAD',
                'title' => 'Akad Default',
                'event_date' => '2027-01-01',
                'start_time' => '08:00',
                'end_time' => '10:00',
                'timezone' => 'Asia/Jakarta',
                'location_name' => 'Gedung Default',
                'address' => 'Alamat default',
                'maps_url' => 'https://maps.google.com',
                'description' => '',
                'live_stream_url' => '',
                'sort_order' => 0,
            ]],
            'stories' => [],
            'families' => [],
            'gallery_items' => [],
            'gift_accounts' => [],
            'events_present' => '1',
            'stories_present' => '1',
            'families_present' => '1',
            'gallery_items_present' => '1',
            'gift_accounts_present' => '1',
            'settings' => [
                'theme' => 'default',
                'font_family' => 'Poppins, sans-serif',
                'primary_color' => '#8b5cf6',
                'secondary_color' => '#d946ef',
                'music_id' => '',
                'music_autoplay' => false,
                'show_stories' => false,
                'show_gallery' => false,
                'show_families' => false,
                'show_gift' => false,
                'show_rsvp' => false,
                'invitation_message' => 'Pesan default.',
                'quote' => 'Kutipan default.',
                'quote_source' => '- Default -',
                'timezone' => 'Asia/Jakarta',
                'gallery_limit' => 0,
            ],
        ];

        return array_replace_recursive($base, $overrides);
    }

    private function createInvitation(User $user, ?Template $template = null): Invitation
    {
        $template ??= Template::firstWhere('slug', 'tema1');

        $this->actingAs($user)->post(route('templates.select', $template));

        return Invitation::where('user_id', $user->id)->latest('id')->firstOrFail();
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
