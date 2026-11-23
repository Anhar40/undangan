@extends('layouts.admin')

@section('title', 'Manajemen Undangan — ' . config('app.name'))
@section('admin_section', 'Undangan')

@section('content')
<div class="admin-page-head">
    <div>
        <span class="admin-eyebrow">Publikasi</span>
        <h1>Manajemen Undangan</h1>
        <p class="muted">Pantau status aktivasi dan pembayaran seluruh undangan customer.</p>
    </div>
</div>

<form class="admin-toolbar" method="GET" action="{{ route('admin.invitations.index') }}" role="search">
    <label class="admin-field admin-field--search">
        <span>Cari undangan</span>
        <span class="admin-search-wrap">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input type="search" name="q" value="{{ $search }}" placeholder="Judul, slug, atau customer">
        </span>
    </label>
    <label class="admin-field">
        <span>Status undangan</span>
        <select name="status">
            <option value="">Semua status</option>
            @foreach (\App\Enums\InvitationStatus::cases() as $case)
                <option value="{{ $case->value }}" @selected($status === $case->value)>{{ $case->label() }}</option>
            @endforeach
        </select>
    </label>
    <label class="admin-field">
        <span>Pembayaran</span>
        <select name="payment">
            <option value="">Semua pembayaran</option>
            <option value="success" @selected($payment === 'success')>Berhasil</option>
            <option value="pending" @selected($payment === 'pending')>Menunggu</option>
            <option value="failed" @selected($payment === 'failed')>Gagal / kedaluwarsa</option>
            <option value="refunded" @selected($payment === 'refunded')>Refund / chargeback</option>
        </select>
    </label>
    <div class="admin-toolbar-actions">
        <button class="btn btn-primary btn-small" type="submit">Terapkan</button>
        <a class="btn btn-light btn-small" href="{{ route('admin.invitations.index') }}">Atur ulang</a>
    </div>
</form>

<div class="admin-card">
    <div class="admin-card-head">
        <div>
            <h2>Daftar Undangan</h2>
            <p class="admin-result-count">{{ $invitations->total() }} undangan ditemukan.</p>
        </div>
    </div>

    @if ($invitations->isEmpty())
        <div class="admin-empty">
            <div class="admin-empty-icon" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
            </div>
            <h3>Belum ada undangan</h3>
            <p>Coba ubah kata kunci atau filter status.</p>
            <a class="btn btn-light btn-small" href="{{ route('admin.invitations.index') }}">Atur ulang filter</a>
        </div>
    @else
        <div class="admin-table-wrap">
            <table class="admin-table admin-table--stack">
                <thead>
                    <tr>
                        <th>Undangan</th>
                        <th>Customer</th>
                        <th>Template</th>
                        <th>Status</th>
                        <th>Pembayaran</th>
                        <th>Dibuat</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invitations as $invitation)
                        <tr>
                            <td data-label="Undangan">
                                <span class="admin-cell-title">{{ $invitation->title }}</span>
                                <span class="admin-cell-sub">{{ $invitation->slug }}</span>
                            </td>
                            <td data-label="Customer">{{ $invitation->user?->name ?? '—' }}</td>
                            <td data-label="Template">{{ $invitation->template_name }}</td>
                            <td data-label="Status">
                                <x-admin.badge :tone="$invitation->status?->value === 'ACTIVE' ? 'success' : ($invitation->status?->value === 'SUSPENDED' || $invitation->status?->value === 'EXPIRED' ? 'danger' : 'neutral')">
                                    {{ $invitation->status?->label() }}
                                </x-admin.badge>
                            </td>
                            <td data-label="Pembayaran">
                                <x-admin.badge :tone="$invitation->payment_status?->isSuccessful() ? 'success' : 'neutral'">
                                    {{ $invitation->payment_status?->value }}
                                </x-admin.badge>
                            </td>
                            <td data-label="Dibuat">{{ $invitation->created_at->translatedFormat('d M Y') }}</td>
                            <td data-label="Detail">
                                <a class="admin-link" href="{{ route('admin.invitations.show', $invitation) }}">Detail</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="admin-pagination">{{ $invitations->onEachSide(1)->links('admin.partials.pagination') }}</div>
    @endif
</div>
@endsection
