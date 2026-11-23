<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('meta_description', 'Buat undangan digital pernikahan yang personal, elegan, dan mudah dibagikan kepada keluarga serta kerabat.')">
    <meta name="theme-color" content="#B5935A">
    <title>@yield('title', config('app.name'))</title>
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
