<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = trim((string) config('app.super_admin_email', 'admin@undangan.test'));
        $password = (string) config('app.super_admin_password', 'Admin12345!');

        if ($email === '' || $password === '') {
            return;
        }

        // Installation seed only: never overwrite administrator-managed settings
        // on a routine re-seed of an existing installation.
        if (! Setting::query()->where('key', 'super_admin_email')->exists()) {
            Setting::set('super_admin_email', $email);
        }

        // firstOrCreate avoids duplicates and only writes the initial password.
        // A re-seed re-applies the administrative role/status but never resets
        // a password that an administrator has changed since installation.
        $admin = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => config('app.super_admin_name', 'Super Admin'),
                'role' => Role::SuperAdmin,
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
                'password' => Hash::make($password),
            ],
        );

        $admin->forceFill([
            'name' => config('app.super_admin_name', 'Super Admin'),
            'role' => Role::SuperAdmin,
            'status' => UserStatus::Active,
            'email_verified_at' => $admin->email_verified_at ?? now(),
        ])->save();
    }
}
