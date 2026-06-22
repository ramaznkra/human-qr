@php
    $hubTheme = function ($cat) {
        if (in_array($cat->slug, ['okey', 'oyun'], true)) {
            return 'okey';
        }

        return match ($cat->type) {
            'bar' => 'drinks',
            'nargile' => 'shisha',
            default => 'food',
        };
    };

    $hubHint = function ($cat) use ($locale) {
        $desc = $cat->localizedDescription($locale);
        if (filled($desc)) {
            return $desc;
        }

        return match ($cat->type) {
            'nargile' => 'Dünyanın En Elit Tütün Çeşitleri',
            'retail' => 'Özel Koleksiyon ve Biblo Standı',
            'bar' => 'İmza İçecekler ve Kokteyller',
            default => match ($cat->slug) {
                'okey', 'oyun' => 'Eğlence ve Rekabet Bir Arada',
                default => 'Burgerler, Atıştırmalıklar ve Dahası',
            },
        };
    };
@endphp

@if($categories->isNotEmpty())
<nav id="menuCategoryHub" class="menu-category-hub" aria-label="Kategoriler">
    <div class="menu-category-hub__head">
        <h2 class="menu-category-hub__title">{{ __('menu.hub_title') }}</h2>
        <span class="menu-category-hub__line" aria-hidden="true"></span>
    </div>

    <div class="menu-category-hub__list space-y-4">
        @foreach($categories as $cat)
        @php
            $catPhoto = $cat->hub_image_url;
            $theme = $hubTheme($cat);
        @endphp
        <button
            type="button"
            class="menu-hub-cat menu-hub-cat--{{ $theme }}"
            data-category-id="{{ $cat->id }}"
            data-category-name="{{ $cat->localizedName() }}"
            data-category-type="{{ $cat->type }}"
            aria-label="{{ $cat->localizedName() }}"
        >
            @if($catPhoto)
            <div
                class="menu-hub-cat__cover"
                style="background-image: url('{{ $catPhoto }}')"
                aria-hidden="true"
            ></div>
            @endif
            <div class="menu-hub-cat__shade" aria-hidden="true"></div>
            <div class="menu-hub-cat__content">
                <div class="menu-hub-cat__copy text-left">
                    <div class="menu-hub-cat__title-row">
                        <h3 class="menu-hub-cat__name">{{ mb_strtoupper($cat->localizedName(), 'UTF-8') }}</h3>
                        @if($theme === 'shisha')
                        <span class="menu-hub-cat__neon-dot" aria-hidden="true"></span>
                        @endif
                    </div>
                    <p class="menu-hub-cat__hint">{{ $hubHint($cat) }}</p>
                </div>
                <span class="menu-hub-cat__ring" aria-hidden="true">
                    <span class="menu-hub-cat__arrow">→</span>
                </span>
            </div>
        </button>
        @endforeach
    </div>
</nav>
@endif

<div id="menuProductBrowse" class="menu-product-browse hidden" aria-hidden="true">
    <div class="menu-browse-head">
        <button type="button" id="menuBackBtn" class="menu-back-btn">
            {{ __('menu.back_to_menu') }}
        </button>
        <span class="menu-browse-head__brand">{{ strtoupper($settings['venue_name']) }}</span>
    </div>

    <div class="product-list-wrap menu-product-list py-3" id="menuSections">
@foreach($categories as $cat)
@php $isRetailGrid = $cat->type === 'retail'; @endphp
<div
    class="menu-category-panel {{ $isRetailGrid ? 'menu-category-panel--retail' : '' }} {{ $cat->distinctActiveMenuTabs($locale)->isNotEmpty() ? 'menu-category-panel--hierarchy' : ($cat->type === 'bar' ? 'menu-category-panel--drinks' : 'space-y-3') }} hidden"
    data-category-panel="{{ $cat->id }}"
    data-category-type="{{ $cat->type }}"
    id="cat-panel-{{ $cat->id }}"
>
    @if($cat->distinctActiveMenuTabs($locale)->isNotEmpty())
        @include('menu.partials.hierarchy.browse-panel', [
            'cat' => $cat,
            'locale' => $locale,
            'settings' => $settings,
            'productPopularity' => $productPopularity,
        ])
    @elseif($cat->type === 'bar')
        @include('menu.partials.drinks.browse-panel', [
            'cat' => $cat,
            'locale' => $locale,
            'settings' => $settings,
        ])
    @else
    @if($cat->products->isEmpty())
    <div class="menu-coming-soon" data-coming-soon>
        <span class="menu-coming-soon__icon" aria-hidden="true">✨</span>
        <p class="menu-coming-soon__title">{{ __('menu.coming_soon') }}</p>
        <p class="menu-coming-soon__hint">{{ __('menu.coming_soon_hint') }}</p>
    </div>
    @endif
    @foreach($cat->products as $product)
    @php
        $optionGroups = $product->cartOptionsPayload($locale);
        $hasOptions = count($optionGroups) > 0;
        $inStock = $product->in_stock ?? true;
    @endphp
    <article
        class="product-item menu-product-card product-row-card {{ $isRetailGrid ? 'menu-product-card--grid flex-col' : '' }} {{ !$inStock ? 'product-row-card--sold-out menu-product-card--sold-out' : '' }}"
        data-id="{{ $product->id }}"
        data-name="{{ $product->localizedName() }}"
        data-price="{{ $product->price }}"
        data-in-stock="{{ $inStock ? '1' : '0' }}"
        data-has-options="{{ $hasOptions ? '1' : '0' }}"
        data-options='@json($optionGroups)'
        data-category-id="{{ $cat->id }}"
        data-search="{{ strtolower($product->localizedName() . ' ' . ($product->localizedDescription() ?? '') . ' ' . $cat->localizedName()) }}"
    >
        @if($isRetailGrid)
        <div class="menu-product-card__media product-row-thumb-wrap w-full">
            @if($product->image)
            <img src="{{ $product->image_url }}" alt="" class="product-row-thumb menu-product-card__img" loading="lazy">
            @else
            <div class="product-row-thumb product-row-thumb--placeholder menu-product-card__img flex aspect-square items-center justify-center text-3xl text-zinc-600">🗿</div>
            @endif
            @if(!$inStock)
            <span class="product-sold-out-badge">{{ __('menu.sold_out') }}</span>
            @endif
        </div>
        @endif
        <div class="menu-product-card__body min-w-0 flex-1">
            <div class="flex items-start justify-between gap-2">
                <h3 class="menu-product-title">{{ $product->localizedName() }}</h3>
                @if($product->badge)
                <span class="product-badge shrink-0">{{ $product->badge }}</span>
                @endif
            </div>
            @if($product->localizedDescription())
            <p class="menu-product-desc mt-1 line-clamp-2">{{ $product->localizedDescription() }}</p>
            @endif
            @php $todayOrders = (int) ($productPopularity[$product->id] ?? 0); @endphp
            @if($todayOrders >= 3)
            <span class="social-proof-badge">{{ __('menu.social_proof', ['count' => $todayOrders]) }}</span>
            @endif
            <div class="menu-product-card__foot">
                <span class="menu-product-card__price">{{ number_format($product->price, 2, ',', '.') }} <span class="menu-product-card__currency">{{ $settings['currency'] ?? '₺' }}</span></span>
                @if(($settings['order_enabled'] ?? '1') === '1')
                    @if($inStock)
                    <button type="button" class="menu-product-card__add add-btn btn-siparis" data-order-label="{{ __('menu.order_btn') }}" aria-label="{{ __('menu.order_btn') }}">+</button>
                    @else
                    <span class="btn-siparis btn-siparis--disabled" aria-disabled="true">{{ __('menu.sold_out') }}</span>
                    @endif
                @endif
            </div>
        </div>
        @if(!$isRetailGrid)
        <div class="menu-product-card__media product-row-thumb-wrap">
            @if($product->image)
            <img src="{{ $product->image_url }}" alt="" class="product-row-thumb menu-product-card__img" loading="lazy">
            @else
            <div class="product-row-thumb product-row-thumb--placeholder menu-product-card__img flex items-center justify-center text-2xl text-zinc-600">☕</div>
            @endif
            @if(!$inStock)
            <span class="product-sold-out-badge">{{ __('menu.sold_out') }}</span>
            @endif
        </div>
        @endif
    </article>
    @endforeach
    @endif
</div>
@endforeach
    </div>
</div>
