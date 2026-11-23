@php
    $appName = \App\Models\Setting::get('app_name', config('app.name'));
    $tagline = \App\Models\Setting::get('site_tagline', 'Undangan digital yang hangat, personal, dan mudah dibagikan.');
@endphp
<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div>
                <a class="brand" href="{{ route('home') }}">
                    <img class="brand-icon" src="{{ asset('assets/site/img/brand-mark.svg') }}" alt="" width="34" height="34">
                    <span>{{ $appName }}<small>Undangan Pernikahan</small></span>
                </a>
                <p>{{ $tagline }}</p>
            </div>
            <div>
                <h3>Jelajahi</h3>
                <ul>
                    <li><a href="{{ route('home') }}">Beranda</a></li>
                    <li><a href="{{ route('templates.index') }}">Katalog template</a></li>
                    <li><a href="{{ route('home') }}#faq">Pertanyaan umum</a></li>
                </ul>
            </div>
            <div>
                <h3>Akun</h3>
                <ul>
                    @auth
                        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li><a href="{{ route('transactions.index') }}">Riwayat transaksi</a></li>
                    @else
                        <li><a href="{{ route('login') }}">Masuk dengan Google</a></li>
                    @endauth
                </ul>
            </div>
            <div>
                <h3>Hubungi</h3>
                <ul>
                    <li>
                        <a href="https://wa.me/6287841947229" target="_blank" rel="noopener noreferrer" style="display:inline-flex;align-items:center;gap:8px;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.52 3.48A11.94 11.94 0 0 0 12.02 0C5.4 0 .02 5.38.02 12c0 2.12.55 4.19 1.6 6.02L0 24l6.15-1.6A11.95 11.95 0 0 0 12.02 24C18.64 24 24 18.62 24 12c0-3.2-1.25-6.21-3.48-8.52ZM12.02 21.8c-1.86 0-3.68-.5-5.27-1.44l-.38-.23-3.65.95.98-3.56-.25-.37A9.76 9.76 0 0 1 2.22 12c0-5.4 4.4-9.8 9.8-9.8 2.62 0 5.08 1.02 6.93 2.87A9.72 9.72 0 0 1 21.82 12c0 5.4-4.4 9.8-9.8 9.8Zm5.37-7.34c-.29-.15-1.73-.86-2-.96-.27-.1-.47-.15-.66.15-.2.29-.76.96-.93 1.15-.17.2-.34.22-.63.07-.29-.15-1.24-.46-2.36-1.46-.87-.78-1.46-1.74-1.63-2.03-.17-.29-.02-.45.13-.6.13-.13.29-.34.44-.51.15-.17.2-.29.29-.49.1-.2.05-.37-.02-.52-.07-.15-.66-1.6-.91-2.19-.24-.58-.48-.5-.66-.51h-.56c-.2 0-.52.07-.79.37-.27.29-1.04 1.02-1.04 2.48s1.07 2.88 1.22 3.08c.15.2 2.1 3.2 5.08 4.49.71.31 1.27.49 1.7.63.71.23 1.36.2 1.87.12.57-.08 1.73-.71 1.97-1.39.24-.68.24-1.27.17-1.39-.07-.12-.27-.2-.56-.34Z"/></svg>
                            <span>+6287841947229</span>
                        </a>
                    </li>
                    <li><a href="{{ route('home') }}#cara-kerja">Cara kerja</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <span>&copy; {{ now()->year }} {{ $appName }}. Seluruh hak dilindungi.</span>
            <span>Dibuat untuk momen yang layak dirayakan.</span>
        </div>
    </div>
</footer>
