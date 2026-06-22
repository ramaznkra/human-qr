@php
    /** @var int $gi */
    /** @var int $oi */
    /** @var array<string, mixed> $option */
    /** @var string $groupType */
    $optionNames = $option['name'] ?? ['tr' => '', 'en' => '', 'ru' => ''];
    $isDefault = filter_var($option['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $isActive = filter_var($option['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN);
    $formId = $formId ?? null;
@endphp
<div class="product-option-row {{ $isActive ? '' : 'product-option-row--inactive' }}" data-option-row>
    @if(!empty($option['id']))
        <input type="hidden" name="option_groups[{{ $gi }}][options][{{ $oi }}][id]" value="{{ $option['id'] }}" @if($formId) form="{{ $formId }}" @endif>
    @endif
    <input type="hidden" name="option_groups[{{ $gi }}][options][{{ $oi }}][sort_order]" value="{{ $option['sort_order'] ?? $oi }}" data-option-sort-order @if($formId) form="{{ $formId }}" @endif>

    <div class="product-option-row__top">
        <span class="product-option-row__status {{ $isActive ? 'product-option-row__status--on' : 'product-option-row__status--off' }}">
            {{ $isActive ? 'Aktif' : 'Pasif' }}
        </span>
        <label class="admin-toggle-wrap flex items-center gap-2">
            <span class="text-xs text-zinc-500">Seçenek durumu</span>
            <label class="relative inline-flex shrink-0 cursor-pointer items-center">
                <input type="hidden" name="option_groups[{{ $gi }}][options][{{ $oi }}][is_active]" value="0" @if($formId) form="{{ $formId }}" @endif>
                <input
                    type="checkbox"
                    class="peer sr-only"
                    name="option_groups[{{ $gi }}][options][{{ $oi }}][is_active]"
                    value="1"
                    data-option-active-toggle
                    {{ $isActive ? 'checked' : '' }}
                    @if($formId) form="{{ $formId }}" @endif
                >
                <span class="admin-toggle__track admin-toggle__track--gold"></span>
            </label>
        </label>
        <button type="button" class="product-option-row__remove ml-auto" data-remove-option aria-label="Seçeneği sil">×</button>
    </div>

    <div class="product-option-row__grid">
        <div>
            <label class="form-label text-xs">Ad (TR)</label>
            <input type="text" name="option_groups[{{ $gi }}][options][{{ $oi }}][name][tr]" value="{{ $optionNames['tr'] ?? '' }}" class="form-input" autocomplete="off" @if($formId) form="{{ $formId }}" @endif>
        </div>
        <div>
            <label class="form-label text-xs">Ad (EN)</label>
            <input type="text" name="option_groups[{{ $gi }}][options][{{ $oi }}][name][en]" value="{{ $optionNames['en'] ?? '' }}" class="form-input" autocomplete="off" @if($formId) form="{{ $formId }}" @endif>
        </div>
        <div>
            <label class="form-label text-xs">Ad (RU)</label>
            <input type="text" name="option_groups[{{ $gi }}][options][{{ $oi }}][name][ru]" value="{{ $optionNames['ru'] ?? '' }}" class="form-input" autocomplete="off" @if($formId) form="{{ $formId }}" @endif>
        </div>
        <div>
            <label class="form-label text-xs">Ek Fiyat (+₺)</label>
            <input type="number" step="0.01" min="0" name="option_groups[{{ $gi }}][options][{{ $oi }}][price_modifier]" value="{{ $option['price_modifier'] ?? 0 }}" class="form-input" placeholder="35" @if($formId) form="{{ $formId }}" @endif>
        </div>
        <div class="product-option-row__default">
            <label class="form-label text-xs">Varsayılan</label>
            <label class="mt-2 flex items-center gap-2 text-sm text-zinc-400">
                <input type="hidden" name="option_groups[{{ $gi }}][options][{{ $oi }}][is_default]" value="0" @if($formId) form="{{ $formId }}" @endif>
                <input
                    type="checkbox"
                    name="option_groups[{{ $gi }}][options][{{ $oi }}][is_default]"
                    value="1"
                    data-option-default
                    {{ $isDefault ? 'checked' : '' }}
                    @if($formId) form="{{ $formId }}" @endif
                >
                <span data-default-hint>{{ $groupType === 'single' ? 'Seçili gelsin' : 'Önceden işaretli' }}</span>
            </label>
        </div>
    </div>
</div>
