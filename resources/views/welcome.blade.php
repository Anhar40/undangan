@extends('layouts.app')

@section('title', 'Undangan Digital Pernikahan — ' . config('app.name'))

@php
    $berandaAsset = fn (string $file): string => asset('assets/beranda/' . rawurlencode($file));
    $previewHours = max(1, (int) config('invitation.preview_hours', 10));
@endphp

@section('content')
<section class="hero">
    <div class="hero-bg" aria-hidden="true">
        <img src="{{ $berandaAsset('hero.webp') }}" alt="" width="1709" height="2560" fetchpriority="high" decoding="async">
    </div>
    <div class="hero-overlay" aria-hidden="true"></div>
    <div class="container hero-inner">
        <div class="hero-copy" data-reveal>
            <span class="eyebrow eyebrow-light">Undangan Digital Pernikahan</span>
            <h1 class="display-title">Rayakan cinta dalam satu <em>tautan indah</em>.</h1>
            <p class="lead">Buat, personalisasi, dan bagikan undangan digitalmu dengan mudah.</p>
            <div class="hero-actions">
                <a class="btn btn-primary" href="{{ route('templates.index') }}">Jelajahi Template</a>
                <a class="btn btn-secondary btn-light" href="#cara-kerja">Lihat Cara Kerja</a>
            </div>
            <div class="hero-proof">
                <div><strong>{{ $previewHours }} jam</strong><span>pratinjau gratis</span></div>
                <div><strong>{{ $templates->count() }} template</strong><span>siap dipakai</span></div>
                <div><strong>1 tautan</strong><span>untuk semua tamu</span></div>
            </div>
        </div>
    </div>
</section>

<section class="section section-tint" id="cara-kerja">
    <div class="container">
        <div class="section-head center" data-reveal>
            <span class="eyebrow center">Cara kerja</span>
            <h2 class="section-title">Dari ide ke undangan, tanpa proses panjang.</h2>
            <p class="lead">Cukup empat langkah. Anda bisa melihat hasilnya lebih dulu sebelum memutuskan untuk mengaktifkan.</p>
        </div>
        <div class="steps-grid">
            <article class="step-card reveal" data-reveal>
                <h3>Pilih template</h3>
                <p>Bandingkan template yang tersedia dan pratinjau masing-masing dengan nama tamu yang berbeda.</p>
            </article>
            <article class="step-card reveal" data-reveal>
                <h3>Isi detail acara</h3>
                <p>Tambahkan pasangan, tanggal, lokasi, cerita, galeri, amplop, dan preferensi lainnya.</p>
            </article>
            <article class="step-card reveal" data-reveal>
                <h3>Pratinjau {{ $previewHours }} jam</h3>
                <p>Edit sepuasnya selama jendela pratinjau. Perubahan tidak mengulang waktu.</p>
            </article>
            <article class="step-card reveal" data-reveal>
                <h3>Aktif &amp; bagikan</h3>
                <p>Selesaikan pembayaran. Undangan aktif otomatis setelah notifikasi terverifikasi.</p>
            </article>
        </div>
    </div>
</section>

<section class="section preview-story" id="pratinjau">
    <div class="container preview-story-grid">
        <div class="preview-visual" data-reveal>
            <img src="{{ $berandaAsset('undangan-website.webp') }}" alt="Tampilan undangan digital yang siap dipersonalisasi" width="3000" height="3000" loading="lazy" decoding="async">
            <span class="preview-visual-label">Pratinjau langsung</span>
        </div>
        <div class="preview-copy" data-reveal>
            <span class="eyebrow">Sekilas tampilannya</span>
            <h2 class="section-title">Rapi di layar besar, tetap enak dibaca di ponsel.</h2>
            <p class="lead">Setiap bagian disusun ulang mengikuti lebar layar. Tamu cukup membuka tautan, tanpa perlu mengunduh apa pun.</p>
            <ul class="check-list">
                <li>Nama tamu bisa disesuaikan lewat tautan.</li>
                <li>Galeri, musik, cerita, dan RSVP dalam satu halaman.</li>
                <li>Amplop digital dan informasi acara mudah diakses.</li>
            </ul>
            <a class="btn btn-primary" href="{{ route('templates.index') }}">Lihat Katalog Template</a>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head" data-reveal>
            <span class="eyebrow">Tersedia sekarang</span>
            <h2 class="section-title">Template untuk setiap gaya.</h2>
            <p class="lead">Pilih desain favorit, lalu sesuaikan detail acara, galeri, musik, dan RSVP.</p>
        </div>
        <div class="showcase-grid">
            @forelse ($templates as $template)
                <article class="showcase-card reveal" data-reveal>
                    <a href="{{ route('templates.sample', $template) }}">
                        <img src="{{ $template->coverUrl() }}" alt="Pratinjau {{ $template->name }}" loading="lazy" decoding="async">
                    </a>
                    <div class="showcase-body">
                        <div class="row" style="justify-content:space-between">
                            <span class="badge">{{ $template->category }}</span>
                            <span class="muted small">v{{ $template->version }}</span>
                        </div>
                        <h3>{{ $template->name }}</h3>
                        <p>{{ $template->description }}</p>
                        <div class="actions">
                            <a class="btn btn-secondary btn-small" href="{{ route('templates.sample', $template) }}">Lihat Demo</a>
                            <a class="link-btn" href="{{ route('templates.index') }}">Bandingkan template</a>
                        </div>
                    </div>
                </article>
            @empty
                <div class="empty-state">
                    <h3>Template belum tersedia</h3>
                    <p class="muted">Super Admin dapat menambahkan template melalui panel administrasi.</p>
                </div>
            @endforelse
        </div>
    </div>
</section>

<section class="section section-tint" id="cerita">
    <div class="container">
        <div class="section-head center" data-reveal>
            <span class="eyebrow center">Cerita &amp; janji</span>
            <h2 class="section-title">Detail kecil yang paling diingat tamu.</h2>
            <p class="lead">Foto, cerita, dan kalimat singkat memberi undangan Anda karakter yang tidak dimiliki format cetak.</p>
        </div>
        <div class="story-banner-grid">
            <article class="story-banner story-banner-tall reveal" data-reveal>
                <img src="{{ $berandaAsset('banner-our-stories.webp') }}" alt="Foto pasangan untuk bagian cerita undangan" width="1703" height="2560" loading="lazy" decoding="async">
                <div class="story-banner-content">
                    <span class="eyebrow light">Cerita pasangan</span>
                    <h3>Setiap momen punya tempat.</h3>
                    <p>Share perjalanan kalian dalam beberapa langkah yang mudah dibaca.</p>
                </div>
            </article>
            <article class="story-banner story-banner-wide reveal" data-reveal>
                <img src="{{ $berandaAsset('banner-our-little-promise.webp') }}" alt="Foto pasangan untuk bagian janji pernikahan" width="1264" height="842" loading="lazy" decoding="async">
                <div class="story-banner-content">
                    <span class="eyebrow light">Janji kecil</span>
                    <h3>Kalimat sederhana, kesan yang lama.</h3>
                    <p>Tutup undangan dengan pesan yang terasa seperti suara kalian sendiri.</p>
                </div>
            </article>
        </div>
    </div>
</section>

<section class="section section-soft">
    <div class="container">
        <div class="section-head center" data-reveal>
            <span class="eyebrow center">Kenapa memilih kami</span>
            <h2 class="section-title">Dirancang untuk dibagikan, bukan sekadar disimpan.</h2>
        </div>
        <div class="feature-grid">
            <article class="feature-card reveal" data-reveal>
                <div class="feature-icon" aria-hidden="true">✦</div>
                <h3>Tampilan editorial</h3>
                <p>Tipografi lembut, palet hangat, dan komposisi yang tetap rapi di layar kecil.</p>
            </article>
            <article class="feature-card reveal" data-reveal>
                <div class="feature-icon" aria-hidden="true">✉</div>
                <h3>Personalisasi tamu</h3>
                <p>Tambahkan parameter nama pada tautan agar setiap tamu mendapat sapaan sendiri.</p>
            </article>
            <article class="feature-card reveal" data-reveal>
                <div class="feature-icon" aria-hidden="true">✓</div>
                <h3>Aktivasi tepercaya</h3>
                <p>Status undangan hanya berubah setelah pembayaran diverifikasi dari notifikasi server.</p>
            </article>
        </div>
    </div>
</section>

<section class="section" id="faq">
    <div class="container">
        <div class="section-head center" data-reveal>
            <span class="eyebrow center">Pertanyaan umum</span>
            <h2 class="section-title">Sebelum mulai membuat.</h2>
        </div>
        <div class="faq-list">
            <details class="faq-item">
                <summary>Berapa lama saya bisa mencoba undangan?</summary>
                <p>Anda memiliki {{ $previewHours }} jam sejak draft dibuat untuk mengedit dan melihat pratinjau. Mengubah isi tidak mengulang waktu tersebut.</p>
            </details>
            <details class="faq-item">
                <summary>Kapan undangan bisa dibagikan?</summary>
                <p>Setelah pembayaran aktivasi terverifikasi. Halaman status pembayaran bukan bukti pembayaran, dan tautan publik hanya aktif untuk undangan berstatus ACTIVE.</p>
            </details>
            <details class="faq-item">
                <summary>Apakah tamu perlu login?</summary>
                <p>Tidak. Tamu cukup membuka tautan undangan. Mereka dapat mengisi buku tamu dan RSVP tanpa akun.</p>
            </details>
            <details class="faq-item">
                <summary>Bisakah nama tamu berbeda-beda?</summary>
                <p>Bisa. Gunakan tautan seperti <code>?to=Nama+Tamu</code> agar sapaan pada undangan menyesuaikan.</p>
            </details>
        </div>
    </div>
</section>

<section class="cta-band">
    <div class="container" data-reveal>
        <h2>Siap membuat undangan Anda?</h2>
        <p>Mulai dari template, lihat hasilnya dalam hitungan menit, lalu bagikan dengan bangga.</p>
        <a class="btn" href="{{ auth()->check() ? route('templates.index') : route('login') }}">
            {{ auth()->check() ? 'Pilih Template' : 'Mulai Sekarang' }}
        </a>
    </div>
</section>
@endsection
