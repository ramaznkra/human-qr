@php
    /** @var array<string, string> $names */
    /** @var array<string, string|null> $descriptions */
    $names = $names ?? ['tr' => '', 'en' => '', 'ru' => ''];
    $descriptions = $descriptions ?? ['tr' => '', 'en' => '', 'ru' => ''];
    $nameRequired = $nameRequired ?? true;
@endphp
<div class="admin-locale-tabs" data-locale-tabs>
    <div class="admin-locale-tabs__nav" role="tablist" aria-label="Çeviri dilleri">
        <button type="button" class="admin-locale-tabs__btn is-active" data-locale-tab="tr" role="tab" aria-selected="true">Türkçe</button>
        <button type="button" class="admin-locale-tabs__btn" data-locale-tab="en" role="tab" aria-selected="false">English</button>
        <button type="button" class="admin-locale-tabs__btn" data-locale-tab="ru" role="tab" aria-selected="false">Русский</button>
    </div>

    <div class="admin-locale-tabs__panel is-active" data-locale-panel="tr" role="tabpanel">
        <div>
            <label class="form-label">Ad (Türkçe){{ $nameRequired ? ' *' : '' }}</label>
            <input
                type="text"
                name="name[tr]"
                value="{{ $names['tr'] ?? '' }}"
                @if($nameRequired) required @endif
                class="form-input"
                autocomplete="off"
            >
        </div>
        <div class="mt-4">
            <label class="form-label">Açıklama (Türkçe)</label>
            <textarea name="description[tr]" class="form-input min-h-[80px]" autocomplete="off">{{ $descriptions['tr'] ?? '' }}</textarea>
        </div>
    </div>

    <div class="admin-locale-tabs__panel" data-locale-panel="en" role="tabpanel" hidden>
        <div>
            <label class="form-label">Name (English)</label>
            <input type="text" name="name[en]" value="{{ $names['en'] ?? '' }}" class="form-input" autocomplete="off" placeholder="Optional">
        </div>
        <div class="mt-4">
            <label class="form-label">Description (English)</label>
            <textarea name="description[en]" class="form-input min-h-[80px]" autocomplete="off" placeholder="Optional">{{ $descriptions['en'] ?? '' }}</textarea>
        </div>
    </div>

    <div class="admin-locale-tabs__panel" data-locale-panel="ru" role="tabpanel" hidden>
        <div>
            <label class="form-label">Название (Русский)</label>
            <input type="text" name="name[ru]" value="{{ $names['ru'] ?? '' }}" class="form-input" autocomplete="off" placeholder="Необязательно">
        </div>
        <div class="mt-4">
            <label class="form-label">Описание (Русский)</label>
            <textarea name="description[ru]" class="form-input min-h-[80px]" autocomplete="off" placeholder="Необязательно">{{ $descriptions['ru'] ?? '' }}</textarea>
        </div>
    </div>
</div>
