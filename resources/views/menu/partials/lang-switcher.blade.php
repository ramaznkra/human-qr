@php
    use App\Support\MenuLocale;

    $current = $locale ?? app()->getLocale();
    $tableRef = $table ?? null;
@endphp
<nav class="menu-lang-switcher" aria-label="Dil / Language">
    @foreach(MenuLocale::LOCALES as $code)
    <a
        href="{{ MenuLocale::menuUrl($tableRef, $code) }}"
        class="menu-lang-btn {{ $current === $code ? 'is-active' : '' }}"
        hreflang="{{ $code }}"
        @if($current === $code) aria-current="true" @endif
    >{{ MenuLocale::LABELS[$code] }}</a>
    @endforeach
</nav>
