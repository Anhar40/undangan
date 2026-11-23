@extends('layouts.app')

@section('title', 'Masuk — ' . config('app.name'))

@section('content')
<div class="container">
    <div class="login-shell card">
        <div class="login-visual">
            <img src="{{ asset('assets/site/img/hero-invitation.svg') }}" alt="" aria-hidden="true">
            <div class="login-visual-content">
                <span class="eyebrow" style="color:var(--sand)">Selamat datang</span>
                <h2>Satu tautan, semua momen bahagia.</h2>
                <p>Mulai undangan digital Anda dalam beberapa menit. Personalisasi nama tamu, cerita, galeri, dan RSVP dalam satu halaman yang hangat.</p>
            </div>
        </div>

        <div class="login-form-side">
            <div class="login-card">
                <span class="eyebrow">Akun Anda</span>
                <h1>Masuk</h1>

                @if ($errors->any())
                    <div class="alert alert-error" role="alert">
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                <a class="btn btn-primary btn-block google-btn" href="{{ route('google.redirect') }}">
                    <svg width="20" height="20" viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59A14.4 14.4 0 0 1 9.75 24c0-1.6.27-3.14.77-4.59l-7.98-6.19A23.94 23.94 0 0 0 0 24c0 3.87.93 7.52 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
                    <span>Masuk dengan Google</span>
                </a>

                <div class="divider-label">atau</div>

                <form method="POST" action="{{ route('login.post') }}">
                    @csrf

                    <label class="field">
                        <span class="field-label">Email</span>
                        <input type="email" name="email" value="{{ old('email') }}" placeholder="nama@domain.com" autocomplete="username" required autofocus>
                        @error('email')
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                    </label>

                    <label class="field">
                        <span class="field-label">Kata sandi</span>
                        <input type="password" name="password" placeholder="••••••••" autocomplete="current-password" required>
                        @error('password')
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                    </label>

                    <label class="remember-row">
                        <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                        <span>Ingat saya</span>
                    </label>

                    <button class="btn btn-primary btn-block" type="submit">Masuk</button>
                </form>

                <ul class="check-list">
                    <li>Pratinjau gratis selama 10 jam sejak draft dibuat.</li>
                    <li>Dua template dengan detail yang bisa disesuaikan.</li><li>Aktivasi hanya setelah pembayaran terverifikasi.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
