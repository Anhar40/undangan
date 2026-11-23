<?php

namespace Tests\Feature;

use App\Enums\EventType;
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
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoGalleryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TemplateSeeder::class);
        Storage::fake('public');
    }

    public function test_gallery_panel_keeps_gallery_and_supporting_limits_separate(): void
    {
        [$user, $invitation] = $this->makeInvitation();

        // Both collections are optional.
        $this->patchPhotos($user, $invitation, [
            'gallery_new' => [],
            'supporting_photos_new' => [],
        ])->assertOk();
        $this->assertSame(0, $invitation->galleries()->count());

        $this->patchPhotos($user, $invitation, ['gallery_new' => array_map(
            static fn (int $index): UploadedFile => UploadedFile::fake()->image('galeri-' . $index . '.jpg', 800, 600),
            range(1, 9),
        )])->assertUnprocessable()->assertJsonValidationErrors(['gallery_items', 'gallery_new']);

        $this->patchPhotos($user, $invitation, ['supporting_photos_new' => array_map(
            static fn (int $index): UploadedFile => UploadedFile::fake()->image('pendukung-' . $index . '.jpg', 800, 600),
            range(1, 4),
        )])->assertUnprocessable()->assertJsonValidationErrors('supporting_photos_new');

        $this->patchPhotos($user, $invitation)->assertOk();

        $custom = $invitation->fresh()->invitationSettings->custom_settings;
        $this->assertCount(2, $custom['photo_gallery']['cover']);
        $this->assertCount(2, $custom['photo_gallery']['footer']);
        $this->assertArrayNotHasKey('sub_cover', $custom['photo_gallery']);
        $this->assertCount(3, $custom['supporting_photos']);
        $this->assertSame(3, $invitation->galleries()->count());

        foreach ([...$custom['photo_gallery']['cover'], ...$custom['photo_gallery']['footer'], ...$custom['supporting_photos']] as $path) {
            Storage::disk('public')->assertExists($path);
        }
    }

    public function test_editor_exposes_two_photo_uploads_for_cover_and_footer(): void
    {
        [$user, $invitation] = $this->makeInvitation();

        $html = (string) $this->actingAs($user)
            ->get(route('invitations.edit', $invitation))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="photo_gallery[cover][]"', $html);
        $this->assertStringContainsString('name="photo_gallery[footer][]"', $html);
        $this->assertStringContainsString('Foto cover / sampul (maksimal 2 foto)', $html);
        $this->assertStringContainsString('Foto footer / penutup (maksimal 2 foto)', $html);
        $this->assertSame(2, substr_count($html, 'name="photo_gallery['));
    }

    public function test_cover_and_footer_slots_accept_two_photos_and_reject_three(): void
    {
        [$user, $invitation] = $this->makeInvitation();

        $this->patchPhotos($user, $invitation)->assertOk();
        $custom = $invitation->fresh()->invitationSettings->custom_settings;
        $this->assertCount(2, $custom['photo_gallery']['cover']);
        $this->assertCount(2, $custom['photo_gallery']['footer']);

        $threePhotos = static fn (string $prefix): array => array_map(
            static fn (int $index): UploadedFile => UploadedFile::fake()->image($prefix . '-' . $index . '.jpg', 800, 600),
            range(1, 3),
        );

        $this->patchPhotos($user, $invitation, [
            'photo_gallery' => [
                'cover' => $threePhotos('cover'),
                'footer' => [
                    UploadedFile::fake()->image('footer.jpg', 800, 600),
                    UploadedFile::fake()->image('footer-2.jpg', 800, 600),
                ],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('photo_gallery.cover');

        $this->patchPhotos($user, $invitation, [
            'photo_gallery' => [
                'cover' => [
                    UploadedFile::fake()->image('cover.jpg', 800, 600),
                    UploadedFile::fake()->image('cover-2.jpg', 800, 600),
                ],
                'footer' => $threePhotos('footer'),
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('photo_gallery.footer');

        $unchanged = $invitation->fresh()->invitationSettings->custom_settings['photo_gallery'];
        $this->assertSame($custom['photo_gallery'], $unchanged);
    }

    public function test_saving_another_section_and_other_users_cannot_change_photo_gallery(): void
    {
        [$user, $invitation] = $this->makeInvitation();
        $this->patchPhotos($user, $invitation)->assertOk();

        $before = $invitation->fresh()->invitationSettings->custom_settings['photo_gallery'];

        $this->actingAs($user)->patch(
            route('invitations.update', $invitation),
            ['title' => 'Judul dari panel lain', 'settings' => ['share_title' => 'Bagikan']],
            ['Accept' => 'application/json'],
        )->assertOk();

        $this->assertSame($before, $invitation->fresh()->invitationSettings->custom_settings['photo_gallery']);

        $other = User::create([
            'name' => 'Penyusup',
            'email' => 'penyusup-' . uniqid() . '@example.test',
            'google_id' => 'google-' . uniqid(),
            'role' => Role::Customer,
            'status' => UserStatus::Active,
        ]);

        $this->actingAs($other)->patch(
            route('invitations.update', $invitation),
            $this->validPhotoPayload($invitation),
            ['Accept' => 'application/json'],
        )->assertForbidden();

        $this->assertSame($before, $invitation->fresh()->invitationSettings->custom_settings['photo_gallery']);
    }

    public function test_replacing_another_slot_keeps_files_still_referenced_by_gallery_or_settings(): void
    {
        [$user, $invitation] = $this->makeInvitation();
        $this->patchPhotos($user, $invitation)->assertOk();

        $invitation->refresh();
        $sharedPath = $invitation->galleries->first()->image;
        $invitation->update(['cover_photo' => $sharedPath]);

        $settings = $invitation->invitationSettings;
        $custom = $settings->custom_settings;
        $custom['backgrounds']['cover'] = [$sharedPath];
        $settings->update(['custom_settings' => $custom]);

        $this->patchPhotos($user, $invitation, [
            'photos' => ['cover' => UploadedFile::fake()->image('sampul-baru.jpg', 800, 600)],
            'gallery_items' => $invitation->galleries
                ->map(static fn ($photo, int $index): array => ['id' => $photo->id, 'sort_order' => $index])
                ->all(),
            'gallery_new' => [],
            'supporting_photos_new' => [],
        ])->assertOk();

        Storage::disk('public')->assertExists($sharedPath);
        $this->assertSame($sharedPath, $invitation->galleries()->first()->image);
        $this->assertSame(
            $sharedPath,
            $invitation->fresh()->invitationSettings->custom_settings['backgrounds']['cover'][0],
        );
    }

    public function test_public_invitation_uses_photo_gallery_instead_of_couple_photos(): void
    {
        [$user, $invitation] = $this->makeInvitation();

        $payload = $this->validPhotoPayload($invitation);
        $payload['photos'] = [
            'groom' => UploadedFile::fake()->image('groom.jpg', 400, 400),
            'bride' => UploadedFile::fake()->image('bride.jpg', 400, 400),
        ];

        $this->actingAs($user)->patch(
            route('invitations.update', $invitation),
            $payload,
            ['Accept' => 'application/json'],
        )->assertOk();

        $invitation->refresh();
        $groomPhoto = asset('storage/' . $invitation->groom->photo);
        $bridePhoto = asset('storage/' . $invitation->bride->photo);
        $custom = $invitation->invitationSettings->custom_settings;
        $cover = array_map(
            static fn (string $path): string => asset('storage/' . $path),
            $custom['photo_gallery']['cover'],
        );
        $supporting = array_map(
            static fn (string $path): string => asset('storage/' . $path),
            $custom['supporting_photos'],
        );
        $footer = array_map(
            static fn (string $path): string => asset('storage/' . $path),
            $custom['photo_gallery']['footer'],
        );

        $this->activate($invitation);
        $html = (string) $this->getPublic($invitation)->getContent();
        $galleries = $this->slideshowGalleries($html);

        $this->assertSame($cover, $galleries['3fe3ed95']);
        $this->assertSame($cover, $galleries['30eed981']);
        $this->assertSame([$supporting[1]], $galleries['5d7ac283']);
        $this->assertSame([$supporting[0]], $galleries['5c22588c']);
        $this->assertSame($footer, $galleries['27998b08']);
        $this->assertStringNotContainsString($groomPhoto, $html);
        $this->assertStringNotContainsString($bridePhoto, $html);

        foreach ($invitation->galleries as $photo) {
            $this->assertStringContainsString(asset('storage/' . $photo->image), $html);
        }
    }

    public function test_tema2_sub_cover_uses_footer_upload_while_cover_keeps_cover_upload(): void
    {
        [$user, $invitation] = $this->makeInvitation('tema2');
        $this->patchPhotos($user, $invitation)->assertOk();

        $invitation->refresh();
        $custom = $invitation->invitationSettings->custom_settings;
        $cover = array_map(
            static fn (string $path): string => asset('storage/' . $path),
            $custom['photo_gallery']['cover'],
        );
        $supporting = array_map(
            static fn (string $path): string => asset('storage/' . $path),
            $custom['supporting_photos'],
        );
        $footer = array_map(
            static fn (string $path): string => asset('storage/' . $path),
            $custom['photo_gallery']['footer'],
        );

        $this->activate($invitation);
        $html = (string) $this->getPublic($invitation)->getContent();
        $galleries = $this->slideshowGalleries($html);

        // 5e3f969e is the photo-bearing column of the sub-cover section
        // 1bc32d64. It followed the cover; it must still follow the footer
        // even though there is no sub-cover upload control anymore.
        $this->assertSame($footer, $galleries['5e3f969e'] ?? null);
        $this->assertNotContains($cover, $galleries['5e3f969e'] ?? []);

        $this->assertSame($cover, $galleries['56efded2'] ?? null, 'opening cover keeps its own upload');
        $this->assertSame($footer, $galleries['a09598'] ?? null, 'closing keeps the footer upload');
        $this->assertSame([$supporting[0]], $galleries['1a8ab2c4'] ?? null);
        $this->assertSame([$supporting[1]], $galleries['76dba0dd'] ?? null);

        // Template closing copy stays in real invitations.
        $this->assertStringContainsString(
            'Merupakan suatu kebahagiaan dan kehormatan bagi kami, apabila Bapak/Ibu/Saudara/i, berkenan hadir dan memberikan do’a restu kepada kami.',
            $html,
        );
        $this->assertStringContainsString('Wassalamu’alaikum Warrahmatullahi Wabarakatuh', $html);

        // With no event date there is no countdown. Its widgets live inside
        // the cover section, so they must be hidden individually instead of
        // hiding the whole section (which would hide the footer background).
        preg_match('/window\.UD_CONFIG=(\{.*?\});/s', $html, $configMatch);
        $this->assertNotSame([], $configMatch);
        $config = json_decode($configMatch[1], true);
        $this->assertIsArray($config);
        foreach (['27f8d37b', '30d71a57', '5e524221'] as $id) {
            $this->assertNotContains($id, $config['hiddenIds'] ?? []);
            $this->assertContains($id, $config['hiddenWidgets'] ?? []);
        }

        // The closing section carries the footer background too. With no
        // family data, only the family heading is hidden, never section
        // 1601753b (column a09598).
        $this->assertNotContains('74116615', $config['hiddenIds'] ?? []);
        $this->assertContains('74116615', $config['hiddenWidgets'] ?? []);

        preg_match_all('/<style[^>]*>(.*?)<\/style>/s', $html, $styleMatches);
        $hideCss = '';
        foreach ($styleMatches[1] as $css) {
            if (str_contains($css, 'display:none!important')) {
                $hideCss .= $css;
            }
        }
        $this->assertStringNotContainsString('[data-id="4ead5e59"]', $hideCss);
        $this->assertStringNotContainsString('[data-id="1601753b"]', $hideCss);

        // Samples and native template files stay untouched.
        $sample = (string) $this->get(route('templates.sample', Template::firstWhere('slug', 'tema2')))->getContent();
        $sampleGalleries = $this->slideshowGalleries($sample);
        $this->assertStringContainsString('pexels-photo-8526254.webp', $sample);
        $this->assertContains(
            '/assets/templates/tema2/wp-content/uploads/2024/07/pexels-photo-8526254.webp',
            $sampleGalleries['5e3f969e'] ?? [],
        );
        foreach ([...$footer, ...$cover] as $customerPhoto) {
            $this->assertStringNotContainsString($customerPhoto, $sample);
        }
    }

    public function test_public_invitation_shows_countdown_without_bottom_share_bar(): void
    {
        [$user, $invitation] = $this->makeInvitation();
        $invitation->events()->create([
            'event_type' => EventType::Akad->value,
            'title' => 'Akad',
            'event_date' => '2027-05-20',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'timezone' => 'Asia/Jakarta',
            'sort_order' => 0,
        ]);
        $this->activate($invitation);

        $html = (string) $this->getPublic($invitation)->getContent();

        $this->assertStringNotContainsString('data-ud-share-bar', $html);
        $this->assertStringContainsString('elementor-countdown-wrapper" data-date="', $html);

        preg_match('/window\.UD_CONFIG=(\{.*?\});/s', $html, $matches);
        $this->assertNotSame([], $matches);
        $config = json_decode($matches[1], true);
        $this->assertIsArray($config);
        $this->assertSame('2027-05-20T08:00:00+07:00', $config['countdown']['target'] ?? null);
        $this->assertNotContains('3adf3466', $config['hiddenIds'] ?? []);
        $this->assertNotContains('5fe8a914', $config['hiddenWidgets'] ?? []);
        $this->assertContains('459ad270', $config['hiddenWidgets'] ?? []);
        $this->assertContains('650f0858', $config['hiddenWidgets'] ?? []);
    }

    public function test_supporting_photos_replace_carousel_and_decorative_samples_in_both_templates(): void
    {
        $slots = [
            'tema1' => [
                'carousel' => '542f3a1f',
                'images' => ['5a32762e', '7b6cf76', '3d7facad'],
                'samples' => [
                    'pexels-azra-tuba-demir-12124416.jpg',
                    'pexels-azra-tuba-demir-12124474.jpg',
                    'pexels-azra-tuba-demir-12124342.jpg',
                    'pexels-azra-tuba-demir-12124421.jpg',
                ],
            ],
            'tema2' => [
                'carousel' => '3fba8d42',
                'images' => ['64d9be4f'],
                'samples' => [
                    'pexels-photo-8526247.jpeg',
                    'pexels-photo-8526246.jpeg',
                    'pexels-photo-8526254.webp',
                ],
            ],
        ];

        foreach ($slots as $template => $ids) {
            [$user, $invitation] = $this->makeInvitation($template);
            $this->patchPhotos($user, $invitation)->assertOk();

            $invitation->refresh();
            $custom = $invitation->invitationSettings->custom_settings;
            $urls = array_map(
                static fn (string $path): string => asset('storage/' . $path),
                $custom['supporting_photos'],
            );
            $galleryUrls = $invitation->galleries
                ->map(static fn ($photo): string => asset('storage/' . $photo->image))
                ->all();

            $this->activate($invitation);
            $html = (string) $this->getPublic($invitation)->getContent();
            $xpath = $this->domXPath($html);

            $carousel = $xpath->query('//*[@data-id="' . $ids['carousel'] . '"]')->item(0);
            $this->assertNotNull($carousel, $template . ': carousel widget is kept');

            $slides = $xpath->query(
                './/*[contains(concat(" ", normalize-space(@class), " "), " elementor-carousel-image ")]',
                $carousel,
            );
            $this->assertSame(count($urls), $slides->length, $template . ': one slide per supporting photo');

            // The gallery grid stays separate from the decorative carousel.
            foreach ($galleryUrls as $galleryUrl) {
                $this->assertStringContainsString($galleryUrl, $html, $template . ': gallery photo is rendered');
            }

            foreach ($slides as $index => $slide) {
                $this->assertStringContainsString($urls[$index], $slide->getAttribute('style'), $template);
            }

            foreach ($ids['images'] as $index => $id) {
                $image = $xpath->query('//*[@data-id="' . $id . '"]//img')->item(0);
                $this->assertNotNull($image, $template . ': ' . $id);
                $this->assertStringContainsString(
                    $urls[$index % count($urls)],
                    $image->getAttribute('src'),
                    $template . ': ' . $id,
                );
            }

            // Sample photos may remain in hidden source widgets, but they must
            // no longer appear in the live carousel itself.
            $carouselMarkup = (string) $carousel->ownerDocument?->saveHTML($carousel);
            foreach ($ids['samples'] as $sample) {
                $this->assertStringNotContainsString($sample, $carouselMarkup, $template . ': ' . $sample);
            }
        }
    }

    /** @return array{0: User, 1: Invitation} */
    private function makeInvitation(string $template = 'tema1'): array
    {
        $user = User::create([
            'name' => 'Customer Foto',
            'email' => 'foto-' . uniqid() . '@example.test',
            'google_id' => 'google-foto-' . uniqid(),
            'role' => Role::Customer,
            'status' => UserStatus::Active,
        ]);

        $this->actingAs($user)->post(route('templates.select', Template::firstWhere('slug', $template)));

        return [$user, Invitation::where('user_id', $user->id)->firstOrFail()];
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function validPhotoPayload(Invitation $invitation, array $overrides = []): array
    {
        $base = [
            'title' => $invitation->title,
            'groom' => ['full_name' => 'Ahmad', 'nickname' => 'A'],
            'bride' => ['full_name' => 'Siti', 'nickname' => 'S'],
            'gallery_items_present' => '1',
            'photo_gallery_present' => '1',
            'gallery_items' => [],
            'gallery_new' => [
                UploadedFile::fake()->image('pendukung-1.jpg', 800, 600),
                UploadedFile::fake()->image('pendukung-2.jpg', 800, 600),
                UploadedFile::fake()->image('pendukung-3.jpg', 800, 600),
            ],
            'photo_gallery' => [
                'cover' => [
                    UploadedFile::fake()->image('cover.jpg', 800, 600),
                    UploadedFile::fake()->image('cover-2.jpg', 800, 600),
                ],
                'footer' => [
                    UploadedFile::fake()->image('footer.jpg', 800, 600),
                    UploadedFile::fake()->image('footer-2.jpg', 800, 600),
                ],
            ],
            'supporting_photos_new' => [
                UploadedFile::fake()->image('pendukung-1.jpg', 800, 600),
                UploadedFile::fake()->image('pendukung-2.jpg', 800, 600),
                UploadedFile::fake()->image('pendukung-3.jpg', 800, 600),
            ],
        ];

        return array_replace($base, $overrides);
    }

    /** @param array<string, mixed> $overrides */
    private function patchPhotos(User $user, Invitation $invitation, array $overrides = [])
    {
        return $this->actingAs($user)->patch(
            route('invitations.update', $invitation),
            $this->validPhotoPayload($invitation, $overrides),
            ['Accept' => 'application/json'],
        );
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
        return $this->get(route('invitation.show', $invitation->slug, false));
    }

    private function domXPath(string $html): \DOMXPath
    {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new \DOMXPath($dom);
    }

    /** @return array<string, array<int, string>> */
    private function slideshowGalleries(string $html): array
    {
        $galleries = [];
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($dom);

        foreach ($xpath->query('//*[@data-settings]') as $node) {
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
}
