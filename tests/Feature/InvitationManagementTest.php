<?php

namespace Tests\Feature;

use App\Enums\InvitationStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Invitation;
use App\Models\Template;
use App\Models\User;
use App\Support\IndonesianBanks;
use Database\Seeders\TemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class InvitationManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TemplateSeeder::class);
    }

    public function test_editor_exposes_only_the_nine_required_sections(): void
    {
        $user = $this->customer();
        $invitation = $this->createInvitation($user);

        $labels = [
            'A Data Pasangan',
            'B Acara dan Lokasi',
            'C Cerita Pasangan',
            'D Turut Mengundang',
            'E Foto dan Galeri',
            'F Musik',
            'G Ucapan Tamu',
            'H Amplop Digital',
            'I Tampilan saat Dibagikan',
        ];
        $keys = [
            'pasangan', 'acara', 'cerita', 'mengundang',
            'galeri', 'musik', 'tamu-rsvp', 'amplop', 'bagikan',
        ];

        $response = $this->actingAs($user)
            ->get(route('invitations.edit', $invitation))
            ->assertOk();

        foreach ($labels as $label) {
            $response->assertSee($label);
        }

        $html = (string) $response->getContent();
        $this->assertSame(9, substr_count($html, 'data-section-title="'));
        $this->assertSame(9, substr_count($html, 'class="btn btn-primary save-section"'));
        foreach ($keys as $key) {
            $this->assertStringContainsString('data-section-panel="' . $key . '"', $html);
        }

        $response
            ->assertSee('name="photos[groom]"', false)
            ->assertSee('name="photos[bride]"', false)
            ->assertSee('id="gallery-new" name="gallery_new[]" accept="image/jpeg,image/png,image/webp" multiple', false)
            ->assertSee('Pilih foto galeri (opsional, maksimal 8)')
            ->assertSee('Pilih foto pendukung (opsional, maksimal 3)')
            ->assertDontSee('Maksimal 30 foto')
            ->assertSee('name="greetings_present"', false)
            ->assertSee('id="greetings-rows"', false)
            ->assertSee('name="settings[share_title]"', false)
            ->assertSee('name="settings[share_description]"', false)
            ->assertSee('name="share_image"', false)
            ->assertDontSee('Tampilan dan Media')
            ->assertDontSee('tampilan-media')
            ->assertDontSee('name="photos[cover]"', false)
            ->assertDontSee('name="backgrounds[', false)
            ->assertDontSee('Daftar tamu')
            ->assertDontSee('+ Tambah Tamu')
            ->assertDontSee('data-add="guests"', false)
            ->assertDontSee('name="guests[', false)
            ->assertDontSee('+ Tambah Foto')
            ->assertDontSee('data-add="gallery_items"', false)
            ->assertDontSee('name="gallery_images[', false)
            ->assertDontSee('name="settings[share_message]"', false)
            ->assertDontSee('id="share-public-url"', false)
            ->assertDontSee('Pesan awal berbagi')
            ->assertDontSee('URL undangan')
            ->assertDontSee('Pengaturan URL')
            ->assertDontSee('Pengaturan WhatsApp')
            ->assertDontSee('name="settings[whatsapp_number]"', false)
            ->assertDontSee('id="slug-input"', false)
            ->assertDontSee('data-slug-check-url=', false)
            ->assertDontSee('editor-preview-stage', false)
            ->assertSee('name="settings[gift_address]"', false)
            ->assertDontSee('gift_qr_images', false)
            ->assertDontSee('name="gift_accounts[0][gift_address]"', false);

        $this->assertSame(1, substr_count($html, 'name="settings[gift_address]"'));
        $this->assertStringContainsString('bank-mark', $html);
        foreach (IndonesianBanks::all() as $bank) {
            $this->assertStringContainsString('data-value="' . $bank['name'] . '"', $html);
        }
    }

    public function test_template_can_be_switched_before_and_after_activation(): void
    {
        $user = $this->customer();
        $invitation = $this->createInvitation($user, Template::firstWhere('slug', 'tema1'));
        $other = Template::firstWhere('slug', 'tema2');

        $this->actingAs($user)
            ->get(route('invitations.templates.index', $invitation))
            ->assertOk()
            ->assertSee('Template aktif')
            ->assertSee('name="template_id"', false)
            ->assertSee('Gunakan Template')
            ->assertSee('Anda dapat mengganti template kapan saja');

        $this->actingAs($user)
            ->post(route('invitations.templates.switch', $invitation), [
                'template_id' => $other->id,
            ])
            ->assertRedirect(route('invitations.edit', $invitation));

        $invitation->refresh();

        $this->assertSame($other->id, $invitation->template_id);
        $this->assertSame($other->name, $invitation->template_name);
        $this->assertSame('tema2', $invitation->template_snapshot['renderer']);
        $this->assertFalse($invitation->templateIsLocked());

        $invitation->update(['title' => 'Judul Yang Dibagikan', 'price' => 123456]);
        $this->activate($invitation);

        $slug = $invitation->slug;
        $activatedAt = $invitation->activated_at?->toIso8601String();
        $publishedAt = $invitation->published_at?->toIso8601String();
        $target = Template::firstWhere('slug', 'tema1');

        $this->actingAs($user)
            ->post(route('invitations.templates.switch', $invitation), [
                'template_id' => $target->id,
            ])
            ->assertRedirect(route('invitations.edit', $invitation));

        $invitation->refresh();

        $this->assertSame($target->id, $invitation->template_id);
        $this->assertSame('tema1', $invitation->template_snapshot['renderer']);
        $this->assertSame('Judul Yang Dibagikan', $invitation->title);
        $this->assertSame($slug, $invitation->slug);
        $this->assertSame(123456, (int) $invitation->price);
        $this->assertSame(InvitationStatus::Active, $invitation->status);
        $this->assertSame(PaymentStatus::Settlement, $invitation->payment_status);
        $this->assertSame($activatedAt, $invitation->activated_at?->toIso8601String());
        $this->assertSame($publishedAt, $invitation->published_at?->toIso8601String());
        $this->assertTrue($invitation->templateIsLocked());

        $html = (string) $this->get(route('invitation.show', $invitation->slug, false))->getContent();
        $this->assertStringContainsString('Kepada yth:', $html);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Template bisa diganti')
            ->assertSee('Pilih Template');

        $this->actingAs($user)
            ->get(route('invitations.templates.index', $invitation))
            ->assertOk()
            ->assertSee('Undangan aktif tetap bisa mengganti template')
            ->assertSee('Sedang dipakai')
            ->assertSee('Gunakan Template');
    }

    public function test_template_switch_is_rejected_for_other_users_and_unavailable_statuses(): void
    {
        $user = $this->customer();
        $invitation = $this->createInvitation($user, Template::firstWhere('slug', 'tema1'));
        $this->activate($invitation);

        $this->actingAs($this->customer('outsider@example.test'))
            ->post(route('invitations.templates.switch', $invitation), [
                'template_id' => Template::firstWhere('slug', 'tema2')->id,
            ])
            ->assertForbidden();

        foreach ([InvitationStatus::Suspended, InvitationStatus::Expired] as $status) {
            $invitation->update(['status' => $status]);

            $this->actingAs($user)
                ->post(route('invitations.templates.switch', $invitation), [
                    'template_id' => Template::firstWhere('slug', 'tema2')->id,
                ])
                ->assertForbidden();
        }

        $this->assertSame('tema1', $invitation->fresh()->template_snapshot['renderer']);
    }

    public function test_custom_slug_keeps_old_link_alive_and_rejects_reserved_or_taken_slugs(): void
    {
        $user = $this->customer();
        $invitation = $this->createInvitation($user);
        $oldSlug = $invitation->slug;
        $newSlug = 'undangan-' . uniqid();

        $this->actingAs($user)
            ->patchJson(route('invitations.update', $invitation), [
                'title' => $invitation->title,
                'slug' => $newSlug,
            ])
            ->assertOk()
            ->assertJson(['slug' => $newSlug]);

        $invitation->refresh();

        $this->assertSame($newSlug, $invitation->slug);
        $this->assertDatabaseHas('invitation_slug_redirects', [
            'old_slug' => $oldSlug,
            'invitation_id' => $invitation->id,
        ]);

        $redirect = $this->get('/u/' . $oldSlug . '?to=' . rawurlencode('Budi Santoso'));

        $redirect->assertStatus(301);

        $location = (string) $redirect->headers->get('Location');

        $this->assertStringStartsWith(route('invitation.show', $newSlug), $location);
        $this->assertStringContainsString('to=Budi', $location);

        $this->actingAs($user)
            ->getJson(route('invitations.slug.check', $invitation) . '?slug=admin')
            ->assertOk()
            ->assertJson(['available' => false]);

        $this->actingAs($user)
            ->getJson(route('invitations.slug.check', $invitation) . '?slug=' . $newSlug . '-baru')
            ->assertOk()
            ->assertJson(['available' => true]);

        $this->actingAs($user)
            ->patchJson(route('invitations.update', $invitation), [
                'title' => $invitation->title,
                'slug' => 'admin',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');

        $second = $this->createInvitation($this->customer('second@example.test'));

        $this->actingAs($user)
            ->patchJson(route('invitations.update', $invitation), [
                'title' => $invitation->title,
                'slug' => $second->slug,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');
    }

    public function test_public_slug_uses_both_nicknames_and_keeps_the_old_link(): void
    {
        $user = $this->customer();
        $invitation = $this->createInvitation($user);
        $oldSlug = $invitation->slug;

        $this->actingAs($user)
            ->patchJson(route('invitations.update', $invitation), [
                'title' => $invitation->title,
                'groom' => ['nickname' => 'Anhar', 'full_name' => 'Anhar Wijaya'],
                'bride' => ['nickname' => 'Ika', 'full_name' => 'Ika Dewi'],
            ])
            ->assertOk()
            ->assertJson(['slug' => 'anhar-dan-ika']);

        $invitation->refresh();

        $this->assertSame('anhar-dan-ika', $invitation->slug);
        $this->assertDatabaseHas('invitation_slug_redirects', [
            'old_slug' => $oldSlug,
            'invitation_id' => $invitation->id,
        ]);

        $this->activate($invitation);

        $redirect = $this->get('/u/' . $oldSlug);

        $redirect->assertStatus(301);
        $this->assertStringStartsWith(route('invitation.show', 'anhar-dan-ika'), (string) $redirect->headers->get('Location'));

        $this->get('/u/anhar-dan-ika')->assertOk()->assertSee('Anhar Wijaya');
    }

    public function test_nickname_slug_collisions_get_a_numeric_suffix_and_custom_slug_is_preserved(): void
    {
        $firstUser = $this->customer();
        $first = $this->createInvitation($firstUser);

        $this->actingAs($firstUser)
            ->patchJson(route('invitations.update', $first), [
                'title' => $first->title,
                'groom' => ['nickname' => 'Anhar'],
                'bride' => ['nickname' => 'Ika'],
            ])
            ->assertOk()
            ->assertJson(['slug' => 'anhar-dan-ika']);

        $secondUser = $this->customer('slug-second@example.test');
        $second = $this->createInvitation($secondUser);

        $this->actingAs($secondUser)
            ->patchJson(route('invitations.update', $second), [
                'title' => $second->title,
                'groom' => ['nickname' => 'Anhar'],
                'bride' => ['nickname' => 'Ika'],
            ])
            ->assertOk()
            ->assertJson(['slug' => 'anhar-dan-ika-2']);

        $this->assertSame('anhar-dan-ika-2', $second->fresh()->slug);

        $custom = $this->createInvitation($this->customer('slug-custom@example.test'));

        $this->actingAs($custom->user)
            ->patchJson(route('invitations.update', $custom), [
                'title' => $custom->title,
                'slug' => 'undangan-kustom',
                'groom' => ['nickname' => 'Anhar'],
                'bride' => ['nickname' => 'Ika'],
            ])
            ->assertOk()
            ->assertJson(['slug' => 'undangan-kustom']);

        $this->actingAs($custom->user)
            ->patchJson(route('invitations.update', $custom), [
                'title' => 'Perubahan Tanpa Slug',
            ])
            ->assertOk()
            ->assertJson(['slug' => 'undangan-kustom']);
    }

    public function test_nickname_slug_backfill_rewrites_generated_slugs_and_honors_force(): void
    {
        $backfillUser = $this->customer();
        $invitation = $this->createInvitation($backfillUser, Template::firstWhere('slug', 'tema2'));

        // A template switch changes the snapshot name but keeps the original
        // generated slug; the backfill must still recognise that old URL.
        $this->actingAs($backfillUser)
            ->post(route('invitations.templates.switch', $invitation), [
                'template_id' => Template::firstWhere('slug', 'tema1')->id,
            ])
            ->assertRedirect();

        $oldSlug = $invitation->fresh()->slug;
        $invitation->groom()->create(['nickname' => 'Anhar']);
        $invitation->bride()->create(['nickname' => 'Ika']);

        $this->assertSame(0, Artisan::call('invitations:sync-nickname-slugs'));
        $this->assertSame('anhar-dan-ika', $invitation->fresh()->slug);
        $this->assertDatabaseHas('invitation_slug_redirects', [
            'old_slug' => $oldSlug,
            'invitation_id' => $invitation->id,
        ]);

        $custom = $this->createInvitation($this->customer('backfill-custom@example.test'));
        $custom->update(['slug' => 'slug-kustom-lama']);
        $custom->groom()->create(['nickname' => 'Rizky']);
        $custom->bride()->create(['nickname' => 'Nadia']);

        $this->assertSame(0, Artisan::call('invitations:sync-nickname-slugs'));
        $this->assertSame('slug-kustom-lama', $custom->fresh()->slug);

        $this->assertSame(0, Artisan::call('invitations:sync-nickname-slugs', ['--force' => true]));
        $this->assertSame('rizky-dan-nadia', $custom->fresh()->slug);
    }

    public function test_share_page_personalizes_the_message_and_builds_a_whatsapp_link(): void
    {
        $user = $this->customer();
        $invitation = $this->createInvitation($user);
        $this->activate($invitation);

        $link = $invitation->publicUrl();

        $response = $this->get(route('share.index', [
            'uri' => $link,
            'to' => 'Budi Santoso',
        ]))
            ->assertOk()
            ->assertSee('Budi Santoso')
            ->assertSee('Pulihkan pesan awal')
            ->assertSee('data-base-link=', false)
            ->assertSee('data-message-template=', false)
            ->assertSee('data-default-message=', false)
            ->assertSee('id="share-guest-name"', false)
            ->assertSee('id="share-whatsapp"', false)
            ->assertSee('id="share-facebook"', false)
            ->assertSee('facebook.com/sharer', false)
            ->assertSee('Salin link')
            ->assertSee('wa.me/?text=', false)
            ->assertSee($link, false)
            ->assertDontSee('Terapkan')
            ->assertDontSee('Salin pesan')
            ->assertDontSee('Bagikan…')
            ->assertDontSee('share-card-wide');

        $response->assertSee(rawurlencode('Budi Santoso'), false);

        $shareHtml = (string) $response->getContent();
        $this->assertSame(1, substr_count($shareHtml, 'class="card share-card"'));
    }

    public function test_whatsapp_number_is_normalized_and_injected_into_the_public_invitation(): void
    {
        $user = $this->customer();
        $invitation = $this->createInvitation($user);

        $this->actingAs($user)
            ->patchJson(route('invitations.update', $invitation), [
                'title' => $invitation->title,
                'settings' => [
                    'whatsapp_number' => '0812-3456-7890',
                    'whatsapp_display_name' => 'Keluarga Andi',
                    'whatsapp_message' => 'Halo, ada yang bisa dibantu?',
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('invitation_settings', [
            'invitation_id' => $invitation->id,
            'whatsapp_number' => '6281234567890',
            'whatsapp_display_name' => 'Keluarga Andi',
        ]);

        $this->actingAs($user)
            ->patchJson(route('invitations.update', $invitation), [
                'title' => $invitation->title,
                'settings' => ['whatsapp_number' => '12345'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('settings.whatsapp_number');

        $this->activate($invitation);

        $this->get(route('invitation.show', $invitation->slug, false))
            ->assertOk()
            ->assertSee('"whatsappContact"', false)
            ->assertSee('"number":"6281234567890"', false)
            ->assertSee('https://wa.me/6281234567890', false)
            ->assertSee('Keluarga Andi');
    }

    public function test_real_invitation_never_falls_back_to_sample_content(): void
    {
        $user = $this->customer();
        $invitation = $this->createInvitation($user);

        $this->actingAs($user)
            ->patchJson(route('invitations.update', $invitation), [
                'title' => $invitation->title,
                'groom' => [
                    'full_name' => 'Andi Wijaya',
                    'nickname' => 'Andi',
                    'father_name' => '',
                    'mother_name' => '',
                    'instagram' => '',
                ],
                'bride' => [
                    'full_name' => 'Sari Dewi',
                    'nickname' => 'Sari',
                    'father_name' => '',
                    'mother_name' => '',
                    'instagram' => '',
                ],
            ])
            ->assertOk();

        $invitation->refresh();
        $this->activate($invitation);

        $this->get(route('invitation.show', $invitation->slug, false) . '?to=' . rawurlencode('Budi'))
            ->assertOk()
            ->assertSee('Andi Wijaya')
            ->assertSee('Sari Dewi')
            ->assertSee('"sample":false', false)
            ->assertSee('"hiddenIds"', false)
            ->assertSee('67c4eb6', false)
            ->assertSee('4a41ce2f', false)
            ->assertSee('background_slideshow_gallery&quot;:[]', false)
            ->assertDontSee('Selamat menempuh hidup baru')
            ->assertDontSee('Rizky');
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
