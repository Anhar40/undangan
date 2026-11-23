@extends('layouts.admin')

@section('title', 'Dashboard Admin — ' . config('app.name'))
@section('admin_section', 'Ringkasan')

@section('content')
@php
    $cards = [
        ['label' => 'Total customer', 'value' => $stats['customers']],
        ['label' => 'Total undangan', 'value' => $stats['invitations']],
        ['label' => 'Total undangan aktif', 'value' => $stats['active_invitations']],
        ['label' => 'Total draft', 'value' => $stats['draft_invitations']],
        ['label' => 'Total transaksi', 'value' => $stats['transactions']],
        ['label' => 'Total pendapatan', 'value' => 'Rp' . number_format($stats['revenue'], 0, ',', '.')],
        ['label' => 'Transaksi pending', 'value' => $stats['pending_transactions']],
        ['label' => 'Template tersedia', 'value' => $stats['templates']],
    ];
@endphp

<div class="admin-page-head">
    <div>
        <span class="admin-eyebrow">Panel kontrol</span>
        <h1>Dashboard Super Admin</h1>
        <p class="muted">Ringkasan penggunaan aplikasi dan aktivitas terbaru.</p>
    </div>
    <div class="admin-head-actions">
        <a class="btn btn-primary" href="{{ route('admin.templates.index') }}">Kelola Template</a>
        <a class="btn btn-light" href="{{ route('admin.transactions.index') }}">Lihat Transaksi</a>
    </div>
</div>

<div class="admin-stats">
    @foreach ($cards as $card)
        <div class="admin-stat">
            <span>{{ $card['label'] }}</span>
            <strong>{{ $card['value'] }}</strong>
        </div>
    @endforeach
</div>

<div class="admin-card">
    <div class="admin-card-head">
        <div>
            <h2>Transaksi Terbaru</h2>
            <p class="muted">Pembayaran yang paling baru tercatat.</p>
        </div>
        <a class="admin-link" href="{{ route('admin.transactions.index') }}">Lihat semua →</a>
    </div>

    @if ($recentTransactions->isEmpty())
        <div class="admin-empty">
            <div class="admin-empty-icon" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><path d="M4 4h16v16l-2.5-1.6L15 20l-2.5-1.6L10 20l-2.5-1.6L5 20V4Z"/></svg>
            </div>
            <h3>Belum ada transaksi</h3>
            <p>Transaksi akan muncul di sini setelah customer memulai pembayaran.</p>
        </div>
    @else
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Undangan</th>
                        <th>Nominal</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recentTransactions as $transaction)
                        <tr>
                            <td><code>{{ $transaction->order_id }}</code></td>
                            <td>{{ $transaction->user?->name ?? '—' }}</td>
                            <td>{{ $transaction->invitation?->title ?? '—' }}</td>
                            <td>Rp{{ number_format((int) $transaction->gross_amount, 0, ',', '.') }}</td>
                            <td>
                                <x-admin.badge :tone="$transaction->isSuccessful() ? 'success' : 'neutral'">
                                    {{ $transaction->transaction_status?->value }}
                                </x-admin.badge>
                            </td>
                            <td><a class="admin-link" href="{{ route('admin.transactions.show', $transaction) }}">Detail</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="admin-card">
    <div class="admin-card-head">
        <div>
            <h2>Undangan Terbaru</h2>
            <p class="muted">Undangan yang paling baru dibuat customer.</p>
        </div>
        <a class="admin-link" href="{{ route('admin.invitations.index') }}">Lihat semua →</a>
    </div>

    @if ($recentInvitations->isEmpty())
        <div class="admin-empty">
            <div class="admin-empty-icon" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
            </div>
            <h3>Belum ada undangan</h3>
            <p>Undangan baru akan tampil di sini.</p>
        </div>
    @else
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Undangan</th>
                        <th>Customer</th>
                        <th>Template</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recentInvitations as $invitation)
                        <tr>
                            <td>{{ $invitation->title }}</td>
                            <td>{{ $invitation->user?->name ?? '—' }}</td>
                            <td>{{ $invitation->template_name }}</td>
                            <td>
                                <x-admin.badge :tone="$invitation->status?->value === 'ACTIVE' ? 'success' : 'neutral'">
                                    {{ $invitation->status?->label() }}
                                </x-admin.badge>
                            </td>
                            <td><a class="admin-link" href="{{ route('admin.invitations.show', $invitation) }}">Detail</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="admin-card">
    <h2>Aktivitas Terbaru</h2>
    @if ($recentActivity->isEmpty())
        <div class="admin-empty">
            <div class="admin-empty-icon" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><path d="M12 8v4l3 2"/><circle cx="12" cy="12" r="9"/></svg>
            </div>
            <h3>Belum ada aktivitas tercatat</h3>
            <p>Perubahan status dan aksi admin akan tampil di sini.</p>
        </div>
    @else
        <ul class="admin-activity">
            @foreach ($recentActivity as $log)
                <li>
                    <span class="muted small">{{ $log->created_at->translatedFormat('d M Y H:i') }}</span>
                    <strong>{{ $log->user?->name ?? 'Sistem' }}</strong> — {{ $log->description }}
                    @if ($log->reason)<div class="reason">Alasan: {{ $log->reason }}</div>@endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endsection
