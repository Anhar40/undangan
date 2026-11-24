@extends('layouts.customer')

@section('title', 'Dashboard — ' . config('app.name'))
@section('customer_section', 'Dashboard')

@section('content')
<div class="container">
    <div class="dashboard-hero">
        <div>
            <span class="eyebrow">Workspace Anda</span>
            <h1>Dashboard Customer</h1>
            <p class="muted">Halo, {{ auth()->user()->name }}. Kelola undangan dan pembayaran Anda dari sini.</p>
        </div>
        <a class="btn btn-primary" href="{{ route('templates.index') }}">Buat Undangan Baru</a>
    </div>

    @if ($needsActivation)
        <div class="alert reminder" role="status">
            <div>
                <strong>Aktifkan undangan Anda!</strong>
                <div class="muted small">
                    {{ $needsActivation->title }} ·
                    @if ($needsActivation->isPreviewExpired())
                        Masa pratinjau sudah berakhir.
                    @else
                        Sisa pratinjau: {{ gmdate('H:i:s', $needsActivation->previewRemainingSeconds()) }}
                    @endif
                    · Biaya Rp{{ number_format((int) $needsActivation->price, 0, ',', '.') }}
                </div>
            </div>
            <a class="btn btn-primary btn-small" href="{{ route('invitations.activation', $needsActivation) }}">Aktifkan Sekarang</a>
        </div>
    @endif

    <div class="stats">
        <div class="stat"><span>Total undangan</span><strong>{{ $stats['total'] }}</strong></div>
        <div class="stat"><span>Undangan aktif</span><strong>{{ $stats['active'] }}</strong><span class="stat-note">siap dibagikan</span></div>
        <div class="stat"><span>Draft / preview</span><strong>{{ $stats['draft'] }}</strong><span class="stat-note">masih bisa diedit</span></div>
        <div class="stat"><span>Kedaluwarsa</span><strong>{{ $stats['expired'] }}</strong></div>
        <div class="stat"><span>Menunggu pembayaran</span><strong>{{ $stats['pending_payment'] }}</strong></div>
    </div>

    @if ($latestTransaction)
        <div class="alert latest">
            <div>
                <span class="field-hint">Transaksi terakhir</span>
                <strong class="mono">{{ $latestTransaction->order_id }}</strong>
                <div class="muted small">
                    {{ $latestTransaction->invitation?->title }} ·
                    Rp{{ number_format((int) $latestTransaction->gross_amount, 0, ',', '.') }} ·
                    <span class="pill {{ $latestTransaction->transaction_status?->badgeClass() ?? 'pill-light' }}">{{ $latestTransaction->transaction_status?->value }}</span>
                </div>
            </div>
            <a class="btn btn-secondary btn-small" href="{{ route('invitations.payment.status', $latestTransaction->invitation) }}">Lihat Status</a>
        </div>
    @endif

    <div class="section-heading" id="undangan-saya">
        <h2>Undangan Saya</h2>
        <a class="link-btn" href="{{ route('templates.index') }}">+ Buat undangan baru</a>
    </div>

    @if ($invitations->isEmpty())
        <div class="empty-state">
            <h3>Belum ada undangan</h3>
            <p class="muted">Pilih template untuk membuat draft pertama Anda.</p>
            <a class="btn btn-primary" href="{{ route('templates.index') }}">Lihat Template</a>
        </div>
    @else
        <div class="invitation-list">
            @foreach ($invitations as $invitation)
                <article class="invitation-item">
                    <div class="invitation-thumb">
                        @if ($invitation->template?->coverUrl())
                            <img src="{{ $invitation->template->coverUrl() }}" alt="Template {{ $invitation->template_name }}">
                        @else
                            <span>{{ mb_strtoupper(mb_substr($invitation->template_name, 0, 2)) }}</span>
                        @endif
                    </div>
                    <div class="invitation-main">
                        <strong>{{ $invitation->title }}</strong>
                        <div class="meta">{{ $invitation->coupleLabel() }}</div>
                        <div class="meta">
                            {{ $invitation->template_name }} v{{ $invitation->template_version }} ·
                            Acara: {{ $invitation->eventDateLabel() }} ·
                            dibuat {{ $invitation->created_at->translatedFormat('d M Y H:i') }}
                        </div>
                        <div class="invitation-badges">
                            <span class="pill status-{{ strtolower($invitation->status?->value ?? 'draft') }}">{{ $invitation->status?->label() }}</span>
                            <span class="pill pill-light">Pembayaran: {{ $invitation->payment_status?->value }}</span>
                            <span class="pill {{ Auth::user()->can('switchTemplate', $invitation) ? 'pill-light' : 'pill-warning' }}">
                                {{ Auth::user()->can('switchTemplate', $invitation) ? 'Template bisa diganti' : 'Template tidak tersedia' }}
                            </span>
                            @if ($invitation->isPreviewExpired())
                                <span class="pill pill-danger">Pratinjau berakhir</span>
                            @elseif ($invitation->status?->isEditable())
                                <span class="pill pill-warning">Sisa {{ gmdate('H:i:s', $invitation->previewRemainingSeconds()) }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="invitation-actions">
                        <a class="btn btn-primary btn-small" href="{{ route('invitations.edit', $invitation) }}">Kelola Undangan</a>
                        <a class="btn btn-secondary btn-small" href="{{ route('invitations.templates.index', $invitation) }}">
                            {{ Auth::user()->can('switchTemplate', $invitation) ? 'Pilih Template' : 'Lihat Template' }}
                        </a>
                        <a class="btn btn-primary btn-small" href="{{ $invitation->publicUrl() }}" target="_blank" rel="noopener">Lihat Undangan</a>
                        @if ($invitation->status === \App\Enums\InvitationStatus::Active)
                            <a class="btn btn-secondary btn-small" href="{{ route('share.index', ['uri' => $invitation->publicUrl()]) }}">Bagikan</a>
                        @else
                            <a class="btn btn-secondary btn-small" href="{{ route('invitations.activation', $invitation) }}">Aktifkan</a>
                        @endif
                        <details class="share-menu">
                            <summary class="btn btn-light btn-small">Lainnya</summary>
                            <div class="share-popover">
                                <a href="{{ route('transactions.index') }}">Riwayat Pembayaran</a>
                                @if ($invitation->status === \App\Enums\InvitationStatus::Active)
                                    <a href="https://wa.me/?text={{ rawurlencode($invitation->title . ' — ' . $invitation->publicUrl()) }}" target="_blank" rel="noopener">WhatsApp</a>
                                    <button type="button" data-copy-link="{{ $invitation->publicUrl() }}">Salin Link Undangan</button>
                                    <a href="{{ $invitation->publicUrl() }}" target="_blank" rel="noopener">Buka Undangan</a>
                                @endif
                                @can('delete', $invitation)
                                    <form method="POST" action="{{ route('invitations.destroy', $invitation) }}" data-swal-confirm="Hapus draft ini? Tindakan ini tidak dapat dibatalkan." data-swal-title="Hapus draft?" data-swal-confirm-text="Ya, hapus" data-swal-icon="warning" data-swal-danger="1">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn-link-danger" type="submit">Hapus Draft</button>
                                    </form>
                                @endcan
                            </div>
                        </details>
                    </div>
                </article>
            @endforeach
        </div>
    @endif

    <div class="section-heading">
        <h2>Riwayat Transaksi</h2>
        <a class="link-btn" href="{{ route('transactions.index') }}">Lihat semua transaksi</a>
    </div>

    <div class="card" style="padding:0;overflow:hidden">
        @if ($transactions->isEmpty())
            <div class="empty-state" style="border:0">
                <h3>Belum ada transaksi</h3>
                <p class="muted">Pembayaran aktivasi undangan Anda akan muncul di sini.</p>
            </div>
        @else
            <div class="table-wrap">
                <table>
                    <caption class="sr-only">Transaksi terbaru undangan digital</caption>
                    <thead>
                        <tr>
                            <th scope="col">ID Transaksi</th>
                            <th scope="col">Undangan</th>
                            <th scope="col">Nominal</th>
                            <th scope="col">Metode</th>
                            <th scope="col">Tanggal</th>
                            <th scope="col">Status</th>
                            <th scope="col"><span class="sr-only">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($transactions as $transaction)
                            <tr>
                                <td><code>{{ $transaction->order_id }}</code></td>
                                <td>{{ $transaction->invitation?->title ?? '—' }}</td>
                                <td>Rp{{ number_format((int) $transaction->gross_amount, 0, ',', '.') }}</td>
                                <td>{{ $transaction->payment_label ?? $transaction->payment_type ?? '—' }}</td>
                                <td>{{ ($transaction->transaction_time ?? $transaction->created_at)?->translatedFormat('d M Y H:i') }}</td>
                                <td><span class="pill {{ $transaction->transaction_status?->badgeClass() ?? 'pill-light' }}">{{ $transaction->transaction_status?->value }}</span></td>
                                <td class="text-right">
                                    @if ($transaction->transaction_status?->value === 'PENDING')
                                        <a class="btn btn-secondary btn-small" href="{{ route('invitations.payment.status', $transaction->invitation) }}">Lanjutkan</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
