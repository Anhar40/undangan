<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#B5935A">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Admin — ' . config('app.name'))</title>
    <link rel="icon" href="{{ asset('assets/site/img/favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('assets/site/css/site.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/site/css/admin.css') }}">
    @stack('styles')
</head>
<body class="admin-body">
    <a class="skip-link" href="#admin-main">Lewati ke konten utama</a>

    @php
        $adminAppName = \App\Models\Setting::get('app_name', config('app.name'));
        $adminNav = [
            [
                'key' => 'dashboard',
                'route' => 'admin.dashboard',
                'pattern' => 'admin.dashboard',
                'label' => 'Ringkasan',
                'icon' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
            ],
            [
                'key' => 'templates',
                'route' => 'admin.templates.index',
                'pattern' => 'admin.templates.*',
                'label' => 'Template',
                'icon' => '<path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 12 9 5 9-5"/><path d="m3 16 9 5 9-5"/>',
            ],
            [
                'key' => 'musics',
                'route' => 'admin.musics.index',
                'pattern' => 'admin.musics.*',
                'label' => 'Musik',
                'icon' => '<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>',
            ],
            [
                'key' => 'vouchers',
                'route' => 'admin.vouchers.index',
                'pattern' => 'admin.vouchers.*',
                'label' => 'Voucher',
                'icon' => '<path d="M20.59 13.41 11 3H4v7l9.59 9.59a2 2 0 0 0 2.82 0l4.18-4.18a2 2 0 0 0 0-2.82Z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
            ],
            [
                'key' => 'customers',
                'route' => 'admin.customers.index',
                'pattern' => 'admin.customers.*',
                'label' => 'Customer',
                'icon' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
            ],
            [
                'key' => 'invitations',
                'route' => 'admin.invitations.index',
                'pattern' => 'admin.invitations.*',
                'label' => 'Undangan',
                'icon' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
            ],
            [
                'key' => 'transactions',
                'route' => 'admin.transactions.index',
                'pattern' => 'admin.transactions.*',
                'label' => 'Transaksi',
                'icon' => '<path d="M4 4h16v16l-2.5-1.6L15 20l-2.5-1.6L10 20l-2.5-1.6L5 20V4Z"/><path d="M8 8h8M8 12h8M8 16h4"/>',
            ],
        ];
    @endphp

    <div class="admin-shell" data-admin-shell>
        <aside class="admin-sidebar" id="admin-sidebar" aria-label="Navigasi admin">
            <div class="admin-sidebar-head">
                <a class="admin-brand" href="{{ route('admin.dashboard') }}">
                    <span class="admin-brand-mark" aria-hidden="true">A</span>
                    <span>
                        {{ $adminAppName }}
                        <small>Panel Admin</small>
                    </span>
                </a>
                <button class="admin-sidebar-close" type="button" data-sidebar-close aria-label="Tutup menu admin">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>

            <nav class="admin-menu" aria-label="Menu utama admin">
                <span class="admin-menu-label">Menu utama</span>
                @foreach ($adminNav as $item)
                    <a href="{{ route($item['route']) }}"
                       @class(['is-active' => request()->routeIs($item['pattern'])])
                       @if (request()->routeIs($item['pattern'])) aria-current="page" @endif>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            {!! $item['icon'] !!}
                        </svg>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="admin-sidebar-foot">
                <a href="{{ route('home') }}" target="_blank" rel="noopener">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3h7v7"/><path d="M10 14 21 3"/><path d="M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5"/></svg>
                    <span>Lihat situs</span>
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></svg>
                        <span>Keluar</span>
                    </button>
                </form>
                <p class="admin-sidebar-version">Versi aplikasi · {{ config('app.version', '1.0') }}</p>
            </div>
        </aside>

        <div class="admin-backdrop" data-sidebar-backdrop hidden></div>

        <div class="admin-content">
            <header class="admin-topbar">
                <button class="admin-menu-toggle" type="button" data-sidebar-open
                        aria-controls="admin-sidebar" aria-expanded="false" aria-label="Buka menu admin">
                    <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                </button>

                <div class="admin-crumb" aria-label="Lokasi halaman">
                    <span>Admin</span>
                    <span aria-hidden="true">/</span>
                    <strong>@yield('admin_section', 'Ringkasan')</strong>
                </div>

                <div class="admin-topbar-user">
                    <span class="admin-user-avatar" aria-hidden="true">{{ strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                    <span class="admin-user-meta">
                        <strong>{{ auth()->user()->name }}</strong>
                        <small>{{ auth()->user()->role?->label() ?? 'Admin' }}</small>
                    </span>
                </div>
            </header>

            <main id="admin-main" class="admin-main" tabindex="-1">
                <div class="admin-container">
                    @if (session('status'))
                        <div class="alert alert-success" role="status">
                            <span>{{ session('status') }}</span>
                        </div>
                    @endif
                    @if (session('error'))
                        <div class="alert alert-error" role="alert">
                            <span>{{ session('error') }}</span>
                        </div>
                    @endif

                    @yield('content')
                </div>
            </main>

            <footer class="admin-footer">
                <span>&copy; {{ now()->year }} {{ $adminAppName }} · Panel administrasi</span>
                <span>Data dan aktivitas admin tercatat dalam sistem.</span>
            </footer>
        </div>
    </div>

    <div class="toast" role="status" aria-live="polite" data-toast></div>
    <script src="{{ asset('assets/site/js/site.js') }}" defer></script>
    <script src="{{ asset('assets/site/js/admin.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
