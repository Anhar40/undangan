@extends('layouts.admin')

@section('title', ($music->exists ? 'Edit' : 'Tambah') . ' Musik — ' . config('app.name'))
@section('admin_section', $music->exists ? 'Edit Musik' : 'Tambah Musik')

@section('content')
@php($editing = $music->exists)

<div class="admin-page-head">
    <div>
        <span class="admin-eyebrow">{{ $editing ? 'Katalog · Edit' : 'Katalog · Baru' }}</span>
        <h1>{{ $editing ? 'Edit Musik' : 'Tambah Musik' }}</h1>
        <p class="muted">Musik aktif akan otomatis muncul di pilihan musik editor undangan customer.</p>
    </div>
    <div class="admin-head-actions">
        <a class="btn btn-light" href="{{ route('admin.musics.index') }}">Kembali</a>
    </div>
</div>

@if ($errors->any())
    <div class="alert alert-error" role="alert">
        <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<form method="POST"
      action="{{ $editing ? route('admin.musics.update', $music) : route('admin.musics.store') }}"
      enctype="multipart/form-data" class="admin-card admin-form">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="admin-card-head">
        <div>
            <h2>Informasi Musik</h2>
            <p class="muted">Unggah file audio atau gunakan URL audio langsung. Musik aktif langsung tampil di daftar musik.</p>
        </div>
    </div>

    <div class="field-grid">
        <div class="field">
            <label for="title">Judul musik</label>
            <input id="title" name="title" type="text" value="{{ old('title', $music->title) }}" required maxlength="120">
        </div>
        <div class="field">
            <label for="artist">Artis / sumber</label>
            <input id="artist" name="artist" type="text" value="{{ old('artist', $music->artist) }}" maxlength="120" placeholder="Opsional">
        </div>
        <div class="field">
            <label for="status">Status</label>
            <select id="status" name="status" required>
                <option value="active" @selected(old('status', $music->status) === 'active')>Aktif</option>
                <option value="inactive" @selected(old('status', $music->status) === 'inactive')>Nonaktif</option>
            </select>
        </div>
        <div class="field">
            <label for="audio_file">Upload file musik</label>
            <input id="audio_file" name="audio_file" type="file" accept=".mp3,.wav,.ogg,.m4a,.aac,audio/*">
            <span class="field-hint">MP3/WAV/OGG/M4A/AAC, maksimal 10 MB. Jika diisi, file ini akan digunakan.</span>
        </div>
        <div class="field full">
            <label for="file_url">URL audio langsung (opsional)</label>
            <input id="file_url" name="file_url" type="url" value="{{ old('file_url', str_starts_with((string) $music->file_path, 'http') ? $music->file_path : '') }}" placeholder="https://contoh.com/musik.mp3" maxlength="2048">
            <span class="field-hint">Gunakan URL file audio langsung yang publik. URL YouTube/SoundCloud halaman biasa tidak bisa diputar langsung.</span>
        </div>

        @if ($editing && $music->file_path)
            <div class="field full">
                <span class="field-label">Preview saat ini</span>
                <audio controls preload="none" style="width:100%; max-width:420px;">
                    <source src="{{ $music->url() }}">
                </audio>
                <span class="field-hint">Sumber saat ini: <code>{{ $music->file_path }}</code></span>
            </div>
        @endif
    </div>

    <div class="admin-form-actions">
        <button class="btn btn-primary" type="submit">{{ $editing ? 'Simpan Perubahan' : 'Simpan Musik' }}</button>
        <a class="btn btn-light" href="{{ route('admin.musics.index') }}">Batal</a>
    </div>
</form>
@endsection
