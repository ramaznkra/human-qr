@php
    use App\Support\DrinkMenuCatalog;

    $catalog = DrinkMenuCatalog::build($cat->products, $locale);
    $sectionIcons = [
        'espresso-based' => '☕',
        'milk-coffee' => '🥛',
        'cold-coffee' => '🧊',
        'filter-coffee' => '💧',
        'hot-tea' => '🍵',
        'iced-tea' => '🧋',
        'fresh-drinks' => '🍋',
        'sparkling' => '🥤',
        'signatures' => '🍹',
        'hot-special' => '☕',
        'vegan-drinks' => '🌱',
        'frappe-coffee' => '🧊',
        'frappe-cream' => '🍨',
        'other-coffee' => '✨',
    ];
@endphp

<div class="drinks-browse" data-drinks-panel="{{ $cat->id }}">
    <header class="drinks-browse__head">
        <h2 class="drinks-browse__title">{{ mb_strtoupper($cat->localizedName(), 'UTF-8') }}</h2>
        <p class="drinks-browse__subtitle">{{ $cat->localizedDescription($locale) ?: __('menu.drinks.subtitle') }}</p>
    </header>

    @if($cat->products->isEmpty())
        <div class="menu-coming-soon" data-coming-soon>
            <span class="menu-coming-soon__icon" aria-hidden="true">✨</span>
            <p class="menu-coming-soon__title">{{ __('menu.coming_soon') }}</p>
            <p class="menu-coming-soon__hint">{{ __('menu.coming_soon_hint') }}</p>
        </div>
    @else
        @include('menu.partials.drinks.sticky-tabs', ['tabs' => $catalog['tabs']])

        <div class="drinks-catalog" id="drinksCatalog-{{ $cat->id }}">
            @foreach($catalog['sections'] as $section)
                @include('menu.partials.drinks.section', [
                    'section' => $section,
                    'locale' => $locale,
                    'settings' => $settings,
                    'sectionIcons' => $sectionIcons,
                ])
            @endforeach
        </div>
    @endif
</div>
