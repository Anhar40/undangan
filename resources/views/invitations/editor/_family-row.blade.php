@php
    $field = fn (string $name, mixed $default = '') => old('families.' . $index . '.' . $name, data_get($row, $name, $default));
@endphp
<div class="repeat-row compact-row" data-row-index="{{ $index }}" data-row-label="Pihak">
    <input type="hidden" name="families[{{ $index }}][id]" value="{{ data_get($row, 'id') }}">
    <div class="row-head">
        <strong>Pihak {{ (int) $index + 1 }}</strong>
        <div class="row-head-actions">
            <span class="row-order">
                <button type="button" data-move="up" aria-label="Naikkan pihak">↑</button>
                <button type="button" data-move="down" aria-label="Turunkan pihak">↓</button>
            </span>
            <button type="button" class="link-btn remove-row">Hapus</button>
        </div>
    </div>
    <label>
        <span>Nama keluarga / pihak</span>
        <input type="text" name="families[{{ $index }}][name]" value="{{ $field('name') }}" placeholder="Keluarga Besar Putri" maxlength="120">
    </label>
    <label>
        <span>Urutan</span>
        <input type="number" name="families[{{ $index }}][sort_order]" value="{{ $field('sort_order', $index) }}" min="0" max="1000">
    </label>
</div>
