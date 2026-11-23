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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class TemplateManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TemplateSeeder::class);
    }

    public function test_customer_can_see_template_list_and_personalized_sample(): void
    {
        $user = $this->customer();

        $this->actingAs($user)
            ->get(route('templates.index'))
            ->assertOk()
            ->assertSee('Thema 38')
            ->assertSee('Thema 40');

        $template = Template::firstWhere('slug', 'tema1');

        $this->actingAs($user)
            ->get(route('templates.sample', $template) . '?to=Budi%20Santoso')
            ->assertOk()
            ->assertSee('Budi Santoso')
            ->assertSee('window.UD_CONFIG');
    }

    public function test_admin_template_edit_renders_absolute_and_relative_thumbnails(): void
    {
        $template = Template::firstWhere('slug', 'tema3');
        $template->update(['thumbnail' => '/uploads/templates/contoh.jpg']);

        $this->actingAs($this->admin())
            ->get(route('admin.templates.edit', $template))
            ->assertOk()
            ->assertSee('/uploads/templates/contoh.jpg', false);

        $this->assertSame('/uploads/templates/contoh.jpg', $template->thumbnailUrl());

        $template->update(['thumbnail' => 'templates/contoh.jpg']);
        $this->assertStringContainsString('storage/templates/contoh.jpg', (string) $template->thumbnailUrl());

        $template->update(['thumbnail' => 'https://cdn.example.test/contoh.jpg']);
        $this->assertSame('https://cdn.example.test/contoh.jpg', $template->thumbnailUrl());

        $template->update(['thumbnail' => null]);
        $this->assertNull($template->thumbnailUrl());
    }

    public function test_admin_can_update_template_with_decimal_cast_price(): void
    {
        $template = Template::firstWhere('slug', 'tema3');

        $this->actingAs($this->admin())
            ->put(route('admin.templates.update', $template), [
                'name' => 'Thema 39',
                'slug' => 'tema3',
                'description' => null,
                'category' => null,
                'price' => '150000.00',
                'version' => '1.0',
                'status' => 'active',
                'renderer' => 'tema3',
                'template_path' => 'template/tema3.html',
                'features' => [],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('150000.00', $template->fresh()->price);
    }

    public function test_admin_template_update_rejects_fractional_rupiah_with_indonesian_message(): void
    {
        $template = Template::firstWhere('slug', 'tema3');

        $this->actingAs($this->admin())
            ->from(route('admin.templates.edit', $template))
            ->put(route('admin.templates.update', $template), [
                'name' => 'Thema 39',
                'slug' => 'tema3',
                'description' => null,
                'category' => null,
                'price' => '150000.50',
                'version' => '1.0',
                'status' => 'active',
                'renderer' => 'tema3',
                'template_path' => 'template/tema3.html',
                'features' => [],
            ])
            ->assertRedirect(route('admin.templates.edit', $template))
            ->assertSessionHasErrors('price');

        $message = (string) session('errors')->first('price');
        $this->assertStringContainsString('bilangan bulat', $message);
        $this->assertStringNotContainsString('validation.', $message);
    }

    public function test_validation_messages_are_translated_to_indonesian(): void
    {
        $this->assertNotSame('validation.integer', __('validation.integer'));
        $this->assertStringContainsString('bilangan bulat', (string) __('validation.integer'));
    }

    public function test_catalog_keeps_all_five_templates_and_their_renderer_config_in_sync(): void
    {
        $expected = [
            'tema1' => [
                'name' => 'Thema 38',
                'config' => [
                    'renderer' => 'tema1',
                    'countdown_selector' => '.elementor-countdown-wrapper',
                    'countdown_format' => 'timestamp',
                    'guest_label' => 'Kepada yth:',
                ],
            ],
            'tema2' => [
                'name' => 'Thema 40',
                'config' => [
                    'renderer' => 'tema2',
                    'countdown_selector' => '.wpkoi-elements-countdown-items',
                    'countdown_format' => 'text',
                    'guest_label' => 'DEAR TO',
                ],
            ],
            'tema3' => [
                'name' => 'Thema 39',
                'config' => [
                    'renderer' => 'tema3',
                    'countdown_selector' => '.wpkoi-elements-countdown-items',
                    'countdown_format' => 'text',
                    'guest_label' => 'DEAR TO',
                ],
            ],
            'tema4' => [
                'name' => 'Thema 42',
                'config' => [
                    'renderer' => 'tema4',
                    'countdown_selector' => '.wpkoi-elements-countdown-items',
                    'countdown_format' => 'text',
                    'guest_label' => 'DEAR TO',
                ],
            ],
            'tema5' => [
                'name' => 'Thema 41',
                'config' => [
                    'renderer' => 'tema5',
                    'countdown_selector' => '.elementor-countdown-wrapper',
                    'countdown_format' => 'timestamp',
                    'guest_label' => 'DEAR TO',
                ],
            ],
        ];

        $this->assertSame(array_keys($expected), array_keys(Template::rendererOptions()));

        $this->get(route('templates.index'))
            ->assertOk()
            ->assertSee('Pilih Template Undangan');

        foreach ($expected as $slug => $definition) {
            $template = Template::firstWhere('slug', $slug);
            $this->assertNotNull($template, $slug);
            $this->assertSame($definition['name'], $template->name, $slug);
            $this->assertTrue($template->isActive(), $slug);
            $this->assertFileExists($template->absolutePath(), $slug);

            foreach ($definition['config'] as $key => $value) {
                $this->assertSame($value, $template->config[$key] ?? null, $slug . ': ' . $key);
            }

            if (in_array($slug, ['tema3', 'tema4', 'tema5'], true)) {
                $this->assertFalse($template->supports('live_stream'), $slug);
            }

            $this->get(route('templates.sample', $template) . '?to=Budi%20Santoso')
                ->assertOk()
                ->assertSee('Budi Santoso')
                ->assertSee('Contoh ' . $definition['name'])
                ->assertSee('window.UD_CONFIG')
                ->assertSee('/assets/templates/' . $slug . '/', false);
        }
    }

    public function test_guests_can_browse_catalog_and_open_the_demo_directly_without_login(): void
    {
        $template = Template::firstWhere('slug', 'tema2');

        $this->get(route('templates.index'))
            ->assertOk()
            ->assertSee('Pilih Template Undangan')
            ->assertSee('Lihat Demo')
            ->assertSee(route('templates.sample', $template))
            ->assertSee('Masuk untuk Memilih');

        $this->get(route('templates.sample', $template) . '?to=Budi%20Santoso')
            ->assertOk()
            ->assertSee('Budi Santoso')
            ->assertSee('window.UD_CONFIG');

        // Halaman perantara preview sudah dihapus.
        $this->get('/templates/' . $template->id . '/preview')->assertNotFound();
        $this->assertNull(Route::getRoutes()->getByName('templates.preview'));

        // Tombol preview editor juga sudah dihapus.
        $this->assertNull(Route::getRoutes()->getByName('invitations.preview'));
    }

    public function test_selecting_template_creates_snapshot_and_ten_hour_preview(): void
    {
        $user = $this->customer();
        $template = Template::firstWhere('slug', 'tema1');

        $this->actingAs($user)
            ->post(route('templates.select', $template))
            ->assertRedirectContains('/invitations/')
            ->assertSessionHas('status');

        $invitation = Invitation::firstOrFail();

        $this->assertSame($user->id, $invitation->user_id);
        $this->assertSame(InvitationStatus::Draft, $invitation->status);
        $this->assertSame(PaymentStatus::Pending, $invitation->payment_status);
        $this->assertSame($template->name, $invitation->template_name);
        $this->assertSame($template->version, $invitation->template_version);
        $this->assertSame($template->price, $invitation->price);
        $this->assertNotNull($invitation->template_snapshot['hash']);
        $this->assertTrue(Storage::disk('local')->exists($invitation->template_snapshot['path']));

        $hours = $invitation->preview_started_at->diffInHours($invitation->preview_expires_at);
        $this->assertEquals((int) config('invitation.preview_hours'), $hours);
    }

    public function test_customer_cannot_view_another_customers_unactivated_invitation(): void
    {
        $owner = $this->customer('owner@example.test');
        $other = $this->customer('other@example.test');

        $this->actingAs($owner)->post(route('templates.select', Template::firstWhere('slug', 'tema2')));
        $invitation = Invitation::firstOrFail();

        $this->actingAs($other)
            ->get(route('invitation.show', $invitation->slug, false))
            ->assertNotFound();
    }

    public function test_admin_template_update_does_not_mutate_existing_invitation_snapshot(): void
    {
        $this->actingAs($this->customer())
            ->post(route('templates.select', Template::firstWhere('slug', 'tema1')));

        $invitation = Invitation::firstOrFail();
        $originalFeatures = $invitation->templateFeatures();
        $originalHash = $invitation->template_snapshot['hash'];

        $template = Template::firstWhere('slug', 'tema1');

        $this->actingAs($this->admin())
            ->put(route('admin.templates.update', $template), [
                'name' => 'Thema 38 Revisi',
                'slug' => 'tema1',
                'description' => 'Deskripsi baru',
                'category' => 'Klasik',
                'price' => 200000,
                'version' => '2.0',
                'status' => 'active',
                'renderer' => 'tema1',
                'template_path' => 'template/tema1.html',
                'features' => ['story' => false, 'gallery' => false],
            ])
            ->assertRedirect();

        $this->assertSame('2.0', $template->refresh()->version);

        $invitation->refresh();
        $this->assertSame('1.0', $invitation->template_version);
        $this->assertSame($originalHash, $invitation->template_snapshot['hash']);
        $this->assertSame($originalFeatures, $invitation->templateFeatures());
    }

    public function test_admin_cannot_delete_template_already_used_by_invitation(): void
    {
        $template = Template::firstWhere('slug', 'tema1');

        $this->actingAs($this->customer())
            ->post(route('templates.select', $template));

        $this->actingAs($this->admin())
            ->from(route('admin.templates.index'))
            ->delete(route('admin.templates.destroy', $template))
            ->assertRedirect(route('admin.templates.index'))
            ->assertSessionHasErrors('template');
    }

    public function test_admin_can_add_template_with_uploaded_html(): void
    {
        Storage::fake('local');

        $html = '<!DOCTYPE html><html><head><title>Custom</title></head><body>Custom</body></html>';

        $this->actingAs($this->admin())
            ->post(route('admin.templates.store'), [
                'name' => 'Template Custom',
                'slug' => 'custom',
                'description' => 'Template unggulan admin',
                'category' => 'Modern',
                'price' => 175000,
                'version' => '1.0',
                'status' => 'active',
                'renderer' => 'tema2',
                'template_html' => UploadedFile::fake()->createWithContent('custom.html', $html),
                'features' => ['story' => true],
            ])
            ->assertRedirect();

        $template = Template::firstWhere('slug', 'custom');
        $this->assertNotNull($template);
        $this->assertStringStartsWith('storage/templates/', $template->template_path);
        $this->assertFileExists($template->absolutePath());
        $this->assertTrue($template->supports('story'));
        $this->assertFalse($template->supports('gallery'));
    }

    public function test_admin_template_update_removes_the_previous_uploaded_html(): void
    {
        Storage::fake('local');

        $base = [
            'name' => 'Template Bersih',
            'slug' => 'bersih',
            'description' => 'Uji pembersihan berkas',
            'category' => 'Modern',
            'price' => 150000,
            'version' => '1.0',
            'status' => 'active',
            'renderer' => 'tema2',
            'features' => ['gallery' => true],
        ];

        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.templates.store'), $base + [
                'template_html' => UploadedFile::fake()->createWithContent('old.html', '<html><body>Lama</body></html>'),
            ])
            ->assertRedirect();

        $template = Template::firstWhere('slug', 'bersih');
        $oldPath = Str::after($template->template_path, 'storage/');
        $this->assertTrue(Storage::disk('local')->exists($oldPath));

        $this->actingAs($admin)
            ->put(route('admin.templates.update', $template), $base + [
                'version' => '2.0',
                'template_html' => UploadedFile::fake()->createWithContent('new.html', '<html><body>Baru</body></html>'),
            ])
            ->assertRedirect();

        $this->assertFalse(Storage::disk('local')->exists($oldPath));
        $this->assertTrue(Storage::disk('local')->exists(Str::after($template->fresh()->template_path, 'storage/')));
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

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin Test',
            'email' => 'admin@example.test',
            'google_id' => 'google-admin',
            'role' => Role::SuperAdmin,
            'status' => UserStatus::Active,
        ]);
    }
}
