@php
    $appName = \App\Models\Setting::get('app_name', config('app.name'));
@endphp
<header class="site-header">
    <div class="site-nav">
        <a class="brand" href="{{ route('home') }}" aria-label="{{ $appName }} — Beranda">
            <img class="brand-icon" src="{{ asset('assets/site/img/brand-mark.svg') }}" alt="" width="34" height="34">
            <span>{{ $appName }}<small>Undangan Pernikahan</small></span>
        </a>

        <button class="nav-toggle" type="button" data-nav-toggle aria-controls="primary-navigation" aria-expanded="false" aria-label="Buka menu navigasi">
            <span class="nav-toggle-box" aria-hidden="true">
                <span class="nav-toggle-line"></span>
                <span class="nav-toggle-line"></span>
                <span class="nav-toggle-line"></span>
            </span>
        </button>

        <nav id="primary-navigation" class="nav-links" aria-label="Navigasi utama">
            <a href="{{ route('home') }}" @class(['is-active' => request()->routeIs('home')])>Beranda</a>
            <a href="{{ route('templates.index') }}" @class(['is-active' => request()->routeIs('templates.*')])>Katalog Template</a>
            @auth
                <a href="{{ route('dashboard') }}" @class(['is-active' => request()->routeIs('dashboard')])>Dashboard</a>
                @if (auth()->user()->isSuperAdmin())
                    <a href="{{ route('admin.dashboard') }}" @class(['is-active' => request()->routeIs('admin.*')])>Admin</a>
                @endif
            @endauth
        </nav>

        <div class="nav-actions">
            @include('components.site.profile-menu')
            @guest
                <a class="btn btn-primary btn-small" href="{{ route('login') }}">Masuk</a>
            @endguest
        </div>
    </div>
</header>
