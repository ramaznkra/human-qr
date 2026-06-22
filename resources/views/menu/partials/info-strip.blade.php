@php
    $showMotto = ($settings['show_motto_banner'] ?? '1') === '1' && filled($settings['daily_motto'] ?? '');
@endphp

@if($showMotto)
<section class="menu-info-strip px-5 pb-4" aria-label="Mekan bilgileri">
    <div class="menu-info-strip__grid">
        <article class="menu-info-card menu-info-card--motto">
            <div class="menu-info-card__icon" aria-hidden="true">✦</div>
            <div class="min-w-0 flex-1">
                <p class="menu-info-card__label">{{ __('menu.motto_label') }}</p>
                <p class="menu-info-card__motto">{{ $settings['daily_motto'] }}</p>
            </div>
        </article>
    </div>
</section>
@endif
