@extends('layouts.admin')

@section('title', 'Detail Customer — ' . config('app.name'))
@section('admin_section', 'Detail Customer')

@section('content')
<div class="admin-page-head">
    <div>
        <span class="admin-eyebrow">Customer · Detail</span>
        <h1>Detail Customer</h1>
        <p class="muted">{{ $customer->name }} · {{ $customer->email }}</p>
    </div>
    <div class="admin-head-actions">
        <a class="btn btn-light" href="{{ route('admin.customers.index') }}">Kembali</a>
    </div>
</div>

@if ($errors->any())
    <div class="alert alert-error" role="alert">
        <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<div class="admin-stats">
    <div class="admin-stat">
        <span>Status akun</span>
        <strong>{{ $customer->isActive() ? 'Aktif' : 'Nonaktif' }}</strong>
        <small>Bergabung {{ $customer->created_at->translatedFormat('d M Y H:i') }}</small>
    </div>
    <div class="admin-stat">
        <span>Jumlah undangan</span>
        <strong>{{ $customer->invitations->count() }}</strong>
        <small>{{ $customer->invitations->where('status', \App\Enums\InvitationStatus::Active)->count() }} undangan aktif</small>
    </div>
    <div class="admin-stat">
        <span>Total transaksi</span>
        <strong>{{ $customer->transactions->count() }}</strong>
        <small>Rp{{ number_format((int) $customer->transactions->sum('gross_amount'), 0, ',', '.') }} tercatat</small>
    </div>
</div>

<div class="admin-card">
    <h2>Status Akun</h2>
    <p class="muted small">
        Menonaktifkan akun langsung memblokir akses customer ke dashboard. Tindakan ini dicatat pada log aktivitas.
    </p>
    <form method="POST" action="{{ route('admin.customers.status', $customer) }}" class="admin-status-form">
        @csrf
        <input type="hidden" name="status" value="{{ $customer->isActive() ? 'inactive' : 'active' }}">
        <label>
            Alasan (opsional)
            <input type="text" name="reason" maxlength="500" value="{{ old('reason') }}" placeholder="Contoh: permintaan customer">
        </label>
        <div class="actions">
            <button class="btn {{ $customer->isActive() ? 'btn-danger' : '' }}" type="submit"
                    data-confirm="{{ $customer->isActive() ? 'Nonaktifkan akun ini? Customer akan kehilangan akses.' : 'Aktifkan kembali akun ini?' }}">
                {{ $customer->isActive() ? 'Nonaktifkan Akun' : 'Aktifkan Kembali' }}
            </button>
        </div>
    </form>
</div>

<div class="admin-card">
    <div class="admin-card-head">
        <h2>Undangan Customer</h2>
        <span class="admin-result-count">{{ $customer->invitations->count() }} undangan</span>
    </div>
    @if ($customer->invitations->isEmpty())
        <div class="admin-empty">
            <h3>Belum ada undangan</h3>
            <p>Undangan customer akan tampil di sini.</p>
        </div>
    @else
        <div class="admin-table-wrap">
            <table class="admin-table admin-table--stack">
                <thead>
                    <tr><th>Undangan</th><th>Template</th><th>Status</th><th>Pembayaran</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($customer->invitations as $invitation)
                        <tr>
                            <td data-label="Undangan">
                                <span class="admin-cell-title">{{ $invitation->title }}</span>
                                <span class="admin-cell-sub">{{ $invitation->slug }}</span>
                            </td>
                            <td data-label="Template">{{ $invitation->template_name }}</td>
                            <td data-label="Status">
                                <x-admin.badge :tone="$invitation->status?->value === 'ACTIVE' ? 'success' : 'neutral'">
                                    {{ $invitation->status?->label() }}
                                </x-admin.badge>
                            </td>
                            <td data-label="Pembayaran">
                                <x-admin.badge :tone="$invitation->payment_status?->isSuccessful() ? 'success' : 'neutral'">
                                    {{ $invitation->payment_status?->value }}
                                </x-admin.badge>
                            </td>
                            <td data-label="Detail">
                                <a class="admin-link" href="{{ route('admin.invitations.show', $invitation) }}">Detail</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="admin-card">
    <div class="admin-card-head">
        <h2>Riwayat Pembayaran</h2>
        <span class="admin-result-count">{{ $customer->transactions->count() }} transaksi</span>
    </div>
    @if ($customer->transactions->isEmpty())
        <div class="admin-empty">
            <h3>Belum ada transaksi</h3>
            <p>Transaksi customer akan tampil di sini.</p>
        </div>
    @else
        <div class="admin-table-wrap">
            <table class="admin-table admin-table--stack">
                <thead>
                    <tr><th>Order ID</th><th>Undangan</th><th>Nominal</th><th>Metode</th><th>Tanggal</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($customer->transactions as $transaction)
                        <tr>
                            <td data-label="Order ID"><code>{{ $transaction->order_id }}</code></td>
                            <td data-label="Undangan">{{ $transaction->invitation?->title ?? '—' }}</td>
                            <td data-label="Nominal">Rp{{ number_format((int) $transaction->gross_amount, 0, ',', '.') }}</td>
                            <td data-label="Metode">{{ $transaction->payment_type ?: '—' }}</td>
                            <td data-label="Tanggal">{{ ($transaction->transaction_time ?? $transaction->created_at)?->translatedFormat('d M Y H:i') }}</td>
                            <td data-label="Status">
                                <x-admin.badge :tone="$transaction->isSuccessful() ? 'success' : 'neutral'">
                                    {{ $transaction->transaction_status?->value }}
                                </x-admin.badge>
                            </td>
                            <td data-label="Detail">
                                <a class="admin-link" href="{{ route('admin.transactions.show', $transaction) }}">Detail</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
