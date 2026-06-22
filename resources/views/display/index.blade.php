<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    @include('partials.site-icons')
    <title>{{ $settings['venue_name'] }} — Brand Showcase</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body class="showcase-body">
@php
    $venueName = $settings['venue_name'];
    $brandMark = strtoupper(mb_substr($venueName, 0, 1));
    $logoUrl = \App\Support\SiteBranding::logoUrl();
@endphp
<div
    class="showcase"
    id="showcaseScreen"
    data-hold-ms="7000"
    data-fade-ms="600"
>
    @forelse($slides as $index => $slide)
    @php
        $badge = $slide->badge ?: 'Ambience';
        $badgeVip = str_contains(strtolower($badge), 'vip');
    @endphp
    <section
        class="showcase-slide {{ $index === 0 ? 'is-active' : '' }}"
        data-showcase-slide
        aria-hidden="{{ $index === 0 ? 'false' : 'true' }}"
    >
        <div
            class="showcase-slide__media animate-prestige-zoom"
            style="background-image: url('{{ $slide->image_url }}')"
            aria-hidden="true"
        ></div>
        <div class="showcase-slide__gradient" aria-hidden="true"></div>

        <div class="showcase-slide__caption">
            <span class="showcase-slide__badge {{ $badgeVip ? 'showcase-slide__badge--vip' : 'showcase-slide__badge--ambience' }}">
                {{ $badge }}
            </span>
            @if($slide->title)
            <h1 class="showcase-slide__title">{{ $slide->title }}</h1>
            @endif
            @if($slide->subtitle)
            <p class="showcase-slide__subtitle">{{ $slide->subtitle }}</p>
            @endif
        </div>
    </section>
    @empty
    <section class="showcase-slide is-active" data-showcase-slide aria-hidden="false">
        <div class="showcase-slide__media showcase-slide__media--fallback animate-prestige-zoom" aria-hidden="true"></div>
        <div class="showcase-slide__gradient" aria-hidden="true"></div>
        <div class="showcase-slide__caption">
            <span class="showcase-slide__badge showcase-slide__badge--ambience">Brand Showcase</span>
            <h1 class="showcase-slide__title">{{ $venueName }}</h1>
            <p class="showcase-slide__subtitle">{{ $settings['venue_tagline'] ?? $settings['venue_slogan'] ?? 'Premium Lounge Experience' }}</p>
        </div>
    </section>
    @endforelse

    <div class="showcase-brand" aria-hidden="true">
        <div class="showcase-brand__mark">
            @if($logoUrl)
            <img src="{{ $logoUrl }}" alt="" class="showcase-brand__logo">
            @else
            <span class="showcase-brand__letter">{{ $brandMark }}</span>
            @endif
        </div>
        <span class="showcase-brand__name">{{ strtoupper($venueName) }}</span>
    </div>
</div>

<script>
(function () {
    var root = document.getElementById('showcaseScreen');
    if (!root) return;

    var slides = Array.prototype.slice.call(root.querySelectorAll('[data-showcase-slide]'));
    if (slides.length < 2) return;

    var holdMs = parseInt(root.getAttribute('data-hold-ms'), 10) || 7000;
    var fadeMs = parseInt(root.getAttribute('data-fade-ms'), 10) || 600;

    root.style.setProperty('--showcase-fade-ms', fadeMs + 'ms');

    var current = 0;
    var timer = null;

    function showSlide(nextIndex) {
        if (nextIndex === current) return;

        slides[current].classList.remove('is-active');
        slides[current].setAttribute('aria-hidden', 'true');

        slides[nextIndex].classList.add('is-active');
        slides[nextIndex].setAttribute('aria-hidden', 'false');

        current = nextIndex;
    }

    function scheduleNext() {
        if (timer) clearTimeout(timer);
        timer = setTimeout(function () {
            showSlide((current + 1) % slides.length);
            scheduleNext();
        }, holdMs);
    }

    scheduleNext();

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            scheduleNext();
        }
    });
})();
</script>
</body>
</html>
