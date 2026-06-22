@php
    $optionGroups = $product->cartOptionsPayload($locale);
    $hasOptions = count($optionGroups) > 0;
    $inStock = $product->in_stock ?? true;
    $currency = $settings['currency'] ?? '₺';
    $desc = $product->localizedDescription();
    $tabId = $tabId ?? 'all';
    $sectionId = $sectionId ?? 'other';
@endphp
<button
    type="button"
    class="drinks-list-row product-item {{ !$inStock ? 'drinks-list-row--sold-out' : '' }}"
    data-id="{{ $product->id }}"
    data-name="{{ $product->localizedName() }}"
    data-price="{{ $product->price }}"
    data-in-stock="{{ $inStock ? '1' : '0' }}"
    data-has-options="{{ $hasOptions ? '1' : '0' }}"
    data-options='@json($optionGroups)'
    data-drink-tab="{{ $tabId }}"
    data-drink-section="{{ $sectionId }}"
    data-desc="{{ $desc ?? '' }}"
    data-search="{{ strtolower($product->localizedName() . ' ' . ($desc ?? '')) }}"
    @if(!$inStock) disabled @endif
>
    <span class="drinks-list-row__thumb" aria-hidden="true">
        @if($product->image)
        <img src="{{ $product->image_url }}" alt="" class="drinks-list-row__img" loading="lazy">
        @else
        <span class="drinks-list-row__icon">{{ $icon ?? '☕' }}</span>
        @endif
    </span>

    <span class="drinks-list-row__body">
        <span class="drinks-list-row__top">
            <span class="drinks-list-row__name">{{ $product->localizedName() }}</span>
            @if($product->badge)
            <span class="drinks-list-row__badge">{{ $product->badge }}</span>
            @endif
        </span>
        @if($desc)
        <span class="drinks-list-row__desc">{{ $desc }}</span>
        @endif
    </span>

    <span class="drinks-list-row__price">
        {{ number_format($product->price, 0, ',', '.') }}
        <span class="drinks-list-row__currency">{{ $currency }}</span>
    </span>
</button>
