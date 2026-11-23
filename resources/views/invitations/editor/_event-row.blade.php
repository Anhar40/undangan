@php
    $field = fn (string $name, mixed $default = '') => old('events.' . $index . '.' . $name, data_get($row, $name, $default));
    // MySQL returns TIME as "HH:MM:SS"; HTML time inputs need "HH:MM".
    $time = fn (mixed $value): string => is_string($value) && strlen($value) >= 5
        ? substr($value, 0, 5)
        : (string) $value;
    $eventType = $field('event_type', 'AKAD');
    $eventType = $eventType instanceof \App\Enums\EventType ? $eventType->value : $eventType;
    $eventDate = $field('event_date', now()->toDateString());
    $eventDate = $eventDate instanceof \Carbon\CarbonInterface ? $eventDate->format('Y-m-d') : $eventDate;
    $eventTimezone = $field('timezone', 'Asia/Jakarta');
@endphp
<div class="repeat-row" data-row-index="{{ $index }}" data-row-label="Acara">
    <input type="hidden" name="events[{{ $index }}][id]" value="{{ data_get($row, 'id') }}">
    <div class="row-head">
        <strong>Acara {{ (int) $index + 1 }}</strong>
        <div class="row-head-actions">
            <span class="row-order">
                <button type="button" data-move="up" aria-label="Naikkan acara">↑</button>
                <button type="button" data-move="down" aria-label="Turunkan acara">↓</button>
            </span>
            <button type="button" class="link-btn remove-row">Hapus</button>
        </div>
    </div>
    <div class="field-grid">
        <label>
            <span>Jenis acara</span>
            <select name="events[{{ $index }}][event_type]" required>
                @foreach (\App\Enums\EventType::cases() as $case)
                    <option value="{{ $case->value }}" @selected($eventType === $case->value)>{{ $case->name === 'Other' ? 'Acara Lainnya' : ucfirst(strtolower($case->name)) }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span>Nama acara</span>
            <input type="text" name="events[{{ $index }}][title]" value="{{ $field('title') }}" placeholder="Akad Nikah" maxlength="120">
        </label>
        <label>
            <span>Tanggal</span>
            <input type="date" name="events[{{ $index }}][event_date]" value="{{ $eventDate }}" required>
        </label>
        <label>
            <span>Jam mulai</span>
            <input type="time" name="events[{{ $index }}][start_time]" value="{{ $time($field('start_time', '08:00')) }}" required>
        </label>
        <label>
            <span>Jam selesai</span>
            <input type="time" name="events[{{ $index }}][end_time]" value="{{ $time($field('end_time')) }}">
            <small class="field-help-inline">Kosongkan jika sampai selesai.</small>
        </label>
        <label>
            <span>Zona waktu</span>
            <select name="events[{{ $index }}][timezone]">
                @foreach ($timezones as $value => $label)
                    <option value="{{ $value }}" @selected($eventTimezone === $value)>{{ $label }}</option>
                @endforeach
                @if (! is_string($eventTimezone) || ! array_key_exists($eventTimezone, $timezones))
                    <option value="{{ $eventTimezone }}" selected>{{ $eventTimezone }}</option>
                @endif
            </select>
        </label>
        <label class="span-2">
            <span>Lokasi</span>
            <input type="text" name="events[{{ $index }}][location_name]" value="{{ $field('location_name') }}" placeholder="Nama gedung" maxlength="150">
        </label>
        <label class="span-2">
            <span>Alamat</span>
            <textarea name="events[{{ $index }}][address]" rows="2" maxlength="500">{{ $field('address') }}</textarea>
        </label>
        <label class="span-2">
            <span>Link Google Maps</span>
            <input type="text" inputmode="url" name="events[{{ $index }}][maps_url]" value="{{ $field('maps_url') }}" placeholder="https://maps.google.com/...">
        </label>
        @if ($withLive)
            <label class="span-2">
                <span>Link live streaming</span>
                <input type="text" inputmode="url" name="events[{{ $index }}][live_stream_url]" value="{{ $field('live_stream_url') }}" placeholder="https://...">
            </label>
        @endif
        <label class="span-2">
            <span>Keterangan tambahan</span>
            <textarea name="events[{{ $index }}][description]" rows="2" maxlength="2000">{{ $field('description') }}</textarea>
        </label>
        <label class="span-2">
            <span>Dress code</span>
            <input type="text" name="events[{{ $index }}][dress_code]" value="{{ $field('dress_code') }}" placeholder="Batak / formal" maxlength="120">
        </label>
        <label>
            <span>Urutan</span>
            <input type="number" name="events[{{ $index }}][sort_order]" value="{{ $field('sort_order', $index) }}" min="0" max="1000">
        </label>
    </div>
</div>
