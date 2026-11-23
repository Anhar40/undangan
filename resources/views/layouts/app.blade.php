<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $seoSiteName = \App\Models\Setting::get('app_name', config('app.name', 'INVITE'));
        $seoTitle = trim($__env->yieldContent('title')) ?: $seoSiteName;
        $seoDescription = trim($__env->yieldContent('meta_description')) ?: 'Buat undangan digital pernikahan yang personal, elegan, dan mudah dibagikan kepada keluarga serta kerabat.';
        $seoCanonical = trim($__env->yieldContent('canonical')) ?: url()->current();
        $seoImage = trim($__env->yieldContent('og_image')) ?: asset('assets/site/img/og-default.png');
        $seoRobots = trim($__env->yieldContent('meta_robots')) ?: 'index, follow, max-image-preview:large';
        $seoType = trim($__env->yieldContent('og_type')) ?: 'website';
    @endphp
    <meta name="description" content="{{ $seoDescription }}">
    <meta name="robots" content="{{ $seoRobots }}">
    <meta name="theme-color" content="#B5935A">
    <link rel="canonical" href="{{ $seoCanonical }}">
    <meta property="og:type" content="{{ $seoType }}">
    <meta property="og:site_name" content="{{ $seoSiteName }}">
    <meta property="og:locale" content="id_ID">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:description" content="{{ $seoDescription }}">
    <meta property="og:url" content="{{ $seoCanonical }}">
    <meta property="og:image" content="{{ $seoImage }}">
    <meta property="og:image:secure_url" content="{{ str_starts_with($seoImage, 'https://') ? $seoImage : preg_replace('/^http:\/\//i', 'https://', $seoImage) }}">
    <meta property="og:image:type" content="{{ str_ends_with(strtolower(parse_url($seoImage, PHP_URL_PATH) ?? ''), '.png') ? 'image/png' : 'image/jpeg' }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seoTitle }}">
    <meta name="twitter:description" content="{{ $seoDescription }}">
    <meta name="twitter:image" content="{{ $seoImage }}">
    <title>{{ $seoTitle }}</title>
    @stack('head_meta')
    <link rel="icon" href="{{ asset('assets/site/img/favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="{{ asset('assets/site/css/site.css') }}">
    @stack('styles')
</head>
<body @class(['page-dashboard' => request()->routeIs('dashboard')])>
    <a class="skip-link" href="#main-content">Lewati ke konten utama</a>

    @include('components.site.header')

    <main id="main-content" class="app-main">
        @if (session('status') || session('error'))
            <div class="container" aria-live="polite">
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
        @endif

        @yield('content')
    </main>

    @include('components.site.footer')

    <div class="toast" role="status" aria-live="polite" data-toast></div>
    <script src="{{ asset('assets/site/js/site.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
