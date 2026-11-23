@extends('layouts.admin')

@section('title', ($voucher->exists ? 'Edit' : 'Tambah') . ' Voucher — ' . config('app.name'))
@section('admin_section', $voucher->exists ? 'Edit Voucher' : 'Tambah Voucher')

@section('content')
@php($editing = $voucher->exists)

<div class="admin-page-head">
    <div>
        <span class="admin-eyebrow">{{ $editing ? 'Pembayaran · Edit' : 'Pembayaran · Baru' }}</span>
        <h1>{{ $editing ? 'Edit Voucher' : 'Tambah Voucher' }}</h1>
        <p class="muted">Voucher aktif dapat digunakan customer saat aktivasi undangan.</p>
    </div>
    <div class="admin-head-actions">
        <a class="btn btn-light" href="{{ route('admin.vouchers.index') }}">Kembali</a>
    </div>
</div>

@if ($errors->any())
    <div class="alert alert-error" role="alert">
        <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<form method="POST"
      action="{{ $editing ? route('admin.vouchers.update', $voucher) : route('admin.vouchers.store') }}"
      class="admin-card admin-form">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="admin-card-head">
        <div>
            <h2>Informasi Voucher</h2>
            <p class="muted">Kode akan disimpan dalam huruf besar. Diskon persen maksimal 100%.</p>
        </div>
    </div>

    <div class="field-grid">
        <div class="field">
            <label for="code">Kode voucher</label>
            <input id="code" name="code" type="text" value="{{ old('code', $voucher->code) }}" required maxlength="50" placeholder="CONTOH2026">
        </div>
        <div class="field">
            <label for="name">Nama voucher</label>
            <input id="name" name="name" type="text" value="{{ old('name', $voucher->name) }}" required maxlength="120">
        </div>
        <div class="field">
            <label for="type">Tipe diskon</label>
            <select id="type" name="type" required>
                <option value="fixed" @selected(old('type', $voucher->type) === 'fixed')>Nominal (Rp)</option>
                <option value="percent" @selected(old('type', $voucher->type) === 'percent')>Persen (%)</option>
            </select>
        </div>
        <div class="field">
            <label for="value">Nilai diskon</label>
            <input id="value" name="value" type="number" step="0.01" min="0.01" value="{{ old('value', $voucher->value) }}" required>
            <span class="field-hint">Isi nominal rupiah atau persen sesuai tipe.</span>
        </div>
        <div class="field">
            <label for="min_purchase">Minimal pembelian (Rp)</label>
            <input id="min_purchase" name="min_purchase" type="number" step="0.01" min="0" value="{{ old('min_purchase', $voucher->min_purchase) }}">
        </div>
        <div class="field">
            <label for="max_uses">Batas pemakaian</label>
            <input id="max_uses" name="max_uses" type="number" step="1" min="1" value="{{ old('max_uses', $voucher->max_uses) }}">
            <span class="field-hint">Kosongkan jika tanpa batas.</span>
        </div>
        <div class="field">
            <label for="starts_at">Mulai berlaku</label>
            <input id="starts_at" name="starts_at" type="datetime-local" value="{{ old('starts_at', optional($voucher->starts_at)->format('Y-m-d\TH:i')) }}">
        </div>
        <div class="field">
            <label for="expires_at">Berakhir</label>
            <input id="expires_at" name="expires_at" type="datetime-local" value="{{ old('expires_at', optional($voucher->expires_at)->format('Y-m-d\TH:i')) }}">
        </div>
        <div class="field">
            <label for="is_active">Status</label>
            <select id="is_active" name="is_active">
                <option value="1" @selected(old('is_active', $voucher->is_active ? '1' : '0') == '1')>Aktif</option>
                <option value="0" @selected(old('is_active', $voucher->is_active ? '1' : '0') == '0')>Nonaktif</option>
            </select>
        </div>
        <div class="field full">
            <label for="description">Deskripsi (opsional)</label>
            <textarea id="description" name="description" rows="3" maxlength="1000">{{ old('description', $voucher->description) }}</textarea>
        </div>
    </div>

    <div class="admin-form-actions">
        <button class="btn btn-primary" type="submit">{{ $editing ? 'Simpan Perubahan' : 'Simpan Voucher' }}</button>
        <a class="btn btn-light" href="{{ route('admin.vouchers.index') }}">Batal</a>
    </div>
</form>
@endsection
