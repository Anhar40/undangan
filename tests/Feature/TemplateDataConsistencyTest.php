<?php

namespace Tests\Feature;

use App\Enums\EventType;
use App\Enums\InvitationStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Invitation;
use App\Models\InvitationGallery;
use App\Models\InvitationGreeting;
use App\Models\Music;
use App\Models\Template;
use App\Models\User;
use Database\Seeders\TemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\Support\TemplateFidelityAssertions;
use Tests\TestCase;

/**
 * End-to-end guarantee: the Kelola Undangan form, the database and the public
 * invitation must all use one consistent data structure for BOTH templates.
 */
class TemplateDataConsistencyTest extends TestCase
{
    use RefreshDatabase;
    use TemplateFidelityAssertions;

    private const SAMPLE_MARKERS = [
        'Putri Pratiwi', 'Putra Pratama', 'Putri &amp; Putra', 'Putra &amp; Putri',
        '12.12.2023', '09.07.2023', 'Nama Tamu', 'Keluarga Besar Kedua Mempelai',
        'pexels-azra', 'pexels-photo', 'bni-logo', 'api.sewaundangan.com/music/',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TemplateSeeder::class);
        Storage::fake('public');
    }

    public function test_both_templates_render_every_customer_field_exactly(): void
    {
        foreach (['tema1', 'tema2'] as $slug) {
            [$user, $invitation] = $this->makeInvitation($slug);
            $payload = $this->fullPayload($invitation);

            $this->actingAs($user)
                ->patch(route('invitations.update', $invitation), $payload, ['Accept' => 'application/json'])
                ->assertOk();

            $this->activate($invitation);
            $html = (string) $this->getPublic($invitation)->getContent();

            // Couple, guests, message, quote and source.
            $this->assertStringContainsString('Siti &amp; Ahmad', $html, $slug);
            $this->assertStringContainsString('12.12.2026', $html, $slug);
            $this->assertStringContainsString('Budi Santoso', $html, $slug);
            $this->assertStringContainsString('Pesan khusus untuk tamu.', $html, $slug);
            $this->assertStringContainsString('Kutipan khusus.', $html, $slug);
            $this->assertStringContainsString('Sumber khusus.', $html, $slug);

            // Event end time, timezone and description.
            $this->assertStringContainsString('09:30 - 11:45 WIB', $html, $slug);
            $this->assertStringContainsString('Keterangan acara khusus.', $html, $slug);
            $this->assertStringContainsString('Gedung Khusus', $html, $slug);
            $this->assertStringContainsString('Jalan Khusus No. 9', $html, $slug);

            // Story date and family names.
            $this->assertStringContainsString('2019 — Awal Ketemu', $html, $slug);
            $this->assertStringContainsString('Keluarga A', $html, $slug);
            $this->assertStringContainsString('Keluarga B', $html, $slug);

            // Gift data binds to the native copy-text/heading widgets. The
            // native envelope panel may start collapsed, so assert the bound
            // values in the source markup and only assert injected nodes are
            // absent from what the visitor sees initially.
            $this->assertStringContainsString('Amplop Khusus', $html, $slug);
            $this->assertStringContainsString('1234567890', $html, $slug);
            $visible = $this->visibleMarkup($html);
            $this->assertStringNotContainsString('ud-envelope-bank-mark', $visible, $slug);
            if ($slug === 'tema2') {
                // Thema 40 has a native address block, so the delivery address
                // is bound there and must be visible on the public invitation.
                $this->assertStringContainsString('Alamat Pengiriman Kado', $visible, $slug);
                $this->assertStringContainsString('Jalan Kado No. 1', $visible, $slug);
            } else {
                $this->assertStringNotContainsString('Alamat Pengiriman Kado', $visible, $slug);
                $this->assertStringNotContainsString('Jalan Kado No. 1', $visible, $slug);
            }
            $this->assertSame(0, substr_count($visible, 'class="ud-gift-address"'), $slug);
            $this->assertStringNotContainsString('gift-qr-0', $html, $slug);
            $this->assertStringNotContainsString('name="gift_accounts[0][gift_address]"', $html, $slug);
            $this->assertStringContainsString('window.UD_CONFIG', $html, $slug);
            $this->assertStringContainsString('wa.me/?text=', $html, $slug);
            $this->assertStringContainsString('name="viewport"', $html, $slug);

            // No sample content is visible, no leaked defaults or null artifacts.
            $this->assertNoVisibleSample($html, self::SAMPLE_MARKERS, $slug);
            $this->assertStringNotContainsString('undefined', $html, $slug);
            $this->assertStringNotContainsString('>null<', $html, $slug);
        }
    }

    public function test_both_templates_wire_media_widgets_and_public_endpoints(): void
    {
        $music = Music::create([
            'title' => 'Lagu Uji',
            'artist' => 'Artis Uji',
            'file_path' => 'music/theme-test.mp3',
            'status' => 'active',
        ]);

        foreach (['tema1', 'tema2'] as $slug) {
            [$user, $invitation] = $this->makeInvitation($slug);
            $payload = $this->fullPayload($invitation);
            $payload['settings']['music_id'] = $music->id;
            $payload['settings']['whatsapp_number'] = '0812 3456 7890';
            $payload['settings']['whatsapp_display_name'] = 'Tim Undangan';
            $payload['settings']['whatsapp_message'] = 'Undangan siap dibuka.';
            $payload['backgrounds']['cover'] = [UploadedFile::fake()->image('latar.jpg', 800, 600)];

            $this->actingAs($user)
                ->patch(route('invitations.update', $invitation), $payload, ['Accept' => 'application/json'])
                ->assertOk();

            $invitation->greetings()->create([
                'guest_name' => 'Tam undangan',
                'message' => 'Ucapan dari tamu.',
                'attendance_status' => 'HADIR',
                'status' => 'approved',
                'ip_address' => '127.0.0.1',
            ]);

            $this->activate($invitation);
            $html = (string) $this->getPublic($invitation)->getContent();

            // Customer music replaces the template sample track on both templates.
            $this->assertStringContainsString('music/theme-test.mp3', $html, $slug);
            $this->assertStringNotContainsString('api.sewaundangan.com/music/', $html, $slug);

            // The native map and Instagram widgets are real hrefs. Extra social
            // links and live-stream URLs have no native slot, so they are not
            // rendered as invented buttons.
            $this->assertStringContainsString('https://maps.google.com/?q=khusus', $html, $slug);
            $this->assertStringContainsString('https://instagram.com/ahmad', $html, $slug);
            $this->assertStringContainsString('https://instagram.com/siti', $html, $slug);
            foreach (['https://youtube.com/live/khusus', 'https://facebook.com/ahmad', 'https://youtube.com/@ahmad', 'https://tiktok.com/@siti'] as $unsupported) {
                $this->assertStringNotContainsString($unsupported, $html, $slug . ': ' . $unsupported);
            }

            // WhatsApp contact config is normalized and exposed to the runtime.
            $this->assertStringContainsString('6281234567890', $html, $slug);
            $this->assertStringContainsString('wa.me/6281234567890', $html, $slug);
            $this->assertStringContainsString('Tim Undangan', $html, $slug);
            $this->assertStringContainsString('Undangan siap dibuka.', $html, $slug);

            // The Foto dan Galeri cover reaches the rendered markup, and gift
            // data stays inside the template's own widgets.
            $stored = $invitation->fresh()->invitationSettings->custom_settings['photo_gallery'];
            $this->assertStringContainsString(
                asset('storage/' . $stored['cover'][0]),
                $html,
                $slug,
            );
            $this->assertStringContainsString('Amplop Khusus', $html, $slug);
            $this->assertStringContainsString('1234567890', $html, $slug);
            $visible = $this->visibleMarkup($html);
            $this->assertStringNotContainsString('class="ud-envelope-card"', $visible, $slug);
            $this->assertStringNotContainsString('data-ud-envelope-copy=', $visible, $slug);
            $this->assertStringNotContainsString('gift-qr-0', $html, $slug);

            // Guestbook/RSVP is rewired to Laravel and shows approved greetings.
            $this->assertStringContainsString('/u/' . $invitation->slug . '/greetings', $html, $slug);
            $this->assertStringContainsString('Tam undangan', $html, $slug);
            $this->assertStringContainsString('Ucapan dari tamu.', $html, $slug);
            $this->assertStringContainsString('Hadir', $html, $slug);

            if ($slug === 'tema2') {
                // The corrected heading must be escaped once, not double-escaped.
                $this->assertStringContainsString('Doa Restu &amp; Ucapan', $html);
                $this->assertStringNotContainsString('Doa Restu &amp;amp; Ucapan', $html);
                $this->assertStringNotContainsString('Wedding Wish', $html);
            }
        }
    }

    public function test_new_templates_render_customer_data_and_gift_without_sample_leakage(): void
    {
        $templates = [
            'tema3' => [
                'present' => ['12/12/2026', 'Sabtu', 'wpkoi-elements-countdown-items'],
                'absent' => ['pexels-artem', 'Lirik-Lagu'],
            ],
            'tema4' => [
                'present' => ['Desember<br>2026', 'wpkoi-elements-countdown-items', 'Salin Alamat'],
                'absent' => ['IMG-20230508', 'Payung-Teduh'],
            ],
            'tema5' => [
                'present' => ['Sabtu, 12 Desember 2026', 'Pukul : 09:30 - 11:45 WIB', 'elementor-countdown-wrapper'],
                'absent' => ['pexels-willy', 'Nuca-Mahalini'],
            ],
        ];

        foreach ($templates as $slug => $expectations) {
            [$user, $invitation] = $this->makeInvitation($slug);

            $this->actingAs($user)
                ->patch(route('invitations.update', $invitation), $this->fullPayload($invitation), ['Accept' => 'application/json'])
                ->assertOk();

            $this->activate($invitation);
            $html = (string) $this->getPublic($invitation)->getContent();

            foreach ([
                'Siti &amp; Ahmad',
                'Budi Santoso',
                'Pesan khusus untuk tamu.',
                'Kutipan khusus.',
                'Sumber khusus.',
                '09:30 - 11:45 WIB',
                'Keterangan acara khusus.',
                'Gedung Khusus',
                'Jalan Khusus No. 9',
                '2019 — Awal Ketemu',
                'Keluarga A',
                'Keluarga B',
                'Amplop Khusus',
                '1234567890',
                'window.UD_CONFIG',
                'name="viewport"',
            ] as $needle) {
                $this->assertStringContainsString($needle, $html, $slug . ': ' . $needle);
            }

            $visible = $this->visibleMarkup($html);
            $this->assertStringContainsString('Amplop Khusus', $html, $slug);
            $this->assertStringContainsString('1234567890', $html, $slug);
            $this->assertStringNotContainsString('class="ud-gift-address"', $visible, $slug);
            $this->assertStringNotContainsString('gift-qr-0', $html, $slug);

            $this->assertNoVisibleSample(
                $html,
                array_merge(self::SAMPLE_MARKERS, $expectations['absent']),
                $slug,
            );
            $this->assertStringNotContainsString('undefined', $html, $slug);
            $this->assertStringNotContainsString('>null<', $html, $slug);

            foreach ($expectations['present'] as $needle) {
                $this->assertStringContainsString($needle, $html, $slug . ': ' . $needle);
            }
        }
    }

    public function test_tema1_public_page_keeps_the_native_sample_structure_with_database_photos(): void
    {
        [$user, $invitation] = $this->makeInvitation('tema1');
        $payload = $this->fullPayload($invitation);
        $payload['photos'] = [
            'groom' => UploadedFile::fake()->image('groom.jpg', 400, 400),
            'bride' => UploadedFile::fake()->image('bride.jpg', 400, 400),
        ];

        $this->actingAs($user)
            ->patch(route('invitations.update', $invitation), $payload, ['Accept' => 'application/json'])
            ->assertOk();

        $invitation->refresh();
        $this->assertNotNull($invitation->groom->photo);
        $this->assertNotNull($invitation->bride->photo);
        $groomPhoto = asset('storage/' . $invitation->groom->photo);
        $bridePhoto = asset('storage/' . $invitation->bride->photo);
        $custom = $invitation->invitationSettings->custom_settings;
        $coverPhotos = array_map(
            static fn (string $path): string => asset('storage/' . $path),
            $custom['photo_gallery']['cover'],
        );
        $supportingPhotos = array_map(
            static fn (string $path): string => asset('storage/' . $path),
            $custom['supporting_photos'],
        );
        $footerPhotos = array_map(
            static fn (string $path): string => asset('storage/' . $path),
            $custom['photo_gallery']['footer'],
        );

        $this->activate($invitation);
        $html = (string) $this->getPublic($invitation)->getContent();
        $sample = (string) $this->get(route('templates.sample', Template::firstWhere('slug', 'tema1')))->getContent();

        // The whole native Elementor wrapper and its top-level sections must
        // survive unchanged, so sample-specific CSS selectors still apply.
        $sectionIds = $this->elementorSectionIds($html);
        $this->assertSame($this->elementorSectionIds($sample), $sectionIds);
        $this->assertCount(16, $sectionIds);

        $sampleGalleries = $this->slideshowGalleries($sample);
        $galleries = $this->slideshowGalleries($html);
        $this->assertSame(array_keys($sampleGalleries), array_keys($galleries));

        // Decorative slideshows come from Foto dan Galeri, never from couple
        // portraits. Cover/footer hold up to two photos; the couple
        // backgrounds now follow Foto Pendukung.
        $this->assertSame($coverPhotos, $galleries['3fe3ed95']);
        $this->assertSame($coverPhotos, $galleries['30eed981']);
        $this->assertSame([$supportingPhotos[1]], $galleries['5d7ac283']);
        $this->assertSame([$supportingPhotos[0]], $galleries['5c22588c']);
        $this->assertSame($footerPhotos, $galleries['27998b08']);
        $this->assertSame([], $galleries['469f4f2']);
        $this->assertSame([], $galleries['2233fd63']);
        $this->assertSame([], $galleries['77b5edb1']);

        $this->assertStringContainsString('Ahmad Fauzi', $html);
        $this->assertStringContainsString('Siti Aisyah', $html);
        foreach ([...$coverPhotos, ...$footerPhotos] as $customerPhoto) {
            $this->assertStringContainsString($customerPhoto, $html);
        }
        $this->assertStringNotContainsString($groomPhoto, $html);
        $this->assertStringNotContainsString($bridePhoto, $html);
        $this->assertStringContainsString('data-id="380098b4"', $html);
        $this->assertStringContainsString('Terima Kasih', $html);
        $this->assertStringContainsString('Atas kehadiran dan Doa Restunya', $html);
        $this->assertStringNotContainsString('Putri Pratiwi', $html);

        // No rejected custom visual layer may replace native Elementor widgets.
        $this->assertStringNotContainsString('data-ud-couple-highlight', $html);
        $this->assertStringNotContainsString('data-ud-static-slideshow', $html);
        $this->assertStringNotContainsString('.elementor-invisible{visibility:visible!important', $html);
    }

    public function test_thema39_event_slideshows_use_cover_and_footer_photos(): void
    {
        [$user, $invitation] = $this->makeInvitation('tema3');

        $this->actingAs($user)
            ->patch(route('invitations.update', $invitation), $this->fullPayload($invitation), ['Accept' => 'application/json'])
            ->assertOk();

        $custom = $invitation->fresh()->invitationSettings->custom_settings;
        $coverPhotos = array_map(
            static fn (string $path): string => asset('storage/' . $path),
            $custom['photo_gallery']['cover'],
        );
        $footerPhotos = array_map(
            static fn (string $path): string => asset('storage/' . $path),
            $custom['photo_gallery']['footer'],
        );

        $this->activate($invitation);
        $html = (string) $this->getPublic($invitation)->getContent();
        $sample = (string) $this->get(route('templates.sample', Template::firstWhere('slug', 'tema3')))->getContent();

        $galleries = $this->slideshowGalleries($html);
        $sampleGalleries = $this->slideshowGalleries($sample);

        $this->assertSame(array_keys($sampleGalleries), array_keys($galleries));

        // Only the three columns that carry a stock gallery in the original
        // Thema 39 source are refilled. The two sibling columns are intentionally
        // empty in the template and must stay empty on public invitations.
        $this->assertSame($coverPhotos, $galleries['4684d4b4']);
        $this->assertSame($coverPhotos, $galleries['b9055e3']);
        $this->assertSame($footerPhotos, $galleries['15865fca']);
        $this->assertSame([], $galleries['4c421c4f']);
        $this->assertSame([], $galleries['37c0b978']);
        $this->assertSame([], $sampleGalleries['4c421c4f']);
        $this->assertSame([], $sampleGalleries['37c0b978']);

        foreach (['4684d4b4', 'b9055e3', '15865fca'] as $id) {
            $this->assertStringNotContainsString('pexels-artem', implode(' ', $galleries[$id]));
        }

        // The sample still demonstrates the untouched stock photography.
        $this->assertStringContainsString('pexels-artem', implode(' ', $sampleGalleries['4684d4b4']));
    }

    public function test_thema39_wedding_gift_binds_account_and_address_to_native_widgets(): void
    {
        [$user, $invitation] = $this->makeInvitation('tema3');

        $this->actingAs($user)
            ->patch(route('invitations.update', $invitation), $this->fullPayload($invitation), ['Accept' => 'application/json'])
            ->assertOk();

        $this->activate($invitation);
        $html = (string) $this->getPublic($invitation)->getContent();
        $visible = $this->visibleMarkup($html);
        $config = $this->fidelityConfig($html);
        $hidden = array_merge($config['hiddenIds'] ?? [], $config['hiddenWidgets'] ?? []);

        // Both native gift blocks and their widgets must stay in the document
        // and be visible when the customer actually supplied data for them.
        foreach (['12489c64', '39b55ef', 'a77be84', '18f5bfce', '680ac935', '7fd7436', '20ea64a'] as $id) {
            $this->assertNotNull(
                $this->fidelityXPath($html)->query('//*[@data-id="' . $id . '"]')->item(0),
                'native gift element missing: ' . $id,
            );
            $this->assertStringContainsString('data-id="' . $id . '"', $visible, 'native gift element not visible: ' . $id);
            $this->assertNotContains($id, $hidden, 'native gift element hidden despite customer data: ' . $id);
        }

        foreach ([
            'Wedding Gift',
            'Amplop Khusus',
            '1234567890',
            'a.n Siti Aisyah',
            'Salin Rekening',
            'Alamat Pengiriman Kado:',
            'Jalan Kado No. 1',
            'Salin Alamat',
        ] as $needle) {
            $this->assertStringContainsString($needle, $visible, 'gift data not visible: ' . $needle);
        }

        // The stock bank logo has no customer field and stays hidden; no
        // envelope panel/button may be injected over the original design.
        $this->assertContains('3c928698', $hidden);
        $this->assertStringNotContainsString('id="udgiftpanel"', $html);
        $this->assertStringNotContainsString('id="udgiftbutton"', $html);
        $this->assertStringNotContainsString('class="ud-envelope-card"', $html);
    }

    public function test_thema42_event_slideshows_use_cover_photos(): void
    {
        [$user, $invitation] = $this->makeInvitation('tema4');

        $this->actingAs($user)
            ->patch(route('invitations.update', $invitation), $this->fullPayload($invitation), ['Accept' => 'application/json'])
            ->assertOk();

        $custom = $invitation->fresh()->invitationSettings->custom_settings;
        $coverPhotos = array_map(
            static fn (string $path): string => asset('storage/' . $path),
            $custom['photo_gallery']['cover'],
        );

        $this->activate($invitation);
        $html = (string) $this->getPublic($invitation)->getContent();
        $sample = (string) $this->get(route('templates.sample', Template::firstWhere('slug', 'tema4')))->getContent();

        $galleries = $this->slideshowGalleries($html);
        $sampleGalleries = $this->slideshowGalleries($sample);

        $this->assertSame(array_keys($sampleGalleries), array_keys($galleries));

        // Only the three columns that carry a stock gallery in the original
        // Thema 42 source are refilled from Foto Sampul/Cover.
        foreach (['528b3f36', '5e892ecd', '5204381e'] as $id) {
            $this->assertSame($coverPhotos, $galleries[$id]);
            $this->assertStringNotContainsString('IMG-20230508', implode(' ', $galleries[$id]));
            $this->assertStringContainsString('IMG-20230508', implode(' ', $sampleGalleries[$id]));
        }

        // The two sibling columns are intentionally empty in the source.
        foreach (['565f4543', '3d6059f4'] as $id) {
            $this->assertSame([], $galleries[$id]);
            $this->assertSame([], $sampleGalleries[$id]);
        }
    }

    public function test_thema42_wedding_gift_binds_account_and_address_to_native_widgets(): void
    {
        [$user, $invitation] = $this->makeInvitation('tema4');

        $this->actingAs($user)
            ->patch(route('invitations.update', $invitation), $this->fullPayload($invitation), ['Accept' => 'application/json'])
            ->assertOk();

        $this->activate($invitation);
        $html = (string) $this->getPublic($invitation)->getContent();
        $visible = $this->visibleMarkup($html);
        $config = $this->fidelityConfig($html);
        $hidden = array_merge($config['hiddenIds'] ?? [], $config['hiddenWidgets'] ?? []);

        foreach (['50a93844', '54dafe21', '35335810', '8716a95', 'a7e0da', '1d424bd1', '9ed974b'] as $id) {
            $this->assertNotNull(
                $this->fidelityXPath($html)->query('//*[@data-id="' . $id . '"]')->item(0),
                'native gift element missing: ' . $id,
            );
            $this->assertStringContainsString('data-id="' . $id . '"', $visible, 'native gift element not visible: ' . $id);
            $this->assertNotContains($id, $hidden, 'native gift element hidden despite customer data: ' . $id);
        }

        foreach ([
            'Wedding Gift',
            'Amplop Khusus',
            '1234567890',
            'a.n Siti Aisyah',
            'Salin Rekening',
            'Alamat Pengiriman Kado:',
            'Jalan Kado No. 1',
            'Salin Alamat',
        ] as $needle) {
            $this->assertStringContainsString($needle, $visible, 'gift data not visible: ' . $needle);
        }

        $this->assertContains('1e1170bb', $hidden);
        $this->assertStringNotContainsString('id="udgiftpanel"', $html);
        $this->assertStringNotContainsString('id="udgiftbutton"', $html);
        $this->assertStringNotContainsString('class="ud-envelope-card"', $html);
    }

    public function test_thema40_the_wedding_of_background_uses_cover_photos(): void
    {
        [$user, $invitation] = $this->makeInvitation('tema2');

        $this->actingAs($user)
            ->patch(route('invitations.update', $invitation), $this->fullPayload($invitation), ['Accept' => 'application/json'])
            ->assertOk();

        $custom = $invitation->fresh()->invitationSettings->custom_settings;
        $coverPhotos = array_map(
            static fn (string $path): string => asset('storage/' . $path),
            $custom['photo_gallery']['cover'],
        );
        $footerPhotos = array_map(
            static fn (string $path): string => asset('storage/' . $path),
            $custom['photo_gallery']['footer'],
        );

        $this->activate($invitation);
        $html = (string) $this->getPublic($invitation)->getContent();
        $sample = (string) $this->get(route('templates.sample', Template::firstWhere('slug', 'tema2')))->getContent();

        $galleries = $this->slideshowGalleries($html);
        $sampleGalleries = $this->slideshowGalleries($sample);

        // The opening cover and the "THE WEDDING OF" section both carry the
        // cover upload. The sub-cover keeps following the footer upload.
        $this->assertSame($coverPhotos, $galleries['56efded2']);
        $this->assertSame($coverPhotos, $galleries['17b7bb78']);
        $this->assertSame($footerPhotos, $galleries['5e3f969e']);

        $this->assertStringNotContainsString('pexels-photo-8526247', implode(' ', $galleries['17b7bb78']));
        $this->assertStringContainsString('pexels-photo-8526247', implode(' ', $sampleGalleries['17b7bb78']));
    }

    public function test_thema40_wedding_gift_binds_account_and_address_to_native_widgets(): void
    {
        [$user, $invitation] = $this->makeInvitation('tema2');

        $this->actingAs($user)
            ->patch(route('invitations.update', $invitation), $this->fullPayload($invitation), ['Accept' => 'application/json'])
            ->assertOk();

        $this->activate($invitation);
        $html = (string) $this->getPublic($invitation)->getContent();
        $visible = $this->visibleMarkup($html);
        $config = $this->fidelityConfig($html);
        $hidden = array_merge($config['hiddenIds'] ?? [], $config['hiddenWidgets'] ?? []);

        foreach ([
            '111523f1', '4d472619', '18363c3b', '7cf88fd6', '265ec209',
            '7eb8f639', '6d98f144', '61f9301f', '6a3f8839', '3c3bc048',
            '4d72bbb8', '7a9e4961', '55fdfd25',
        ] as $id) {
            $this->assertNotNull(
                $this->fidelityXPath($html)->query('//*[@data-id="' . $id . '"]')->item(0),
                'native gift element missing: ' . $id,
            );
            $this->assertStringContainsString('data-id="' . $id . '"', $visible, 'native gift element not visible: ' . $id);
            $this->assertNotContains($id, $hidden, 'native gift element hidden despite customer data: ' . $id);
        }

        foreach ([
            'Wedding Gift',
            'Amplop Khusus',
            '1234567890',
            'a.n Siti Aisyah',
            'Salin Rekening',
            'Alamat Pengiriman Kado:',
            'Jalan Kado No. 1',
            'Salin Alamat',
        ] as $needle) {
            $this->assertStringContainsString($needle, $visible, 'gift data not visible: ' . $needle);
        }

        // The stock bank logo has no customer field to bind to; applyGifts()
        // hides it inline when the account has no QR upload.
        $this->assertNotNull(
            $this->fidelityXPath($html)->query('//*[@data-id="19b4f468"]')->item(0),
        );
        $this->assertStringNotContainsString('data-id="19b4f468"', $visible);
        $this->assertStringNotContainsString('id="udgiftpanel"', $html);
        $this->assertStringNotContainsString('id="udgiftbutton"', $html);
        $this->assertStringNotContainsString('class="ud-envelope-card"', $html);
    }

    public function test_thema41_gate_slideshow_uses_cover_photos(): void
    {
        [$user, $invitation] = $this->makeInvitation('tema5');

        $this->actingAs($user)
            ->patch(route('invitations.update', $invitation), $this->fullPayload($invitation), ['Accept' => 'application/json'])
            ->assertOk();

        $custom = $invitation->fresh()->invitationSettings->custom_settings;
        $coverPhotos = array_map(
            static fn (string $path): string => asset('storage/' . $path),
            $custom['photo_gallery']['cover'],
        );

        $this->activate($invitation);
        $html = (string) $this->getPublic($invitation)->getContent();
        $sample = (string) $this->get(route('templates.sample', Template::firstWhere('slug', 'tema5')))->getContent();

        $galleries = $this->slideshowGalleries($html);
        $sampleGalleries = $this->slideshowGalleries($sample);

        $this->assertSame($coverPhotos, $galleries['90e124f']);
        $this->assertStringNotContainsString('pexels-willy-wft', implode(' ', $galleries['90e124f']));
        $this->assertStringContainsString('pexels-willy-wft', implode(' ', $sampleGalleries['90e124f']));
    }

    public function test_thema41_wedding_gift_binds_account_and_address_to_native_widgets(): void
    {
        [$user, $invitation] = $this->makeInvitation('tema5');

        $this->actingAs($user)
            ->patch(route('invitations.update', $invitation), $this->fullPayload($invitation), ['Accept' => 'application/json'])
            ->assertOk();

        $this->activate($invitation);
        $html = (string) $this->getPublic($invitation)->getContent();
        $visible = $this->visibleMarkup($html);
        $config = $this->fidelityConfig($html);
        $hidden = array_merge($config['hiddenIds'] ?? [], $config['hiddenWidgets'] ?? []);

        foreach ([
            '30764fe7', '8126b21', '7394cb94', 'c8e81b4',
            '72bab81f', '49f3294d', '2105d08f',
            '371ca36', '3c0cb7a', '6da138f', 'a07c3d9',
        ] as $id) {
            $this->assertNotNull(
                $this->fidelityXPath($html)->query('//*[@data-id="' . $id . '"]')->item(0),
                'native gift element missing: ' . $id,
            );
            $this->assertStringContainsString('data-id="' . $id . '"', $visible, 'native gift element not visible: ' . $id);
            $this->assertNotContains($id, $hidden, 'native gift element hidden despite customer data: ' . $id);
        }

        foreach ([
            'Wedding Gift',
            'Amplop Khusus',
            '1234567890',
            'a.n Siti Aisyah',
            'Salin Rekening',
            'Alamat Pengirirman Kado:',
            'Jalan Kado No. 1',
            'Salin Alamat',
        ] as $needle) {
            $this->assertStringContainsString($needle, $visible, 'gift data not visible: ' . $needle);
        }

        // Only one account was supplied, so the remaining native bank cards
        // stay hidden. Their stock logos have no customer-owned field.
        foreach (['080baa5', '9954c09', '28795dca', '1e7a5042', '3cd4ce6', '2446b53'] as $id) {
            $this->assertContains($id, $hidden, 'empty bank card or stock logo is visible: ' . $id);
        }

        $this->assertStringNotContainsString('id="udgiftpanel"', $html);
        $this->assertStringNotContainsString('id="udgiftbutton"', $html);
        $this->assertStringNotContainsString('class="ud-envelope-card"', $html);
    }

    public function test_both_templates_hide_empty_optional_sections_without_placeholders(): void
    {
        $sampleWidgetIds = [
            'tema1' => ['542f3a1f', '60d7dd04'],
            'tema2' => ['3fba8d42'],
        ];

        foreach (['tema1', 'tema2'] as $slug) {
            [$user, $invitation] = $this->makeInvitation($slug);
            $payload = $this->minimalPayload($invitation);

            $this->actingAs($user)
                ->patch(route('invitations.update', $invitation), $payload, ['Accept' => 'application/json'])
                ->assertOk();

            $this->activate($invitation);
            $html = (string) $this->getPublic($invitation)->getContent();

            // Server-side hiding style exists so nothing flashes before JS.
            $this->assertStringContainsString('display:none!important', $html, $slug);
            $this->assertNoVisibleSample($html, self::SAMPLE_MARKERS, $slug);

            // Sample widgets stay in the markup but are hidden through the
            // same runtime config and CSS, never deleted from the template.
            $config = $this->fidelityConfig($html);
            $hidden = array_merge($config['hiddenIds'] ?? [], $config['hiddenWidgets'] ?? []);
            $this->assertContains($sampleWidgetIds[$slug][0], $hidden, $slug);

            $visibleMarkup = $this->visibleMarkup($html);
            foreach ($sampleWidgetIds[$slug] as $id) {
                $this->assertStringContainsString('data-id="' . $id . '"', $html, $slug . ': ' . $id);
                $this->assertStringNotContainsString('data-id="' . $id . '"', $visibleMarkup, $slug . ': ' . $id);
            }

            $this->assertNativeAudioHidden($html, $slug);
        }
    }

    public function test_saved_changes_are_reflected_on_public_invitation(): void
    {
        [$user, $invitation] = $this->makeInvitation('tema1');

        $this->actingAs($user)
            ->patch(route('invitations.update', $invitation), $this->minimalPayload($invitation, [
                'title' => 'Undangan Pertama',
                'groom' => ['full_name' => 'Nama Awal'],
            ]), ['Accept' => 'application/json'])
            ->assertOk();
        $this->activate($invitation);

        $first = (string) $this->getPublic($invitation)->getContent();
        $this->assertStringContainsString('Nama Awal', $first);

        // ACTIVE invitations are locked: return to preview before editing again.
        $invitation->update(['status' => InvitationStatus::Preview]);
        $this->actingAs($user)
            ->patch(route('invitations.update', $invitation), $this->minimalPayload($invitation, [
                'groom' => ['full_name' => 'Nama Ubahan'],
            ]), ['Accept' => 'application/json'])
            ->assertOk();
        $this->activate($invitation);

        $second = (string) $this->getPublic($invitation)->getContent();
        $this->assertStringContainsString('Nama Ubahan', $second);
        $this->assertStringNotContainsString('Nama Awal', $second);
    }

    public function test_template_switch_is_allowed_before_and_after_activation(): void
    {
        [$user, $invitation] = $this->makeInvitation('tema1');
        $slug = $invitation->slug;

        $this->actingAs($user)
            ->post(route('invitations.templates.switch', $invitation), ['template_id' => Template::firstWhere('slug', 'tema2')->id])
            ->assertRedirect();
        $this->assertSame('tema2', $invitation->fresh()->template_snapshot['renderer']);

        $this->activate($invitation);

        $this->actingAs($user)
            ->post(route('invitations.templates.switch', $invitation), ['template_id' => Template::firstWhere('slug', 'tema1')->id])
            ->assertRedirect();

        $invitation->refresh();

        $this->assertSame('tema1', $invitation->template_snapshot['renderer']);
        $this->assertSame($slug, $invitation->slug);
        $this->assertSame(InvitationStatus::Active, $invitation->status);
        $this->assertStringContainsString('Kepada yth:', (string) $this->getPublic($invitation)->getContent());
    }

    public function test_customers_cannot_access_another_customers_invitation(): void
    {
        [$owner, $invitation] = $this->makeInvitation('tema1');
        $other = $this->customer('other@consistency.test');

        $this->actingAs($other)->get(route('invitations.edit', $invitation))->assertForbidden();
        $this->actingAs($other)->get(route('invitation.show', $invitation->slug, false))->assertNotFound();
        $this->actingAs($other)
            ->patch(route('invitations.update', $invitation), $this->minimalPayload($invitation), ['Accept' => 'application/json'])
            ->assertForbidden();
    }

    public function test_missing_relations_and_null_values_render_safely(): void
    {
        [$user, $invitation] = $this->makeInvitation('tema2');
        $invitation->groom()->delete();
        $invitation->bride()->delete();
        $invitation->events()->delete();
        $invitation->invitationSettings()->delete();
        $invitation->galleries()->delete();
        $invitation->giftAccounts()->delete();
        $invitation->stories()->delete();
        $invitation->families()->delete();

        $this->activate($invitation);
        $response = $this->getPublic($invitation);
        $response->assertOk();

        $html = (string) $response->getContent();
        $this->assertStringNotContainsString('undefined', $html);
        $this->assertStringNotContainsString('>null<', $html);
        $this->assertNoVisibleSample($html, self::SAMPLE_MARKERS);
    }

    public function test_activation_requires_complete_customer_information(): void
    {
        $user = $this->customer();
        $invitation = app(\App\Services\Invitation\InvitationCreationService::class)
            ->create(Template::firstWhere('slug', 'tema1'), $user);

        $this->actingAs($user)
            ->post(route('invitations.payment.start', $invitation))
            ->assertSessionHas('error');

        $this->assertSame(0, $invitation->transactions()->count());
        $this->assertSame(InvitationStatus::Draft, $invitation->fresh()->status);
    }

    public function test_tema2_quote_source_is_rendered_once(): void
    {
        [, $invitation] = $this->makeInvitation('tema2');
        $payload = $this->minimalPayload($invitation, [
            'settings' => ['quote' => 'Kutipan unik.', 'quote_source' => 'Sumber unik.'],
        ]);
        $user = $invitation->user;

        $this->actingAs($user)
            ->patch(route('invitations.update', $invitation), $payload, ['Accept' => 'application/json'])
            ->assertOk();
        $this->activate($invitation);
        $html = (string) $this->getPublic($invitation)->getContent();

        $this->assertSame(1, substr_count($html, 'Sumber unik.'));
        $this->assertSame(1, substr_count($html, 'Kutipan unik.'));
    }

    public function test_numeric_leading_date_is_not_corrupted(): void
    {
        [, $invitation] = $this->makeInvitation('tema1');
        $payload = $this->minimalPayload($invitation, [
            'events' => [[
                'id' => null,
                'event_type' => EventType::Akad->value,
                'title' => 'Akad',
                'event_date' => '2026-12-12',
                'start_time' => '08:00',
                'end_time' => '',
                'timezone' => 'Asia/Jakarta',
                'location_name' => 'Gedung',
                'address' => 'Alamat',
                'maps_url' => '',
                'description' => '',
                'live_stream_url' => '',
                'sort_order' => 0,
            ]],
        ]);

        $this->actingAs($invitation->user)
            ->patch(route('invitations.update', $invitation), $payload, ['Accept' => 'application/json'])
            ->assertOk();
        $this->activate($invitation);
        $html = (string) $this->getPublic($invitation)->getContent();

        $this->assertStringContainsString('12.12.2026', $html);
        $this->assertDoesNotMatchRegularExpression('/>2\.12\.2026</', $html);
    }

    public function test_gallery_cover_is_first_and_gallery_removal_hides_section(): void
    {
        [$user, $invitation] = $this->makeInvitation('tema1');
        $payload = $this->minimalPayload($invitation, [
            'settings' => ['show_gallery' => true],
        ]);

        $this->actingAs($user)
            ->patch(route('invitations.update', $invitation), $payload, ['Accept' => 'application/json'])
            ->assertOk();

        $invitation->galleries()->create([
            'image' => 'gallery/first.jpg',
            'caption' => 'Foto pertama',
            'is_cover' => false,
            'sort_order' => 0,
        ]);
        $invitation->galleries()->create([
            'image' => 'gallery/cover.jpg',
            'caption' => 'Foto utama',
            'is_cover' => true,
            'sort_order' => 1,
        ]);

        $this->activate($invitation);
        $html = (string) $this->getPublic($invitation)->getContent();

        $coverPos = strpos($html, 'Foto utama');
        $firstPos = strpos($html, 'Foto pertama');
        $this->assertNotFalse($coverPos);
        $this->assertNotFalse($firstPos);
        $this->assertLessThan($firstPos, $coverPos, 'Cover photo must render first.');

        // Removing every gallery item must hide the gallery section entirely.
        $invitation->update(['status' => InvitationStatus::Preview]);
        $invitation->galleries()->delete();
        $this->actingAs($user)
            ->patch(route('invitations.update', $invitation), $payload, ['Accept' => 'application/json'])
            ->assertOk();
        $this->activate($invitation);
        $after = (string) $this->getPublic($invitation)->getContent();

        $this->assertStringNotContainsString('Foto utama', $after);
        $this->assertStringNotContainsString('Foto pertama', $after);
        $this->assertStringContainsString('display:none!important', $after);
    }

    public function test_all_templates_render_at_most_eight_customer_gallery_photos(): void
    {
        $slots = [
            'tema1' => ['section' => '1ff62bc5', 'widget' => '45a07475'],
            'tema2' => ['section' => '71779c60', 'widget' => '1a5fc17a'],
            'tema3' => ['section' => '7cb92d5b', 'widget' => '313e6f45'],
            'tema4' => ['section' => '51bf5d5a', 'widget' => '34fc0486'],
            'tema5' => ['section' => '2d3f5286', 'widget' => '4be1b556'],
        ];

        foreach ($slots as $slug => $ids) {
            [, $invitation] = $this->makeInvitation($slug);

            // Nine rows simulate a legacy/imported invitation; only eight may
            // reach the public gallery grid.
            for ($index = 0; $index < 9; $index++) {
                $invitation->galleries()->create([
                    'image' => 'invitations/' . $invitation->id . '/gallery/customer-' . $index . '.jpg',
                    'caption' => 'Foto customer ' . $index,
                    'is_cover' => $index === 8,
                    'sort_order' => $index,
                ]);
            }

            $this->activate($invitation);
            $html = (string) $this->getPublic($invitation)->getContent();
            $xpath = $this->xpathFor($html);

            $widget = $xpath->query('//*[@data-id="' . $ids['widget'] . '"]')->item(0);
            $this->assertNotNull($widget, $slug . ': gallery widget is present');

            $items = $xpath->query(
                './/*[contains(concat(" ", normalize-space(@class), " "), " e-gallery-item ")]',
                $widget,
            );
            $this->assertSame(8, $items->length, $slug . ': exactly eight customer photos');

            foreach ($items as $item) {
                $url = '/storage/invitations/' . $invitation->id . '/gallery/';
                $this->assertStringContainsString(
                    $url,
                    $item->getAttribute('href'),
                    $slug . ': only customer photos are rendered',
                );
                $thumbnail = $xpath->query(
                    './/*[contains(concat(" ", normalize-space(@class), " "), " e-gallery-image ")]',
                    $item,
                )->item(0);
                $this->assertNotNull($thumbnail, $slug . ': gallery thumbnail is present');
                $this->assertMatchesRegularExpression(
                    '~background-image:url\(["\']?[^"\']*' . preg_quote($url, '~') . '~',
                    $thumbnail->getAttribute('style'),
                    $slug . ': thumbnail uses the uploaded customer photo',
                );
                $this->assertGreaterThan(
                    0,
                    (int) $thumbnail->getAttribute('data-width'),
                    $slug . ': image width is available for masonry layout',
                );
                $this->assertGreaterThan(
                    0,
                    (int) $thumbnail->getAttribute('data-height'),
                    $slug . ': image height is available for masonry layout',
                );
            }

            // The cover is sorted first even when it is the last stored row.
            $this->assertStringContainsString('Foto customer 8', $html, $slug . ': cover photo');
            $this->assertStringNotContainsString('Foto customer 7', $html, $slug . ': ninth photo is dropped');

            // Source templates hardcode a `hidden` class on optional sections.
            // With customer photos the section must be visibly revealed.
            $section = $xpath->query('//section[@data-id="' . $ids['section'] . '"]')->item(0);
            $this->assertNotNull($section, $slug . ': gallery section is present');
            $classes = preg_split('~\s+~', trim($section->getAttribute('class'))) ?: [];
            $this->assertNotContains('hidden', $classes, $slug . ': gallery section is revealed');
            $this->assertStringNotContainsString(
                '[data-id="' . $ids['section'] . '"]{display:none!important}',
                $html,
                $slug . ': gallery section is not hidden',
            );
        }
    }

    public function test_sample_pages_autoplay_music_and_limit_gallery_to_eight_photos(): void
    {
        $widgets = ['45a07475', '1a5fc17a', '313e6f45', '34fc0486', '4be1b556'];

        foreach (Template::query()->orderBy('id')->get() as $template) {
            $response = $this->get('/templates/' . $template->id . '/sample?to=Budi%20Santoso');
            $response->assertOk();
            $html = (string) $response->getContent();

            $this->assertMatchesRegularExpression(
                '~"audio":\{"url":"[^"]+","autoplay":true,"enabled":true\}~',
                $html,
                $template->slug . ': demo enables audio autoplay',
            );
            $this->assertStringContainsString('function attemptAutoplay()', $html);
            $this->assertStringContainsString('bindGestureFallback', $html);

            $xpath = $this->xpathFor($html);
            foreach ($widgets as $widgetId) {
                $widget = $xpath->query('//*[@data-id="' . $widgetId . '"]')->item(0);
                if (! $widget) {
                    continue;
                }

                $items = $xpath->query(
                    './/*[contains(concat(" ", normalize-space(@class), " "), " e-gallery-item ")]',
                    $widget,
                );
                $this->assertGreaterThan(0, $items->length, $template->slug . ': sample gallery has photos');
                $this->assertLessThanOrEqual(8, $items->length, $template->slug . ': sample gallery is capped at eight');
            }
        }
    }

    public function test_all_templates_preserve_native_structure_and_hide_unfilled_elements(): void
    {
        $hiddenSampleIds = [
            'tema1' => '61e9402c',
            'tema2' => '55dab7a2',
            'tema3' => '3c928698',
            'tema4' => '1e1170bb',
            'tema5' => '7fa41008',
        ];
        $injectedMarkers = [
            'data-ud-extra-events', 'data-ud-extra-stories',
            'data-ud-couple-highlight', 'data-ud-static-slideshow',
            'udgiftbutton', 'udgiftpanel', 'ud-envelope-card',
            'data-ud-envelope-copy=', 'data-ud-copy-address', 'data-ud-contact',
            'Tonton Live Streaming', 'Acara Lainnya', 'Cerita Lainnya',
        ];

        foreach (Template::query()->orderBy('id')->get() as $template) {
            $source = File::get($template->absolutePath());
            $slug = $template->slug;

            $sample = (string) $this->get('/templates/' . $template->id . '/sample?to=Budi%20Santoso')->getContent();
            $this->assertNativeStructurePreserved($source, $sample, $slug . ' sample');

            [, $invitation] = $this->makeInvitation($slug);
            $this->actingAs($invitation->user)
                ->patch(route('invitations.update', $invitation), $this->fullPayload($invitation), ['Accept' => 'application/json'])
                ->assertOk();
            $this->activate($invitation);
            $html = (string) $this->getPublic($invitation)->getContent();

            $this->assertNativeStructurePreserved($source, $html, $slug . ' public');
            $this->assertNoInjectedMarkup($source, $html, $injectedMarkers, $slug);

            $sourceMarkup = $this->markupWithoutScripts($source);
            $renderedMarkup = $this->markupWithoutScripts($html);
            $this->assertSame(
                substr_count($sourceMarkup, 'elementor-gallery__container'),
                substr_count($renderedMarkup, 'elementor-gallery__container'),
                $slug . ' empty gallery container must stay in the source',
            );

            $config = $this->fidelityConfig($html);
            $hidden = array_merge($config['hiddenIds'] ?? [], $config['hiddenWidgets'] ?? []);
            $this->assertContains($hiddenSampleIds[$slug], $hidden, $slug . ' sample widget must be hidden');

            $this->assertNoVisibleSample($html, self::SAMPLE_MARKERS, $slug);

            $visible = $this->visibleMarkup($html);
            foreach (['Siti &amp; Ahmad', 'Budi Santoso', 'Gedung Khusus'] as $needle) {
                $this->assertStringContainsString($needle, $visible, $slug . ': ' . $needle);
            }
            foreach (['Amplop Khusus', '1234567890'] as $needle) {
                $this->assertStringContainsString($needle, $html, $slug . ': ' . $needle);
            }
        }
    }

    public function test_preview_and_public_use_the_same_native_renderer(): void
    {
        [$user, $invitation] = $this->makeInvitation('tema3');
        $this->actingAs($user)
            ->patch(route('invitations.update', $invitation), $this->fullPayload($invitation), ['Accept' => 'application/json'])
            ->assertOk();

        $invitation->refresh();
        $previewResponse = $this->actingAs($user)
            ->get(route('invitation.show', $invitation->slug, false) . '?to=Budi%20Santoso');
        $previewResponse->assertOk();
        $preview = (string) $previewResponse->getContent();

        $this->activate($invitation);
        $public = (string) $this->getPublic($invitation)->getContent();

        $this->assertNativeStructurePreserved($preview, $public, 'preview/public');
        $this->assertNoInjectedMarkup($preview, $public, [
            'data-ud-extra-events', 'data-ud-extra-stories', 'udgiftbutton',
            'udgiftpanel', 'ud-envelope-card', 'data-ud-copy-address',
        ]);

        foreach (['Siti &amp; Ahmad', 'Budi Santoso'] as $needle) {
            $this->assertStringContainsString($needle, $this->visibleMarkup($preview), 'preview: ' . $needle);
            $this->assertStringContainsString($needle, $this->visibleMarkup($public), 'public: ' . $needle);
        }
        foreach (['Amplop Khusus', '1234567890'] as $needle) {
            $this->assertStringContainsString($needle, $preview, 'preview: ' . $needle);
            $this->assertStringContainsString($needle, $public, 'public: ' . $needle);
        }
    }

    public function test_mobile_and_desktop_render_the_same_customer_data(): void
    {
        [, $invitation] = $this->makeInvitation('tema2');
        $this->activate($invitation);

        $desktop = (string) $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'])
            ->getPublic($invitation)->getContent();
        $mobile = (string) $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)'])
            ->getPublic($invitation)->getContent();

        $this->assertSame($desktop, $mobile);
        $this->assertStringContainsString('width=device-width, initial-scale=1', $desktop);
    }

    /** @return array<int, string> */
    private function elementorSectionIds(string $html): array
    {
        $xpath = $this->xpathFor($html);
        $wrapper = $xpath
            ->query('//div[contains(concat(" ", normalize-space(@class), " "), " elementor-27626 ")]')
            ->item(0);
        $this->assertNotNull($wrapper);

        $ids = [];
        foreach ($xpath->query('./section[@data-id]', $wrapper) as $section) {
            $ids[] = $section->getAttribute('data-id');
        }

        return $ids;
    }

    /** @return array<string, array<int, string>> */
    private function slideshowGalleries(string $html): array
    {
        $galleries = [];

        foreach ($this->xpathFor($html)->query('//*[@data-settings]') as $node) {
            $settings = json_decode($node->getAttribute('data-settings'), true);

            if (! is_array($settings) || ($settings['background_background'] ?? null) !== 'slideshow') {
                continue;
            }

            $id = $node->getAttribute('data-id');

            if ($id === '') {
                continue;
            }

            $urls = [];
            foreach ($settings['background_slideshow_gallery'] ?? [] as $item) {
                if (is_array($item) && isset($item['url'])) {
                    $urls[] = $item['url'];
                }
            }

            $galleries[$id] = $urls;
        }

        return $galleries;
    }

    private function xpathFor(string $html): \DOMXPath
    {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new \DOMXPath($dom);
    }

    /** @return array{0: User, 1: Invitation} */
    private function makeInvitation(string $templateSlug): array
    {
        $user = $this->customer();
        $this->actingAs($user)->post(route('templates.select', Template::firstWhere('slug', $templateSlug)));

        return [$user, Invitation::where('user_id', $user->id)->firstOrFail()];
    }

    private function customer(string $email = 'customer@consistency.test'): User
    {
        return User::create([
            'name' => 'Customer Consistency',
            'email' => str_replace('@', '-' . uniqid() . '@', $email),
            'google_id' => 'google-' . $email . '-' . uniqid(),
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

    private function getPublic(Invitation $invitation)
    {
        $invitation->refresh();

        return $this->get(route('invitation.show', $invitation->slug, false) . '?to=' . rawurlencode('Budi Santoso'));
    }

    /** @param array<string, mixed> $overrides */
    private function minimalPayload(Invitation $invitation, array $overrides = []): array
    {
        $music = Music::first();
        $base = [
            'title' => 'Undangan Uji',
            'groom' => ['full_name' => 'Ahmad Fauzi', 'nickname' => '', 'father_name' => '', 'mother_name' => '', 'instagram' => '', 'social_links' => ''],
            'bride' => ['full_name' => 'Siti Aisyah', 'nickname' => '', 'father_name' => '', 'mother_name' => '', 'instagram' => '', 'social_links' => ''],
            'events' => [[
                'id' => null,
                'event_type' => EventType::Akad->value,
                'title' => 'Akad Nikah',
                'event_date' => '2026-12-12',
                'start_time' => '08:00',
                'end_time' => '',
                'timezone' => 'Asia/Jakarta',
                'location_name' => 'Gedung',
                'address' => 'Alamat',
                'maps_url' => '',
                'description' => '',
                'live_stream_url' => '',
                'sort_order' => 0,
            ]],
            'stories' => [], 'families' => [], 'gallery_items' => [], 'gift_accounts' => [],
            'events_present' => '1', 'stories_present' => '1', 'families_present' => '1',
            'gallery_items_present' => '1', 'gift_accounts_present' => '1',
            'settings' => [
                'theme' => 'default', 'font_family' => '', 'primary_color' => '', 'secondary_color' => '',
                'music_id' => $music?->id, 'music_autoplay' => false,
                'show_stories' => false, 'show_gallery' => false, 'show_families' => false,
                'show_gift' => false, 'show_rsvp' => false,
                'invitation_message' => '', 'quote' => '', 'quote_source' => '',
                'timezone' => 'Asia/Jakarta', 'gallery_limit' => 0,
            ],
        ];

        return $this->mergePayload($base, $overrides);
    }

    /** @param array<string, mixed> $overrides */
    private function fullPayload(Invitation $invitation): array
    {
        $music = Music::first();
        $base = [
            'title' => 'Undangan Lengkap',
            'groom' => [
                'full_name' => 'Ahmad Fauzi', 'nickname' => 'Ahmad',
                'father_name' => 'Bapak Satu', 'mother_name' => 'Ibu Satu',
                'instagram' => '@ahmad', 'social_links' => "Facebook|https://facebook.com/ahmad\nYouTube|https://youtube.com/@ahmad",
            ],
            'bride' => [
                'full_name' => 'Siti Aisyah', 'nickname' => 'Siti',
                'father_name' => 'Bapak Dua', 'mother_name' => 'Ibu Dua',
                'instagram' => '@siti', 'social_links' => "TikTok|https://tiktok.com/@siti",
            ],
            'events' => [[
                'id' => null,
                'event_type' => EventType::Akad->value,
                'title' => 'Akad Nikah',
                'event_date' => '2026-12-12',
                'start_time' => '09:30',
                'end_time' => '11:45',
                'timezone' => 'Asia/Jakarta',
                'location_name' => 'Gedung Khusus',
                'address' => 'Jalan Khusus No. 9',
                'maps_url' => 'https://maps.google.com/?q=khusus',
                'description' => 'Keterangan acara khusus.',
                'live_stream_url' => 'https://youtube.com/live/khusus',
                'sort_order' => 0,
            ]],
            'stories' => [[
                'id' => null, 'title' => 'Awal Ketemu', 'story_date' => '2019',
                'description' => 'Cerita khusus pasangan.', 'sort_order' => 0,
            ]],
            'families' => [
                ['id' => null, 'name' => 'Keluarga A', 'sort_order' => 0],
                ['id' => null, 'name' => 'Keluarga B', 'sort_order' => 1],
            ],
            'gallery_items' => [], 'gift_accounts' => [[
                'id' => null, 'label' => 'Amplop Khusus', 'account_name' => 'Siti Aisyah',
                'account_number' => '1234567890', 'sort_order' => 0,
            ]],
            'events_present' => '1', 'stories_present' => '1', 'families_present' => '1',
            'gallery_items_present' => '1', 'gift_accounts_present' => '1',
            'settings' => [
                'theme' => 'default', 'font_family' => '', 'primary_color' => '', 'secondary_color' => '',
                'music_id' => $music?->id, 'music_autoplay' => false,
                'show_stories' => true, 'show_gallery' => false, 'show_families' => true,
                'show_gift' => true, 'show_rsvp' => true,
                'invitation_message' => 'Pesan khusus untuk tamu.',
                'quote' => 'Kutipan khusus.', 'quote_source' => 'Sumber khusus.',
                'timezone' => 'Asia/Jakarta', 'gallery_limit' => 0,
                'gift_address' => 'Jalan Kado No. 1',
            ],
        ];

        return array_merge($base, $this->photoGalleryPayload());
    }

    /** @return array<string, mixed> */
    private function photoGalleryPayload(): array
    {
        return [
            'photo_gallery_present' => '1',
            'gallery_new' => [
                UploadedFile::fake()->image('pendukung-1.jpg', 800, 600),
                UploadedFile::fake()->image('pendukung-2.jpg', 800, 600),
                UploadedFile::fake()->image('pendukung-3.jpg', 800, 600),
            ],
            'photo_gallery' => [
                'cover' => [
                    UploadedFile::fake()->image('cover-baru.jpg', 800, 600),
                    UploadedFile::fake()->image('cover-baru-2.jpg', 800, 600),
                ],
                'footer' => [
                    UploadedFile::fake()->image('footer-baru.jpg', 800, 600),
                    UploadedFile::fake()->image('footer-baru-2.jpg', 800, 600),
                ],
            ],
            'supporting_photos_new' => [
                UploadedFile::fake()->image('pendukung-1.jpg', 800, 600),
                UploadedFile::fake()->image('pendukung-2.jpg', 800, 600),
                UploadedFile::fake()->image('pendukung-3.jpg', 800, 600),
            ],
        ];
    }

    /** @param array<string, mixed> $base @param array<string, mixed> $overrides */
    private function mergePayload(array $base, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if ($key === 'settings' && is_array($value)) {
                $base['settings'] = array_replace($base['settings'], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}
