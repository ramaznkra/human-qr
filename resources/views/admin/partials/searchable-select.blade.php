@props([
    'name',
    'label',
    'value' => '',
    'required' => false,
    'placeholder' => 'Seçin…',
    'searchPlaceholder' => 'Ara…',
    'options' => [],
])
@php
    $selected = (string) old($name, $value);
    $selectedLabel = collect($options)->firstWhere('value', $selected)['label'] ?? $placeholder;
@endphp
<div class="searchable-select" data-searchable-select>
    <label class="form-label">{{ $label }}</label>
    <input
        type="hidden"
        name="{{ $name }}"
        value="{{ $selected }}"
        data-searchable-value
        @if($required) required @endif
    >
    <button
        type="button"
        class="searchable-select__trigger"
        data-searchable-trigger
        aria-haspopup="listbox"
        aria-expanded="false"
    >
        <span class="searchable-select__label" data-searchable-label>{{ $selectedLabel }}</span>
        <span class="searchable-select__chevron" aria-hidden="true">▾</span>
    </button>
    <div class="searchable-select__panel hidden" data-searchable-panel role="listbox">
        <input
            type="search"
            class="searchable-select__search"
            placeholder="{{ $searchPlaceholder }}"
            data-searchable-filter
            autocomplete="off"
        >
        <ul class="searchable-select__list">
            @foreach($options as $option)
            <li>
                <button
                    type="button"
                    class="searchable-select__option {{ (string) $option['value'] === $selected ? 'is-selected' : '' }}"
                    data-searchable-option
                    data-value="{{ $option['value'] }}"
                    data-label="{{ $option['label'] }}"
                    @if(!empty($option['type'])) data-category-type="{{ $option['type'] }}" @endif
                    @if(!empty($option['slug'])) data-category-slug="{{ $option['slug'] }}" @endif
                >{{ $option['label'] }}</button>
            </li>
            @endforeach
        </ul>
    </div>
</div>
