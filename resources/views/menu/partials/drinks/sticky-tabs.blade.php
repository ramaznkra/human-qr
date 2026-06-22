<nav class="drinks-tabs-nav" aria-label="{{ __('menu.drinks.tabs_aria') }}">
    <div class="drinks-tabs scrollbar-hide" role="tablist">
        @foreach($tabs as $index => $tab)
        <button
            type="button"
            role="tab"
            class="drinks-tab {{ $index === 0 ? 'is-active' : '' }}"
            data-drink-tab="{{ $tab['id'] }}"
            aria-selected="{{ $index === 0 ? 'true' : 'false' }}"
        >
            {{ $tab['label'] }}
        </button>
        @endforeach
    </div>
</nav>
