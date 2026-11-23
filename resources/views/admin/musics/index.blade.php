@extends('layouts.admin')

@section('title', 'Manajemen Musik — ' . config('app.name'))
@section('admin_section', 'Musik')

@section('content')
<div class="admin-page-head">
    <div>
        <span class="admin-eyebrow">Katalog</span>
        <h1>Manajemen Musik</h1>
        <p class="muted">Kelola musik latar yang dapat dipilih customer pada undangan.</p>
    </div>
    <div class="admin-head-actions">
        <a class="btn btn-primary" href="{{ route('admin.musics.create') }}">Tambah Musik</a>
    </div>
</div>

<form class="admin-toolbar" method="GET" action="{{ route('admin.musics.index') }}" role="search">
    <label class="admin-field admin-field--search">
        <span>Cari musik</span>
        <span class="admin-search-wrap">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input type="search" name="q" value="{{ $search }}" placeholder="Judul, artis, atau sumber">
        </span>
    </label>
    <label class="admin-field">
        <span>Status</span>
        <select name="status">
            <option value="">Semua status</option>
            <option value="active" @selected($status === 'active')>Aktif</option>
            <option value="inactive" @selected($status === 'inactive')>Nonaktif</option>
        </select>
    </label>
    <div class="admin-toolbar-actions">
        <button class="btn btn-primary btn-small" type="submit">Terapkan</button>
        <a class="btn btn-light btn-small" href="{{ route('admin.musics.index') }}">Atur ulang</a>
    </div>
</form>

<div class="admin-card">
    <div class="admin-card-head">
        <div>
            <h2>Daftar Musik</h2>
            <p class="admin-result-count">{{ $musics->total() }} musik ditemukan.</p>
        </div>
    </div>

    @if ($musics->isEmpty())
        <div class="admin-empty">
            <div class="admin-empty-icon" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>
            </div>
            <h3>Belum ada musik</h3>
            <p>Tambah musik agar bisa dipilih customer di editor undangan.</p>
            <a class="btn btn-light btn-small" href="{{ route('admin.musics.create') }}">Tambah Musik</a>
        </div>
    @else
        <div class="admin-table-wrap">
            <table class="admin-table admin-table--stack">
                <thead>
                    <tr>
                        <th>Musik</th>
                        <th>Sumber</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($musics as $music)
                        <tr>
                            <td data-label="Musik">
                                <span class="admin-cell-title">{{ $music->title }}</span>
                                <span class="admin-cell-sub">{{ $music->artist ?: '—' }}</span>
                            </td>
                            <td data-label="Sumber">
                                @if (str_starts_with($music->file_path, 'http'))
                                    <span class="admin-cell-sub">URL eksternal</span>
                                @else
                                    <span class="admin-cell-sub">File upload</span>
                                @endif
                                <audio controls preload="none" style="display:block; max-width:260px; width:100%; margin-top:6px; height:34px;">
                                    <source src="{{ $music->url() }}">
                                </audio>
                            </td>
                            <td data-label="Status">
                                <x-admin.badge :tone="$music->status === 'active' ? 'success' : 'neutral'">
                                    {{ $music->status === 'active' ? 'Aktif' : 'Nonaktif' }}
                                </x-admin.badge>
                            </td>
                            <td data-label="Aksi">
                                <div class="admin-row-actions">
                                    <a class="admin-link" href="{{ route('admin.musics.edit', $music) }}">Edit</a>
                                    <form method="POST" action="{{ route('admin.musics.toggle', $music) }}">
                                        @csrf
                                        <button class="admin-link-btn" type="submit">
                                            {{ $music->status === 'active' ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.musics.destroy', $music) }}"
                                          data-confirm="Hapus musik {{ $music->title }}? Tindakan ini tidak dapat dibatalkan.">
                                        @csrf
                                        @method('DELETE')
                                        <button class="admin-link-btn danger" type="submit">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="admin-pagination">{{ $musics->onEachSide(1)->links('admin.partials.pagination') }}</div>
    @endif
</div>
@endsection
