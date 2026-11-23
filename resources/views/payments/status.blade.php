@extends('layouts.customer')

@section('title', 'Status Pembayaran — ' . config('app.name'))
@section('customer_section', 'Status Pembayaran')

@section('content')
@php
    $instructions = $instructions ?? \App\Data\PaymentInstructionData::fromTransaction($transaction);
    $status = $transaction->transaction_status;
    $isActive = $invitation->status?->value === 'ACTIVE';
    $isPending = $status?->value === 'PENDING';
    $expiresAt = $transaction->payment_expires_at;
    $appTimezone = (string) config('app.timezone', 'Asia/Makassar');
    $timezoneLabel = match ($appTimezone) {
        'Asia/Jakarta' => 'WIB',
        'Asia/Makassar' => 'WITA',
        'Asia/Jayapura' => 'WIT',
        default => 'Waktu setempat',
    };
@endphp
<div class="container">
    <div class="page-head">
        <div>
            <span class="eyebrow">Pembayaran</span>
            <h1>Status Pembayaran</h1>
            <p class="muted">{{ $invitation->title }}</p>
        </div>
    </div>

    <div class="status-timeline" aria-label="Tahapan pembayaran">
        <span class="status-step done">Pembayaran dibuat</span>
        <span class="status-step {{ $isActive ? 'done' : ($isPending ? 'current' : '') }}">Menunggu verifikasi</span>
        <span class="status-step {{ $isActive ? 'done' : '' }}">Undangan aktif</span>
    </div>

    @if ($isActive)
        <div class="alert alert-success">
            <span>Pembayaran terverifikasi. Undangan sudah aktif dan siap dibagikan.</span>
        </div>
    @else
        <div class="alert alert-warning">
            <span>Halaman ini bukan bukti pembayaran. Undangan hanya aktif setelah notifikasi pembayaran terverifikasi.</span>
        </div>
    @endif

    <div class="payment-layout">
        <div class="card">
            @if ($isPending)
                <h2>Instruksi Pembayaran</h2>
                <p class="muted">Selesaikan pembayaran sesuai metode yang Anda pilih. Jangan tutup halaman ini sebelum selesai.</p>

                @if ($expiresAt !== null)
                    <div class="payment-countdown" data-payment-countdown data-expires-at="{{ $expiresAt->toIso8601String() }}"
                         role="timer" aria-label="Sisa waktu pembayaran">
                        <div>
                            <span class="field-hint">Sisa waktu pembayaran</span>
                            <strong class="mono" data-countdown-value>--:--:--</strong>
                        </div>
                        <span class="field-hint" data-countdown-note>{{ $timezoneLabel }}</span>
                    </div>
                @endif

                <div class="payment-reference">
                    <div>
                        <span class="field-hint">{{ $instructions->label }}</span>
                        @if ($instructions->reference)
                            <strong class="mono" id="payment-reference">{{ $instructions->reference }}</strong>
                        @elseif ($instructions->qrString)
                            <strong class="mono" id="payment-reference">QRIS</strong>
                        @else
                            <strong id="payment-reference">Ikuti petunjuk pada halaman pembayaran</strong>
                        @endif
                        @if ($instructions->formattedExpiry())
                            <span class="field-hint">Berlaku sampai {{ $instructions->formattedExpiry() }} {{ $timezoneLabel }}</span>
                        @endif
                    </div>
                    @if ($instructions->reference)
                        <button type="button" class="btn btn-secondary btn-small" data-copy-link="{{ $instructions->reference }}">Salin Nomor</button>
                    @endif
                </div>

                @if ($instructions->qrUrl)
                    <div class="qr-panel">
                        <img src="{{ $instructions->qrUrl }}" alt="Kode QR pembayaran {{ $instructions->label }}" width="220" height="220">
                        <p class="field-hint">Pindai dengan aplikasi pembayaran Anda.</p>
                    </div>
                @endif

                @if ($instructions->steps !== [])
                    <ol class="instruction-steps">
                        @foreach ($instructions->steps as $step)
                            <li>{{ $step }}</li>
                        @endforeach
                    </ol>
                @endif
            @else
                <h2>Detail Transaksi</h2>
                <p class="muted">Status transaksi terbaru yang tercatat pada sistem kami.</p>
            @endif

            <div class="divider"></div>
            <dl class="details">
                <dt>Order ID</dt><dd class="mono">{{ $transaction->order_id }}</dd>
                <dt>Nominal</dt><dd>Rp{{ number_format((int) $transaction->gross_amount, 0, ',', '.') }}</dd>
                <dt>Status transaksi</dt><dd><span class="pill {{ $status?->badgeClass() ?? 'pill-light' }}">{{ $status?->value ?? $transaction->transaction_status }}</span></dd>
                <dt>Status undangan</dt><dd><span class="pill {{ $isActive ? 'pill-success' : 'pill-warning' }}">{{ $invitation->status?->label() }}</span></dd>
                <dt>Metode</dt><dd>{{ $transaction->payment_label ?? $instructions->label }}</dd>
                <dt>Dibuat</dt><dd>{{ $transaction->created_at?->translatedFormat('d M Y H:i') }}</dd>
                @if ($transaction->transaction_time)
                    <dt>Waktu transaksi</dt><dd>{{ $transaction->transaction_time->translatedFormat('d M Y H:i') }}</dd>
                @endif
                @if ($transaction->payment_expires_at)
                    <dt>Kedaluwarsa</dt><dd>{{ $transaction->payment_expires_at->translatedFormat('d M Y H:i') }} {{ $timezoneLabel }}</dd>
                @endif
            </dl>

            <div class="actions">
                @if ($isActive)
                    <a class="btn btn-primary" href="{{ $invitation->publicUrl() }}" target="_blank" rel="noopener">Lihat Undangan</a>
                    <a class="btn btn-secondary" href="https://wa.me/?text={{ rawurlencode($invitation->title . ' — ' . $invitation->publicUrl()) }}" target="_blank" rel="noopener">Bagikan via WhatsApp</a>
                @endif
                @if (! $isActive)
                    @if ($transaction->snap_url)
                        <a class="btn btn-secondary" href="{{ $transaction->snap_url }}" target="_blank" rel="noopener">Lanjutkan Pembayaran</a>
                    @endif
                    <form method="POST" action="{{ route('invitations.payment.check', $invitation) }}">
                        @csrf
                        <button type="submit" class="btn btn-secondary">Periksa Ulang Status</button>
                    </form>
                    <a class="btn btn-ghost" href="{{ route('invitations.activation', $invitation) }}">Buat Pembayaran Baru</a>
                @endif
            </div>
        </div>

        <aside class="payment-summary card" aria-label="Bantuan pembayaran">
            <span class="eyebrow">Bantuan</span>
            <h2>Setelah pembayaran</h2>
            <p class="muted small">Anda tidak perlu mengirim bukti transfer. Sistem akan mencocokkan notifikasi pembayaran dengan order ID dan nominal yang terdaftar.</p>
            <div class="divider"></div>
            <ul class="check-list">
                <li>Gunakan order ID di atas jika ada pertanyaan.</li>
                <li>Tombol periksa ulang hanya membaca status pembayaran terbaru dari sistem kami.</li>
                <li>Status undangan dan status pembayaran dicatat terpisah.</li>
            </ul>
            <a class="link-btn" href="{{ route('transactions.index') }}">Lihat riwayat transaksi</a>
        </aside>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (() => {
        const root = document.querySelector('[data-payment-countdown]');
        if (!root) return;

        const expiry = Date.parse(root.dataset.expiresAt || '');
        const value = root.querySelector('[data-countdown-value]');
        const note = root.querySelector('[data-countdown-note]');
        let timer = null;

        const pad = (number) => String(number).padStart(2, '0');

        function render() {
            if (!Number.isFinite(expiry)) {
                value.textContent = '--:--:--';
                return;
            }

            const remaining = Math.floor((expiry - Date.now()) / 1000);

            if (remaining <= 0) {
                value.textContent = '00:00:00';
                note.textContent = 'Waktu pembayaran habis';
                root.classList.add('is-expired');

                if (timer !== null) {
                    clearInterval(timer);
                }

                return;
            }

            const hours = Math.floor(remaining / 3600);
            const minutes = Math.floor((remaining % 3600) / 60);
            const seconds = remaining % 60;

            value.textContent = `${pad(hours)}:${pad(minutes)}:${pad(seconds)}`;
        }

        render();
        timer = window.setInterval(render, 1000);
    })();
</script>
@endpush
