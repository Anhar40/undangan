@php
    $field = fn (string $name, mixed $default = '') => old('stories.' . $index . '.' . $name, data_get($row, $name, $default));
    $image = data_get($row, 'image');
    $imageUrl = $image ? (str_starts_with($image, 'http') ? $image : asset('storage/' . ltrim($image, '/'))) : '';
@endphp
<div class="repeat-row" data-row-index="{{ $index }}" data-row-label="Cerita">
    <input type="hidden" name="stories[{{ $index }}][id]" value="{{ data_get($row, 'id') }}">
    <div class="row-head">
        <strong>Cerita {{ (int) $index + 1 }}</strong>
        <div class="row-head-actions">
            <span class="row-order">
                <button type="button" data-move="up" aria-label="Naikkan cerita">↑</button>
                <button type="button" data-move="down" aria-label="Turunkan cerita">↓</button>
            </span>
            <button type="button" class="link-btn remove-row">Hapus</button>
        </div>
    </div>
    <div class="field-grid">
        <label>
            <span>Judul</span>
            <input type="text" name="stories[{{ $index }}][title]" value="{{ $field('title') }}" placeholder="Awal Ketemu" maxlength="120">
        </label>
        <label>
            <span>Tanggal / tahun</span>
            <input type="text" name="stories[{{ $index }}][story_date]" value="{{ $field('story_date') }}" placeholder="2019" maxlength="50">
        </label>
        <label class="span-2">
            <span>Cerita</span>
            <textarea name="stories[{{ $index }}][description]" rows="3" maxlength="5000">{{ $field('description') }}</textarea>
        </label>
        <div class="span-2 upload-row">
            <img id="story-preview-{{ $index }}" class="thumb" src="{{ $imageUrl }}" alt="" style="{{ $imageUrl ? '' : 'display:none' }}">
            <label class="file-label">
                <span>Foto pendukung</span>
                <input type="file" name="stories_images[{{ $index }}]" accept="image/jpeg,image/png,image/webp" data-preview="story-preview-{{ $index }}">
            </label>
            @if ($image)
                <label class="check"><input type="checkbox" name="stories_remove_image[{{ $index }}]" value="1"> Hapus foto</label>
            @endif
        </div>
        <label>
            <span>Urutan</span>
            <input type="number" name="stories[{{ $index }}][sort_order]" value="{{ $field('sort_order', $index) }}" min="0" max="1000">
        </label>
    </div>
</div>
