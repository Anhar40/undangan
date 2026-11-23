<?php

namespace Tests\Feature;

use App\Enums\InvitationStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Invitation;
use App\Models\Music;
use App\Models\Template;
use App\Models\User;
use App\Services\Invitation\InvitationCreationService;
use Database\Seeders\TemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Regression tests for section-scoped saves. A save from one editor panel must
 * never delete or overwrite data owned by another panel.
 */
class EditorSectionIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TemplateSeeder::class);
        Storage::fake('public');
    }

    public function test_saving_another_section_preserves_events_gallery_and_files(): void
    {
        [$user, $invitation] = $this->makeInvitation();

        $this->patchSection($user, $invitation, [
            'events_present' => 1,
            'events' => [[
                'event_type' => 'AKAD',
                'title' => 'Akad Isolasi',
                'event_date' => '2027-06-06',
                'start_time' => '09:00',
                'timezone' => 'Asia/Jakarta',
                'sort_order' => 0,
            ]],
        ])->assertOk();

        $this->seedGallery($user, $invitation, 2);
        $paths = $invitation->galleries()->orderBy('id')->pluck('image')->all();
        $this->assertCount(2, $paths);

        $this->patchSection($user, $invitation, [
            'settings' => ['share_title' => 'Judul Isolasi'],
        ])->assertOk();

        $invitation->refresh();

        $this->assertCount(1, $invitation->events);
        $this->assertSame('Akad Isolasi', $invitation->events->first()->title);
        $this->assertCount(2, $invitation->galleries);
        $this->assertSame($paths, $invitation->galleries()->orderBy('id')->pluck('image')->all());
        $this->assertSame('Judul Isolasi', $invitation->invitationSettings->share_title);

        foreach ($paths as $path) {
            Storage::disk('public')->assertExists($path);
        }
    }

    public function test_deleting_every_gallery_photo_still_works_with_the_present_marker(): void
    {
        [$user, $invitation] = $this->makeInvitation();
        $this->seedGallery($user, $invitation, 2);

        $paths = $invitation->galleries()->pluck('image')->all();

        $this->patchSection($user, $invitation, [
            'gallery_items_present' => 1,
            'gallery_items' => [],
        ])->assertOk();

        $this->assertSame(0, $invitation->galleries()->count());

        foreach ($paths as $path) {
            Storage::disk('public')->assertMissing($path);
        }
    }

    public function test_saving_another_section_preserves_music_autoplay(): void
    {
        [$user, $invitation] = $this->makeInvitation();

        $music = Music::create([
            'title' => 'Musik Isolasi',
            'artist' => 'Uji',
            'file_path' => 'music/isolasi.mp3',
            'status' => 'active',
        ]);

        $this->patchSection($user, $invitation, [
            'settings' => ['music_id' => $music->id, 'music_autoplay' => true],
        ])->assertOk();

        $settings = $invitation->fresh()->invitationSettings;
        $this->assertSame($music->id, $settings->music_id);
        $this->assertTrue((bool) $settings->music_autoplay);

        $this->patchSection($user, $invitation, [
            'events_present' => 1,
            'events' => [[
                'event_type' => 'AKAD',
                'event_date' => '2027-07-07',
                'start_time' => '08:00',
                'sort_order' => 0,
            ]],
        ])->assertOk();

        $settings = $invitation->fresh()->invitationSettings;
        $this->assertSame($music->id, $settings->music_id);
        $this->assertTrue((bool) $settings->music_autoplay);

        $this->patchSection($user, $invitation, [
            'settings' => ['music_autoplay' => false],
        ])->assertOk();

        $this->assertFalse((bool) $invitation->fresh()->invitationSettings->music_autoplay);
    }

    public function test_a_section_patch_without_title_is_accepted_and_preserves_other_data(): void
    {
        [$user, $invitation] = $this->makeInvitation();

        $this->patchSection($user, $invitation, [
            'groom' => [
                'full_name' => 'Ahmad Lengkap',
                'nickname' => 'A',
                'father_name' => 'Ayah Ahmad',
                'mother_name' => 'Ibu Ahmad',
            ],
        ])->assertOk();

        $originalTitle = $invitation->title;

        $this->patchPayload($user, $invitation, [
            'settings' => ['share_title' => 'Tanpa Judul'],
        ])->assertOk();

        $invitation->refresh();

        $this->assertSame($originalTitle, $invitation->title);
        $this->assertSame('Ahmad Lengkap', $invitation->groom->full_name);
        $this->assertSame('A', $invitation->groom->nickname);
        $this->assertSame('Tanpa Judul', $invitation->invitationSettings->share_title);
    }

    public function test_a_partial_person_patch_preserves_omitted_columns(): void
    {
        [$user, $invitation] = $this->makeInvitation();

        $this->patchSection($user, $invitation, [
            'groom' => [
                'full_name' => 'Ahmad Penuh',
                'nickname' => 'Ahmad',
                'father_name' => 'Ayah',
                'mother_name' => 'Ibu',
                'instagram' => '@ahmad',
                'social_links' => "Facebook|https://facebook.com/ahmad",
            ],
        ])->assertOk();

        $this->patchSection($user, $invitation, [
            'groom' => ['nickname' => 'A'],
        ])->assertOk();

        $groom = $invitation->fresh()->groom;

        $this->assertSame('A', $groom->nickname);
        $this->assertSame('Ahmad Penuh', $groom->full_name);
        $this->assertSame('Ayah', $groom->father_name);
        $this->assertSame('Ibu', $groom->mother_name);
        $this->assertSame('@ahmad', $groom->instagram);
        $this->assertSame('Facebook', $groom->social_links[0]['label']);
    }

    public function test_guest_remove_alone_does_not_delete_unlisted_guests(): void
    {
        [$user, $invitation] = $this->makeInvitation();

        $guests = [];

        foreach (['Satu', 'Dua', 'Tiga'] as $name) {
            $guests[] = $invitation->guests()->create([
                'guest_name' => $name,
                'attendance_status' => 'HADIR',
                'guest_count' => 1,
            ]);
        }

        $this->patchSection($user, $invitation, [
            'guest_remove' => [$guests[0]->id => true],
        ])->assertOk();

        $this->assertSame(2, $invitation->guests()->count());
        $this->assertNull($invitation->guests()->find($guests[0]->id));
        $this->assertNotNull($invitation->guests()->find($guests[1]->id));
        $this->assertNotNull($invitation->guests()->find($guests[2]->id));
    }

    public function test_malformed_collection_and_settings_shapes_return_validation_errors(): void
    {
        [$user, $invitation] = $this->makeInvitation();

        $this->patchSection($user, $invitation, [
            'settings' => 'bukan-array',
        ])->assertUnprocessable()->assertJsonValidationErrors('settings');

        $this->patchSection($user, $invitation, [
            'gallery_items' => 'bukan-array',
        ])->assertUnprocessable()->assertJsonValidationErrors('gallery_items');
    }

    public function test_owner_can_open_update_and_reskin_an_active_invitation(): void
    {
        [$user, $invitation] = $this->makeInvitation();
        $this->activate($invitation);

        $this->actingAs($user)
            ->get(route('invitations.edit', $invitation))
            ->assertOk()
            ->assertSee('id="editor-form"', false)
            ->assertSee('Template dapat diganti kapan saja.')
            ->assertSee('class="btn btn-primary save-section"', false);

        $this->patchSection($user, $invitation, [
            'title' => 'Undangan Aktif Diperbarui',
            'settings' => ['share_title' => 'Bagikan Aktif'],
        ])->assertOk();

        $invitation->refresh();

        $this->assertSame('Undangan Aktif Diperbarui', $invitation->title);
        $this->assertSame('Bagikan Aktif', $invitation->invitationSettings->share_title);
        $this->assertTrue($invitation->templateIsLocked());

        $price = (int) $invitation->price;

        $this->actingAs($user)
            ->post(route('invitations.templates.switch', $invitation), [
                'template_id' => Template::firstWhere('slug', 'tema2')->id,
            ])
            ->assertRedirect(route('invitations.edit', $invitation));

        $invitation->refresh();

        $this->assertSame('tema2', $invitation->template_snapshot['renderer']);
        $this->assertSame(InvitationStatus::Active, $invitation->status);
        $this->assertSame($price, (int) $invitation->price);
        $this->assertSame('Undangan Aktif Diperbarui', $invitation->title);
    }

    public function test_expired_invitation_editor_stays_locked(): void
    {
        [$user, $invitation] = $this->makeInvitation();

        $invitation->update(['status' => InvitationStatus::Expired]);

        $this->actingAs($user)
            ->get(route('invitations.edit', $invitation))
            ->assertOk()
            ->assertSee('Editor terkunci')
            ->assertDontSee('id="editor-form"', false);

        $this->patchSection($user, $invitation, [
            'title' => 'Tidak Boleh Diubah',
        ])->assertForbidden();
    }

    public function test_super_admin_gets_a_read_only_editor_and_the_access_is_logged(): void
    {
        [$owner, $invitation] = $this->makeInvitation();

        $admin = User::create([
            'name' => 'Super Admin Audit',
            'email' => 'super-audit@example.test',
            'google_id' => 'google-super-audit',
            'role' => Role::SuperAdmin,
            'status' => UserStatus::Active,
        ]);

        $this->actingAs($admin)
            ->get(route('invitations.edit', $invitation))
            ->assertOk()
            ->assertSee('akses lihat saja')
            ->assertDontSee('class="btn btn-primary save-section"', false);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'action' => 'invitation.viewed',
            'subject_type' => 'invitation',
            'subject_id' => $invitation->id,
        ]);

        $this->assertTrue($owner->is($invitation->user));
    }

    private function activate(Invitation $invitation): void
    {
        $invitation->update([
            'status' => InvitationStatus::Active,
            'payment_status' => PaymentStatus::Settlement,
            'activated_at' => now(),
            'published_at' => $invitation->published_at ?? now(),
        ]);
    }

    /** @return array{0: User, 1: Invitation} */
    private function makeInvitation(): array
    {
        $user = User::create([
            'name' => 'Customer Isolation',
            'email' => 'isolation-' . uniqid() . '@example.test',
            'google_id' => 'google-' . uniqid(),
            'role' => Role::Customer,
            'status' => UserStatus::Active,
        ]);

        $invitation = app(InvitationCreationService::class)
            ->create(Template::firstWhere('slug', 'tema1'), $user);

        return [$user, $invitation];
    }

    private function seedGallery(User $user, Invitation $invitation, int $count): void
    {
        $files = [];

        for ($index = 1; $index <= $count; $index++) {
            $files[] = UploadedFile::fake()->image('galeri-' . $index . '.jpg', 800, 600);
        }

        $this->patchSection($user, $invitation, [
            'gallery_items_present' => 1,
            'gallery_items' => [],
            'gallery_new' => $files,
        ])->assertOk();
    }

    /** @param array<string, mixed> $payload */
    private function patchSection(User $user, Invitation $invitation, array $payload): \Illuminate\Testing\TestResponse
    {
        $payload['title'] = $payload['title'] ?? $invitation->title;

        return $this->patchPayload($user, $invitation, $payload);
    }

    /** @param array<string, mixed> $payload */
    private function patchPayload(User $user, Invitation $invitation, array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)->patch(
            route('invitations.update', $invitation),
            $payload,
            ['Accept' => 'application/json'],
        );
    }
}
