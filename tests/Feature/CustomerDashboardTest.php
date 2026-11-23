<?php

namespace Tests\Feature;

use App\Enums\InvitationStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Invitation;
use App\Models\Template;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\TemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TemplateSeeder::class);
    }

    public function test_dashboard_shows_statistics_couple_event_details_and_transaction_history(): void
    {
        $owner = $this->customer('owner@example.test');
        $other = $this->customer('other@example.test');

        $active = $this->invitation($owner, 'Aktif', InvitationStatus::Active);
        $draft = $this->invitation($owner, 'Draft', InvitationStatus::Draft);
        $expired = $this->invitation($owner, 'Kedaluwarsa', InvitationStatus::Expired);
        $foreign = $this->invitation($other, 'Undangan Orang Lain', InvitationStatus::Active);

        $draft->groom()->create(['full_name' => 'Rizky Anhar']);
        $draft->bride()->create(['full_name' => 'Nadia Sari']);
        $draft->events()->create([
            'event_type' => 'AKAD',
            'title' => 'Akad Nikah',
            'event_date' => '2026-12-20',
        ]);

        Transaction::create([
            'user_id' => $owner->id,
            'invitation_id' => $active->id,
            'order_id' => 'UD-DASH-1',
            'gross_amount' => 150000,
            'payment_type' => 'qris',
            'transaction_status' => PaymentStatus::Settlement,
            'transaction_time' => now(),
        ]);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Total undangan')
            ->assertSee('Undangan aktif')
            ->assertSee('Draft / preview')
            ->assertSee('Kedaluwarsa')
            ->assertSee('Transaksi terakhir')
            ->assertSee('Riwayat Transaksi')
            ->assertSee('UD-DASH-1')
            ->assertSee('Rp150.000')
            ->assertSee('Rizky Anhar & Nadia Sari')
            ->assertSee('20 Des 2026')
            ->assertSee('Lihat Undangan')
            ->assertDontSee('>Preview</a>', false)
            ->assertDontSee('Undangan Orang Lain');

        $this->assertSame(3, $owner->invitations()->count());
    }

    public function test_owner_can_delete_a_draft_but_not_an_active_invitation(): void
    {
        $owner = $this->customer('delete-owner@example.test');
        $draft = $this->invitation($owner, 'Draft Hapus', InvitationStatus::Draft);
        $active = $this->invitation($owner, 'Aktif', InvitationStatus::Active);

        $this->actingAs($owner)
            ->delete(route('invitations.destroy', $draft))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status', 'Draft undangan berhasil dihapus.');

        $this->assertSoftDeleted('invitations', ['id' => $draft->id]);

        $this->actingAs($owner)
            ->delete(route('invitations.destroy', $active))
            ->assertForbidden();

        $this->assertNotSoftDeleted('invitations', ['id' => $active->id]);
    }

    public function test_other_customer_and_super_admin_cannot_delete_a_draft(): void
    {
        $owner = $this->customer('protect-owner@example.test');
        $other = $this->customer('protect-other@example.test');
        $draft = $this->invitation($owner, 'Draft', InvitationStatus::Draft);

        $this->actingAs($other)
            ->delete(route('invitations.destroy', $draft))
            ->assertForbidden();

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin-dashboard@example.test',
            'google_id' => 'google-admin-dashboard',
            'role' => Role::SuperAdmin,
            'status' => UserStatus::Active,
        ]);

        $this->actingAs($admin)
            ->delete(route('invitations.destroy', $draft))
            ->assertForbidden();

        $this->assertNotSoftDeleted('invitations', ['id' => $draft->id]);
    }

    private function invitation(User $user, string $title, InvitationStatus $status): Invitation
    {
        $this->actingAs($user)->post(route('templates.select', Template::firstWhere('slug', 'tema1')));

        $invitation = Invitation::where('user_id', $user->id)->latest('id')->firstOrFail();
        $invitation->update(['title' => $title, 'status' => $status]);

        return $invitation;
    }

    private function customer(string $email): User
    {
        return User::create([
            'name' => 'Customer',
            'email' => $email,
            'google_id' => 'google-' . $email,
            'role' => Role::Customer,
            'status' => UserStatus::Active,
        ]);
    }
}
