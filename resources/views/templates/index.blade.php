@extends(auth()->check() ? 'layouts.customer' : 'layouts.app')

@section('title', 'Katalog Template — ' . config('app.name'))
@section('customer_section', 'Katalog Template')

@section('content')
@php
    $labels = [
        'story' => 'Cerita', 'gallery' => 'Galeri', 'music' => 'Musik',
        'theme' => 'Tema', 'rsvp' => 'RSVP', 'families' => 'Turut Mengundang',
        'gift' => 'Amplop', 'live_stream' => 'Live Streaming', 'countdown' => 'Hitung Mundur',
    ];
@endphp
<div class="container">
    <div class="page-head">
        <div>
            <span class="eyebrow">Katalog</span>
            <h1>Pilih Template Undangan</h1>
            <p class="muted">Pratinjau kedua template, bandingkan fiturnya, lalu pilih satu untuk mulai membuat undangan.</p>
        </div>
        <a class="btn btn-secondary" href="{{ route('home') }}">Kembali ke Beranda</a>
    </div>

    <div class="catalog-controls">
        <label class="search-field">
            <span aria-hidden="true">⌕</span>
            <input type="search" placeholder="Cari nama atau fitur template…" aria-label="Cari template" data-template-search>
        </label>
        <div class="filter-group" role="group" aria-label="Filter kategori">
            <button type="button" class="filter-btn is-active" data-category-filter="all">Semua</button>
            @foreach ($templates->pluck('category')->filter()->unique() as $category)
                <button type="button" class="filter-btn" data-category-filter="{{ $category }}">{{ $category }}</button>
            @endforeach
        </div>
    </div>

    @if ($templates->isEmpty())
        <div class="empty-state">
            <h3>Belum ada template yang tersedia.</h3>
            <p class="muted">Super Admin dapat menambahkan template melalui panel administrasi.</p>
        </div>
    @else
        <div class="template-grid">
            @foreach ($templates as $template)
                @php
                    $features = $labels;
                    $enabled = collect($template->features())
                        ->filter(fn ($enabled) => (bool) $enabled)
                        ->keys()
                        ->map(fn ($key) => $features[$key] ?? $key)
                        ->implode(' ');
                @endphp
                <article class="template-card"
                    data-template-card
                    data-template-category="{{ $template->category }}"
                    data-template-search="{{ $template->name }} {{ $template->category }} {{ $template->description }} {{ $enabled }}">
                    <div class="template-thumb">
                        <img src="{{ $template->coverUrl() }}" alt="Pratinjau {{ $template->name }}" loading="lazy">
                        <span class="badge status">Tersedia</span>
                    </div>
                    <div class="template-body">
                        <div class="template-meta">
                            <span class="badge">{{ $template->category }}</span>
                            <span class="muted small">Versi {{ $template->version }}</span>
                        </div>
                        <h2>{{ $template->name }}</h2>
                        <p>{{ $template->description }}</p>
                        <div class="chips">
                            @foreach ($template->features() as $feature => $supported)
                                @if ($supported)
                                    <span class="chip">{{ $labels[$feature] ?? $feature }}</span>
                                @endif
                            @endforeach
                        </div>
                        <div class="price">
                            Rp{{ number_format((int) $template->price, 0, ',', '.') }}
                            <small>Biaya aktivasi sekali bayar</small>
                        </div>
                        <div class="actions">
                            <a class="btn btn-secondary btn-small" href="{{ route('templates.sample', $template) }}">Lihat Demo</a>
                            @auth
                                <form method="POST" action="{{ route('templates.select', $template) }}">
                                    @csrf
                                    <button class="btn btn-primary btn-small" type="submit">Gunakan Template</button>
                                </form>
                            @else
                                <a class="btn btn-primary btn-small" href="{{ route('login') }}">Masuk untuk Memilih</a>
                            @endauth
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
        <div class="empty-state" data-template-empty hidden style="margin-top:18px">
            <h3>Template tidak ditemukan</h3>
            <p class="muted">Coba kata kunci atau kategori lain.</p>
        </div>
    @endif
</div>
@endsection
