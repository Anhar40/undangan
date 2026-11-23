@extends('layouts.admin')

@section('title', 'Manajemen Template — ' . config('app.name'))
@section('admin_section', 'Template')

@section('content')
<div class="admin-page-head">
    <div>
        <span class="admin-eyebrow">Katalog</span>
        <h1>Manajemen Template</h1>
        <p class="muted">Kelola katalog template, harga, fitur, dan status ketersediaan.</p>
    </div>
    <div class="admin-head-actions">
        <a class="btn btn-primary" href="{{ route('admin.templates.create') }}">Tambah Template</a>
    </div>
</div>

<form class="admin-toolbar" method="GET" action="{{ route('admin.templates.index') }}" role="search">
    <label class="admin-field admin-field--search">
        <span>Cari template</span>
        <span class="admin-search-wrap">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input type="search" name="q" value="{{ $search }}" placeholder="Nama, slug, atau kategori">
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
        <a class="btn btn-light btn-small" href="{{ route('admin.templates.index') }}">Atur ulang</a>
    </div>
</form>

<div class="admin-card">
    <div class="admin-card-head">
        <div>
            <h2>Daftar Template</h2>
            <p class="admin-result-count">{{ $templates->total() }} template ditemukan.</p>
        </div>
    </div>

    @if ($templates->isEmpty())
        <div class="admin-empty">
            <div class="admin-empty-icon" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 12 9 5 9-5"/></svg>
            </div>
            <h3>Belum ada template</h3>
            <p>Coba ubah kata kunci atau status filter.</p>
            <a class="btn btn-light btn-small" href="{{ route('admin.templates.index') }}">Atur ulang filter</a>
        </div>
    @else
        <div class="admin-table-wrap">
            <table class="admin-table admin-table--stack">
                <thead>
                    <tr>
                        <th>Template</th>
                        <th>Kategori</th>
                        <th>Harga</th>
                        <th>Versi</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($templates as $template)
                        <tr>
                            <td data-label="Template">
                                <span class="admin-cell-title">{{ $template->name }}</span>
                                <span class="admin-cell-sub">{{ $template->slug }}</span>
                            </td>
                            <td data-label="Kategori">{{ $template->category ?: '—' }}</td>
                            <td data-label="Harga">Rp{{ number_format($template->price, 0, ',', '.') }}</td>
                            <td data-label="Versi">v{{ $template->version }}</td>
                            <td data-label="Status">
                                <x-admin.badge :tone="$template->isActive() ? 'success' : 'neutral'">
                                    {{ $template->isActive() ? 'Aktif' : 'Nonaktif' }}
                                </x-admin.badge>
                            </td>
                            <td data-label="Aksi">
                                <div class="admin-row-actions">
                                    <a class="admin-link" href="{{ route('admin.templates.edit', $template) }}">Edit</a>
                                    <form method="POST" action="{{ route('admin.templates.toggle', $template) }}">
                                        @csrf
                                        <button class="admin-link-btn" type="submit">
                                            {{ $template->isActive() ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.templates.destroy', $template) }}"
                                          data-confirm="Hapus template {{ $template->name }}? Tindakan ini tidak dapat dibatalkan.">
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

        <div class="admin-pagination">{{ $templates->onEachSide(1)->links('admin.partials.pagination') }}</div>
    @endif
</div>
@endsection
