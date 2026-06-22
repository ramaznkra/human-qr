@php
    $tabs = $cat->distinctActiveMenuTabs($locale);
@endphp

@if($tabs->isEmpty())
<div class="menu-coming-soon">
    <p class="menu-coming-soon__title">{{ __('menu.coming_soon') }}</p>
    <p class="menu-coming-soon__hint">{{ __('menu.coming_soon_hint') }}</p>
</div>
@else
<div
    class="menu-hierarchy-browse"
    data-hierarchy-panel="{{ $cat->id }}"
    x-data="{
        activeTab: {{ $tabs->first()->id }},
        search: '',
        expanded: {},
        toggleSection(id) { this.expanded[id] = !this.expanded[id]; },
        isOpen(id) { return !!this.expanded[id]; },
        matchesQuery(text) {
            const q = this.search.trim().toLowerCase();
            return q === '' || (text || '').toLowerCase().includes(q);
        },
        sectionHasMatch(haystacks) {
            const q = this.search.trim().toLowerCase();
            if (!q) return true;
            return haystacks.some(h => String(h).includes(q));
        }
    }"
    x-cloak
>
    <header class="menu-hierarchy-browse__head">
        <h2 class="menu-hierarchy-browse__title">{{ mb_strtoupper($cat->localizedName(), 'UTF-8') }}</h2>
        @if($cat->localizedDescription($locale))
        <p class="menu-hierarchy-browse__subtitle">{{ $cat->localizedDescription($locale) }}</p>
        @endif
    </header>

    <nav class="menu-hierarchy-tabs-nav" aria-label="{{ __('menu.drinks.tabs_aria') }}">
        <div class="menu-hierarchy-tabs scrollbar-hide" role="tablist">
            @foreach($tabs as $tab)
            <button
                type="button"
                role="tab"
                class="menu-hierarchy-tab"
                :class="{ 'is-active': activeTab === {{ $tab->id }} }"
                @click="activeTab = {{ $tab->id }}"
                :aria-selected="activeTab === {{ $tab->id }}"
            >
                {{ $tab->localizedName($locale) }}
            </button>
            @endforeach
        </div>
    </nav>

    <div class="menu-hierarchy-search">
        <span class="menu-hierarchy-search__icon" aria-hidden="true">⌕</span>
        <input
            type="search"
            x-model.debounce.200ms="search"
            class="menu-hierarchy-search__input"
            placeholder="{{ __('menu.search_placeholder') }}"
            autocomplete="off"
            aria-label="{{ __('menu.search_placeholder') }}"
        >
    </div>

    @foreach($tabs as $tab)
    <div
        class="menu-hierarchy-tab-panel"
        x-show="activeTab === {{ $tab->id }}"
        x-cloak
        data-tab-panel="{{ $tab->id }}"
    >
        @if($tab->isFlat())
            @include('menu.partials.hierarchy.flat-list', [
                'tab' => $tab,
                'locale' => $locale,
                'settings' => $settings,
            ])
        @else
        @php
            $sections = $tab->menuSections->filter(fn ($s) => $s->is_active);
        @endphp

        @if($sections->isEmpty())
        <div class="menu-coming-soon">
            <p class="menu-coming-soon__title">{{ __('menu.coming_soon') }}</p>
        </div>
        @else
        <div class="menu-hierarchy-accordions">
            @foreach($sections as $section)
                @include('menu.partials.hierarchy.accordion-section', [
                    'section' => $section,
                    'locale' => $locale,
                    'settings' => $settings,
                    'productPopularity' => $productPopularity,
                ])
            @endforeach
        </div>
        @endif
        @endif
    </div>
    @endforeach
</div>
@endif
