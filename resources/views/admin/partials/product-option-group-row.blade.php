@php
    /** @var int $gi */
    /** @var array<string, mixed> $group */
    $groupNames = $group['name'] ?? ['tr' => '', 'en' => '', 'ru' => ''];
    $groupType = $group['type'] ?? 'single';
    $groupRequired = filter_var($group['required'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $options = array_values($group['options'] ?? []);
    $formId = $formId ?? null;
@endphp
<article class="product-option-group-card" data-option-group data-group-type="{{ $groupType }}">
    <div class="product-option-group-card__head">
        <strong class="text-sm font-semibold text-zinc-200">Varyasyon Grubu</strong>
        <button type="button" class="product-option-group-card__remove" data-remove-option-group aria-label="Grubu sil">Sil</button>
    </div>

    @if(!empty($group['id']))
        <input type="hidden" name="option_groups[{{ $gi }}][id]" value="{{ $group['id'] }}" @if($formId) form="{{ $formId }}" @endif>
    @endif
    <input type="hidden" name="option_groups[{{ $gi }}][sort_order]" value="{{ $group['sort_order'] ?? $gi }}" data-group-sort-order @if($formId) form="{{ $formId }}" @endif>

    <div class="mt-3 grid gap-3 sm:grid-cols-2">
        <div>
            <label class="form-label">Grup adı (TR)</label>
            <input type="text" name="option_groups[{{ $gi }}][name][tr]" value="{{ $groupNames['tr'] ?? '' }}" class="form-input" autocomplete="off" @if($formId) form="{{ $formId }}" @endif>
        </div>
        <div>
            <label class="form-label">Grup adı (EN)</label>
            <input type="text" name="option_groups[{{ $gi }}][name][en]" value="{{ $groupNames['en'] ?? '' }}" class="form-input" autocomplete="off" placeholder="Opsiyonel" @if($formId) form="{{ $formId }}" @endif>
        </div>
        <div>
            <label class="form-label">Grup adı (RU)</label>
            <input type="text" name="option_groups[{{ $gi }}][name][ru]" value="{{ $groupNames['ru'] ?? '' }}" class="form-input" autocomplete="off" placeholder="Необязательно" @if($formId) form="{{ $formId }}" @endif>
        </div>
        <div>
            <label class="form-label">Seçim tipi</label>
            <select name="option_groups[{{ $gi }}][type]" class="form-input" data-group-type-select @if($formId) form="{{ $formId }}" @endif>
                <option value="single" {{ $groupType === 'single' ? 'selected' : '' }}>Tek seçim (radio)</option>
                <option value="multiple" {{ $groupType === 'multiple' ? 'selected' : '' }}>Çoklu seçim (checkbox)</option>
            </select>
        </div>
    </div>

    <label class="mt-3 flex items-center gap-2 text-sm text-zinc-400">
        <input type="hidden" name="option_groups[{{ $gi }}][required]" value="0" @if($formId) form="{{ $formId }}" @endif>
        <input type="checkbox" name="option_groups[{{ $gi }}][required]" value="1" {{ $groupRequired ? 'checked' : '' }} @if($formId) form="{{ $formId }}" @endif>
        Zorunlu seçim
    </label>

    <div class="mt-4">
        <div class="mb-2 flex items-center justify-between gap-2">
            <span class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Seçenekler</span>
            <button type="button" class="text-xs font-semibold text-[#C6A046] hover:underline" data-add-option>+ Seçenek ekle</button>
        </div>
        <div class="space-y-2" data-options-list>
            @foreach($options as $oi => $option)
                @include('admin.partials.product-option-row', ['gi' => $gi, 'oi' => $oi, 'option' => $option, 'groupType' => $groupType, 'formId' => $formId])
            @endforeach
        </div>
    </div>
</article>
