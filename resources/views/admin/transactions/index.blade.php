@extends('layouts.admin')

@section('title', 'Manajemen Transaksi — ' . config('app.name'))
@section('admin_section', 'Transaksi')

@section('content')
<div class="admin-page-head">
    <div>
        <span class="admin-eyebrow">Pembayaran</span>
        <h1>Manajemen Transaksi</h1>
        <p class="muted">Lihat seluruh transaksi dan periksa ulang status ke Midtrans bila diperlukan.</p>
    </div>
</div>

<form class="admin-toolbar" method="GET" action="{{ route('admin.transactions.index') }}" role="search">
    <label class="admin-field admin-field--search">
        <span>Cari transaksi</span>
        <span class="admin-search-wrap">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input type="search" name="q" value="{{ $search }}" placeholder="Order ID, customer, atau undangan">
        </span>
    </label>
    <label class="admin-field">
        <span>Status pembayaran</span>
        <select name="status">
            <option value="">Semua status</option>
            <option value="success" @selected($status === 'success')>Berhasil</option>
            <option value="pending" @selected($status === 'pending')>Menunggu</option>
            <option value="failed" @selected($status === 'failed')>Gagal / kedaluwarsa</option>
            <option value="refunded" @selected($status === 'refunded')>Refund / chargeback</option>
        </select>
    </label>
    <div class="admin-toolbar-actions">
        <button class="btn btn-primary btn-small" type="submit">Terapkan</button>
        <a class="btn btn-light btn-small" href="{{ route('admin.transactions.index') }}">Atur ulang</a>
    </div>
</form>

<div class="admin-card">
    <div class="admin-card-head">
        <div>
            <h2>Daftar Transaksi</h2>
            <p class="admin-result-count">{{ $transactions->total() }} transaksi ditemukan.</p>
        </div>
    </div>

    @if ($transactions->isEmpty())
        <div class="admin-empty">
            <div class="admin-empty-icon" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><path d="M4 4h16v16l-2.5-1.6L15 20l-2.5-1.6L10 20l-2.5-1.6L5 20V4Z"/></svg>
            </div>
            <h3>Belum ada transaksi</h3>
            <p>Coba ubah kata kunci atau status filter.</p>
            <a class="btn btn-light btn-small" href="{{ route('admin.transactions.index') }}">Atur ulang filter</a>
        </div>
    @else
        <div class="admin-table-wrap">
            <table class="admin-table admin-table--stack">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Undangan</th>
                        <th>Nominal</th>
                        <th>Metode</th>
                        <th>Status</th>
                        <th>Tanggal</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($transactions as $transaction)
                        <tr>
                            <td data-label="Order ID"><code>{{ $transaction->order_id }}</code></td>
                            <td data-label="Customer">{{ $transaction->user?->name ?? '—' }}</td>
                            <td data-label="Undangan">{{ $transaction->invitation?->title ?? '—' }}</td>
                            <td data-label="Nominal">Rp{{ number_format((int) $transaction->gross_amount, 0, ',', '.') }}</td>
                            <td data-label="Metode">{{ $transaction->payment_type ?: '—' }}</td>
                            <td data-label="Status">
                                <x-admin.badge :tone="$transaction->isSuccessful() ? 'success' : 'neutral'">
                                    {{ $transaction->transaction_status?->value }}
                                </x-admin.badge>
                            </td>
                            <td data-label="Tanggal">{{ ($transaction->transaction_time ?? $transaction->created_at)?->translatedFormat('d M Y H:i') }}</td>
                            <td data-label="Detail">
                                <a class="admin-link" href="{{ route('admin.transactions.show', $transaction) }}">Detail</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="admin-pagination">{{ $transactions->onEachSide(1)->links('admin.partials.pagination') }}</div>
    @endif
</div>
@endsection
