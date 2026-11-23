@if ($enabled ?? true)
    @php
        $sectionKeys = collect($sections ?? [])->pluck('key')->values();
        $currentIndex = $sectionKeys->search($key);
        $isLastSection = $currentIndex !== false && $currentIndex === $sectionKeys->count() - 1;
        $nextSectionKey = (! $isLastSection && $currentIndex !== false) ? $sectionKeys->get($currentIndex + 1) : null;
        $saveLabel = $isLastSection ? 'Simpan' : 'Simpan & Lanjut';
    @endphp
    <div class="section-actions">
        <span class="section-save-state" data-section-state="{{ $key }}">Tersimpan</span>
        <div class="section-actions-buttons">
            @if (! empty($publicUrl))
                <a class="btn btn-secondary" href="{{ $publicUrl }}" target="_blank" rel="noopener">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                    <span class="btn-label">Lihat Undangan</span>
                </a>
            @endif
            <button type="button"
                    class="btn btn-primary save-section"
                    data-save-section="{{ $key }}"
                    @if ($nextSectionKey) data-next-section="{{ $nextSectionKey }}" @endif>
                @if ($isLastSection)
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><path d="M17 21v-8H7v8"/><path d="M7 3v5h8"/></svg>
                @else
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><path d="M17 21v-8H7v8"/><path d="M7 3v5h8"/><path d="m9 14 2 2 4-4"/></svg>
                @endif
                <span class="btn-label">{{ $saveLabel }}</span>
            </button>
        </div>
    </div>
@endif
