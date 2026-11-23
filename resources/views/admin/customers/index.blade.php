@extends('layouts.admin')

@section('title', 'Manajemen Customer — ' . config('app.name'))
@section('admin_section', 'Customer')

@section('content')
<div class="admin-page-head">
    <div>
        <span class="admin-eyebrow">Pengguna</span>
        <h1>Manajemen Customer</h1>
        <p class="muted">Lihat seluruh customer, jumlah undangan, dan riwayat transaksinya.</p>
    </div>
</div>

<form class="admin-toolbar" method="GET" action="{{ route('admin.customers.index') }}" role="search">
    <label class="admin-field admin-field--search">
        <span>Cari customer</span>
        <span class="admin-search-wrap">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input type="search" name="q" value="{{ $search }}" placeholder="Nama atau email customer">
        </span>
    </label>
    <label class="admin-field">
        <span>Status akun</span>
        <select name="status">
            <option value="">Semua status</option>
            <option value="active" @selected($status === 'active')>Aktif</option>
            <option value="inactive" @selected($status === 'inactive')>Nonaktif</option>
        </select>
    </label>
    <div class="admin-toolbar-actions">
        <button class="btn btn-primary btn-small" type="submit">Terapkan</button>
        <a class="btn btn-light btn-small" href="{{ route('admin.customers.index') }}">Atur ulang</a>
    </div>
</form>

<div class="admin-card">
    <div class="admin-card-head">
        <div>
            <h2>Daftar Customer</h2>
            <p class="admin-result-count">{{ $customers->total() }} customer ditemukan.</p>
        </div>
    </div>

    @if ($customers->isEmpty())
        <div class="admin-empty">
            <div class="admin-empty-icon" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
            </div>
            <h3>Belum ada customer</h3>
            <p>Coba ubah kata kunci atau status filter.</p>
            <a class="btn btn-light btn-small" href="{{ route('admin.customers.index') }}">Atur ulang filter</a>
        </div>
    @else
        <div class="admin-table-wrap">
            <table class="admin-table admin-table--stack">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Status</th>
                        <th>Undangan</th>
                        <th>Transaksi</th>
                        <th>Bergabung</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($customers as $customer)
                        <tr>
                            <td data-label="Customer">
                                <span class="admin-cell-title">{{ $customer->name }}</span>
                                <span class="admin-cell-sub">{{ $customer->email }}</span>
                            </td>
                            <td data-label="Status">
                                <x-admin.badge :tone="$customer->isActive() ? 'success' : 'danger'">
                                    {{ $customer->isActive() ? 'Aktif' : 'Nonaktif' }}
                                </x-admin.badge>
                            </td>
                            <td data-label="Undangan">{{ $customer->invitations_count }}</td>
                            <td data-label="Transaksi">{{ $customer->transactions_count }}</td>
                            <td data-label="Bergabung">{{ $customer->created_at->translatedFormat('d M Y') }}</td>
                            <td data-label="Detail">
                                <a class="admin-link" href="{{ route('admin.customers.show', $customer) }}">Detail</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="admin-pagination">{{ $customers->onEachSide(1)->links('admin.partials.pagination') }}</div>
    @endif
</div>
@endsection
