@extends('layouts.menu')

@section('title', $settings['venue_name'] . ' — Menü')

@section('content')
@php
    $spotifyUrl = trim($settings['spotify_url'] ?? '');
    $instagramUrl = trim($settings['instagram_url'] ?? '');
    $instagramHandle = $settings['instagram_handle'] ?? '@ramaznkra';
    $orderOn = ($settings['order_enabled'] ?? '1') === '1';
    $socialWidgetsGrid = true;
    $scrollPad = 'menu-scroll-pad';
    if ($table && $orderOn) {
        $scrollPad .= ' menu-scroll-pad--table-cart';
    } else    if ($table) {
        $scrollPad .= ' menu-scroll-pad--table';
    }
@endphp
<div class="menu-page">
<main class="menu-shell menu-shell--compact {{ $scrollPad }}" id="menuApp">

    @include('menu.partials.top-bar', compact('settings', 'table', 'locale'))

    {{-- Social Spotted banner --}}
    @if($spottedSliders->isNotEmpty())
    <section class="menu-spotted-compact px-5 pt-5" aria-label="Social Spotted">
        <div id="spottedCarousel" class="spotted-hero spotted-hero--lounge">
            <div class="spotted-hero-track" data-spotted-track>
                @foreach($spottedSliders as $slider)
                <article class="spotted-hero-slide" data-spotted-card>
                    <div class="spotted-hero-card spotted-hero-card--lounge">
                        <img
                            src="{{ $slider->image_url }}"
                            alt="{{ $slider->title ?? 'HSP Moments' }}"
                            class="spotted-hero-img spotted-hero-img--lounge"
                            loading="lazy"
                            draggable="false"
                        >
                        <div class="spotted-hero-overlay spotted-hero-overlay--lounge" aria-hidden="true"></div>
                        @if($slider->title || $slider->description)
                        <div class="spotted-hero-caption spotted-hero-caption--lounge">
                            @if($slider->title)
                            <span class="spotted-hero-badge spotted-hero-badge--lounge">{{ $slider->title }}</span>
                            @endif
                            @if($slider->description)
                            <p class="spotted-hero-quote">{{ $slider->description }}</p>
                            @endif
                        </div>
                        @endif
                    </div>
                </article>
                @endforeach
            </div>
            @if($spottedSliders->count() > 1)
            <div class="mt-2 flex justify-center gap-1.5">
                @foreach($spottedSliders as $i => $slider)
                <button
                    type="button"
                    data-spotted-dot
                    data-index="{{ $i }}"
                    class="h-1.5 rounded-full transition-all {{ $i === 0 ? 'w-5 bg-[#C6A046]' : 'w-1.5 bg-zinc-700' }}"
                    aria-label="Slayt {{ $i + 1 }}"
                ></button>
                @endforeach
            </div>
            @endif
        </div>
    </section>
    @endif

    @include('menu.partials.info-strip', compact('settings'))

    {{-- Kategoriler: banner hemen altında --}}
    <section id="menuDynamicZone" class="menu-dynamic-zone mx-auto w-full max-w-md px-5 pb-5 pt-4" aria-live="polite">
        @include('menu.partials.dynamic-zone', compact('categories', 'settings', 'productPopularity', 'locale'))
    </section>

    {{-- Sosyal widget'lar: kategorilerin altında --}}
    <div id="menuSocialZone" class="menu-social-zone">
        @if($socialWidgetsGrid)
            @include('menu.partials.social-widgets-grid', compact('spotifyUrl', 'instagramUrl', 'instagramHandle', 'settings'))
        @else
            @include('menu.partials.social-widgets-legacy', compact('spotifyUrl', 'instagramUrl', 'instagramHandle', 'settings'))
        @endif
    </div>
</main>
</div>

@push('menu-overlays')
@if($table)
<div
    id="menuActionBar"
    class="menu-action-bar menu-fixed-dock bottom-0"
    data-call-status-url="{{ route('table.call.status') }}"
>
    <div class="menu-fixed-panel menu-action-bar__panel" style="padding-bottom: calc(12px + env(safe-area-inset-bottom))">
        <div id="callActionButtons">
            <button type="button" id="callWaiter" class="menu-call-waiter" data-call-type="waiter">
                <span class="menu-call-waiter__label">{{ \Illuminate\Support\Str::upper(__('menu.call_waiter')) }}</span>
            </button>
        </div>
        <p id="callCooldownHint" class="call-cooldown-hint hidden text-center text-xs text-zinc-500"></p>
        <p id="callSuccessMsg" class="call-success-msg hidden text-center text-sm font-medium text-[#C6A046]"></p>
    </div>
</div>
@endif

@if(($settings['order_enabled'] ?? '1') === '1')
<div class="menu-floating-cart menu-cart-dock {{ $table ? 'menu-floating-cart--above-actions' : '' }}">
    <button
        type="button"
        id="cartBar"
        class="menu-floating-cart__btn cart-bar"
        aria-label="{{ __('menu.place_order') }}"
    >
        <div class="menu-floating-cart__left">
            <span class="menu-floating-cart__badge uppercase" id="cartCount">0</span>
            <span class="menu-floating-cart__label">{{ __('menu.place_order') }}</span>
        </div>
        <span class="menu-floating-cart__total" id="cartTotal">0 {{ $settings['currency'] ?? '₺' }}</span>
    </button>
</div>

<div class="modal-overlay menu-modal menu-sheet-modal" id="cartModal">
    <div class="menu-fixed-panel menu-sheet-panel p-6">
        <div class="menu-sheet-handle" aria-hidden="true"></div>
        <h2 class="mb-4 text-xl font-black text-zinc-100">{{ __('menu.your_order') }}</h2>
        <div id="cartItems" class="cart-items-list"></div>
        <div class="cart-modal-total mt-4 flex items-center justify-between border-t border-zinc-800 pt-4">
            <span class="text-sm text-zinc-400">{{ __('menu.total') }}</span>
            <span id="cartModalTotal" class="text-lg font-black text-[#C6A046]">0 {{ $settings['currency'] ?? '₺' }}</span>
        </div>
        <textarea id="orderNotes" placeholder="{{ __('menu.order_notes') }}" class="menu-sheet-textarea mt-4"></textarea>
        <div class="mt-4 flex gap-3">
            <button type="button" id="closeCart" class="menu-sheet-btn-secondary flex-1">{{ __('menu.cancel') }}</button>
            <button type="button" id="submitOrder" class="menu-sheet-btn-primary flex-1">{{ __('menu.send') }}</button>
        </div>
    </div>
</div>

<div class="modal-overlay menu-modal menu-sheet-modal menu-product-sheet" id="productModal">
    <div class="menu-fixed-panel menu-sheet-panel menu-product-sheet-panel p-6">
        <div class="menu-sheet-handle" aria-hidden="true"></div>
        <div class="mb-4 flex items-start justify-between gap-3">
            <div class="min-w-0">
                <h2 id="productModalTitle" class="text-xl font-black text-zinc-100"></h2>
                <p id="productModalDesc" class="mt-1 hidden text-sm leading-relaxed text-zinc-500"></p>
                <p id="productModalBasePrice" class="mt-1 text-sm text-zinc-400"></p>
            </div>
            <button type="button" id="productModalClose" class="menu-sheet-close" aria-label="{{ __('menu.cancel') }}">×</button>
        </div>
        <div id="productModalOptions" class="product-modal-options space-y-4"></div>
        <p id="productModalError" class="product-modal-error hidden mt-3 text-sm text-red-400"></p>
        <button type="button" id="productModalAdd" class="menu-sheet-btn-primary mt-5 w-full"></button>
    </div>
</div>
@endif
@endpush

@endsection

@push('scripts')
<script>
window.HSP_MENU = {
    tableToken: @json($table?->uuid ?? $table?->qr_token),
    restaurantId: @json(\App\Support\CurrentRestaurant::resolveId()),
    currency: @json($settings['currency'] ?? '₺'),
    locale: @json($locale),
    orderStoreUrl: @json(route('order.store')),
    callApiUrl: @json(route('table.call.api')),
    reverb: {
        key: @json(config('broadcasting.connections.reverb.key', env('REVERB_APP_KEY'))),
        host: @json(config('broadcasting.connections.reverb.options.host', env('REVERB_HOST', '127.0.0.1'))),
        port: @json((int) config('broadcasting.connections.reverb.options.port', env('REVERB_PORT', 8080))),
        scheme: @json(config('broadcasting.connections.reverb.options.scheme', env('REVERB_SCHEME', 'http'))),
    },
    i18n: {
        cartItems: @json(__('menu.cart_items', ['count' => ':count'])),
        cartRemove: @json(__('menu.cart_remove')),
        cartDecrease: @json(__('menu.cart_decrease')),
        cartIncrease: @json(__('menu.cart_increase')),
        send: @json(__('menu.send')),
        sending: @json(__('menu.sending')),
        addToCart: @json(__('menu.add_to_cart')),
        addToCartPrice: @json(__('menu.add_to_cart_price')),
        basePrice: @json(__('menu.base_price')),
        optionRequired: @json(__('menu.option_required')),
        callWaiterSent: @json(__('menu.call.waiter_sent')),
        waiterOnTheWay: @json(__('menu.call.waiter_on_the_way', ['name' => ':name'])),
        callWaiterActive: @json(__('menu.call.waiter_active')),
        callFail: @json(__('menu.call.fail')),
        connection: @json(__('menu.call.connection')),
        orderFail: @json(__('menu.call.fail')),
        callCooldown: @json(__('menu.call_cooldown')),
        callCooldownTimer: @json(__('menu.call_cooldown_timer')),
        soldOut: @json(__('menu.sold_out')),
    },
};
</script>
@vite(['resources/js/pages/menu-cart.js', 'resources/js/pages/menu-hierarchy-browse.js', 'resources/js/pages/menu-spotted.js'])
@endpush
