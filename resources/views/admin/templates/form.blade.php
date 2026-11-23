@extends('layouts.admin')

@section('title', ($template->exists ? 'Edit' : 'Tambah') . ' Template — ' . config('app.name'))
@section('admin_section', $template->exists ? 'Edit Template' : 'Tambah Template')

@section('content')
@php
    $editing = $template->exists;
    $renderer = old('renderer', $template->config['renderer'] ?? 'tema1');
    $pathValue = old('template_path', $template->template_path);
    $sources = $sources + ($pathValue && ! isset($sources[$pathValue]) ? [$pathValue => basename($pathValue)] : []);
    $labels = [
        'story' => 'Cerita pasangan', 'gallery' => 'Galeri foto', 'music' => 'Musik',
        'theme' => 'Pengaturan tema', 'rsvp' => 'RSVP', 'families' => 'Turut mengundang',
        'gift' => 'Amplop digital', 'live_stream' => 'Live streaming', 'countdown' => 'Hitung mundur',
    ];
@endphp

<div class="admin-page-head">
    <div>
        <span class="admin-eyebrow">{{ $editing ? 'Katalog · Edit' : 'Katalog · Baru' }}</span>
        <h1>{{ $editing ? 'Edit Template' : 'Tambah Template' }}</h1>
        <p class="muted">Perubahan tidak merusak undangan yang sudah dibuat karena setiap undangan menyimpan salinan template.</p>
    </div>
    <div class="admin-head-actions">
        <a class="btn btn-light" href="{{ route('admin.templates.index') }}">Kembali</a>
    </div>
</div>

@if ($errors->any())
    <div class="alert alert-error" role="alert">
        <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<form method="POST"
      action="{{ $editing ? route('admin.templates.update', $template) : route('admin.templates.store') }}"
      enctype="multipart/form-data" class="admin-card admin-form">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="admin-card-head">
        <div>
            <h2>Informasi Template</h2>
            <p class="muted">Field bertanda wajib harus diisi sebelum template disimpan.</p>
        </div>
    </div>

    <div class="field-grid">
        <div class="field">
            <label for="name">Nama template</label>
            <input id="name" name="name" type="text" value="{{ old('name', $template->name) }}" required>
        </div>
        <div class="field">
            <label for="slug">Slug</label>
            <input id="slug" name="slug" type="text" value="{{ old('slug', $template->slug) }}" required>
        </div>
        <div class="field">
            <label for="category">Kategori</label>
            <input id="category" name="category" type="text" value="{{ old('category', $template->category) }}" placeholder="Klasik / Modern">
        </div>
        <div class="field">
            <label for="price">Harga (Rp)</label>
            <input id="price" name="price" type="number" min="0" step="1" value="{{ old('price', $template->price !== null ? (int) $template->price : '') }}" required>
        </div>
        <div class="field">
            <label for="version">Versi</label>
            <input id="version" name="version" type="text" value="{{ old('version', $template->version) }}" required>
        </div>
        <div class="field">
            <label for="status">Status</label>
            <select id="status" name="status" required>
                <option value="active" @selected(old('status', $template->status) === 'active')>Aktif</option>
                <option value="inactive" @selected(old('status', $template->status) === 'inactive')>Nonaktif</option>
            </select>
        </div>
        <div class="field">
            <label for="renderer">Renderer</label>
            <select id="renderer" name="renderer" required>
                @foreach (App\Models\Template::rendererOptions() as $value => $label)
                    <option value="{{ $value }}" @selected($renderer === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="thumbnail">Thumbnail</label>
            <input id="thumbnail" name="thumbnail" type="file" accept="image/jpeg,image/png,image/webp">
            @if ($template->thumbnailUrl())
                <img class="admin-current-thumb" src="{{ $template->thumbnailUrl() }}" alt="Thumbnail saat ini">
            @endif
        </div>
        <div class="field full">
            <label for="description">Deskripsi</label>
            <textarea id="description" name="description" rows="3">{{ old('description', $template->description) }}</textarea>
        </div>
        <div class="field full">
            <label for="template_path">File template bawaan</label>
            <select id="template_path" name="template_path">
                <option value="">— Pilih file —</option>
                @foreach ($sources as $path => $label)
                    <option value="{{ $path }}" @selected($pathValue === $path)>{{ $label }}</option>
                @endforeach
            </select>
            <span class="field-hint">Pilih salah satu HTML bawaan atau unggah file baru di bawah.</span>
        </div>
        <div class="field full">
            <label for="template_html">Unggah template HTML baru</label>
            <input id="template_html" name="template_html" type="file" accept=".html,text/html">
            <span class="field-hint">Maksimal 10 MB. File disimpan di storage, bukan direktori publik.</span>
            @if ($editing)
                <span class="field-hint">File saat ini: <code>{{ $template->template_path }}</code></span>
            @endif
        </div>
    </div>

    <fieldset>
        <legend>Fitur yang didukung</legend>
        <div class="feature-grid">
            @foreach ($labels as $feature => $label)
                <label class="feature">
                    <input type="checkbox" name="features[{{ $feature }}]" value="1"
                           @checked((bool) old("features.{$feature}", $template->features()[$feature] ?? false))>
                    <span>{{ $label }}</span>
                </label>
            @endforeach
        </div>
    </fieldset>

    <div class="admin-form-actions">
        <button class="btn btn-primary" type="submit">{{ $editing ? 'Simpan Perubahan' : 'Simpan Template' }}</button>
        <a class="btn btn-light" href="{{ route('admin.templates.index') }}">Batal</a>
    </div>
</form>
@endsection
