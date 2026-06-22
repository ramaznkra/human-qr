<section
    class="drinks-section"
    id="drinks-section-{{ $section['id'] }}"
    data-drink-section="{{ $section['id'] }}"
    data-drink-tab="{{ $section['tab'] }}"
>
    <h3 class="drinks-section__title">{{ $section['title'] }}</h3>

    <div class="drinks-section__list">
        @foreach($section['products'] as $entry)
            @include('menu.partials.drinks.list-row', [
                'product' => $entry['product'],
                'locale' => $locale,
                'settings' => $settings,
                'tabId' => $entry['tab'],
                'sectionId' => $section['id'],
                'icon' => $sectionIcons[$section['id']] ?? '☕',
            ])
        @endforeach
    </div>
</section>
