@php
    $field = fn (string $name, mixed $default = '') => old('guests.' . $index . '.' . $name, data_get($row, $name, $default));
    $name = trim((string) $field('guest_name'));
    $attendance = $field('attendance_status', 'HADIR');
    $attendance = $attendance instanceof \App\Enums\AttendanceStatus ? $attendance->value : $attendance;
    $guestLink = rtrim((string) $publicUrl, '/') . '?to=' . rawurlencode($name);
@endphp
<div class="repeat-row" data-row-index="{{ $index }}" data-guest-row>
    <input type="hidden" name="guests[{{ $index }}][id]" value="{{ data_get($row, 'id') }}">
    <div class="row-head">
        <strong>Tamu {{ $index }}</strong>
        <button type="button" class="link-btn remove-row">Hapus</button>
    </div>
    <div class="field-grid">
        <label>
            <span>Nama tamu</span>
            <input type="text" name="guests[{{ $index }}][guest_name]" value="{{ $field('guest_name') }}" data-guest-name maxlength="120" required placeholder="Nama tamu">
        </label>
        <label>
            <span>Status kehadiran</span>
            <select name="guests[{{ $index }}][attendance_status]">
                @foreach (\App\Enums\AttendanceStatus::cases() as $case)
                    <option value="{{ $case->value }}" @selected($attendance === $case->value)>{{ $case->label() }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span>Jumlah tamu</span>
            <input type="number" name="guests[{{ $index }}][guest_count]" value="{{ $field('guest_count', 1) }}" min="1" max="50">
        </label>
        <label class="span-2">
            <span>Catatan (opsional)</span>
            <textarea name="guests[{{ $index }}][message]" rows="2" maxlength="2000">{{ $field('message') }}</textarea>
        </label>
        <label class="span-2">
            <span>Tautan personal tamu</span>
            <div class="copy-field">
                <input type="text" value="{{ $guestLink }}" data-guest-link readonly>
                <button type="button" class="btn btn-light" data-copy-link="{{ $guestLink }}">Salin</button>
            </div>
            <small class="field-help-inline">Tautan ini otomatis mengikuti slug undangan dan nama tamu.</small>
        </label>
    </div>
</div>
