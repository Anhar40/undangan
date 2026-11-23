<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('meta_description', 'Kelola undangan digital pernikahan dan pembayarannya dalam satu workspace.')">
    <meta name="theme-color" content="#B5935A">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Workspace — ' . config('app.name'))</title>
    <link rel="icon" href="{{ asset('assets/site/img/favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="{{ asset('assets/site/css/site.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/site/css/customer.css') }}">
    @stack('styles')
</head>
<body class="customer-body">
    <a class="skip-link" href="#customer-main">Lewati ke konten utama</a>

    @php
        $customerAppName = \App\Models\Setting::get('app_name', config('app.name'));
        $customerNav = [
            [
                'route' => 'dashboard',
                'pattern' => 'dashboard',
                'label' => 'Ringkasan',
                'icon' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
            ],
            [
                'url' => route('dashboard') . '#undangan-saya',
                'pattern' => 'invitations.*',
                'label' => 'Undangan Saya',
                'icon' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
            ],
            [
                'route' => 'templates.index',
                'pattern' => 'templates.index',
                'label' => 'Katalog Template',
                'icon' => '<path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 12 9 5 9-5"/><path d="m3 16 9 5 9-5"/>',
            ],
            [
                'route' => 'transactions.index',
                'pattern' => 'transactions.*',
                'label' => 'Transaksi',
                'icon' => '<path d="M4 4h16v16l-2.5-1.6L15 20l-2.5-1.6L10 20l-2.5-1.6L5 20V4Z"/><path d="M8 8h8M8 12h8M8 16h4"/>',
            ],
            [
                'route' => 'admin.dashboard',
                'pattern' => 'admin.*',
                'label' => 'Panel Admin',
                'icon' => '<path d="M12 15a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1.03 1.56V21h-4v-.07A1.7 1.7 0 0 0 8.97 19.4a1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-1.56-1.03H3v-4h.07A1.7 1.7 0 0 0 4.6 8.97a1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.83-2.83.06.06A1.7 1.7 0 0 0 8.97 4.6 1.7 1.7 0 0 0 10 3.07V3h4v.07a1.7 1.7 0 0 0 1.03 1.56 1.7 1.7 0 0 0 1.88-.34l.06-.06 2.83 2.83-.06.06a1.7 1.7 0 0 0-.34 1.88 1.7 1.7 0 0 0 1.56 1.03H21v4h-.07A1.7 1.7 0 0 0 19.4 15Z"/>',
                'adminOnly' => true,
            ],
        ];
    @endphp

    <div class="customer-shell" data-customer-shell>
        <aside class="customer-sidebar" id="customer-sidebar" aria-label="Navigasi workspace">
            <div class="customer-sidebar-head">
                <a class="customer-brand" href="{{ route('dashboard') }}">
                    <img src="{{ asset('assets/site/img/brand-mark.svg') }}" alt="" width="34" height="34">
                    <span>
                        {{ $customerAppName }}
                        <small>Workspace Customer</small>
                    </span>
                </a>
                <button class="customer-sidebar-close" type="button" data-sidebar-close aria-label="Tutup menu workspace">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>

            <nav class="customer-menu" aria-label="Menu utama workspace">
                <span class="customer-menu-label">Menu utama</span>
                @foreach ($customerNav as $item)
                    @if (! empty($item['adminOnly']) && ! auth()->user()->isSuperAdmin())
                        @continue
                    @endif
                    @php $isActive = request()->routeIs($item['pattern']); @endphp
                    <a href="{{ $item['url'] ?? route($item['route']) }}"
                       @class(['is-active' => $isActive])
                       @if ($isActive) aria-current="page" @endif>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            {!! $item['icon'] !!}
                        </svg>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="customer-sidebar-foot">
                <a href="{{ route('home') }}" target="_blank" rel="noopener">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3h7v7"/><path d="M10 14 21 3"/><path d="M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5"/></svg>
                    <span>Lihat situs</span>
                </a>
                <a href="{{ route('home') }}#faq">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.7.3-1 .9-1 1.7"/><path d="M12 17h.01"/></svg>
                    <span>Pusat bantuan</span>
                </a>
                <p class="customer-sidebar-version">Versi aplikasi · {{ config('app.version', '1.0') }}</p>
            </div>
        </aside>

        <div class="customer-backdrop" data-sidebar-backdrop hidden></div>

        <div class="customer-content">
            <header class="customer-topbar">
                <button class="customer-menu-toggle" type="button" data-sidebar-open
                        aria-controls="customer-sidebar" aria-expanded="false" aria-label="Buka menu workspace">
                    <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                </button>

                <div class="customer-crumb" aria-label="Lokasi halaman">
                    <span>Workspace</span>
                    <span aria-hidden="true">/</span>
                    <strong>@yield('customer_section', 'Dashboard')</strong>
                </div>

                <div class="customer-topbar-actions">
                    <a class="btn btn-primary btn-small customer-topbar-cta" href="{{ route('templates.index') }}">Buat Undangan</a>
                    @include('components.site.profile-menu')
                </div>
            </header>

            <main id="customer-main" class="customer-main" tabindex="-1">
                <div class="alert-stack">
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
                </div>

                @yield('content')
            </main>

            <footer class="customer-footer">
                <span>&copy; {{ now()->year }} {{ $customerAppName }} · Workspace customer</span>
                <span>Kelola undangan dan pembayaran Anda dengan tenang.</span>
            </footer>
        </div>
    </div>

    <nav class="customer-bottom-nav" aria-label="Navigasi utama workspace">
        @foreach ($customerNav as $item)
            @if (! empty($item['adminOnly']) && ! auth()->user()->isSuperAdmin())
                @continue
            @endif
            @php $isActive = request()->routeIs($item['pattern']); @endphp
            <a href="{{ $item['url'] ?? route($item['route']) }}"
               @class(['is-active' => $isActive])
               @if ($isActive) aria-current="page" @endif>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    {!! $item['icon'] !!}
                </svg>
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>

    <div class="toast" role="status" aria-live="polite" data-toast></div>
    <script src="{{ asset('assets/site/js/site.js') }}" defer></script>
    <script src="{{ asset('assets/site/js/customer.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
