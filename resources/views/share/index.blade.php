@extends('layouts.app')

@section('title', 'Bagikan Undangan — ' . config('app.name'))

@section('content')
@php
    $coupleLabel = $couple !== '' ? $couple : 'Undangan Digital';
@endphp
<div class="container share-page"
     data-share
     data-base-link="{{ $baseLink }}"
     data-message-template="{{ $messageTemplate }}"
     data-couple="{{ $couple }}"
     data-default-message="{{ $defaultMessage }}">

    <div class="page-head">
        <div>
            <span class="eyebrow">Bagikan Undangan</span>
            <h1>{{ $coupleLabel }}</h1>
            <p class="muted">
                @if ($invitation)
                    Undangan aktif. Ketik nama tamu; tautan dan pesan langsung berubah, lalu bagikan lewat WhatsApp atau Facebook.
                @else
                    Halaman bantuan berbagi. Tautan ini belum terhubung ke undangan aktif.
                @endif
            </p>
        </div>
        @auth
            @if ($invitation && auth()->id() === $invitation->user_id)
                <div class="page-head-actions">
                    <a class="btn btn-secondary" href="{{ route('invitations.edit', $invitation) }}">Kelola Undangan</a>
                    <a class="btn btn-light" href="{{ route('dashboard') }}">Dashboard</a>
                </div>
            @endif
        @endauth
    </div>

    <div class="share-grid">
        <section class="card share-card">
            <h2>Bagikan Undangan</h2>
            <p class="muted small">Nama tamu langsung ditambahkan ke tautan sebagai <code>?to=</code> dan disisipkan ke pesan. Tidak perlu menekan tombol terapkan.</p>

            <label class="share-guest-field">
                <span class="field-label">Nama tamu (opsional)</span>
                <input id="share-guest-name" type="text" value="{{ $guestName }}" maxlength="120" placeholder="Contoh: Bapak Budi Santoso" autocomplete="off">
            </label>

            <div class="share-link-block">
                <span class="field-label">Tautan undangan</span>
                <input type="text" id="share-link" value="{{ $link }}" readonly>
                <a class="link-inline" id="share-open" href="{{ $link }}" target="_blank" rel="noopener">Buka undangan</a>
            </div>

            <label class="share-message-field">
                <span class="field-label">Pesan</span>
                <textarea id="share-message" rows="9" maxlength="2000">{{ $message }}</textarea>
            </label>
            <div class="share-message-actions">
                <button type="button" class="btn-link" data-restore-message>Pulihkan pesan awal</button>
            </div>

            <div class="share-actions">
                <a class="btn btn-primary" id="share-whatsapp" href="{{ $whatsapp }}" target="_blank" rel="noopener noreferrer">
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M12.04 2C6.5 2 2.1 6.4 2.1 11.94c0 1.75.46 3.46 1.33 4.96L2 22l5.24-1.37a9.9 9.9 0 0 0 4.8 1.22h.01c5.54 0 10.04-4.5 10.04-10.04 0-2.68-1.04-5.2-2.93-7.09A9.96 9.96 0 0 0 12.04 2Zm0 18.16h-.01a8.2 8.2 0 0 1-4.18-1.14l-.3-.18-3.1.81.83-3.02-.2-.31a8.16 8.16 0 0 1-1.25-4.38c0-4.52 3.68-8.2 8.2-8.2 2.19 0 4.25.85 5.8 2.4a8.14 8.14 0 0 1 2.4 5.8c0 4.52-3.68 8.2-8.2 8.2Zm4.5-6.13c-.25-.13-1.46-.72-1.69-.8-.23-.08-.4-.13-.57.13-.17.25-.65.8-.8.97-.15.17-.29.19-.54.06-.25-.12-1.05-.39-2-1.23-.74-.49-1.24-1.1-1.39-1.29-.14-.19-.02-.29.11-.42.11-.11.25-.29.37-.44.12-.15.16-.25.25-.42.08-.17.04-.31-.02-.44-.06-.13-.57-1.37-.78-1.87-.2-.49-.41-.42-.57-.43h-.49c-.17 0-.44.06-.67.31-.23.25-.88.86-.88 2.1s.9 2.43 1.03 2.6c.13.17 1.77 2.7 4.3 3.79.6.26 1.07.41 1.43.53.6.19 1.15.16 1.58.1.48-.07 1.46-.6 1.67-1.17.2-.58.2-1.07.14-1.18-.06-.11-.23-.17-.48-.29Z"/>
                    </svg>
                    <span>WhatsApp</span>
                </a>
                <a class="btn btn-secondary" id="share-facebook" href="{{ $facebook }}" target="_blank" rel="noopener noreferrer">
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M22 12a10 10 0 1 0-11.56 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.49-3.89 3.77-3.89 1.09 0 2.24.2 2.24.2v2.46H15.2c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.45 2.89h-2.33v6.99A10 10 0 0 0 22 12Z"/>
                    </svg>
                    <span>Facebook</span>
                </a>
                <button type="button" class="btn btn-light" data-copy-target="share-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
                        <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
                    </svg>
                    <span>Salin link</span>
                </button>
            </div>
            <p class="muted small" data-share-note hidden></p>
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var page = document.querySelector('[data-share]');
    if (!page) return;

    var guestInput = document.getElementById('share-guest-name');
    var linkInput = document.getElementById('share-link');
    var openLink = document.getElementById('share-open');
    var message = document.getElementById('share-message');
    var whatsapp = document.getElementById('share-whatsapp');
    var facebook = document.getElementById('share-facebook');
    var note = page.querySelector('[data-share-note]');
    var restore = page.querySelector('[data-restore-message]');

    var baseLink = page.getAttribute('data-base-link') || '';
    var template = page.getAttribute('data-message-template') || '';
    var couple = page.getAttribute('data-couple') || '';
    var fallbackGuest = 'Bapak/Ibu/Saudara/i';
    var placeholders = ['{guest}', '{link}', '{couple}', '{nama_tamu}', '{url}', '{nama_pasangan}'];
    var currentGuest = guestInput ? cleanName(guestInput.value) : '';
    var messageTouched = false;

    function cleanName(value) {
        var holder = document.createElement('div');
        holder.textContent = value == null ? '' : String(value);
        return holder.textContent.replace(/\s+/g, ' ').trim();
    }

    function guestLabel(name) {
        return name !== '' ? name : fallbackGuest;
    }

    function replaceAll(value, search, replacement) {
        return value.split(search).join(replacement);
    }

    function buildMessage(name, link) {
        var values = [guestLabel(name), link, couple, guestLabel(name), link, couple];
        var result = template;

        placeholders.forEach(function (placeholder, index) {
            result = replaceAll(result, placeholder, values[index]);
        });

        return result;
    }

    function buildLink(name) {
        if (!baseLink) return '';
        if (name === '') return baseLink;

        var hashIndex = baseLink.indexOf('#');
        var hash = hashIndex >= 0 ? baseLink.slice(hashIndex) : '';
        var withoutHash = hashIndex >= 0 ? baseLink.slice(0, hashIndex) : baseLink;
        var queryIndex = withoutHash.indexOf('?');
        var path = queryIndex >= 0 ? withoutHash.slice(0, queryIndex) : withoutHash;
        var query = queryIndex >= 0 ? withoutHash.slice(queryIndex + 1) : '';
        var parts = query.split('&').filter(function (part) {
            return part !== '' && part.split('=')[0] !== 'to';
        });

        parts.push('to=' + encodeURIComponent(name));

        return path + '?' + parts.join('&') + hash;
    }

    function currentLink() {
        return linkInput ? linkInput.value : baseLink;
    }

    function notify(text) {
        if (typeof window.showToast === 'function') {
            window.showToast(text);
            return;
        }
        if (!note) return;
        note.hidden = false;
        note.textContent = text;
    }

    function copyValue(value) {
        if (!value) return;
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(value).then(function () {
                notify('Tersalin ke papan klip.');
            }).catch(function () {
                legacyCopy(value);
            });
            return;
        }
        legacyCopy(value);
    }

    function legacyCopy(value) {
        var helper = document.createElement('textarea');
        helper.value = value;
        helper.setAttribute('readonly', '');
        helper.style.position = 'fixed';
        helper.style.opacity = '0';
        document.body.appendChild(helper);
        helper.select();
        try {
            document.execCommand('copy');
            notify('Tersalin ke papan klip.');
        } catch (error) {
            notify('Tidak dapat menyalin otomatis. Salin manual dari kolom teks.');
        }
        document.body.removeChild(helper);
    }

    function updateShareLinks() {
        var link = currentLink();
        var text = message ? message.value.trim() : '';

        if (whatsapp) whatsapp.href = 'https://wa.me/?text=' + encodeURIComponent(text);
        if (facebook) facebook.href = 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(link);
    }

    function applyGuest() {
        if (!guestInput) return;

        var name = cleanName(guestInput.value);
        var link = buildLink(name);

        if (linkInput) {
            linkInput.value = link;
            linkInput.scrollLeft = 0;
        }
        if (openLink) openLink.href = link;

        if (message) {
            if (!messageTouched) {
                message.value = buildMessage(name, link);
            } else {
                var previous = guestLabel(currentGuest);
                var next = guestLabel(name);
                if (previous !== next) {
                    message.value = replaceAll(message.value, previous, next);
                }
            }
        }

        currentGuest = name;
        updateShareLinks();
    }

    document.querySelectorAll('[data-copy-target]').forEach(function (button) {
        button.addEventListener('click', function () {
            var target = document.getElementById(button.getAttribute('data-copy-target'));
            if (!target) return;
            copyValue('value' in target ? target.value : target.textContent);
        });
    });

    if (restore && message) {
        restore.addEventListener('click', function () {
            messageTouched = false;
            message.value = buildMessage(currentGuest, currentLink());
            updateShareLinks();
            notify('Pesan dikembalikan ke versi awal.');
        });
    }

    if (guestInput) guestInput.addEventListener('input', applyGuest);

    if (message) {
        message.addEventListener('input', function () {
            messageTouched = true;
            updateShareLinks();
        });
    }

    if (whatsapp) whatsapp.addEventListener('click', updateShareLinks);
    if (facebook) facebook.addEventListener('click', updateShareLinks);

    applyGuest();
})();
</script>
@endpush
