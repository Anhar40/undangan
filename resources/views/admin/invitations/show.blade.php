@extends('layouts.admin')

@section('title', 'Detail Undangan — ' . config('app.name'))
@section('admin_section', 'Detail Undangan')

@section('content')
<div class="admin-page-head">
    <div>
        <span class="admin-eyebrow">Undangan · Detail</span>
        <h1>Detail Undangan</h1>
        <p class="muted">{{ $invitation->title }} · {{ $invitation->slug }}</p>
    </div>
    <div class="admin-head-actions">
        <a class="btn btn-light" href="{{ route('admin.invitations.index') }}">Kembali</a>
        @if ($invitation->status === \App\Enums\InvitationStatus::Active)
            <a class="btn" href="{{ $invitation->publicUrl() }}" target="_blank" rel="noopener">Buka undangan publik ↗</a>
        @endif
    </div>
</div>

@if ($errors->any())
    <div class="alert alert-error" role="alert">
        <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<div class="admin-stats">
    <div class="admin-stat">
        <span>Status publikasi</span>
        <strong>{{ $invitation->status?->label() }}</strong>
        <small>{{ $invitation->activated_at?->translatedFormat('d M Y H:i') ?? 'Belum diaktifkan' }}</small>
    </div>
    <div class="admin-stat">
        <span>Status pembayaran</span>
        <strong>{{ $invitation->payment_status?->value }}</strong>
        <small>Harga Rp{{ number_format((int) $invitation->price, 0, ',', '.') }}</small>
    </div>
    <div class="admin-stat">
        <span>Pasangan</span>
        <strong style="font-size:1rem">{{ $invitation->coupleLabel() }}</strong>
        <small>Acara: {{ $invitation->eventDateLabel() }}</small>
    </div>
</div>

<div class="admin-card">
    <h2>Ubah Status Manual</h2>
    <p class="muted small">
        Aktivasi manual mencatat alasan dan aktivitas admin. Status pembayaran tidak diubah oleh tindakan ini.
    </p>
    <form method="POST" action="{{ route('admin.invitations.status', $invitation) }}" class="admin-status-form">
        @csrf
        <label>
            Status
            <select name="status">
                <option value="ACTIVE" @selected(old('status', $invitation->status?->value) === 'ACTIVE')>Aktifkan undangan</option>
                <option value="SUSPENDED" @selected(old('status', $invitation->status?->value) === 'SUSPENDED')>Nonaktifkan undangan</option>
            </select>
        </label>
        <label>
            Alasan perubahan (wajib)
            <textarea name="reason" rows="3" minlength="5" maxlength="500" required placeholder="Contoh: pembayaran dikonfirmasi manual oleh admin">{{ old('reason') }}</textarea>
        </label>
        <div class="actions">
            <button class="btn btn-primary" type="submit"
                    data-confirm="Simpan perubahan status undangan ini? Alasan akan dicatat dalam log aktivitas.">
                Simpan Status
            </button>
        </div>
    </form>
</div>

<div class="admin-card">
    <h2>Informasi Undangan</h2>
    <dl class="admin-detail-grid">
        <div><dt>Customer</dt><dd>{{ $invitation->user?->name ?? '—' }} ({{ $invitation->user?->email ?? '—' }})</dd></div>
        <div><dt>Template</dt><dd>{{ $invitation->template_name }} v{{ $invitation->template_version }}</dd></div>
        <div><dt>Dibuat</dt><dd>{{ $invitation->created_at->translatedFormat('d M Y H:i') }}</dd></div>
        <div><dt>Diterbitkan</dt><dd>{{ $invitation->published_at?->translatedFormat('d M Y H:i') ?? '—' }}</dd></div>
        <div><dt>Preview berakhir</dt><dd>{{ $invitation->preview_expires_at?->translatedFormat('d M Y H:i') ?? '—' }}</dd></div>
    </dl>

    @if ($invitation->events->isNotEmpty())
        <h3 class="admin-subhead">Acara</h3>
        <div class="admin-table-wrap">
            <table class="admin-table admin-table--stack">
                <thead><tr><th>Jenis</th><th>Judul</th><th>Tanggal</th><th>Waktu</th><th>Lokasi</th></tr></thead>
                <tbody>
                    @foreach ($invitation->events as $event)
                        <tr>
                            <td data-label="Jenis">{{ $event->event_type?->value }}</td>
                            <td data-label="Judul">{{ $event->title ?: '—' }}</td>
                            <td data-label="Tanggal">{{ $event->event_date?->translatedFormat('d M Y') ?? '—' }}</td>
                            <td data-label="Waktu">{{ $event->start_time ? substr((string) $event->start_time, 0, 5) : '—' }}</td>
                            <td data-label="Lokasi">{{ $event->location_name ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="admin-card">
    <div class="admin-card-head">
        <h2>Transaksi</h2>
        <span class="admin-result-count">{{ $invitation->transactions->count() }} transaksi</span>
    </div>
    @if ($invitation->transactions->isEmpty())
        <div class="admin-empty">
            <h3>Belum ada transaksi</h3>
            <p>Transaksi undangan akan tampil di sini.</p>
        </div>
    @else
        <div class="admin-table-wrap">
            <table class="admin-table admin-table--stack">
                <thead><tr><th>Order ID</th><th>Nominal</th><th>Metode</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @foreach ($invitation->transactions as $transaction)
                        <tr>
                            <td data-label="Order ID"><code>{{ $transaction->order_id }}</code></td>
                            <td data-label="Nominal">Rp{{ number_format((int) $transaction->gross_amount, 0, ',', '.') }}</td>
                            <td data-label="Metode">{{ $transaction->payment_type ?: '—' }}</td>
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

<div class="admin-card">
    <h2>Riwayat Aktivitas Admin</h2>
    @if ($auditLogs->isEmpty())
        <div class="admin-empty">
            <h3>Belum ada perubahan status yang dicatat</h3>
            <p>Aksi admin pada undangan ini akan tampil di sini.</p>
        </div>
    @else
        <ul class="admin-activity">
            @foreach ($auditLogs as $log)
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
