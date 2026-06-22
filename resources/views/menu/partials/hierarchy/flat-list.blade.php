@php
    $products = $tab->directProducts->filter(fn ($p) => $p->is_available);
@endphp

@if($products->isEmpty())
<div class="menu-coming-soon">
    <p class="menu-coming-soon__title">{{ __('menu.coming_soon') }}</p>
</div>
@else
<div class="menu-hierarchy-flat-list">
    @foreach($products as $product)
    @php
        $searchHaystack = strtolower(trim($product->localizedName().' '.($product->localizedDescription() ?? '')));
    @endphp
    <div x-show="matchesQuery(@js($searchHaystack))" x-cloak>
        @include('menu.partials.drinks.list-row', [
            'product' => $product,
            'locale' => $locale,
            'settings' => $settings,
            'tabId' => $tab->id,
            'sectionId' => 'flat',
            'icon' => '🥤',
        ])
    </div>
    @endforeach
</div>
@endif
