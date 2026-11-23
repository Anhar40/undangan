@extends('layouts.admin')

@section('title', 'Detail Transaksi — ' . config('app.name'))
@section('admin_section', 'Detail Transaksi')

@section('content')
<div class="admin-page-head">
    <div>
        <span class="admin-eyebrow">Transaksi · Detail</span>
        <h1>Detail Transaksi</h1>
        <p class="muted">{{ $transaction->order_id }} · {{ $transaction->user?->name ?? '—' }}</p>
    </div>
    <div class="admin-head-actions">
        <a class="btn btn-light" href="{{ route('admin.transactions.index') }}">Kembali</a>
    </div>
</div>

<div class="admin-stats">
    <div class="admin-stat">
        <span>Status pembayaran</span>
        <strong>{{ $transaction->transaction_status?->value }}</strong>
        <small>Notifikasi diterima {{ $transaction->notification_count }} kali</small>
    </div>
    <div class="admin-stat">
        <span>Nominal</span>
        <strong>Rp{{ number_format((int) $transaction->gross_amount, 0, ',', '.') }}</strong>
        <small>{{ $transaction->payment_type ?: 'Metode belum dilaporkan' }}</small>
    </div>
    <div class="admin-stat">
        <span>Status undangan</span>
        <strong>{{ $transaction->invitation?->status?->label() ?? '—' }}</strong>
        <small>{{ $transaction->invitation?->slug ?? '—' }}</small>
    </div>
</div>

<div class="admin-card">
    <h2>Pengecekan Status</h2>
    <p class="muted small">
        Pengecekan memanggil Midtrans Core API dan memakai jalur verifikasi yang sama dengan webhook.
        Aktivasi tetap hanya terjadi setelah status pembayaran terverifikasi dari server pembayaran.
    </p>
    <form method="POST" action="{{ route('admin.transactions.check', $transaction) }}">
        @csrf
        <button class="btn btn-primary" type="submit"
                data-confirm="Periksa status transaksi ini ke Midtrans sekarang?">
            Periksa Ulang ke Midtrans
        </button>
    </form>
</div>

<div class="admin-card">
    <h2>Detail Transaksi</h2>
    <dl class="admin-detail-grid">
        <div><dt>Order ID</dt><dd><code>{{ $transaction->order_id }}</code></dd></div>
        <div><dt>Waktu transaksi</dt><dd>{{ $transaction->transaction_time?->translatedFormat('d M Y H:i') ?? '—' }}</dd></div>
        <div><dt>Dibayar pada</dt><dd>{{ $transaction->paid_at?->translatedFormat('d M Y H:i') ?? '—' }}</dd></div>
        <div><dt>Terverifikasi pada</dt><dd>{{ $transaction->verified_at?->translatedFormat('d M Y H:i') ?? '—' }}</dd></div>
        <div><dt>ID Midtrans</dt><dd>{{ $transaction->midtrans_transaction_id ?: '—' }}</dd></div>
        <div><dt>Fraud status</dt><dd>{{ $transaction->fraud_status ?: '—' }}</dd></div>
        <div><dt>Customer</dt><dd>{{ $transaction->user?->name ?? '—' }} · {{ $transaction->user?->email ?? '—' }}</dd></div>
        <div><dt>Undangan</dt><dd>{{ $transaction->invitation?->title ?? '—' }}</dd></div>
    </dl>
    @if ($transaction->invitation)
        <p class="small"><a class="admin-link" href="{{ route('admin.invitations.show', $transaction->invitation) }}">Lihat detail undangan ↗</a></p>
    @endif
</div>
@endsection
