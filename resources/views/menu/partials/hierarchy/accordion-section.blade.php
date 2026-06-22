@php
    $products = $section->products->filter(fn ($p) => $p->is_available);
    $searchHaystacks = $products
        ->map(fn ($p) => strtolower(trim($p->localizedName().' '.($p->localizedDescription() ?? ''))))
        ->values()
        ->all();
@endphp

<section
    class="menu-hierarchy-accordion"
    x-data="{ open: false }"
    x-show="sectionHasMatch(@js($searchHaystacks))"
>
    <button
        type="button"
        class="menu-hierarchy-accordion__trigger"
        @click="open = !open"
        :aria-expanded="open"
    >
        <span class="menu-hierarchy-accordion__title">{{ $section->localizedName($locale) }}</span>
        <span class="menu-hierarchy-accordion__meta">{{ $products->count() }}</span>
        <span class="menu-hierarchy-accordion__chevron" :class="{ 'is-open': open }" aria-hidden="true">⌄</span>
    </button>

    <div class="menu-hierarchy-accordion__body" x-show="open" x-collapse>
        @if($products->isEmpty())
        <p class="menu-hierarchy-accordion__empty px-4 py-3 text-sm text-zinc-500">{{ __('menu.coming_soon') }}</p>
        @else
        <div class="menu-hierarchy-accordion__list">
            @foreach($products as $product)
            @php
                $searchHaystack = strtolower(trim($product->localizedName().' '.($product->localizedDescription() ?? '')));
            @endphp
            <div x-show="matchesQuery(@js($searchHaystack))" x-cloak>
                @include('menu.partials.drinks.list-row', [
                    'product' => $product,
                    'locale' => $locale,
                    'settings' => $settings,
                    'tabId' => $section->menu_tab_id,
                    'sectionId' => $section->id,
                    'icon' => '☕',
                ])
            </div>
            @endforeach
        </div>
        @endif
    </div>
</section>
