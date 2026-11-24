@extends('layouts.customer')

@section('title', 'Pilih Template — ' . $invitation->title)
@section('customer_section', 'Template Undangan')

@section('content')
@php
    $labels = [
        'story' => 'Cerita', 'gallery' => 'Galeri', 'music' => 'Musik',
        'theme' => 'Tema', 'rsvp' => 'RSVP', 'families' => 'Turut Mengundang',
        'gift' => 'Amplop', 'live_stream' => 'Live Streaming', 'countdown' => 'Hitung Mundur',
    ];
    $currentTemplateId = (int) ($invitation->template_id ?? 0);
@endphp
<div class="container">
    <div class="page-head">
        <div>
            <span class="eyebrow">Template Undangan</span>
            <h1>Pilih Template</h1>
            <p class="muted">
                Template menentukan tampilan dan bagian yang tersedia pada undangan
                <strong>{{ $invitation->title }}</strong>.
                @if ($canSwitchTemplate)
                    Anda dapat mengganti template kapan saja, termasuk setelah undangan aktif.
                    Tautan publik undangan tetap sama.
                @else
                    Template tidak dapat diganti pada status undangan ini.
                @endif
            </p>
        </div>
        <div class="page-head-actions">
            <a class="btn btn-secondary" href="{{ route('invitations.edit', $invitation) }}">Kembali ke Editor</a>
        </div>
    </div>

    @if ($invitation->status === \App\Enums\InvitationStatus::Active)
        <div class="alert alert-success" role="status">
            <div>
                <strong>Undangan aktif tetap bisa mengganti template.</strong>
                <div class="muted small">
                    Tautan publik, isi undangan, ucapan, dan data RSVP tidak berubah. Template baru langsung dipakai saat tampilan undangan dibuka kembali.
                </div>
            </div>
        </div>
    @endif

    @if ($templates->isEmpty())
        <div class="empty-state">
            <h3>Belum ada template yang tersedia.</h3>
            <p class="muted">Super Admin dapat menambahkan template melalui panel administrasi.</p>
        </div>
    @else
        <div class="template-grid">
            @foreach ($templates as $template)
                @php
                    $isCurrent = (int) $template->id === $currentTemplateId;
                    $enabled = collect($template->features())
                        ->filter(fn ($enabled) => (bool) $enabled)
                        ->keys()
                        ->map(fn ($key) => $labels[$key] ?? $key)
                        ->implode(' ');
                @endphp
                <article class="template-card{{ $isCurrent ? ' is-current' : '' }}"
                    data-template-category="{{ $template->category }}"
                    data-template-search="{{ $template->name }} {{ $template->category }} {{ $template->description }} {{ $enabled }}">
                    <div class="template-thumb">
                        <img src="{{ $template->coverUrl() }}" alt="Pratinjau {{ $template->name }}" loading="lazy">
                        @if ($isCurrent)
                            <span class="badge status">Template aktif</span>
                        @endif
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
                        <div class="actions">
                            <a class="btn btn-secondary btn-small" href="{{ route('templates.sample', $template) }}" target="_blank" rel="noopener">Lihat Demo</a>
                            @if ($isCurrent)
                                <span class="btn btn-light btn-small is-disabled" aria-disabled="true">Sedang dipakai</span>
                            @elseif (! $canSwitchTemplate)
                                <span class="btn btn-light btn-small is-disabled" aria-disabled="true">Tidak tersedia</span>
                            @else
                                <form method="POST"
                                      action="{{ route('invitations.templates.switch', $invitation) }}"
                                      data-template-switch
                                      data-template-name="{{ $template->name }}">
                                    @csrf
                                    <input type="hidden" name="template_id" value="{{ $template->id }}">
                                    <button class="btn btn-primary btn-small" type="submit">Gunakan Template</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif

    <div class="alert alert-warning" role="note">
        <div>
            <strong>Tentang penggantian template</strong>
            <div class="muted small">
                Isi undangan Anda tetap tersimpan, baik sebelum maupun sesudah aktivasi. Bagian yang tidak didukung template baru akan disembunyikan,
                dan latar khusus yang tidak memiliki slot pada template baru tidak akan ditampilkan.
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    document.querySelectorAll('[data-template-switch]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (form.dataset.confirmed === '1') return;
            event.preventDefault();
            var name = form.getAttribute('data-template-name') || 'template ini';
            var doSubmit = function () {
                form.dataset.confirmed = '1';
                var btn = form.querySelector('button[type="submit"]');
                if (btn) { btn.disabled = true; btn.textContent = 'Memproses...'; }
                if (window.InviteUI && window.InviteUI.hasSwal()) {
                    Swal.fire({ title: 'Mengganti template...', text: 'Mohon tunggu sebentar.', allowOutsideClick: false, allowEscapeKey: false, didOpen: function () { Swal.showLoading(); } });
                }
                form.submit();
            };
            if (window.InviteUI) {
                window.InviteUI.confirm({
                    icon: 'question',
                    title: 'Ganti template?',
                    text: 'Template akan diganti menjadi ' + name + '. Isi undangan tetap tersimpan.',
                    confirmButtonText: 'Ya, ganti template',
                }).then(function (r) { if (r.isConfirmed) doSubmit(); });
            } else {
                if (window.confirm('Ganti template menjadi ' + name + '? Isi undangan tetap tersimpan.')) doSubmit();
            }
        });
    });
})();
</script>
@endpush
