@php
    $field = fn (string $name, mixed $default = '') => old('gallery_items.' . $index . '.' . $name, data_get($row, $name, $default));
    $image = data_get($row, 'image');
    $imageUrl = $image ? (str_starts_with($image, 'http') ? $image : asset('storage/' . ltrim($image, '/'))) : '';
    $isCover = filter_var($field('is_cover', false), FILTER_VALIDATE_BOOLEAN);
@endphp
<div class="repeat-row" data-row-index="{{ $index }}" data-row-label="Foto">
    <input type="hidden" name="gallery_items[{{ $index }}][id]" value="{{ data_get($row, 'id') }}">
    <input type="hidden" name="gallery_items[{{ $index }}][sort_order]" value="{{ $field('sort_order', $index) }}">
    <div class="row-head">
        <strong>Foto {{ $index + 1 }}</strong>
        <div class="row-head-actions">
            <span class="row-order">
                <button type="button" data-move="up" aria-label="Naikkan foto">↑</button>
                <button type="button" data-move="down" aria-label="Turunkan foto">↓</button>
            </span>
            <button type="button" class="link-btn remove-row">Hapus</button>
        </div>
    </div>
    <div class="upload-row">
        <img id="gallery-preview-{{ $index }}" class="thumb" src="{{ $imageUrl }}" alt="" style="{{ $imageUrl ? '' : 'display:none' }}">
        <div class="upload-control">
            <label>
                <span>Caption</span>
                <input type="text" name="gallery_items[{{ $index }}][caption]" value="{{ $field('caption') }}" maxlength="150">
            </label>
            <label class="check span-2">
                <input type="hidden" name="gallery_items[{{ $index }}][is_cover]" value="0">
                <input type="checkbox" name="gallery_items[{{ $index }}][is_cover]" value="1" @checked($isCover)> Jadikan foto utama
            </label>
        </div>
    </div>
</div>
