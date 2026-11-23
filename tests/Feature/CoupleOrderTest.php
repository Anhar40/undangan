<?php

namespace Tests\Feature;

use App\Enums\CoupleOrder;
use App\Enums\InvitationStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Invitation;
use App\Models\Template;
use App\Models\User;
use App\Services\Invitation\InvitationCreationService;
use App\Services\Invitation\ShareMessageService;
use Database\Seeders\TemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\TemplateFidelityAssertions;
use Tests\TestCase;

/**
 * Global couple order: one setting drives names, biodata, social links, role
 * captions and photos in every template without touching the stored identities.
 */
class CoupleOrderTest extends TestCase
{
    use RefreshDatabase;
    use TemplateFidelityAssertions;

    /** @var array<string, array<string, mixed>> */
    private const SLOTS = [
        'tema1' => [
            'names' => ['7d5344c4', '5de56291'],
            'parents' => ['7d466437', '1aeac5e5'],
            'instagram' => ['3ecedd8e', '190cf58c'],
            'role_labels' => ['32936610', '7df01bdf'],
            'role_text' => ['BRIDE', 'GROOM'],
            'photos' => ['5d7ac283', '5c22588c'],
            'photo_type' => 'background',
        ],
        'tema2' => [
            'names' => ['6c936581', '446761ce'],
            'parents' => ['7158d3b2', '39e1866d'],
            'instagram' => ['7368c603', '636f3f63'],
            'role_labels' => ['a982656', '3ba075a1'],
            'role_text' => ['BRIDE', 'GROOM'],
            'photos' => ['1a8ab2c4', '76dba0dd'],
            'photo_type' => 'background',
        ],
        'tema3' => [
            'names' => ['48290d3', '7c52ae4f'],
            'parents' => ['58dbf87', '64770940'],
            'instagram' => ['1abe6d75', '1ff27945'],
            'role_labels' => ['79ee9ee0', '58509836'],
            'role_text' => ['BRIDE', 'GROOM'],
            'photos' => ['171b4b90', '7ffa566e'],
            'photo_type' => 'background',
        ],
        'tema4' => [
            'names' => ['5650d75c', '977dd53'],
            'parents' => ['18876561', '51431a41'],
            'instagram' => ['72bbb22', '86eaba3'],
            'role_labels' => ['5ecfbd74', '70d52ef9'],
            'role_text' => ['THE BRIDE', 'THE GROOM'],
            'photos' => ['200aee0e', '5bcef27b'],
            'photo_type' => 'image',
        ],
        'tema5' => [
            'names' => ['4d9a0eda', '1ac8960f'],
            'parents' => ['288c6a37', '15b90be7'],
            'instagram' => ['6162c34f', '14560187'],
            'role_labels' => [],
            'role_text' => [],
            'photos' => ['5c77959a', '6fa17f37'],
            'photo_type' => 'image',
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TemplateSeeder::class);
        Storage::fake('public');
    }

    public function test_setting_is_validated_and_survives_unrelated_saves_and_login(): void
    {
        [$user, $invitation] = $this->makeInvitation();

        $this->patchSection($user, $invitation, [
            'settings' => ['couple_order' => 'tidak-ada'],
        ])->assertUnprocessable()->assertJsonValidationErrors('settings.couple_order');

        $this->patchSection($user, $invitation, $this->couplePayload('male_first'))->assertOk();

        $invitation->refresh();
        $this->assertSame(CoupleOrder::MaleFirst, $invitation->invitationSettings->couple_order);
        $this->assertSame('Anhar Pratama', $invitation->groom->full_name);
        $this->assertSame('Ika Pertiwi', $invitation->bride->full_name);

        // A save from another panel does not submit the radio, so the existing
        // choice must stay untouched.
        $this->patchSection($user, $invitation, [
            'settings' => ['share_title' => 'Judul Bagikan'],
        ])->assertOk();

        $this->assertSame(CoupleOrder::MaleFirst, $invitation->fresh()->invitationSettings->couple_order);

        $this->post(route('logout'));
        $this->actingAs($user)
            ->get(route('invitations.edit', $invitation))
            ->assertOk()
            ->assertSee('Urutan Mempelai')
            ->assertSee('value="male_first" checked', false)
            ->assertSee('Anhar Pratama &amp; Ika Pertiwi', false)
            ->assertSee('Ika Pertiwi &amp; Anhar Pratama', false);

        $this->patchSection($user, $invitation, [
            'settings' => ['couple_order' => 'female_first'],
        ])->assertOk();

        $invitation->refresh();
        $this->assertSame(CoupleOrder::FemaleFirst, $invitation->invitationSettings->couple_order);
        $this->assertSame('Anhar Pratama', $invitation->groom->full_name);
        $this->assertSame('Ika Pertiwi', $invitation->bride->full_name);
    }

    public function test_legacy_null_keeps_the_original_public_order_and_labels(): void
    {
        [$user, $invitation] = $this->makeInvitation('tema1');
        $this->patchSection($user, $invitation, $this->couplePayload(null))->assertOk();
        $this->activate($invitation);

        $this->assertNull($invitation->fresh()->invitationSettings->couple_order);

        $html = $this->publicHtml($invitation);
        $slots = self::SLOTS['tema1'];

        $this->assertSame('Ika Pertiwi', $this->widgetText($html, $slots['names'][0]));
        $this->assertSame('Anhar Pratama', $this->widgetText($html, $slots['names'][1]));
        $this->assertSame('BRIDE', $this->widgetText($html, $slots['role_labels'][0]));
        $this->assertSame('GROOM', $this->widgetText($html, $slots['role_labels'][1]));
        $this->assertSame('Anhar Pratama & Ika Pertiwi', $invitation->fresh()->coupleLabel());
        $this->assertSame(
            'Anhar Pratama & Ika Pertiwi',
            app(ShareMessageService::class)->forInvitation($invitation->fresh(), 'Budi', 'https://contoh.test/u/' . $invitation->slug)['couple'],
        );
    }

    public function test_both_orders_apply_consistently_in_all_templates(): void
    {
        foreach (self::SLOTS as $slug => $slots) {
            foreach ([CoupleOrder::MaleFirst, CoupleOrder::FemaleFirst] as $order) {
                [$user, $invitation] = $this->makeInvitation($slug);
                $this->patchSection($user, $invitation, $this->couplePayload($order->value))->assertOk();
                $this->activate($invitation);

                $fresh = $invitation->fresh();
                $first = $order->isMaleFirst() ? $fresh->groom : $fresh->bride;
                $second = $order->isMaleFirst() ? $fresh->bride : $fresh->groom;
                $expectedLabel = $order->isMaleFirst() ? 'Anhar & Ika' : 'Ika & Anhar';
                $expectedFullLabel = $order->isMaleFirst()
                    ? 'Anhar Pratama & Ika Pertiwi'
                    : 'Ika Pertiwi & Anhar Pratama';
                $html = $this->publicHtml($invitation);
                $context = $slug . ' ' . $order->value;

                $this->assertSame($first->full_name, $this->widgetText($html, $slots['names'][0]), $context . ' first name');
                $this->assertSame($second->full_name, $this->widgetText($html, $slots['names'][1]), $context . ' second name');

                $firstParents = $this->widgetText($html, $slots['parents'][0]);
                $secondParents = $this->widgetText($html, $slots['parents'][1]);
                $this->assertStringContainsString($first->father_name, $firstParents, $context . ' first father');
                $this->assertStringContainsString($first->mother_name, $firstParents, $context . ' first mother');
                $this->assertStringContainsString($second->father_name, $secondParents, $context . ' second father');
                $this->assertStringContainsString($second->mother_name, $secondParents, $context . ' second mother');

                $this->assertSame((string) $first->instagram, $this->widgetText($html, $slots['instagram'][0]), $context . ' first instagram');
                $this->assertSame((string) $second->instagram, $this->widgetText($html, $slots['instagram'][1]), $context . ' second instagram');

                if ($slots['role_labels'] !== []) {
                    if ($order->isMaleFirst()) {
                        $expectedLabels = [$slots['role_text'][1], $slots['role_text'][0]];
                    } else {
                        $expectedLabels = $slots['role_text'];
                    }
                    $this->assertSame($expectedLabels[0], $this->widgetText($html, $slots['role_labels'][0]), $context . ' first role');
                    $this->assertSame($expectedLabels[1], $this->widgetText($html, $slots['role_labels'][1]), $context . ' second role');
                }

                $this->assertPhotoInSlot($html, $slots['photos'][0], (string) $first->photo, $slots['photo_type'], $context . ' first photo');
                $this->assertPhotoInSlot($html, $slots['photos'][1], (string) $second->photo, $slots['photo_type'], $context . ' second photo');

                $this->assertStringContainsString(str_replace('&', '&amp;', $expectedLabel), $html, $context . ' couple heading');
                $this->assertSame($expectedFullLabel, $fresh->coupleLabel(), $context . ' dashboard label');
                $this->assertSame(
                    $expectedFullLabel,
                    app(ShareMessageService::class)->forInvitation($fresh, 'Budi', 'https://contoh.test')['couple'],
                    $context . ' share label',
                );

                $source = File::get(base_path('template/' . $slug . '.html'));
                $this->assertNativeStructurePreserved($source, $html, $context);
            }
        }
    }

    public function test_re_editing_couple_data_updates_the_chosen_order_without_swapping_fields(): void
    {
        [$user, $invitation] = $this->makeInvitation('tema5');
        $this->patchSection($user, $invitation, $this->couplePayload('male_first'))->assertOk();
        $this->activate($invitation);

        $html = $this->publicHtml($invitation);
        $slots = self::SLOTS['tema5'];
        $this->assertSame('Anhar & Ika', str_replace('&amp;', '&', $this->firstCoupleHeading($html, $slots)));
        $this->assertSame('Anhar Pratama', $this->widgetText($html, $slots['names'][0]));

        $this->patchSection($user, $invitation, [
            'groom' => ['nickname' => 'Anhar Baru'],
            'bride' => ['nickname' => 'Ika Baru'],
        ])->assertOk();

        $html = $this->publicHtml($invitation);
        $this->assertSame('Anhar Baru & Ika Baru', str_replace('&amp;', '&', $this->firstCoupleHeading($html, $slots)));
        $this->assertSame(CoupleOrder::MaleFirst, $invitation->fresh()->invitationSettings->couple_order);
        $this->assertSame('Anhar Pratama', $invitation->fresh()->groom->full_name);
        $this->assertSame('Ika Pertiwi', $invitation->fresh()->bride->full_name);
    }

    public function test_preview_and_public_invitations_render_the_same_order(): void
    {
        [$user, $invitation] = $this->makeInvitation('tema5');
        $this->patchSection($user, $invitation, $this->couplePayload(CoupleOrder::FemaleFirst->value))->assertOk();

        $invitation->refresh();
        $preview = $this->actingAs($user)
            ->get(route('invitation.show', $invitation->slug, false))
            ->assertOk()
            ->getContent();
        $slots = self::SLOTS['tema5'];

        $this->activate($invitation);
        $public = $this->publicHtml($invitation);

        $pairs = [
            [$slots['names'][0], $slots['names'][0]],
            [$slots['names'][1], $slots['names'][1]],
            [$slots['photos'][0], $slots['photos'][0]],
        ];

        foreach ($pairs as [$previewId, $publicId]) {
            $this->assertSame($this->widgetText($preview, $previewId), $this->widgetText($public, $publicId));
        }

        $this->assertStringContainsString('Ika &amp; Anhar', $preview);
        $this->assertStringContainsString('Ika &amp; Anhar', $public);
        $this->assertPhotoInSlot($preview, $slots['photos'][0], (string) $invitation->fresh()->bride->photo, 'image', 'preview bride photo');
        $this->assertPhotoInSlot($public, $slots['photos'][0], (string) $invitation->fresh()->bride->photo, 'image', 'public bride photo');
    }

    public function test_template_switch_preserves_the_couple_order(): void
    {
        [$user, $invitation] = $this->makeInvitation('tema1');
        $this->patchSection($user, $invitation, $this->couplePayload(CoupleOrder::MaleFirst->value))->assertOk();
        $this->activate($invitation);

        $this->actingAs($user)
            ->post(route('invitations.templates.switch', $invitation), [
                'template_id' => Template::firstWhere('slug', 'tema2')->id,
            ])
            ->assertRedirect(route('invitations.edit', $invitation));

        $fresh = $invitation->fresh();
        $this->assertSame('tema2', $fresh->template_snapshot['renderer']);
        $this->assertSame(CoupleOrder::MaleFirst, $fresh->invitationSettings->couple_order);

        $html = $this->publicHtml($fresh);
        $slots = self::SLOTS['tema2'];
        $this->assertSame('Anhar Pratama', $this->widgetText($html, $slots['names'][0]));
        $this->assertSame('Ika Pertiwi', $this->widgetText($html, $slots['names'][1]));
        $this->assertSame('GROOM', $this->widgetText($html, $slots['role_labels'][0]));
        $this->assertSame('BRIDE', $this->widgetText($html, $slots['role_labels'][1]));
    }

    /** @param array<string, mixed> $payload */
    private function patchSection(User $user, Invitation $invitation, array $payload): TestResponse
    {
        $payload['title'] = $payload['title'] ?? $invitation->title;

        return $this->actingAs($user)->patch(
            route('invitations.update', $invitation),
            $payload,
            ['Accept' => 'application/json'],
        );
    }

    /** @return array{0: User, 1: Invitation} */
    private function makeInvitation(string $slug = 'tema1'): array
    {
        $user = User::create([
            'name' => 'Customer Couple Order',
            'email' => 'couple-order-' . uniqid() . '@example.test',
            'google_id' => 'google-' . uniqid(),
            'role' => Role::Customer,
            'status' => UserStatus::Active,
        ]);

        $invitation = app(InvitationCreationService::class)
            ->create(Template::firstWhere('slug', $slug), $user);

        return [$user, $invitation];
    }

    /** @return array<string, mixed> */
    private function couplePayload(?string $order): array
    {
        $payload = [
            'title' => 'Undangan Anhar & Ika',
            'groom' => [
                'full_name' => 'Anhar Pratama',
                'nickname' => 'Anhar',
                'father_name' => 'Ayah Pria',
                'mother_name' => 'Ibu Pria',
                'instagram' => '@anhar',
            ],
            'bride' => [
                'full_name' => 'Ika Pertiwi',
                'nickname' => 'Ika',
                'father_name' => 'Ayah Wanita',
                'mother_name' => 'Ibu Wanita',
                'instagram' => '@ika',
            ],
            'photos' => [
                'groom' => UploadedFile::fake()->image('groom-portrait.jpg', 600, 800),
                'bride' => UploadedFile::fake()->image('bride-portrait.jpg', 600, 800),
            ],
        ];

        if ($order !== null) {
            $payload['settings'] = ['couple_order' => $order];
        }

        return $payload;
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

    private function publicHtml(Invitation $invitation): string
    {
        $invitation->refresh();

        return $this->get(route('invitation.show', $invitation->slug, false) . '?to=Budi')
            ->assertOk()
            ->getContent();
    }

    private function widget(string $html, string $dataId): ?\DOMElement
    {
        $node = $this->fidelityXPath($html)
            ->query('//*[@data-id="' . $dataId . '"]')
            ->item(0);

        return $node instanceof \DOMElement ? $node : null;
    }

    private function widgetText(string $html, string $dataId): string
    {
        $widget = $this->widget($html, $dataId);

        return $widget === null
            ? ''
            : trim(preg_replace('/\s+/', ' ', $widget->textContent) ?? '');
    }

    /** @return array<string, mixed> */
    private function widgetSettings(string $html, string $dataId): array
    {
        $widget = $this->widget($html, $dataId);
        $decoded = $widget === null ? null : json_decode($widget->getAttribute('data-settings'), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function widgetImage(string $html, string $dataId): string
    {
        $widget = $this->widget($html, $dataId);
        $image = $widget?->getElementsByTagName('img')->item(0);

        return $image instanceof \DOMElement ? (string) $image->getAttribute('src') : '';
    }

    private function assertPhotoInSlot(
        string $html,
        string $dataId,
        string $photoPath,
        string $type,
        string $context,
    ): void {
        $filename = basename($photoPath);
        $this->assertNotSame('', $photoPath, $context . ' has no uploaded photo');

        if ($type === 'background') {
            $gallery = $this->widgetSettings($html, $dataId)['background_slideshow_gallery'] ?? [];
            $url = is_array($gallery[0] ?? null) ? (string) ($gallery[0]['url'] ?? '') : '';
        } else {
            $url = $this->widgetImage($html, $dataId);
        }

        $this->assertStringContainsString($filename, $url, $context);
    }

    /** @param array<string, mixed> $slots */
    private function firstCoupleHeading(string $html, array $slots): string
    {
        // Tema5 uses three identical couple headings; the first one is enough
        // for a deterministic label assertion.
        foreach (['3434e463', '1c6048c8', 'f664efe'] as $id) {
            $text = $this->widgetText($html, $id);
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }
}
