<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\ActivityLog;
use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_has_email_password_and_no_public_registration(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Masuk')
            ->assertSee('Masuk dengan Google')
            ->assertSee(route('google.redirect'), false)
            ->assertSee('name="email"', false)
            ->assertSee('type="email"', false)
            ->assertSee('name="password"', false)
            ->assertSee('type="password"', false)
            ->assertSee(route('login.post'), false)
            ->assertSee('google-btn', false)
            ->assertDontSee('Daftar', false)
            ->assertDontSee('register', false)
            ->assertDontSee('Anda telah keluar', false)
            ->assertDontSee('<div class="container" aria-live="polite">', false);

        $this->assertTrue(Route::has('login.post'));
        $this->assertFalse(Route::has('register'));
    }

    public function test_login_validates_email_and_password(): void
    {
        $this->post(route('login.post'), [])
            ->assertRedirect()
            ->assertSessionHasErrors(['email', 'password']);

        $this->assertGuest();
    }

    public function test_seeded_super_admin_can_log_in_with_password(): void
    {
        config()->set('app.super_admin_email', 'admin-login@undangan.test');
        config()->set('app.super_admin_name', 'Admin Utama');
        config()->set('app.super_admin_password', 'Rahasia123!');

        $this->seed(SuperAdminSeeder::class);

        $this->post(route('login.post'), [
            'email' => 'admin-login@undangan.test',
            'password' => 'Rahasia123!',
        ])->assertRedirect(route('dashboard'));

        $admin = User::where('email', 'admin-login@undangan.test')->firstOrFail();
        $this->assertAuthenticatedAs($admin);
        $this->assertTrue($admin->isSuperAdmin());
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'action' => 'login',
            'description' => 'Login dengan email dan kata sandi',
        ]);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = User::query()->create([
            'name' => 'Customer Login',
            'email' => 'customer-login@undangan.test',
            'role' => Role::Customer,
            'status' => UserStatus::Active,
            'password' => Hash::make('kata-sandi-benar'),
        ]);

        $this->from(route('login'))
            ->post(route('login.post'), [
                'email' => $user->email,
                'password' => 'kata-sandi-salah',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(0, ActivityLog::where('user_id', $user->id)->count());
    }

    public function test_inactive_account_cannot_log_in(): void
    {
        $user = User::query()->create([
            'name' => 'Nonaktif',
            'email' => 'nonaktif@undangan.test',
            'role' => Role::Customer,
            'status' => UserStatus::Inactive,
            'password' => Hash::make('kata-sandi-benar'),
        ]);

        $this->from(route('login'))
            ->post(route('login.post'), [
                'email' => $user->email,
                'password' => 'kata-sandi-benar',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_super_admin_seeder_is_idempotent_and_hashes_password(): void
    {
        config()->set('app.super_admin_email', 'seeded@undangan.test');
        config()->set('app.super_admin_name', 'Admin Uji');
        config()->set('app.super_admin_password', 'rahasia123');

        $this->seed(SuperAdminSeeder::class);
        $this->seed(SuperAdminSeeder::class);

        $this->assertSame(1, User::where('email', 'seeded@undangan.test')->count());

        $admin = User::where('email', 'seeded@undangan.test')->firstOrFail();
        $this->assertSame(Role::SuperAdmin, $admin->role);
        $this->assertSame(UserStatus::Active, $admin->status);
        $this->assertNotSame('rahasia123', $admin->password);
        $this->assertTrue(Hash::check('rahasia123', $admin->password));
    }

    public function test_logout_does_not_leave_an_alert_or_empty_gap(): void
    {
        $user = User::query()->create([
            'name' => 'Logout Test',
            'email' => 'logout@undangan.test',
            'role' => Role::Customer,
            'status' => UserStatus::Active,
            'password' => Hash::make('kata-sandi-benar'),
        ]);

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('home'))
            ->assertSessionMissing('status');

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Anda telah keluar', false)
            ->assertDontSee('<div class="container" aria-live="polite">', false);

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('Anda telah keluar', false)
            ->assertDontSee('<div class="container" aria-live="polite">', false);
    }
}
