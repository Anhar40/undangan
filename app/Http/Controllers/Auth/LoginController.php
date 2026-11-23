<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function __invoke(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi tidak valid.',
            ]);
        }

        $user = $request->user();

        if (! $user?->isActive()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'Akun Anda telah dinonaktifkan.',
            ]);
        }

        $request->session()->regenerate();

        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'login',
            'description' => 'Login dengan email dan kata sandi',
            'ip_address' => $request->ip(),
        ]);

        return redirect()->intended(route('dashboard'));
    }
}
