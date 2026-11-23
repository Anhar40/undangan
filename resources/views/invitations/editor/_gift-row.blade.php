@php
    $field = fn (string $name, mixed $default = '') => old('gift_accounts.' . $index . '.' . $name, data_get($row, $name, $default));
    $banks = $banks ?? \App\Support\IndonesianBanks::grouped();
    $selected = \App\Support\IndonesianBanks::find($field('label'));
    $selectedName = $selected['name'] ?? (trim((string) $field('label')) !== '' ? (string) $field('label') : '');
@endphp
<div class="repeat-row" data-row-index="{{ $index }}" data-row-label="Rekening bank">
    <input type="hidden" name="gift_accounts[{{ $index }}][id]" value="{{ data_get($row, 'id') }}">
    <div class="row-head">
        <strong>Rekening bank {{ is_numeric($index) ? (int) $index + 1 : $index }}</strong>
        <div class="row-head-actions">
            <span class="row-order">
                <button type="button" data-move="up" aria-label="Naikkan rekening">↑</button>
                <button type="button" data-move="down" aria-label="Turunkan rekening">↓</button>
            </span>
            <button type="button" class="link-btn remove-row">Hapus</button>
        </div>
    </div>
    <div class="field-grid">
        <div class="span-2 bank-field" data-bank-picker>
            <span class="field-label">Bank</span>
            <input type="hidden" class="bank-value" name="gift_accounts[{{ $index }}][label]" value="{{ $field('label') }}">
            <button type="button" class="bank-select-trigger" aria-haspopup="listbox" aria-expanded="false" aria-controls="">
                <span class="bank-trigger-content">
                    {!! \App\Support\IndonesianBanks::badge($field('label'), 'bank-mark bank-mark-small') !!}
                    <span class="bank-name">{{ $selectedName !== '' ? $selectedName : 'Pilih bank' }}</span>
                </span>
                <span class="bank-chevron" aria-hidden="true">▾</span>
            </button>
            <div class="bank-select-menu" hidden>
                <input type="search" class="bank-search" placeholder="Cari bank…" aria-label="Cari bank">
                <div class="bank-options" role="listbox" aria-label="Daftar bank">
                    @foreach ($banks as $group => $items)
                        <div class="bank-group">
                            <span class="bank-group-label">{{ $group }}</span>
                            @foreach ($items as $bank)
                                <button type="button"
                                        class="bank-option"
                                        role="option"
                                        data-value="{{ $bank['name'] }}"
                                        data-search="{{ strtolower($bank['name'] . ' ' . $bank['full_name'] . ' ' . $bank['code']) }}"
                                        aria-selected="{{ $selected && $selected['code'] === $bank['code'] ? 'true' : 'false' }}">
                                    {!! \App\Support\IndonesianBanks::badge($bank['name'], 'bank-mark bank-mark-small') !!}
                                    <span class="bank-option-name">
                                        {{ $bank['name'] }}
                                        <small>{{ $bank['full_name'] }}</small>
                                    </span>
                                </button>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        <label>
            <span>Atas nama</span>
            <input type="text" name="gift_accounts[{{ $index }}][account_name]" value="{{ $field('account_name') }}" maxlength="120">
        </label>
        <label>
            <span>Nomor rekening</span>
            <input type="text" name="gift_accounts[{{ $index }}][account_number]" value="{{ $field('account_number') }}" maxlength="100">
        </label>
        <label>
            <span>Urutan</span>
            <input type="number" name="gift_accounts[{{ $index }}][sort_order]" value="{{ $field('sort_order', $index) }}" min="0" max="1000">
        </label>
    </div>
</div>
