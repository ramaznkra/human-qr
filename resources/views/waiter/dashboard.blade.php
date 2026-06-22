@extends('layouts.waiter')

@section('title', 'Garson Paneli')

@section('content')
<div class="waiter-pwa flex h-[100dvh] w-full flex-col overflow-hidden bg-[#0A0A0A] font-sans text-zinc-100">

    <header class="waiter-pwa__header z-50 flex shrink-0 items-center justify-between border-b border-zinc-900 bg-[#111111] px-4 py-4">
        <div id="waiterLiveBadge" class="flex items-center gap-2">
            <span class="waiter-pwa__live-dot h-2.5 w-2.5 rounded-full bg-emerald-500 animate-pulse" aria-hidden="true"></span>
            <span class="text-xs font-bold uppercase tracking-widest text-[#C6A046]">Human Waiter</span>
        </div>
        <time id="waiterClock" class="font-mono text-sm font-medium text-zinc-400" datetime="">--:--</time>
    </header>

    <main id="waiterMain" class="waiter-pwa__main flex-1 space-y-6 overflow-y-auto overscroll-contain p-4 pb-28">

        <div id="waiterPanelHome" class="waiter-pwa__panel space-y-6">
            <section id="waiterRequestsBlock" aria-label="Anlık istekler">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-zinc-500">Anlık İstekler</h2>
                    <span id="waiterFeedBadge" class="hidden rounded-full bg-amber-500/10 px-2 py-0.5 text-[10px] font-bold text-amber-500">Yeni Bildirimler</span>
                    <p id="waiterFeedStatus" class="sr-only" aria-live="polite">Yükleniyor…</p>
                </div>
                <div id="waiterFeedWrap" class="waiter-feed-wrap" data-waiter-feed-root>
                    <div id="waiterFeed" class="waiter-feed space-y-3">
                        <p class="waiter-feed__empty">Bekleyen çağrı veya sipariş yok ✨</p>
                    </div>
                </div>
            </section>

            <section id="waiterTablesBlock" aria-label="Masalarım">
                <h2 class="mb-3 text-xs font-bold uppercase tracking-wider text-zinc-500">Masalarım</h2>
                <div id="waiterTableGrid" class="grid grid-cols-2 gap-3">
                    <p class="col-span-2 py-6 text-center text-sm text-zinc-500">Masalar yükleniyor…</p>
                </div>
            </section>
        </div>

        <div id="waiterPanelAccount" class="waiter-pwa__panel space-y-4" hidden>
            <section class="rounded-2xl border border-zinc-800 bg-[#111111] p-4">
                <h2 class="mb-1 text-xs font-bold uppercase tracking-wider text-zinc-500">Hesabım</h2>
                <p class="text-lg font-bold text-zinc-100">{{ $waiterName ?? session('admin_name') }}</p>
                <p class="mt-0.5 text-xs text-zinc-500">{{ $venueName }}</p>
            </section>

            <div id="waiterSoundGate" class="waiter-sound-gate" hidden>
                <div class="waiter-sound-gate__card">
                    <p class="waiter-sound-gate__title">🔔 Masa çağrılarını kaçırmayın</p>
                    <p class="waiter-sound-gate__text">Sesli bildirim için bir kez onay verin.</p>
                    <button type="button" id="waiterSoundEnableBtn" class="waiter-sound-gate__btn">Sesli Bildirimleri Aktif Et</button>
                </div>
            </div>

            <button type="button" id="waiterInstallBtn" class="waiter-install-btn waiter-install-btn--account" hidden>
                ⬇ Uygulamayı Yükle
            </button>

            <section class="waiter-transfer-section" aria-label="Masa taşıma">
                <h2 class="waiter-feed-section__title">Masa Taşıma</h2>
                <p class="waiter-transfer-section__hint">Dolu masadaki sipariş ve açık çağrıları boş bir masaya aktarın.</p>
                <div class="waiter-transfer-form">
                    <select id="transferFromTable" class="waiter-transfer-select"></select>
                    <select id="transferToTable" class="waiter-transfer-select"></select>
                    <button type="button" id="transferTableBtn" class="waiter-transfer-btn">Aktar</button>
                </div>
            </section>

            <form action="{{ route('admin.logout') }}" method="POST" class="waiter-logout-form">
                @csrf
                <button type="submit" class="waiter-logout-btn waiter-logout-btn--account" aria-label="Çıkış yap">
                    <span class="waiter-logout-btn__text">Çıkış Yap</span>
                </button>
            </form>
        </div>
    </main>

    <button
        type="button"
        class="manual-order-fab"
        data-manual-order-trigger
        aria-label="Yeni sipariş gir — önce masa seçin"
    >+</button>

    <nav class="waiter-pwa-nav fixed bottom-0 left-0 right-0 z-50 flex h-16 items-center justify-around border-t border-zinc-900 bg-[#111111]/95 px-6 backdrop-blur-md" aria-label="Garson navigasyonu">
        <button type="button" class="waiter-pwa-nav__btn waiter-pwa-nav__btn--active flex h-full w-full flex-col items-center justify-center gap-1 text-[#C6A046]" data-waiter-tab="tables" aria-current="page">
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
            <span class="w-full text-center text-[10px] font-bold uppercase tracking-wider">Masalar</span>
        </button>

        <button type="button" class="waiter-pwa-nav__btn relative flex h-full w-full flex-col items-center justify-center gap-1 text-zinc-500 transition-all" data-waiter-tab="requests">
            <span id="waiterNavRequestsBadge" class="absolute right-2 top-2 hidden h-2 w-2 rounded-full bg-amber-500" aria-hidden="true"></span>
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
            <span class="w-full text-center text-[10px] font-bold uppercase tracking-wider">İstekler</span>
        </button>

        <button type="button" class="waiter-pwa-nav__btn flex h-full w-full flex-col items-center justify-center gap-1 text-zinc-500 transition-all" data-waiter-tab="account">
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            <span class="w-full text-center text-[10px] font-bold uppercase tracking-wider">Hesabım</span>
        </button>
    </nav>
</div>

@include('admin.partials.manual-order-modal')
@endsection

@push('scripts')
<script>
window.HSP_WAITER = {
    feedUrl: @json(route('live-orders.api')),
    completeUrl: @json(route('waiter.complete')),
    resolveCallUrl: @json(url('/api/waiter/call')),
    claimCallUrl: @json(url('/api/waiter/call')),
    taskAcceptUrl: @json(url('/api/waiter/tasks')),
    taskCompleteUrl: @json(url('/api/waiter/tasks')),
    approveOrderUrl: @json(url('/api/waiter/order')),
    transferTableUrl: @json(route('waiter.tables.transfer')),
    userId: @json(session('admin_user_id')),
    waiterName: @json($waiterName ?? session('admin_name')),
    templateLayout: true,
    pollMs: 8000,
    feedPollMs: 15000,
    restaurantId: @json(session('admin_restaurant_id')),
    selectedTableId: null,
    selectedTableNumber: null,
    reverb: {
        key: @json(config('broadcasting.connections.reverb.key')),
        host: @json(config('broadcasting.connections.reverb.options.host', '127.0.0.1')),
        port: @json((int) config('broadcasting.connections.reverb.options.port', 8080)),
        scheme: @json(config('broadcasting.connections.reverb.options.scheme', 'http')),
    },
};
</script>
@vite(['resources/js/pages/admin-shell.js', 'resources/js/pages/admin-manual-order.js'])
@endpush
