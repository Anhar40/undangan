@extends('layouts.admin')

@section('title', 'Manajemen Voucher — ' . config('app.name'))
@section('admin_section', 'Voucher')

@section('content')
<div class="admin-page-head">
    <div>
        <span class="admin-eyebrow">Pembayaran</span>
        <h1>Manajemen Voucher</h1>
        <p class="muted">Kelola kode voucher untuk diskon aktivasi undangan.</p>
    </div>
    <div class="admin-head-actions">
        <a class="btn btn-primary" href="{{ route('admin.vouchers.create') }}">Tambah Voucher</a>
    </div>
</div>

<form class="admin-toolbar" method="GET" action="{{ route('admin.vouchers.index') }}" role="search">
    <label class="admin-field admin-field--search">
        <span>Cari voucher</span>
        <span class="admin-search-wrap">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input type="search" name="q" value="{{ $search }}" placeholder="Kode atau nama voucher">
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
        <a class="btn btn-light btn-small" href="{{ route('admin.vouchers.index') }}">Atur ulang</a>
    </div>
</form>

<div class="admin-card">
    <div class="admin-card-head">
        <div>
            <h2>Daftar Voucher</h2>
            <p class="admin-result-count">{{ $vouchers->total() }} voucher ditemukan.</p>
        </div>
    </div>

    @if ($vouchers->isEmpty())
        <div class="admin-empty">
            <div class="admin-empty-icon" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.59 13.41 11 3H4v7l9.59 9.59a2 2 0 0 0 2.82 0l4.18-4.18a2 2 0 0 0 0-2.82Z"/><circle cx="7.5" cy="7.5" r="1.5"/></svg>
            </div>
            <h3>Belum ada voucher</h3>
            <p>Tambah voucher agar customer bisa mendapat diskon saat aktivasi.</p>
            <a class="btn btn-light btn-small" href="{{ route('admin.vouchers.create') }}">Tambah Voucher</a>
        </div>
    @else
        <div class="admin-table-wrap">
            <table class="admin-table admin-table--stack">
                <thead>
                    <tr>
                        <th>Voucher</th>
                        <th>Diskon</th>
                        <th>Pakai</th>
                        <th>Masa berlaku</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($vouchers as $voucher)
                        @php
                            $discountLabel = $voucher->type === 'percent'
                                ? rtrim(rtrim(number_format((float) $voucher->value, 2, ',', '.'), '0'), ',') . '%'
                                : 'Rp' . number_format((float) $voucher->value, 0, ',', '.');
                        @endphp
                        <tr>
                            <td data-label="Voucher">
                                <span class="admin-cell-title">{{ $voucher->code }}</span>
                                <span class="admin-cell-sub">{{ $voucher->name }}</span>
                            </td>
                            <td data-label="Diskon">
                                <span class="admin-cell-title">{{ $discountLabel }}</span>
                                @if ($voucher->min_purchase)
                                    <span class="admin-cell-sub">Min. Rp{{ number_format((float) $voucher->min_purchase, 0, ',', '.') }}</span>
                                @endif
                            </td>
                            <td data-label="Pakai">
                                {{ $voucher->used_count }}{{ $voucher->max_uses ? ' / ' . $voucher->max_uses : '' }}
                            </td>
                            <td data-label="Masa berlaku">
                                <span class="admin-cell-sub">
                                    {{ $voucher->starts_at?->format('d M Y H:i') ?? 'Mulai sekarang' }}
                                    —
                                    {{ $voucher->expires_at?->format('d M Y H:i') ?? 'Tanpa batas' }}
                                </span>
                            </td>
                            <td data-label="Status">
                                <x-admin.badge :tone="$voucher->is_active ? 'success' : 'neutral'">
                                    {{ $voucher->is_active ? 'Aktif' : 'Nonaktif' }}
                                </x-admin.badge>
                            </td>
                            <td data-label="Aksi">
                                <div class="admin-row-actions">
                                    <a class="admin-link" href="{{ route('admin.vouchers.edit', $voucher) }}">Edit</a>
                                    <form method="POST" action="{{ route('admin.vouchers.toggle', $voucher) }}">
                                        @csrf
                                        <button class="admin-link-btn" type="submit">
                                            {{ $voucher->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.vouchers.destroy', $voucher) }}"
                                          data-confirm="Hapus voucher {{ $voucher->code }}? Tindakan ini tidak dapat dibatalkan.">
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

        <div class="admin-pagination">{{ $vouchers->onEachSide(1)->links('admin.partials.pagination') }}</div>
    @endif
</div>
@endsection
