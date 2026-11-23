@php
    $id = data_get($row, 'id');
    $status = old('greetings.' . $index . '.status', data_get($row, 'status', 'approved'));
    $attendance = data_get($row, 'attendance_status');
    $attendance = $attendance instanceof \App\Enums\AttendanceStatus ? $attendance->label() : (string) $attendance;
@endphp
<div class="repeat-row" data-row-index="{{ $index }}" data-greeting-row @if ($id) data-greeting-id="{{ $id }}" @endif>
    <input type="hidden" name="greetings[{{ $index }}][id]" value="{{ $id }}">
    @if ($id)
        <input type="hidden" name="greeting_remove[{{ $id }}]" value="0" data-greeting-remove>
    @endif
    <div class="row-head">
        <strong>{{ data_get($row, 'guest_name', 'Tamu') }}</strong>
        <button type="button" class="link-btn remove-greeting">Hapus</button>
    </div>
    <p class="greeting-message">{{ data_get($row, 'message') }}</p>
    <p class="muted small">
        {{ $attendance !== '' ? $attendance . ' · ' : '' }}{{ data_get($row, 'created_at')?->translatedFormat('d M Y') ?? '' }}
    </p>
    <label>
        <span>Status tampil</span>
        <select name="greetings[{{ $index }}][status]">
            <option value="approved" @selected($status === 'approved')>Tampilkan</option>
            <option value="hidden" @selected($status === 'hidden')>Sembunyikan</option>
        </select>
    </label>
</div>
