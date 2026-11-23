<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerWorkspaceShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_dashboard_uses_sidebar_and_profile_dropdown(): void
    {
        $customer = $this->user('shell-customer@example.test', Role::Customer);

        $this->actingAs($customer)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('class="customer-shell"', false)
            ->assertSee('id="customer-sidebar"', false)
            ->assertSee('data-sidebar-open', false)
            ->assertSee('href="' . route('dashboard') . '"', false)
            ->assertSee('href="' . route('transactions.index') . '"', false)
            ->assertSee('data-profile-menu', false)
            ->assertSee('data-profile-toggle', false)
            ->assertSee('role="menu"', false)
            ->assertSee('action="' . route('logout') . '"', false);
    }

    public function test_guest_catalog_keeps_public_layout(): void
    {
        $this->get(route('templates.index'))
            ->assertOk()
            ->assertSee('site-header', false)
            ->assertDontSee('customer-shell', false);
    }

    public function test_super_admin_gets_admin_link_in_profile_menu(): void
    {
        $admin = $this->user('shell-admin@example.test', Role::SuperAdmin);

        $this->actingAs($admin)
            ->get(route('templates.index'))
            ->assertOk()
            ->assertSee('customer-shell', false)
            ->assertSee('Panel Admin')
            ->assertSee('href="' . route('admin.dashboard') . '"', false);
    }

    private function user(string $email, Role $role): User
    {
        return User::create([
            'name' => 'Workspace User',
            'email' => $email,
            'google_id' => 'google-' . $email,
            'role' => $role,
            'status' => UserStatus::Active,
        ]);
    }
}
