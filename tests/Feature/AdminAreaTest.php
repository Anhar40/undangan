<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAreaTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_admin_pages_render_inside_the_sidebar_shell(): void
    {
        $admin = $this->user('area-admin@example.test', Role::SuperAdmin, 'Admin Area');

        $urls = [
            route('admin.dashboard'),
            route('admin.templates.index'),
            route('admin.customers.index'),
            route('admin.invitations.index'),
            route('admin.transactions.index'),
        ];

        foreach ($urls as $url) {
            $this->actingAs($admin)
                ->get($url)
                ->assertOk()
                ->assertSee('Navigasi admin')
                ->assertSee('Menu utama admin')
                ->assertSee('Ringkasan')
                ->assertSee('Template')
                ->assertSee('Customer')
                ->assertSee('Undangan')
                ->assertSee('Transaksi')
                ->assertSee('Keluar')
                ->assertDontSee('id="primary-navigation"', false);
        }
    }

    public function test_customer_index_can_be_searched_filtered_and_paginated(): void
    {
        $admin = $this->user('filter-admin@example.test', Role::SuperAdmin, 'Admin Filter');

        foreach (range(1, 20) as $number) {
            $this->user(
                sprintf('customer-%02d@example.test', $number),
                Role::Customer,
                sprintf('Customer Uji %02d', $number),
            );
        }

        $this->user('target@example.test', Role::Customer, 'ZZTargetOnly');
        $inactive = $this->user('inactive@example.test', Role::Customer, 'Customer Nonaktif', UserStatus::Inactive);

        $this->actingAs($admin)
            ->get(route('admin.customers.index'))
            ->assertOk()
            ->assertSee('Customer Uji 20')
            ->assertSee('page=2', false);

        $this->actingAs($admin)
            ->get(route('admin.customers.index', ['q' => 'ZZTargetOnly']))
            ->assertOk()
            ->assertSee('ZZTargetOnly')
            ->assertDontSee('Customer Uji 20');

        $this->actingAs($admin)
            ->get(route('admin.customers.index', ['status' => 'inactive']))
            ->assertOk()
            ->assertSee($inactive->email)
            ->assertDontSee('Customer Uji 20');
    }

    public function test_customer_account_cannot_open_admin_pages(): void
    {
        $customer = $this->user('area-customer@example.test', Role::Customer, 'Customer Biasa');

        $this->actingAs($customer)
            ->get(route('admin.customers.index'))
            ->assertForbidden();
    }

    private function user(
        string $email,
        Role $role,
        string $name,
        UserStatus $status = UserStatus::Active,
    ): User {
        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'google_id' => 'google-' . $email,
            'role' => $role,
            'status' => $status,
        ]);
    }
}
