<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    private function configureSocialite(): void
    {
        config()->set('services.google.redirect', route('google.callback'));
    }

    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function redirect(): RedirectResponse
    {
        if (! config('services.google.client_id') || ! config('services.google.client_secret')) {
            return redirect()->route('login')->with('error', 'Google OAuth belum dikonfigurasi. Hubungi administrator.');
        }

        $this->configureSocialite();

        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    public function callback(): RedirectResponse
    {
        $this->configureSocialite();

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $exception) {
            Log::warning('Google OAuth callback failed', ['message' => $exception->getMessage()]);

            return redirect()->route('login')->with('error', 'Autentikasi Google gagal. Silakan coba lagi.');
        }

        $email = $googleUser->getEmail();

        if (! $email || ($googleUser->user['verified_email'] ?? true) === false) {
            return redirect()->route('login')->with('error', 'Email Google belum terverifikasi.');
        }

        $user = User::query()->where('google_id', $googleUser->getId())->first();

        if (! $user) {
            // A seeded Super Admin (or an existing account) is linked by email,
            // never promoted by a value coming from the login form.
            $user = User::query()->where('email', $email)->first();
        }

        if ($user) {
            $changes = [
                'google_id' => $googleUser->getId(),
                'name' => $googleUser->getName() ?: $user->name,
                'avatar' => $googleUser->getAvatar() ?: $user->avatar,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ];

            $adminEmail = Setting::get('super_admin_email', config('app.super_admin_email', ''));

            if ($adminEmail && strcasecmp($adminEmail, $email) === 0) {
                $changes['role'] = Role::SuperAdmin;
            }

            $user->forceFill($changes)->save();
        } else {
            $user = User::query()->create([
                'google_id' => $googleUser->getId(),
                'name' => $googleUser->getName() ?: explode('@', $email)[0],
                'email' => $email,
                'avatar' => $googleUser->getAvatar(),
                'role' => Role::Customer,
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
            ]);
        }

        if (! $user->isActive()) {
            return redirect()->route('login')->with('error', 'Akun Anda telah dinonaktifkan.');
        }

        Auth::login($user, true);
        request()->session()->regenerate();

        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'login',
            'description' => 'Login melalui Google OAuth',
            'ip_address' => request()->ip(),
        ]);

        $intended = session()->pull('url.intended');

        if ($intended && str_starts_with($intended, url('/'))) {
            return redirect()->to($intended);
        }

        return redirect()->route('dashboard');
    }

    public function logout(): RedirectResponse
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('home');
    }
}
