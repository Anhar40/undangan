@extends('layouts.customer')

@section('title', 'Kelola ' . $invitation->title . ' — ' . config('app.name'))
@section('customer_section', 'Kelola Undangan')

@section('content')
@php
    $settings = $invitation->invitationSettings;
    $custom = is_array($settings?->custom_settings) ? $settings->custom_settings : [];
    $setting = fn (string $key, mixed $default = null) => old('settings.' . $key, $settings?->{$key} ?? $custom[$key] ?? $default);
    $checked = fn (mixed $value): bool => filter_var($value, FILTER_VALIDATE_BOOLEAN);
    $coupleOrder = $setting('couple_order', 'female_first');
    $coupleOrder = $coupleOrder instanceof \App\Enums\CoupleOrder ? $coupleOrder->value : (string) $coupleOrder;
    $photoUrl = fn (?string $path): string => $path
        ? (str_starts_with($path, 'http') ? $path : asset('storage/' . ltrim($path, '/')))
        : '';
    $canSwitchTemplate = (bool) ($canSwitchTemplate ?? false);
    // Undangan aktif tetap bisa dikelola, termasuk mengganti template.
    $statusEditable = $invitation->isEditable()
        || $invitation->status === \App\Enums\InvitationStatus::Active;
    $editable = (bool) ($canUpdate ?? false) && $statusEditable;
    $previewExpired = $invitation->isEditable() && $invitation->isPreviewExpired();
    $multipleEvents = (bool) ($features['multiple_events'] ?? false);
    $sections = [
        ['key' => 'pasangan', 'label' => 'A Data Pasangan'],
        ['key' => 'acara', 'label' => 'B Acara dan Lokasi'],
        ['key' => 'cerita', 'label' => 'C Cerita Pasangan'],
        ['key' => 'mengundang', 'label' => 'D Turut Mengundang'],
        ['key' => 'galeri', 'label' => 'E Foto dan Galeri'],
        ['key' => 'musik', 'label' => 'F Musik'],
        ['key' => 'tamu-rsvp', 'label' => 'G Ucapan Tamu'],
        ['key' => 'amplop', 'label' => 'H Amplop Digital'],
        ['key' => 'bagikan', 'label' => 'I Tampilan saat Dibagikan'],
    ];
    $timezones = [
        'Asia/Jakarta' => 'WIB — Asia/Jakarta',
        'Asia/Makassar' => 'WITA — Asia/Makassar',
        'Asia/Jayapura' => 'WIT — Asia/Jayapura',
        'UTC' => 'UTC',
    ];
@endphp

<div class="editor-shell"
     data-editor
     data-default-quote="{{ $defaultQuote['quote'] }}"
     data-default-quote-source="{{ $defaultQuote['source'] }}">

    <header class="editor-head card">
        <div class="editor-head-main">
            <span class="eyebrow">Kelola Undangan</span>
            <h1>{{ $invitation->title }}</h1>
            <div class="editor-meta">
                <span class="pill">{{ $invitation->template_name }} v{{ $invitation->template_version }}</span>
                <span class="pill status-{{ strtolower($invitation->status?->value ?? 'draft') }}">{{ $invitation->status?->label() }}</span>
                <span class="pill {{ $canSwitchTemplate ? 'pill-light' : 'pill-warning' }}">{{ $canSwitchTemplate ? 'Template bisa diganti' : 'Template tidak tersedia' }}</span>
                @if ($invitation->isEditable() && ! $previewExpired)
                    <span class="pill pill-light">Sisa pratinjau <strong id="preview-timer">{{ gmdate('H:i:s', $invitation->previewRemainingSeconds()) }}</strong></span>
                @endif
            </div>
        </div>
        <div class="editor-actions">
            <a class="btn btn-secondary" href="{{ $shareUrl }}">Bagikan</a>
            <a class="btn btn-secondary" href="{{ $templateSelectUrl }}">
                {{ $canSwitchTemplate ? 'Pilih Template' : 'Lihat Template' }}
            </a>
        </div>
    </header>

    @if ($previewExpired)
        <div class="alert alert-error" role="alert">Masa pratinjau 10 jam sudah berakhir. Selesaikan aktivasi untuk melanjutkan.</div>
    @endif

    @if ($invitation->status === \App\Enums\InvitationStatus::Active)
        <div class="alert alert-success" role="status">
            <div>
                <strong>Template dapat diganti kapan saja.</strong>
                <div class="muted small">Undangan aktif tetap dapat mengganti template. Tautan publik, isi, ucapan, dan data RSVP tidak berubah.</div>
            </div>
        </div>
    @endif

    <div id="editor-feedback" class="alert editor-feedback" hidden role="status" aria-live="polite"></div>
    <div id="editor-upload-progress" class="editor-upload-progress" hidden aria-live="polite">
        <div class="editor-upload-progress-head">
            <span id="editor-upload-progress-label">Mengunggah...</span>
            <strong id="editor-upload-progress-value">0%</strong>
        </div>
        <div class="editor-upload-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
            <div id="editor-upload-progress-bar" class="editor-upload-progress-bar" style="width:0%"></div>
        </div>
    </div>

    <div class="editor-workspace">
        <aside class="editor-nav card" aria-label="Bagian pengelolaan undangan">
            <div class="editor-nav-head">
                <span class="eyebrow">Bagian</span>
                <span id="save-state" class="editor-save-state">Tersimpan</span>
            </div>
            <nav class="editor-nav-list">
                @foreach ($sections as $section)
                    <button type="button" class="{{ $loop->first ? 'is-active' : '' }}" data-section="{{ $section['key'] }}">
                        <span>{{ substr($section['label'], 0, 1) }}</span>{{ $section['label'] }}
                    </button>
                @endforeach
            </nav>
            <div class="editor-nav-note">
                <strong>Tips</strong>
                <p>Simpan setiap bagian setelah selesai diubah. Bagian yang kosong otomatis disembunyikan pada undangan publik.</p>
            </div>
        </aside>

        <div class="editor-content card">
            @if ($statusEditable || $editable)
                @if (! $editable)
                    <div class="alert" role="status">Anda memiliki akses lihat saja pada undangan ini. Perubahan hanya dapat dilakukan oleh pemilik undangan.</div>
                @endif
                <form id="editor-form" method="POST" action="{{ route('invitations.update', $invitation) }}" enctype="multipart/form-data" novalidate>
                    @csrf
                    @method('PATCH')

                    <fieldset class="editor-fieldset" @disabled(! $editable)>
                    <section class="editor-section is-active" data-section-panel="pasangan" data-section-title="A Data Pasangan">
                        <div class="section-head">
                            <span class="eyebrow">A</span>
                            <h2>Data Pasangan</h2>
                            <p class="muted">Judul undangan, nama, orang tua, dan foto kedua mempelai. Kolom kosong tidak ditampilkan.</p>
                        </div>

                        <div class="field-grid">
                            <label class="span-2">
                                <span>Judul undangan</span>
                                <input type="text" id="editor-title" name="title" value="{{ old('title', $invitation->title) }}" maxlength="120" required>
                                <small class="field-help-inline">Dipakai pada tab browser, dashboard, dan metadata undangan.</small>
                            </label>
                        </div>

                        @foreach (['groom' => 'Mempelai Pria', 'bride' => 'Mempelai Wanita'] as $role => $label)
                            @php($person = $invitation->{$role})
                            <div class="person-block">
                                <div class="person-head">
                                    <h3>{{ $label }}</h3>
                                    <span class="pill pill-light">{{ $role === 'groom' ? 'Putra' : 'Putri' }}</span>
                                </div>
                                <div class="upload-row">
                                    <img id="{{ $role }}-photo" class="thumb portrait" src="{{ $photoUrl($person?->photo) }}" alt="Foto {{ $label }}" style="{{ $person?->photo ? '' : 'display:none' }}">
                                    <div class="upload-control">
                                        <label class="file-label">
                                            <span>Foto {{ $label }}</span>
                                            <input type="file" name="photos[{{ $role }}]" accept="image/jpeg,image/png,image/webp" data-preview="{{ $role }}-photo">
                                        </label>
                                        <small class="muted small">JPG, PNG, atau WEBP. Maksimal 5 MB.</small>
                                    </div>
                                    @if ($person?->photo)
                                        <label class="check"><input type="checkbox" name="remove_photo[{{ $role }}]" value="1"> Hapus foto</label>
                                    @endif
                                </div>
                                <div class="field-grid">
                                    <label>
                                        <span>Nama lengkap</span>
                                        <input type="text" name="{{ $role }}[full_name]" value="{{ old($role . '.full_name', $person?->full_name) }}" maxlength="120" placeholder="Nama lengkap mempelai">
                                    </label>
                                    <label>
                                        <span>Nama panggilan</span>
                                        <input type="text" name="{{ $role }}[nickname]" value="{{ old($role . '.nickname', $person?->nickname) }}" maxlength="80" placeholder="Nama panggilan">
                                    </label>
                                    <label>
                                        <span>Nama ayah</span>
                                        <input type="text" name="{{ $role }}[father_name]" value="{{ old($role . '.father_name', $person?->father_name) }}" maxlength="120">
                                    </label>
                                    <label>
                                        <span>Nama ibu</span>
                                        <input type="text" name="{{ $role }}[mother_name]" value="{{ old($role . '.mother_name', $person?->mother_name) }}" maxlength="120">
                                    </label>
                                    <label>
                                        <span>Instagram</span>
                                        <input type="text" name="{{ $role }}[instagram]" value="{{ old($role . '.instagram', $person?->instagram) }}" placeholder="@nama" maxlength="120">
                                    </label>
                                    <label>
                                        <span>Media sosial lain</span>
                                        <textarea name="{{ $role }}[social_links]" rows="2" maxlength="1000" placeholder="Facebook|https://...">{{ old($role . '.social_links', is_array($person?->social_links) ? implode("\n", array_map(fn ($link) => is_array($link) ? (($link['label'] ?? 'Link') . '|' . ($link['url'] ?? '')) : (string) $link, $person->social_links)) : '') }}</textarea>
                                        <small class="field-help-inline">Satu tautan per baris: <code>Label|https://...</code>.</small>
                                    </label>
                                </div>
                            </div>
                        @endforeach

                        @php($groomOrderName = trim((string) old('groom.full_name', $invitation->groom?->full_name ?? '')))
                        @php($brideOrderName = trim((string) old('bride.full_name', $invitation->bride?->full_name ?? '')))
                        @php($groomOrderName = $groomOrderName !== '' ? $groomOrderName : 'Nama mempelai pria'))
                        @php($brideOrderName = $brideOrderName !== '' ? $brideOrderName : 'Nama mempelai wanita'))
                        <div class="couple-order-block">
                            <span class="field-label">Urutan Mempelai</span>
                            <p class="muted small">Nama, biodata, Instagram, dan foto mengikuti urutan yang dipilih di seluruh undangan.</p>
                            <div class="couple-order-options">
                                <label class="couple-order-option">
                                    <input type="radio" name="settings[couple_order]" value="male_first" @checked($coupleOrder === 'male_first')>
                                    <span>Laki-laki dulu — <strong data-couple-order-label="male">{{ $groomOrderName }} &amp; {{ $brideOrderName }}</strong></span>
                                </label>
                                <label class="couple-order-option">
                                    <input type="radio" name="settings[couple_order]" value="female_first" @checked($coupleOrder === 'female_first')>
                                    <span>Perempuan dulu — <strong data-couple-order-label="female">{{ $brideOrderName }} &amp; {{ $groomOrderName }}</strong></span>
                                </label>
                            </div>
                        </div>

                        @include('invitations.editor._section-actions', [
                            'key' => 'pasangan',
                            'enabled' => $editable,
                        ])
                    </section>

                    <section class="editor-section" data-section-panel="acara" data-section-title="B Acara dan Lokasi">
                        <div class="section-head">
                            <span class="eyebrow">B</span>
                            <h2>Acara dan Lokasi</h2>
                            <p class="muted">Pesan undangan, kutipan, dan detail akad, resepsi, atau acara lainnya.</p>
                        </div>

                        <div class="field-grid">
                            <label class="span-2">
                                <span>Pesan undangan</span>
                                <textarea name="settings[invitation_message]" rows="4" maxlength="2000" placeholder="Tanpa mengurangi rasa hormat, kami bermaksud mengundang...">{{ $setting('invitation_message', '') }}</textarea>
                            </label>
                            <label class="span-2">
                                <span>Kutipan pernikahan</span>
                                <textarea id="quote-input" name="settings[quote]" rows="3" maxlength="2000">{{ $setting('quote', $defaultQuote['quote']) }}</textarea>
                                <small class="field-help-inline">Nilai awal diisi otomatis saat undangan dibuat dan tersimpan di database.</small>
                            </label>
                            <label>
                                <span>Sumber kutipan</span>
                                <input id="quote-source-input" type="text" name="settings[quote_source]" value="{{ $setting('quote_source', $defaultQuote['source']) }}" maxlength="200">
                            </label>
                            <label>
                                <span>Zona waktu acara</span>
                                @php($acaraTimezone = $setting('timezone', 'Asia/Jakarta'))
                                <select name="settings[timezone]">
                                    @foreach ($timezones as $value => $label)
                                        <option value="{{ $value }}" @selected($acaraTimezone === $value)>{{ $label }}</option>
                                    @endforeach
                                    @if (! is_string($acaraTimezone) || ! array_key_exists($acaraTimezone, $timezones))
                                        <option value="{{ $acaraTimezone }}" selected>{{ $acaraTimezone }}</option>
                                    @endif
                                </select>
                            </label>
                        </div>

                        <input type="hidden" id="quote-action" name="settings[quote_action]" value="">
                        <button type="button" class="btn btn-light" id="restore-quote"><span class="btn-label">Kembalikan kutipan bawaan</span></button>

                        <div class="subsection-head">
                            <h3>Daftar acara</h3>
                            <p class="muted small">Urutan acara mengikuti kolom urutan.</p>
                        </div>
                        <input type="hidden" name="events_present" value="1">
                        <div id="events-rows" class="repeat-list">
                            @forelse (old('events', $invitation->events->all()) as $index => $row)
                                @include('invitations.editor._event-row', [
                                    'index' => $index,
                                    'row' => $row,
                                    'withLive' => (bool) ($features['live_stream'] ?? false),
                                ])
                            @empty
                                @include('invitations.editor._event-row', [
                                    'index' => 0,
                                    'row' => null,
                                    'withLive' => (bool) ($features['live_stream'] ?? false),
                                ])
                            @endforelse
                        </div>
                        <button type="button" class="btn btn-light add-row" data-add="events">+ Tambah Acara</button>

                        @include('invitations.editor._section-actions', [
                            'key' => 'acara',
                            'enabled' => $editable,
                        ])
                    </section>

                    <section class="editor-section" data-section-panel="cerita" data-section-title="C Cerita Pasangan">
                        <div class="section-head">
                            <span class="eyebrow">C</span>
                            <h2>Cerita Pasangan</h2>
                            <p class="muted">Momen penting perjalanan pasangan. Bagian ini otomatis tersembunyi bila kosong.</p>
                        </div>

                        @if ($features['story'] ?? false)
                            <input type="hidden" name="stories_present" value="1">
                            <div id="stories-rows" class="repeat-list">
                                @forelse (old('stories', $invitation->stories->all()) as $index => $row)
                                    @include('invitations.editor._story-row', ['index' => $index, 'row' => $row])
                                @empty
                                    @include('invitations.editor._story-row', ['index' => 0, 'row' => null])
                                @endforelse
                            </div>
                            <button type="button" class="btn btn-light add-row" data-add="stories">+ Tambah Cerita</button>
                        @else
                            <div class="feature-note">Template ini belum mendukung cerita pasangan.</div>
                        @endif

                        @include('invitations.editor._section-actions', [
                            'key' => 'cerita',
                            'enabled' => $editable && ($features['story'] ?? false),
                        ])
                    </section>

                    <section class="editor-section" data-section-panel="mengundang" data-section-title="D Turut Mengundang">
                        <div class="section-head">
                            <span class="eyebrow">D</span>
                            <h2>Turut Mengundang</h2>
                            <p class="muted">Nama keluarga atau pihak yang turut mengundang.</p>
                        </div>

                        @if ($features['families'] ?? false)
                            <input type="hidden" name="families_present" value="1">
                            <div id="families-rows" class="repeat-list">
                                @forelse (old('families', $invitation->families->all()) as $index => $row)
                                    @include('invitations.editor._family-row', ['index' => $index, 'row' => $row])
                                @empty
                                    @include('invitations.editor._family-row', ['index' => 0, 'row' => null])
                                @endforelse
                            </div>
                            <button type="button" class="btn btn-light add-row" data-add="families">+ Tambah Nama</button>
                        @else
                            <div class="feature-note">Template ini belum mendukung bagian turut mengundang.</div>
                        @endif

                        @include('invitations.editor._section-actions', [
                            'key' => 'mengundang',
                            'enabled' => $editable && ($features['families'] ?? false),
                        ])
                    </section>

                    <section class="editor-section" data-section-panel="galeri" data-section-title="E Foto dan Galeri">
                        <div class="section-head">
                            <span class="eyebrow">E</span>
                            <h2>Foto dan Galeri</h2>
                            <p class="muted">Atur foto sampul, footer, foto galeri, dan foto pendukung yang dipakai undangan publik.</p>
                        </div>

                        @if ($features['gallery'] ?? false)
                            @if (! $editable)
                                <div class="alert" role="status">Mode lihat saja: foto yang dipilih tidak akan tersimpan. Masuk sebagai pemilik undangan, lalu tekan <strong>Simpan &amp; Lanjut</strong> pada galeri.</div>
                            @endif

                            @php($photoGalleryStored = is_array($custom['photo_gallery'] ?? null) ? $custom['photo_gallery'] : [])
                            @php($supportingStored = array_values(array_filter((array) ($custom['supporting_photos'] ?? []), static fn ($path): bool => is_string($path) && $path !== '')))

                            <input type="hidden" name="gallery_items_present" value="1">
                            <input type="hidden" name="photo_gallery_present" value="1">

                            <div class="new-upload-block">
                                <h3>Foto cover dan footer</h3>
                                <p class="muted small">Unggahan di bawah mengganti foto yang tersimpan pada slot terkait. Foto mempelai tidak dipakai lagi sebagai latar cover.</p>
                                <div class="field-grid photo-slot-grid">
                                    <div class="photo-slot-group">
                                        <label class="file-label">
                                            <span>Foto cover / sampul (maksimal 2 foto)</span>
                                            <input type="file" name="photo_gallery[cover][]" accept="image/jpeg,image/png,image/webp" multiple data-preview-list="photo-cover-preview">
                                        </label>
                                        <div class="thumb-grid" aria-live="polite">
                                            @foreach ((array) ($photoGalleryStored['cover'] ?? []) as $path)
                                                @if (is_string($path) && $path !== '')
                                                    <figure><img class="thumb" src="{{ $photoUrl($path) }}" alt="Foto cover tersimpan"><figcaption>Cover</figcaption></figure>
                                                @endif
                                            @endforeach
                                        </div>
                                        <div id="photo-cover-preview" class="thumb-grid" aria-live="polite"></div>
                                    </div>

                                    <div class="photo-slot-group">
                                        <label class="file-label">
                                            <span>Foto footer / penutup (maksimal 2 foto)</span>
                                            <input type="file" name="photo_gallery[footer][]" accept="image/jpeg,image/png,image/webp" multiple data-preview-list="photo-footer-preview">
                                        </label>
                                        <div class="thumb-grid" aria-live="polite">
                                            @foreach ((array) ($photoGalleryStored['footer'] ?? []) as $path)
                                                @if (is_string($path) && $path !== '')
                                                    <figure><img class="thumb" src="{{ $photoUrl($path) }}" alt="Foto footer tersimpan"><figcaption>Footer</figcaption></figure>
                                                @endif
                                            @endforeach
                                        </div>
                                        <div id="photo-footer-preview" class="thumb-grid" aria-live="polite"></div>
                                    </div>
                                </div>
                                <small class="muted small">Pilih hingga 2 foto per slot. Unggahan baru mengganti foto tersimpan pada slot tersebut; kosongkan kolom untuk mempertahankan foto lama. Bagian sub cover pada undangan mengikuti foto footer.</small>
                            </div>

                            <div class="new-upload-block">
                                <h3>Foto Galeri</h3>
                                <label class="file-label">
                                    <span>Pilih foto galeri (opsional, maksimal 8)</span>
                                    <input type="file" id="gallery-new" name="gallery_new[]" accept="image/jpeg,image/png,image/webp" multiple data-preview-list="gallery-new-preview">
                                </label>
                                <div id="gallery-new-preview" class="thumb-grid" aria-live="polite"></div>
                                <small class="muted small">Foto galeri tampil sebagai galeri “Our Moments”. Boleh kosong, maksimal 8 foto termasuk yang sudah tersimpan.</small>
                            </div>

                            @php($galleryRows = old('gallery_items', $invitation->galleries->all()))
                            @if (is_array($galleryRows) && $galleryRows !== [])
                                <div class="subsection-head">
                                    <h3>Foto galeri tersimpan</h3>
                                    <p class="muted small">Ubah keterangan, urutan, atau foto utama. Gunakan unggahan di atas untuk menambah foto.</p>
                                </div>
                                <div id="gallery_items-rows" class="repeat-list">
                                    @foreach ($galleryRows as $index => $row)
                                        @include('invitations.editor._gallery-row', ['index' => $index, 'row' => $row])
                                    @endforeach
                                </div>
                            @else
                                <p class="muted small" data-empty-note="gallery_items">Belum ada foto galeri.</p>
                            @endif

                            <div class="new-upload-block">
                                <h3>Foto Pendukung</h3>
                                <label class="file-label">
                                    <span>Pilih foto pendukung (opsional, maksimal 3)</span>
                                    <input type="file" id="supporting-photos-new" name="supporting_photos_new[]" accept="image/jpeg,image/png,image/webp" multiple data-preview-list="supporting-photos-new-preview">
                                </label>
                                <div id="supporting-photos-new-preview" class="thumb-grid" aria-live="polite"></div>
                                @if ($supportingStored !== [])
                                    <div class="subsection-head">
                                        <h4>Foto pendukung tersimpan</h4>
                                        <p class="muted small">Centang foto untuk menghapusnya.</p>
                                    </div>
                                    <div class="thumb-grid">
                                        @foreach ($supportingStored as $index => $path)
                                            <figure>
                                                <img class="thumb" src="{{ $photoUrl($path) }}" alt="Foto pendukung tersimpan {{ $index + 1 }}">
                                                <figcaption>
                                                    <label class="muted small">
                                                        <input type="checkbox" name="supporting_photos_remove[{{ $index }}]" value="1" @checked(old('supporting_photos_remove.' . $index))>
                                                        Hapus
                                                    </label>
                                                </figcaption>
                                            </figure>
                                        @endforeach
                                    </div>
                                @endif
                                <small class="muted small">Foto pendukung dipakai untuk foto dekoratif dan latar mempelai. Foto ini terpisah dari galeri dan tidak muncul di galeri “Our Moments”.</small>
                            </div>
                        @else
                            <div class="feature-note">Template ini belum mendukung galeri foto.</div>
                        @endif

                        @include('invitations.editor._section-actions', [
                            'key' => 'galeri',
                            'enabled' => $editable && ($features['gallery'] ?? false),
                        ])
                    </section>

                    <section class="editor-section" data-section-panel="musik" data-section-title="F Musik">
                        <div class="section-head">
                            <span class="eyebrow">F</span>
                            <h2>Musik</h2>
                            <p class="muted">Musik latar undangan. Kosongkan untuk menyembunyikan tombol musik.</p>
                        </div>

                        @if ($features['music'] ?? false)
                            @php($customMusicUrl = (string) ($settings?->music_url ?? ''))
                            @if ($musics->isEmpty() && $customMusicUrl === '')
                                <div class="feature-note">Belum ada musik tersedia. Anda bisa upload musik sendiri di bawah.</div>
                            @endif
                            <div class="field-grid">
                                <label class="span-2">
                                    <span>Pilih musik dari daftar</span>
                                    <select name="settings[music_id]" id="music-select">
                                        <option value="">Tanpa musik / pakai musik custom</option>
                                        @foreach ($musics as $music)
                                            <option value="{{ $music->id }}"
                                                    data-url="{{ $music->url() }}"
                                                    @selected((string) $setting('music_id', '') === (string) $music->id && $customMusicUrl === '')>{{ $music->title }}{{ $music->artist ? ' — ' . $music->artist : '' }}</option>
                                        @endforeach
                                    </select>
                                    <small class="field-help-inline">Memilih musik dari daftar akan mengganti musik custom yang diupload.</small>
                                </label>

                                <div class="span-2 new-upload-block" style="margin-top:0;">
                                    <h3 style="margin-bottom:10px;">Upload musik sendiri</h3>
                                    @if ($customMusicUrl !== '')
                                        <p class="muted small" style="margin-bottom:8px;">Musik custom aktif saat ini:</p>
                                        <audio controls preload="none" style="width:100%; max-width:420px; margin-bottom:10px;">
                                            <source src="{{ str_starts_with($customMusicUrl, 'http') ? $customMusicUrl : asset('storage/' . ltrim($customMusicUrl, '/')) }}">
                                        </audio>
                                        <label class="check" style="margin-bottom:10px;">
                                            <input type="checkbox" name="music_file_remove" value="1">
                                            Hapus musik custom
                                        </label>
                                    @endif
                                    <label class="file-label">
                                        <span>{{ $customMusicUrl !== '' ? 'Ganti file musik custom' : 'Pilih file musik' }}</span>
                                        <input type="file" name="music_file" accept=".mp3,.wav,.ogg,.m4a,.aac,audio/*">
                                    </label>
                                    <small class="field-help-inline">MP3/WAV/OGG/M4A/AAC, maksimal 10 MB. Musik custom yang diupload akan diprioritaskan.</small>
                                </div>

                                <label class="check span-2">
                                    <input type="hidden" name="settings[music_autoplay]" value="0">
                                    <input type="checkbox" name="settings[music_autoplay]" value="1" @checked($checked($setting('music_autoplay', false)))>
                                    Putar otomatis saat sampul dibuka
                                </label>
                            </div>
                            <audio id="music-preview" controls preload="none" class="music-preview"></audio>
                            <p class="muted small">Browser dapat membatasi autoplay. Tombol musik tetap tersedia bila musik dipilih atau musik custom diupload.</p>
                        @else
                            <div class="feature-note">Template ini belum mendukung musik latar.</div>
                        @endif

                        @include('invitations.editor._section-actions', [
                            'key' => 'musik',
                            'enabled' => $editable && ($features['music'] ?? false),
                        ])
                    </section>

                    <section class="editor-section" data-section-panel="tamu-rsvp" data-section-title="G Ucapan Tamu">
                        <div class="section-head">
                            <span class="eyebrow">G</span>
                            <h2>Ucapan Tamu</h2>
                            <p class="muted">Ucapan berasal dari formulir publik. Pilih tampilkan atau sembunyikan.</p>
                        </div>

                        @if ($features['rsvp'] ?? false)
                            <input type="hidden" name="greetings_present" value="1">
                            <div id="greetings-rows" class="repeat-list">
                                @forelse (old('greetings', $invitation->greetings->all()) as $index => $row)
                                    @include('invitations.editor._greeting-row', ['index' => $index, 'row' => $row])
                                @empty
                                    <p class="muted small" data-empty-note="greetings">Belum ada ucapan dari tamu.</p>
                                @endforelse
                            </div>
                        @else
                            <div class="feature-note">Template ini belum mendukung ucapan publik.</div>
                        @endif

                        @include('invitations.editor._section-actions', [
                            'key' => 'tamu-rsvp',
                            'enabled' => $editable && ($features['rsvp'] ?? false),
                        ])
                    </section>

                    <section class="editor-section" data-section-panel="amplop" data-section-title="H Amplop Digital">
                        <div class="section-head">
                            <span class="eyebrow">H</span>
                            <h2>Amplop Digital</h2>
                            <p class="muted">Pilih bank dan isi rekening. Tambahkan satu alamat pengiriman kado jika perlu. Bagian kosong tidak ditampilkan.</p>
                        </div>

                        @if ($features['gift'] ?? false)
                            <input type="hidden" name="gift_accounts_present" value="1">
                            <div id="gift_accounts-rows" class="repeat-list">
                                @forelse (old('gift_accounts', $invitation->giftAccounts->all()) as $index => $row)
                                    @include('invitations.editor._gift-row', ['index' => $index, 'row' => $row, 'banks' => $banks])
                                @empty
                                    @include('invitations.editor._gift-row', ['index' => 0, 'row' => null, 'banks' => $banks])
                                @endforelse
                            </div>
                            <button type="button" class="btn btn-light add-row" data-add="gift_accounts">+ Tambah Rekening Bank</button>

                            <div class="new-upload-block gift-address-block">
                                <h3>Alamat Pengiriman Kado</h3>
                                <p class="field-help-inline">Isi satu alamat jika tamu ingin mengirim kado fisik. Kosongkan jika tidak diperlukan.</p>
                                <label>
                                    <span>Alamat lengkap</span>
                                    <textarea name="settings[gift_address]" rows="2" maxlength="500" placeholder="Nama penerima, jalan, nomor, kota, kode pos">{{ $setting('gift_address', '') }}</textarea>
                                </label>
                            </div>
                        @else
                            <div class="feature-note">Template ini belum mendukung amplop digital.</div>
                        @endif

                        @include('invitations.editor._section-actions', [
                            'key' => 'amplop',
                            'enabled' => $editable && ($features['gift'] ?? false),
                        ])
                    </section>

                    <section class="editor-section" data-section-panel="bagikan" data-section-title="I Tampilan saat Dibagikan">
                        <div class="section-head">
                            <span class="eyebrow">I</span>
                            <h2>Tampilan saat Dibagikan</h2>
                            <p class="muted">Judul, deskripsi, dan gambar pratinjau saat tautan undangan dibagikan ke WhatsApp atau media sosial.</p>
                        </div>

                        <div class="field-grid">
                            <label class="span-2">
                                <span>Judul saat dibagikan</span>
                                <input type="text" name="settings[share_title]" value="{{ $setting('share_title', '') }}" maxlength="120" placeholder="Undangan Pernikahan Ahmad & Siti">
                            </label>
                            <label class="span-2">
                                <span>Deskripsi saat dibagikan</span>
                                <textarea name="settings[share_description]" rows="3" maxlength="300">{{ $setting('share_description', '') }}</textarea>
                            </label>
                        </div>

                        <div class="media-block">
                            <h3>Gambar pratinjau</h3>
                            <div class="upload-row">
                                <img id="share-image-preview" class="thumb" src="{{ $photoUrl($setting('share_image', '')) }}" alt="Gambar saat dibagikan" style="{{ $setting('share_image', '') ? '' : 'display:none' }}">
                                <div class="upload-control">
                                    <label class="file-label">
                                        <span>Unggah gambar</span>
                                        <input type="file" name="share_image" accept="image/jpeg,image/png,image/webp" data-preview="share-image-preview">
                                    </label>
                                    <small class="muted small">JPG, PNG, atau WEBP. Maksimal 5 MB.</small>
                                </div>
                                @if ($setting('share_image', ''))
                                    <label class="check"><input type="checkbox" name="share_image_remove" value="1"> Hapus gambar</label>
                                @endif
                            </div>
                        </div>

                        @include('invitations.editor._section-actions', [
                            'key' => 'bagikan',
                            'enabled' => $editable,
                        ])
                    </section>
                    </fieldset>
                </form>
            @else
                <div class="readonly-pane">
                    <h2>Editor terkunci</h2>
                    <p class="muted">Undangan ini tidak dapat diubah pada status saat ini.</p>
                    <a class="btn btn-secondary" href="{{ route('dashboard') }}">Kembali ke Dashboard</a>
                </div>
            @endif
        </div>
    </div>
</div>

@if ($editable)
    <template id="events-row-template">
        @include('invitations.editor._event-row', [
            'index' => '__INDEX__',
            'row' => null,
            'withLive' => (bool) ($features['live_stream'] ?? false),
        ])
    </template>
    @if ($features['story'] ?? false)
        <template id="stories-row-template">@include('invitations.editor._story-row', ['index' => '__INDEX__', 'row' => null])</template>
    @endif
    @if ($features['families'] ?? false)
        <template id="families-row-template">@include('invitations.editor._family-row', ['index' => '__INDEX__', 'row' => null])</template>
    @endif
    @if ($features['gift'] ?? false)
        <template id="gift_accounts-row-template">@include('invitations.editor._gift-row', ['index' => '__INDEX__', 'row' => null, 'banks' => $banks])</template>
    @endif
@endif

<script>
(function () {
    const shell = document.querySelector('[data-editor]');
    if (!shell) return;

    const form = document.getElementById('editor-form');
    if (!form) return;

    const groomNameInput = form.querySelector('input[name="groom[full_name]"]');
    const brideNameInput = form.querySelector('input[name="bride[full_name]"]');
    const maleOrderLabel = form.querySelector('[data-couple-order-label="male"]');
    const femaleOrderLabel = form.querySelector('[data-couple-order-label="female"]');

    function syncCoupleOrderLabels() {
        const groom = (groomNameInput ? groomNameInput.value : '').trim() || 'Nama mempelai pria';
        const bride = (brideNameInput ? brideNameInput.value : '').trim() || 'Nama mempelai wanita';
        if (maleOrderLabel) maleOrderLabel.textContent = groom + ' & ' + bride;
        if (femaleOrderLabel) femaleOrderLabel.textContent = bride + ' & ' + groom;
    }

    if (groomNameInput && brideNameInput && (maleOrderLabel || femaleOrderLabel)) {
        syncCoupleOrderLabels();
        groomNameInput.addEventListener('input', syncCoupleOrderLabels);
        brideNameInput.addEventListener('input', syncCoupleOrderLabels);
    }

    const feedback = document.getElementById('editor-feedback');
    const stateTop = document.getElementById('save-state');
    const defaultQuote = shell.dataset.defaultQuote || '';
    const defaultQuoteSource = shell.dataset.defaultQuoteSource || '';
    const dirtySections = new Set();

    function sectionTitle(key) {
        const panel = document.querySelector('[data-section-panel="' + key + '"]');
        return panel ? (panel.dataset.sectionTitle || key) : key;
    }

    function setSectionState(key, text, kind) {
        const node = document.querySelector('[data-section-state="' + key + '"]');
        if (!node) return;
        node.textContent = text;
        node.classList.toggle('is-dirty', kind === 'dirty');
        node.classList.toggle('is-error', kind === 'error');
        node.classList.toggle('is-saved', kind === 'saved');
    }

    function refreshGlobalState() {
        const dirty = dirtySections.size > 0;
        if (stateTop) {
            stateTop.textContent = dirty ? dirtySections.size + ' bagian belum disimpan' : 'Tersimpan';
            stateTop.classList.toggle('is-dirty', dirty);
            stateTop.classList.toggle('is-saved', !dirty);
        }
    }

    function revokePreviewUrl(node) {
        if (!node || !node.dataset) return;
        if (node.dataset.objectUrl) {
            URL.revokeObjectURL(node.dataset.objectUrl);
            delete node.dataset.objectUrl;
        }
    }

    function setPreviewFromFiles(target, file) {
        if (!target || !file) return;
        revokePreviewUrl(target);
        const url = URL.createObjectURL(file);
        target.src = url;
        target.dataset.objectUrl = url;
        target.style.display = '';
    }

    function renumberRows(container) {
        if (!container) return;

        Array.from(container.querySelectorAll('[data-row-index]')).forEach(function (row, index) {
            row.dataset.rowIndex = String(index);

            row.querySelectorAll('[name]').forEach(function (field) {
                const current = field.getAttribute('name');
                if (!current) return;
                const next = current.replace(/\[\d+\]/, '[' + index + ']');
                if (next !== current) field.setAttribute('name', next);
            });

            row.querySelectorAll('[id]').forEach(function (node) {
                const next = node.id.replace(/-\d+$/, '-' + index);
                if (next !== node.id) node.id = next;
            });

            row.querySelectorAll('[data-preview]').forEach(function (node) {
                const current = node.dataset.preview;
                if (!current) return;
                const next = current.replace(/-\d+$/, '-' + index);
                if (next !== current) node.dataset.preview = next;
            });

            const label = row.querySelector('.row-head strong');
            if (label && row.dataset.rowLabel) {
                label.textContent = row.dataset.rowLabel + ' ' + (index + 1);
            }

            const order = row.querySelector('input[name$="[sort_order]"]');
            if (order) order.value = String(index);
        });
    }

    function markDirty(target) {
        const panel = typeof target === 'string'
            ? null
            : (target instanceof Element ? target.closest('[data-section-panel]') : null);
        const key = typeof target === 'string'
            ? target
            : (panel ? panel.dataset.sectionPanel : null);

        if (!key) return;
        dirtySections.add(key);
        setSectionState(key, 'Belum disimpan', 'dirty');
        refreshGlobalState();
    }

    let bankListboxSequence = 0;

    function bankListbox(picker) {
        const listbox = picker ? picker.querySelector('.bank-options') : null;
        if (listbox && !listbox.id) listbox.id = 'bank-listbox-' + (++bankListboxSequence);
        return listbox;
    }

    function visibleBankOptions(listbox) {
        if (!listbox) return [];
        return Array.from(listbox.querySelectorAll('.bank-option')).filter(function (option) { return !option.hidden; });
    }

    function clearBankActiveOption(picker) {
        if (!picker) return;
        picker.querySelectorAll('.bank-option.is-active').forEach(function (option) { option.classList.remove('is-active'); });
        const search = picker.querySelector('.bank-search');
        if (search) search.removeAttribute('aria-activedescendant');
    }

    function closeBankMenus(except) {
        form.querySelectorAll('[data-bank-picker] .bank-select-menu').forEach(function (menu) {
            if (menu === except) return;
            menu.hidden = true;
            clearBankActiveOption(menu.closest('[data-bank-picker]'));
            const trigger = menu.parentElement.querySelector('.bank-select-trigger');
            if (trigger) trigger.setAttribute('aria-expanded', 'false');
        });
    }

    function selectBank(picker, bank) {
        const input = picker.querySelector('.bank-value');
        const content = picker.querySelector('.bank-trigger-content');
        const name = picker.querySelector('.bank-name');

        if (input) input.value = bank.dataset.value;
        if (name) name.textContent = bank.dataset.value;
        if (content) {
            const mark = content.querySelector('.bank-mark');
            if (mark) {
                mark.textContent = bank.querySelector('.bank-mark')?.textContent || bank.dataset.value.slice(0, 3).toUpperCase();
                mark.style.setProperty('--bank-color', bank.querySelector('.bank-mark')?.style.getPropertyValue('--bank-color') || '#8A6B3D');
            }
        }

        picker.querySelectorAll('.bank-option').forEach(function (option) {
            option.setAttribute('aria-selected', option === bank ? 'true' : 'false');
        });

        closeBankMenus();
        markDirty('amplop');
    }

    form.addEventListener('click', function (event) {
        const trigger = event.target.closest('.bank-select-trigger');
        if (trigger) {
            const picker = trigger.closest('[data-bank-picker]');
            const menu = picker ? picker.querySelector('.bank-select-menu') : null;
            const listbox = bankListbox(picker);
            const willOpen = menu && menu.hidden;
            closeBankMenus(willOpen ? menu : null);
            if (menu) {
                menu.hidden = !willOpen;
                trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
                if (listbox) trigger.setAttribute('aria-controls', listbox.id);
                if (willOpen) {
                    clearBankActiveOption(picker);
                    const search = menu.querySelector('.bank-search');
                    if (search) {
                        search.value = '';
                        menu.querySelectorAll('.bank-option').forEach(function (option) { option.hidden = false; });
                        menu.querySelectorAll('.bank-group').forEach(function (group) { group.hidden = false; });
                        if (listbox) search.setAttribute('aria-controls', listbox.id);
                        search.focus();
                    }
                }
            }
            return;
        }

        const option = event.target.closest('.bank-option');
        if (option) {
            selectBank(option.closest('[data-bank-picker]'), option);
            return;
        }

        if (!event.target.closest('[data-bank-picker]')) closeBankMenus();
    });

    form.addEventListener('input', function (event) {
        if (!event.target.classList.contains('bank-search')) return;
        const query = event.target.value.trim().toLowerCase();
        const menu = event.target.closest('.bank-select-menu');
        if (!menu) return;

        clearBankActiveOption(menu.closest('[data-bank-picker]'));

        menu.querySelectorAll('.bank-option').forEach(function (option) {
            option.hidden = query !== '' && !(option.dataset.search || '').includes(query);
        });
        menu.querySelectorAll('.bank-group').forEach(function (group) {
            group.hidden = Array.from(group.querySelectorAll('.bank-option')).every(function (option) { return option.hidden; });
        });
    });

    form.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeBankMenus();
            return;
        }

        const search = event.target.closest('.bank-search');
        if (!search) return;

        const menu = search.closest('.bank-select-menu');
        if (!menu || menu.hidden) return;

        const listbox = bankListbox(menu.closest('[data-bank-picker]'));
        const options = visibleBankOptions(listbox);
        if (options.length === 0) return;

        const navigationKeys = ['ArrowDown', 'ArrowUp', 'Home', 'End', 'Enter'];
        if (!navigationKeys.includes(event.key)) return;

        event.preventDefault();

        const active = menu.querySelector('.bank-option.is-active');
        const selected = options.find(function (option) { return option.getAttribute('aria-selected') === 'true'; });
        let index = options.indexOf(active);
        if (index < 0) index = options.indexOf(selected);

        if (event.key === 'Enter') {
            const chosen = index >= 0 ? options[index] : options[0];
            if (chosen) selectBank(chosen.closest('[data-bank-picker]'), chosen);
            return;
        }

        if (index < 0) index = event.key === 'ArrowUp' ? options.length - 1 : 0;
        else if (event.key === 'ArrowDown') index = (index + 1) % options.length;
        else if (event.key === 'ArrowUp') index = (index - 1 + options.length) % options.length;
        else if (event.key === 'Home') index = 0;
        else index = options.length - 1;

        options.forEach(function (option) { option.classList.remove('is-active'); });
        const next = options[index];
        next.classList.add('is-active');
        if (!next.id) next.id = 'bank-option-' + (++bankListboxSequence);
        search.setAttribute('aria-activedescendant', next.id);
        next.scrollIntoView({ block: 'nearest' });
    });

    function flash(message, kind) {
        if (!feedback) return;
        feedback.hidden = false;
        feedback.replaceChildren();
        feedback.className = 'alert editor-feedback ' + (kind === 'error' ? 'alert-error' : 'alert-success');
        const items = Array.isArray(message) ? message : [message];
        if (items.length === 1) {
            feedback.textContent = items[0];
        } else {
            const list = document.createElement('ul');
            items.forEach(function (item) {
                const li = document.createElement('li');
                li.textContent = item;
                list.appendChild(li);
            });
            feedback.appendChild(list);
        }
        if (window.InviteUI) {
            const text = items.join('\n');
            if (kind === 'error') window.InviteUI.error(items.length === 1 ? items[0] : 'Ada kesalahan pada input.', { timer: 4500 });
            else window.InviteUI.success(items.length === 1 ? items[0] : text);
        }
    }

    function errorsFrom(payload) {
        if (payload && payload.errors) {
            return Object.values(payload.errors).flat().map(String);
        }
        return [payload && payload.message ? payload.message : 'Perubahan gagal disimpan.'];
    }

    function setLoading(button, loading) {
        if (!button) return;
        button.disabled = loading;
        button.classList.toggle('is-loading', loading);
        const label = button.querySelector('.btn-label');
        if (label) {
            if (!label.dataset.original) label.dataset.original = label.textContent;
            label.textContent = loading ? 'Menyimpan...' : label.dataset.original;
        }
    }

    const uploadProgress = document.getElementById('editor-upload-progress');
    const uploadProgressLabel = document.getElementById('editor-upload-progress-label');
    const uploadProgressValue = document.getElementById('editor-upload-progress-value');
    const uploadProgressBar = document.getElementById('editor-upload-progress-bar');
    const uploadProgressTrack = uploadProgress ? uploadProgress.querySelector('[role="progressbar"]') : null;

    function showUploadProgress(label, percent, compressing) {
        if (!uploadProgress) return;
        uploadProgress.hidden = false;
        uploadProgress.classList.toggle('is-compressing', !!compressing);
        if (uploadProgressLabel) uploadProgressLabel.textContent = label || 'Mengunggah...';
        const p = Math.max(0, Math.min(100, Math.round(percent || 0)));
        if (uploadProgressValue) uploadProgressValue.textContent = p + '%';
        if (uploadProgressBar) uploadProgressBar.style.width = p + '%';
        if (uploadProgressTrack) uploadProgressTrack.setAttribute('aria-valuenow', String(p));
    }

    function hideUploadProgress() {
        if (!uploadProgress) return;
        uploadProgress.hidden = true;
        uploadProgress.classList.remove('is-compressing');
        if (uploadProgressBar) uploadProgressBar.style.width = '0%';
        if (uploadProgressValue) uploadProgressValue.textContent = '0%';
        if (uploadProgressTrack) uploadProgressTrack.setAttribute('aria-valuenow', '0');
    }

    async function compressPanelImages(panel) {
        const inputs = Array.from(panel.querySelectorAll('input[type="file"]')).filter(function (input) {
            return !input.disabled && input.files && input.files.length > 0;
        });
        if (inputs.length === 0 || !window.InviteCompress) return;
        const total = inputs.reduce(function (n, i) { return n + i.files.length; }, 0);
        let done = 0;
        showUploadProgress('Mengompres foto...', 0, true);
        for (const input of inputs) {
            const files = Array.from(input.files || []);
            const out = [];
            for (const file of files) {
                const compressed = await window.InviteCompress.compressImage(file);
                out.push(compressed);
                done += 1;
                showUploadProgress('Mengompres foto (' + done + '/' + total + ')...', Math.round((done / total) * 100), true);
            }
            if (out.length > 0) {
                try {
                    const dt = new DataTransfer();
                    out.forEach(function (f) { dt.items.add(f); });
                    input.files = dt.files;
                } catch (e) { /* leave original */ }
            }
        }
    }

    function postFormWithProgress(url, formData) {
        return new Promise(function (resolve, reject) {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', url, true);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.upload.onprogress = function (event) {
                if (event.lengthComputable) {
                    const pct = Math.round((event.loaded / event.total) * 100);
                    showUploadProgress('Mengunggah data...', pct, false);
                } else {
                    showUploadProgress('Mengunggah data...', 50, false);
                }
            };
            xhr.upload.onload = function () { showUploadProgress('Memproses...', 100, false); };
            xhr.onerror = function () { reject(new Error('Network error')); };
            xhr.onabort = function () { reject(new Error('Aborted')); };
                        xhr.onload = function () {
                let payload = {};
                try { payload = JSON.parse(xhr.responseText || '{}'); } catch (e) {
                    if (xhr.status === 413) payload = { message: 'File terlalu besar untuk diunggah. Coba kurangi jumlah/ukuran foto.' };
                    else payload = { message: 'Server mengembalikan respons tidak valid. Coba lagi.' };
                }
                if (xhr.status === 413 && !payload.message) payload.message = 'File terlalu besar untuk diunggah.';
                resolve({ ok: xhr.status >= 200 && xhr.status < 300, status: xhr.status, payload: payload });
            };
            xhr.send(formData);
        });
    }

    function validatePanel(panel) {
        const fields = Array.from(panel.querySelectorAll('input[name], select[name], textarea[name]'));

        // Title lives in the Pasangan panel. Validating it from another panel
        // reports an error on a control the customer cannot see.
        if (panel.dataset.sectionPanel === 'pasangan') {
            const title = document.getElementById('editor-title');
            if (title) fields.unshift(title);
        }

        for (const field of fields) {
            if (field.disabled || field.type === 'hidden' || field.getAttribute('name') === 'settings[quote_action]') continue;
            if (typeof field.checkValidity === 'function' && !field.checkValidity()) {
                if (typeof field.reportValidity === 'function') field.reportValidity();
                return false;
            }
        }
        return true;
    }

    function buildPayload(panel) {
        const data = new FormData();
        const token = document.querySelector('meta[name="csrf-token"]');
        data.append('_token', token ? token.content : '');
        data.append('_method', 'PATCH');

        // Title is owned by the Pasangan panel. Sending it from every panel
        // turns an empty title into a hidden 422 on unrelated saves.
        if (panel.dataset.sectionPanel === 'pasangan') {
            const title = document.getElementById('editor-title');
            if (title) data.append('title', title.value);
        }

        panel.querySelectorAll('input[name], select[name], textarea[name]').forEach(function (field) {
            if (field.disabled || field.id === 'editor-title') return;

            if (field.type === 'file') {
                Array.from(field.files || []).forEach(function (file) {
                    data.append(field.name, file);
                });
                return;
            }

            if (field.type === 'checkbox' || field.type === 'radio') {
                if (field.checked) data.append(field.name, field.value);
                return;
            }

            if (field.getAttribute('name') === 'settings[quote_action]' && field.value === '') {
                return;
            }

            data.append(field.name, field.value);
        });

        return data;
    }

    function focusFirstError(payload) {
        if (!payload || !payload.errors) return;
        const firstName = Object.keys(payload.errors)[0];
        if (!firstName) return;

        const parts = firstName.split('.');
        const name = parts.length === 1
            ? parts[0]
            : parts[0] + parts.slice(1).map(function (part) { return '[' + part + ']'; }).join('');
        const candidates = [firstName, name];
        let field = null;

        for (const element of form.elements) {
            if (candidates.includes(element.name)) {
                field = element;
                break;
            }
        }

        if (field && typeof field.focus === 'function') {
            field.focus();
            field.scrollIntoView({ block: 'center' });
        }
    }

    async function saveSection(key, button) {
        const panel = document.querySelector('[data-section-panel="' + key + '"]');
        if (!panel || !validatePanel(panel)) return;

        setLoading(button, true);
        setSectionState(key, 'Menyimpan...', 'dirty');
        hideUploadProgress();

        try {
            await compressPanelImages(panel);
            showUploadProgress('Mengunggah data...', 0, false);
            const payloadData = buildPayload(panel);
            const result = await postFormWithProgress(form.action, payloadData);
            const payload = result.payload || {};

            if (!result.ok) {
                setSectionState(key, 'Gagal disimpan', 'error');
                flash(errorsFrom(payload), 'error');
                focusFirstError(payload);
                return;
            }

            dirtySections.delete(key);
            setSectionState(key, 'Tersimpan', 'saved');
            refreshGlobalState();
            flash(sectionTitle(key) + ' tersimpan.', 'success');

            const nextKey = button ? button.dataset.nextSection : '';
            if (nextKey) {
                activateSection(nextKey);
                if (window.history && window.history.replaceState) {
                    window.history.replaceState(null, '', '#' + nextKey);
                }
                const content = document.querySelector('.editor-content');
                const compact = window.matchMedia('(max-width: 1080px)').matches;
                const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                if (content && compact) {
                    content.scrollIntoView({ block: 'start', behavior: reduceMotion ? 'auto' : 'smooth' });
                }
            }
        } catch (error) {
            setSectionState(key, 'Gagal disimpan', 'error');
            flash('Koneksi terputus atau file terlalu besar. Perubahan belum tersimpan.', 'error');
        } finally {
            hideUploadProgress();
            setLoading(button, false);
        }
    }

    form.addEventListener('input', function (event) { markDirty(event.target); });
    form.addEventListener('change', function (event) { markDirty(event.target); });

    document.querySelectorAll('.save-section').forEach(function (button) {
        button.addEventListener('click', function () {
            saveSection(button.dataset.saveSection, button);
        });
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        const active = document.querySelector('.editor-section.is-active');
        const key = active ? active.dataset.sectionPanel : 'pasangan';
        saveSection(key, document.querySelector('.save-section[data-save-section="' + key + '"]'));
    });

    window.addEventListener('beforeunload', function (event) {
        if (dirtySections.size === 0) return;
        event.preventDefault();
        event.returnValue = '';
    });

    function activateSection(key) {
        const button = document.querySelector('[data-section="' + key + '"]');
        const panel = document.querySelector('[data-section-panel="' + key + '"]');
        if (!button || !panel) return;
        document.querySelectorAll('[data-section]').forEach(function (item) { item.classList.remove('is-active'); });
        document.querySelectorAll('[data-section-panel]').forEach(function (item) { item.classList.remove('is-active'); });
        button.classList.add('is-active');
        panel.classList.add('is-active');
    }

    document.querySelectorAll('[data-section]').forEach(function (button) {
        button.addEventListener('click', function () {
            const key = button.dataset.section;
            activateSection(key);
            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, '', '#' + key);
            }
            const content = document.querySelector('.editor-content');
            const compact = window.matchMedia('(max-width: 1080px)').matches;
            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (content && compact) {
                content.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
            }
        });
    });

    if (window.location.hash) activateSection(window.location.hash.replace('#', ''));

    const rowContainers = {
        events: document.getElementById('events-rows'),
        stories: document.getElementById('stories-rows'),
        families: document.getElementById('families-rows'),
        gift_accounts: document.getElementById('gift_accounts-rows'),
    };

    document.querySelectorAll('[data-add]').forEach(function (button) {
        button.addEventListener('click', function () {
            const key = button.dataset.add;
            const container = rowContainers[key];
            const template = document.getElementById(key + '-row-template');
            if (!container || !template) return;
            const indexes = Array.from(container.querySelectorAll('[data-row-index]'))
                .map(function (node) { return parseInt(node.dataset.rowIndex, 10) || 0; });
            const next = indexes.length ? Math.max.apply(null, indexes) + 1 : 0;
            container.insertAdjacentHTML('beforeend', template.innerHTML.split('__INDEX__').join(String(next)));
            renumberRows(container);
            const note = document.querySelector('[data-empty-note="' + key + '"]');
            if (note) note.hidden = true;
            markDirty(container);
        });
    });

    form.addEventListener('input', function (event) {
        const field = event.target;
        if (field && (field.id === 'quote-input' || field.id === 'quote-source-input')) {
            const action = document.getElementById('quote-action');
            if (action) action.value = '';
        }
    });

    form.addEventListener('click', function (event) {
        const remove = event.target.closest('.remove-row');
        if (remove) {
            const panel = remove.closest('[data-section-panel]');
            const key = panel ? panel.dataset.sectionPanel : null;
            const row = remove.closest('[data-row-index]');
            const container = row ? row.parentElement : null;

            if (row) {
                row.querySelectorAll('[data-preview]').forEach(function (node) {
                    const image = document.getElementById(node.dataset.preview);
                    revokePreviewUrl(image);
                });
                row.remove();
            }

            // The button is detached now, so mark the section by key.
            if (key) markDirty(key);
            if (container) renumberRows(container);
            return;
        }

        const move = event.target.closest('[data-move]');
        if (move) {
            const row = move.closest('[data-row-index]');
            if (!row) return;
            const container = row.parentElement;
            const sibling = move.dataset.move === 'up' ? row.previousElementSibling : row.nextElementSibling;
            if (!sibling || !sibling.hasAttribute('data-row-index')) return;
            if (move.dataset.move === 'up') {
                container.insertBefore(row, sibling);
            } else {
                container.insertBefore(sibling, row);
            }
            renumberRows(container);
            markDirty(move);
            return;
        }

        const removeGreeting = event.target.closest('.remove-greeting');
        if (removeGreeting) {
            const row = removeGreeting.closest('[data-greeting-row]');
            if (!row) return;
            const marker = row.querySelector('[data-greeting-remove]');
            if (marker) marker.value = '1';
            row.hidden = true;
            markDirty(removeGreeting);
            return;
        }

        const clear = event.target.closest('.file-clear');
        if (clear) {
            const input = clear.previousElementSibling;
            const target = document.getElementById(clear.dataset.previewTarget);
            if (input && input.type === 'file') input.value = '';
            if (target) {
                revokePreviewUrl(target);
                if (target.dataset.originalSrc) {
                    target.src = target.dataset.originalSrc;
                    target.style.display = '';
                } else {
                    target.removeAttribute('src');
                    target.style.display = 'none';
                }
            }
            clear.hidden = true;
            markDirty(clear);
            return;
        }
    });

    form.addEventListener('change', async function (event) {
        const input = event.target;
        if (!input.matches || !input.matches('input[type=\"file\"][data-preview]')) return;

        const target = document.getElementById(input.dataset.preview);
        if (!target) return;
        if (!target.dataset.originalSrc) target.dataset.originalSrc = target.getAttribute('src') || '';

        let clear = input.parentElement.querySelector('.file-clear');
        if (!clear) {
            clear = document.createElement('button');
            clear.type = 'button';
            clear.className = 'link-btn file-clear';
            clear.textContent = 'Batal pilih';
            clear.dataset.previewTarget = input.dataset.preview;
            clear.hidden = true;
            input.insertAdjacentElement('afterend', clear);
        }

        const originalFile = input.files && input.files[0];
        if (!originalFile) { clear.hidden = true; return; }

        clear.hidden = true;
        if (window.InviteUI) window.InviteUI.info('Memproses foto...');
        try {
            if (window.InviteCompress && /^image\/(jpeg|png|webp)$/i.test(originalFile.type)) {
                await window.InviteCompress.compressFileInput(input);
            }
        } catch (e) { /* use original */ }
        const file = input.files && input.files[0];
        if (!file) { clear.hidden = true; return; }
        setPreviewFromFiles(target, file);
        clear.hidden = false;
        markDirty(input);
    });

    document.querySelectorAll('input[type=\"file\"][data-preview-list]').forEach(function (input) {
        const target = document.getElementById(input.dataset.previewList);
        if (!target) return;
        const objectUrls = [];
        input.addEventListener('change', async function () {
            if (window.InviteCompress && input.files && input.files.length) {
                try { await window.InviteCompress.compressFileInput(input); } catch (e) { /* original */ }
            }
            objectUrls.splice(0).forEach(function (url) { URL.revokeObjectURL(url); });
            target.replaceChildren();
            Array.from(input.files || []).forEach(function (file) {
                const figure = document.createElement('figure');
                const image = document.createElement('img');
                const url = URL.createObjectURL(file);
                image.className = 'thumb';
                image.alt = file.name;
                image.src = url;
                objectUrls.push(url);
                const caption = document.createElement('figcaption');
                caption.textContent = file.name;
                figure.appendChild(image);
                figure.appendChild(caption);
                target.appendChild(figure);
            });
            markDirty(input);
        });
    });

    const musicSelect = document.getElementById('music-select');
    const musicPreview = document.getElementById('music-preview');
    function updateMusicPreview() {
        if (!musicSelect || !musicPreview) return;
        const option = musicSelect.selectedOptions[0];
        const url = option ? option.dataset.url : '';
        if (url) {
            musicPreview.src = url;
            musicPreview.style.display = '';
        } else {
            musicPreview.removeAttribute('src');
            musicPreview.style.display = 'none';
        }
    }
    if (musicSelect) {
        updateMusicPreview();
        musicSelect.addEventListener('change', updateMusicPreview);
    }

    document.getElementById('restore-quote')?.addEventListener('click', function (event) {
        const quote = document.getElementById('quote-input');
        const source = document.getElementById('quote-source-input');
        const action = document.getElementById('quote-action');
        if (quote) quote.value = defaultQuote;
        if (source) source.value = defaultQuoteSource;
        if (action) action.value = 'default';
        markDirty(event.currentTarget);
        saveSection('acara', event.currentTarget);
    });

    let remaining = {{ $invitation->isEditable() ? $invitation->previewRemainingSeconds() : 0 }};
    const timer = document.getElementById('preview-timer');
    let previewInterval = null;

    if (remaining > 0) {
        previewInterval = window.setInterval(function () {
            remaining -= 1;

            if (remaining <= 0) {
                window.clearInterval(previewInterval);
                previewInterval = null;
                remaining = 0;
            }

            if (timer) {
                timer.textContent = [
                    String(Math.floor(remaining / 3600)).padStart(2, '0'),
                    String(Math.floor((remaining % 3600) / 60)).padStart(2, '0'),
                    String(remaining % 60).padStart(2, '0'),
                ].join(':');
            }
        }, 1000);
    }
})();
</script>

<style>
    main:has(.editor-shell) { max-width: 1320px; padding-bottom: 28px; }
    .editor-shell { width: min(100% - 32px, 1280px); margin-inline: auto; padding-top: 24px; }
    .editor-head { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 18px; padding: 20px 22px; margin-bottom: 14px; }
    .editor-head h1 { margin: 2px 0 8px; font-family: var(--font-display); font-size: clamp(1.7rem, 3vw, 2.3rem); font-weight: 500; line-height: 1.15; }
    .editor-meta { display: flex; flex-wrap: wrap; gap: 7px; align-items: center; }
    .editor-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-left: auto; }
    .editor-feedback { margin-bottom: 14px; }
    .editor-upload-progress { margin-bottom: 14px; padding: 12px 14px; border: 1px solid rgba(181,147,90,.28); border-radius: 12px; background: #fffaf2; }
    .editor-upload-progress-head { display:flex; justify-content:space-between; gap:12px; font-size:.88rem; margin-bottom:8px; color:#5a4a2f; }
    .editor-upload-progress-track { width:100%; height:10px; background:#eadfca; border-radius:999px; overflow:hidden; }
    .editor-upload-progress-bar { height:100%; width:0%; background:linear-gradient(90deg,#b5935a,#d4b273); transition:width .15s ease; }
    .editor-upload-progress.is-compressing .editor-upload-progress-bar { background:linear-gradient(90deg,#8b7040,#b5935a); }
    .editor-workspace { display: grid; grid-template-columns: 280px minmax(0, 1fr); gap: 16px; align-items: start; }
    .editor-nav { position: sticky; top: calc(var(--header-h) + 16px); padding: 16px; }
    .editor-nav-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 12px; }
    .editor-nav-list { display: flex; flex-direction: column; gap: 4px; }
    .editor-nav-list button { display: flex; align-items: center; gap: 9px; width: 100%; min-height: 44px; padding: 8px 10px; border: 0; border-radius: 9px; background: transparent; color: var(--muted); font: inherit; font-size: .78rem; font-weight: 600; text-align: left; cursor: pointer; }
    .editor-nav-list button span { display: grid; place-items: center; flex: none; width: 24px; height: 24px; border-radius: 7px; background: var(--paper-warm); color: var(--gold-dark); font-size: .68rem; }
    .editor-nav-list button:hover { background: var(--paper-warm); color: var(--ink); }
    .editor-nav-list button.is-active { background: var(--gold); color: #fff; }
    .editor-nav-list button.is-active span { background: rgba(255,255,255,.2); color: #fff; }
    .editor-nav-note { margin-top: 16px; padding: 12px 13px; border-radius: 10px; background: var(--paper-warm); }
    .editor-nav-note strong { font-size: .76rem; }
    .editor-nav-note p { margin: 4px 0 0; color: var(--muted); font-size: .74rem; line-height: 1.55; }
    .editor-save-state { font-size: .7rem; color: var(--muted); }
    .editor-save-state.is-dirty { color: var(--warning, #a06b12); }
    .editor-save-state.is-saved { color: var(--success); }
    .editor-fieldset { min-width: 0; margin: 0; padding: 0; border: 0; }
    .editor-content { min-width: 0; padding: 0; overflow: visible; scroll-margin-top: calc(var(--header-h, 72px) + 12px); }
    .editor-section { display: none; padding: 26px; min-width: 0; }
    .editor-section.is-active { display: block; animation: fadeUp .28s ease both; }
    .section-head { margin-bottom: 22px; }
    .section-head h2 { margin: 2px 0 8px; font-family: var(--font-display); font-size: clamp(1.6rem, 2.4vw, 2rem); font-weight: 600; }
    .section-head p { margin: 0; max-width: 70ch; }
    .subsection-head { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 10px; margin: 26px 0 12px; padding-top: 20px; border-top: 1px solid var(--line); }
    .subsection-head h3, .media-block h3 { margin: 0; font-size: 1rem; }
    .subsection-head p { margin: 0; }
    .person-block, .media-block { margin-top: 22px; padding-top: 20px; border-top: 1px solid var(--line); }
    .person-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 10px; }
    .person-head h3 { margin: 0; font-size: 1rem; }
    .field-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
    .field-grid label, .upload-row label, .inline-field { display: flex; flex-direction: column; gap: 6px; font-size: .78rem; font-weight: 600; }
    .field-grid label.span-2 { grid-column: 1 / -1; }
    .field-grid input, .field-grid select, .field-grid textarea, .editor-content input[type="text"], .editor-content input[type="url"], .editor-content input[type="number"], .editor-content input[type="date"], .editor-content input[type="time"], .editor-content select, .editor-content textarea {
        width: 100%; border: 1px solid var(--line-strong); border-radius: 10px; padding: 9px 11px; font: inherit; font-size: .84rem; background: #fff; color: var(--ink);
    }
    .editor-content textarea { resize: vertical; }
    .field-help-inline { color: var(--muted); font-size: .72rem; font-weight: 400; line-height: 1.5; }
    .check { flex-direction: row !important; align-items: center; gap: 8px !important; font-weight: 600; }
    .check input { width: auto !important; }
    .couple-order-block { margin-top: 22px; padding-top: 20px; border-top: 1px solid var(--line); }
    .couple-order-block .field-label { display: block; margin-bottom: 6px; font-size: .78rem; font-weight: 700; }
    .couple-order-block p { margin: 0 0 10px; }
    .couple-order-options { display: flex; flex-direction: column; gap: 8px; }
    .couple-order-option { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border: 1px solid var(--line); border-radius: 10px; background: var(--paper); font-size: .82rem; cursor: pointer; }
    .couple-order-option:has(input:checked) { border-color: var(--accent, #b5935a); background: var(--paper-warm); }
    .couple-order-option input { width: auto !important; margin: 0; }
    .couple-order-option strong { font-weight: 700; }
    .upload-row { display: flex; align-items: flex-end; flex-wrap: wrap; gap: 14px; margin: 12px 0 16px; }
    .upload-control { display: flex; flex: 1 1 220px; flex-direction: column; min-width: 0; gap: 5px; }
    .file-label { display: flex; flex-direction: column; gap: 5px; font-size: .78rem; font-weight: 600; }
    .thumb { width: 92px; height: 74px; object-fit: cover; border-radius: 10px; border: 1px solid var(--line); background: var(--paper-warm); }
    .thumb.portrait { width: 82px; height: 100px; }
    .thumb-grid { display: flex; flex-wrap: wrap; gap: 10px; margin: 10px 0; }
    .thumb-grid figure { margin: 0; width: 108px; max-width: 100%; }
    .thumb-grid figure img { width: 100%; height: auto; aspect-ratio: 4 / 3; object-fit: cover; border-radius: 8px; }
    .thumb-grid figcaption { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--muted); font-size: .66rem; margin-top: 4px; }
    .photo-slot-group { min-width: 0; display: flex; flex-direction: column; gap: 8px; }
    .photo-slot-group .file-label { width: 100%; }
    .photo-slot-group .thumb-grid { margin: 0; }
    .background-slot { padding: 14px; margin-bottom: 12px; border: 1px solid var(--line); border-radius: var(--radius); background: var(--paper); }
    .background-slot-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 8px; }
    .bank-field { position: relative; display: flex; flex-direction: column; gap: 6px; min-width: 0; }
    .bank-field .field-label, .bank-field > span:first-child { font-size: .78rem; font-weight: 600; }
    .bank-select-trigger { display: flex; align-items: center; justify-content: space-between; gap: 10px; width: 100%; min-height: 44px; padding: 7px 11px; border: 1px solid var(--line-strong); border-radius: 10px; background: #fff; color: var(--ink); font: inherit; font-size: .84rem; cursor: pointer; text-align: left; }
    .bank-select-trigger:hover { border-color: var(--accent, #b5935a); }
    .bank-trigger-content { display: flex; align-items: center; gap: 10px; min-width: 0; }
    .bank-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .bank-chevron { flex: none; color: var(--muted); }
    .bank-mark { display: inline-flex; align-items: center; justify-content: center; flex: none; min-width: 42px; height: 42px; padding: 0 6px; border-radius: 10px; background: var(--bank-color, #8a6b3d); color: #fff; font: 800 10px/1 ui-sans-serif, system-ui, sans-serif; letter-spacing: .04em; box-shadow: inset 0 0 0 1px rgba(255,255,255,.24); }
    .bank-mark-small { min-width: 32px; height: 32px; border-radius: 8px; font-size: 9px; }
    .bank-select-menu { position: absolute; z-index: 40; top: 100%; left: 0; right: 0; max-height: 280px; overflow: auto; margin-top: 6px; padding: 8px; border: 1px solid var(--line-strong); border-radius: 12px; background: #fff; box-shadow: 0 18px 44px rgba(43,32,21,.18); }
    .bank-select-menu[hidden] { display: none; }
    .bank-search { position: sticky; top: 0; z-index: 1; width: 100%; margin-bottom: 6px; padding: 9px 11px; border: 1px solid var(--line-strong); border-radius: 8px; font: inherit; font-size: .82rem; background: #fff; }
    .bank-option.is-active { outline: 2px solid var(--accent, #8a6b3d); outline-offset: -2px; }
    .bank-group + .bank-group { margin-top: 6px; padding-top: 6px; border-top: 1px solid var(--line); }
    .bank-group-label { display: block; padding: 4px 6px; color: var(--muted); font-size: .68rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
    .bank-option { display: flex; align-items: center; gap: 10px; width: 100%; padding: 7px 8px; border: 0; border-radius: 9px; background: none; color: var(--ink); font: inherit; font-size: .82rem; text-align: left; cursor: pointer; }
    .bank-option:hover, .bank-option[aria-selected="true"] { background: var(--paper-warm); }
    .bank-option[hidden], .bank-group[hidden] { display: none; }
    .bank-option-name { display: flex; flex-direction: column; min-width: 0; font-weight: 600; }
    .bank-option-name small { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--muted); font-size: .68rem; font-weight: 400; }
    .gift-address-block { margin-top: 18px; }
    .gift-address-block label { display: flex; flex-direction: column; gap: 6px; margin-top: 10px; font-size: .78rem; font-weight: 600; }
    .repeat-list { display: flex; flex-direction: column; gap: 14px; margin: 16px 0; }
    .repeat-row { min-width: 0; border: 1px solid var(--line); border-radius: var(--radius); padding: 16px; background: var(--paper); }
    .row-head { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 12px; font-size: .82rem; }
    .row-head-actions { display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: 8px; }
    .row-order { display: inline-flex; gap: 3px; }
    .row-order button { width: 36px; height: 36px; border: 1px solid var(--line); border-radius: 7px; background: #fff; color: var(--muted); font: inherit; font-size: .8rem; cursor: pointer; }
    .link-btn { border: 0; background: none; color: var(--danger); cursor: pointer; font: inherit; font-size: .8rem; font-weight: 600; }
    .file-clear { color: var(--muted); }
    .compact-row { display: grid; grid-template-columns: 1fr 120px; gap: 10px 14px; }
    .compact-row .row-head { grid-column: 1 / -1; }
    .feature-note { padding: 14px 16px; border: 1px dashed var(--line-strong); border-radius: 10px; background: var(--paper-warm); color: var(--muted); font-size: .82rem; }
    .new-upload-block { margin-top: 18px; padding: 16px; border: 1px dashed var(--line-strong); border-radius: var(--radius); background: var(--paper-warm); }
    .new-upload-block h3 { margin: 0 0 10px; font-size: .9rem; }
    .copy-field { display: flex; gap: 8px; }
    .copy-field input { flex: 1; min-width: 0; }
    .copy-field .btn { flex: none; }
    .guest-link { font-size: .78rem !important; }
    .greeting-message { margin: 8px 0 4px; font-size: .86rem; line-height: 1.55; }
    .music-preview { width: 100%; margin-top: 16px; }
    .section-actions { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin-top: 26px; padding-top: 18px; border-top: 1px solid var(--line); }
    .section-actions-buttons { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-left: auto; }
    .section-actions-buttons .btn { display: inline-flex; align-items: center; gap: 8px; }
    .section-actions-buttons .btn svg { flex: 0 0 auto; }
    .section-save-state { font-size: .78rem; color: var(--muted); }
    .section-save-state.is-dirty { color: var(--warning, #a06b12); }
    .section-save-state.is-error { color: var(--danger); }
    .section-save-state.is-saved { color: var(--success); }
    .btn.is-loading { opacity: .7; cursor: progress; }
    .readonly-pane { padding: 28px; }
    @media (max-width: 1080px) {
        .editor-workspace { grid-template-columns: 1fr; }
        .editor-nav { position: static; }
        .editor-nav-list { flex-direction: row; overflow-x: auto; padding-bottom: 4px; }
        .editor-nav-list button { width: auto; flex: none; white-space: nowrap; }
        .editor-nav-note { display: none; }
    }
    @media (max-width: 720px) {
        .editor-shell { width: min(100% - 20px, 1280px); padding-top: 14px; }
        .editor-head { flex-direction: column; padding: 18px; }
        .editor-actions { width: 100%; margin-left: 0; }
        .editor-actions .btn { flex: 1 1 auto; }
        .editor-section { padding: 18px 16px; }
        .field-grid { grid-template-columns: minmax(0, 1fr); }
        .compact-row { grid-template-columns: minmax(0, 1fr); }
        .upload-row { gap: 12px; }
        .upload-control { flex-basis: 100%; }
        .thumb-grid figure { width: calc(50% - 5px); }
        .new-upload-block { padding: 14px; }
        .copy-field { flex-wrap: wrap; }
        .copy-field input { flex: 1 1 100%; }
        .section-actions { align-items: stretch; flex-direction: column; }
        .section-actions-buttons { width: 100%; margin-left: 0; flex-direction: column; }
        .section-actions .btn { width: 100%; min-height: 44px; justify-content: center; }
        .row-order button { width: 40px; height: 40px; }
        .bank-select-menu { max-height: min(52vh, 320px); }
        .bank-option { min-height: 44px; }
    }
</style>
@endsection
