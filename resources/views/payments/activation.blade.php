@extends('layouts.customer')

@section('title', 'Aktivasi Undangan — ' . config('app.name'))
@section('customer_section', 'Aktivasi')

@section('content')
@php
    $methods = $paymentMethods ?? \App\Data\PaymentRequestData::labels();
    $default = old('payment_method', $defaultPaymentType ?? 'qris');
    $discount = (int) ($discount ?? 0);
    $finalPrice = (int) ($finalPrice ?? $price);
    $voucherCode = (string) ($voucherCode ?? '');
    $voucherError = $voucherError ?? null;
    $appliedVoucher = $voucher ?? null;
@endphp
<div class="container">
    <div class="page-head">
        <div>
            <span class="eyebrow">Aktivasi</span>
            <h1>Aktifkan Undangan Digital</h1>
            <p class="muted">{{ $invitation->title }}</p>
        </div>
        <a class="btn btn-secondary" href="{{ route('invitations.edit', $invitation) }}">Kembali ke Editor</a>
    </div>

    <div class="card" aria-label="Ringkasan aktivasi" style="margin-bottom:20px;">
        <span class="eyebrow">Ringkasan</span>
        <h2 style="margin-bottom:14px;">Detail Aktivasi</h2>
        <dl style="display:grid;grid-template-columns:minmax(140px,220px) 1fr;gap:10px 16px;margin:0;">
            <dt style="color:var(--muted,#666);">Undangan</dt><dd style="margin:0;font-weight:600;">{{ $invitation->title }}</dd>
            <dt style="color:var(--muted,#666);">Template</dt><dd style="margin:0;">{{ $invitation->template_name }} v{{ $invitation->template_version }}</dd>
            <dt style="color:var(--muted,#666);">Jenis</dt><dd style="margin:0;">Sekali bayar</dd>
            <dt style="color:var(--muted,#666);">Harga</dt><dd style="margin:0;">Rp{{ number_format((int) $price, 0, ',', '.') }}</dd>
            @if ($discount > 0 && $appliedVoucher)
                <dt style="color:var(--muted,#666);">Voucher ({{ $appliedVoucher->code }})</dt>
                <dd style="margin:0;color:#1a7f37;font-weight:600;">-Rp{{ number_format($discount, 0, ',', '.') }}</dd>
            @endif
            <dt style="color:var(--muted,#666);">Total bayar</dt>
            <dd style="margin:0;font-size:1.35rem;font-weight:700;color:var(--primary,#2f5d50);">Rp{{ number_format($finalPrice, 0, ',', '.') }}</dd>
        </dl>
    </div>

    <div class="card">
        <h2>Pembayaran</h2>
        <p class="muted">Pilih metode pembayaran dan masukkan kode voucher jika ada.</p>

        <form method="GET" action="{{ route('invitations.activation', $invitation) }}" style="margin-bottom:20px;">
            <label class="field" style="margin-bottom:0;">
                <span class="field-label">Kode Voucher</span>
                <div style="display:flex;gap:10px;align-items:stretch;flex-wrap:wrap;">
                    <input type="text" name="voucher" value="{{ $voucherCode }}" placeholder="Masukkan kode voucher" style="flex:1;min-width:220px;">
                    <button type="submit" class="btn btn-secondary">Terapkan</button>
                </div>
            </label>
            @if ($voucherError)
                <div class="alert alert-error" style="margin-top:10px;"><span>{{ $voucherError }}</span></div>
            @elseif ($appliedVoucher && $discount > 0)
                <div class="alert alert-success" style="margin-top:10px;"><span>Voucher <strong>{{ $appliedVoucher->code }}</strong> berhasil diterapkan. Diskon Rp{{ number_format($discount, 0, ',', '.') }}.</span></div>
            @endif
        </form>

        <form method="POST" action="{{ route('invitations.payment.start', $invitation) }}">
            @csrf
            <input type="hidden" name="voucher_code" value="{{ $appliedVoucher?->code ?? '' }}">

            <label class="field">
                <span class="field-label">Metode Pembayaran</span>
                <select name="payment_method" required>
                    @foreach ($methods as $value => $label)
                        <option value="{{ $value }}" @selected($value === $default)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            @if ($pendingTransaction ?? null)
                <div class="alert alert-warning" style="margin-top:18px">
                    <span>Metode {{ $pendingTransaction->payment_label ?? 'sebelumnya' }} masih menunggu pembayaran. Memilih metode lain atau voucher berbeda akan membatalkan pembayaran tersebut dan membuat instruksi baru.</span>
                </div>
            @endif

            <div class="alert" style="margin-top:6px">
                <span>Undangan hanya aktif setelah notifikasi pembayaran terverifikasi. Halaman sukses bukan bukti pembayaran.</span>
            </div>

            <button type="submit" class="btn btn-primary">Buat Pembayaran</button>
        </form>
    </div>
</div>
@endsection
