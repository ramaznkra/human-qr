@php
    $fullscreen = $fullscreen ?? false;
    $showSidebar = $showSidebar ?? ! $fullscreen;
    $isCashier = session('admin_role') === 'cashier';
    $panelTitle = $isCashier ? 'Kasa · Canlı' : 'Canlı Siparişler';
    $useKasaTemplate = $showSidebar && ! $fullscreen;
    $categories = $categories ?? collect();
    $kasaLogo = $kasaLogo ?? \App\Support\SiteBranding::logoUrl();
    $reverbConnection = config('broadcasting.connections.reverb', []);
    $reverbOptions = $reverbConnection['options'] ?? [];
    $categoryIcons = [
        'yiyecek' => '🍽',
        'icecek' => '🥤',
        'nargile' => '💨',
        'okey' => '🎲',
        'biblo' => '🏛',
    ];
@endphp

@if($useKasaTemplate)
<div class="lo-kasa lo-kasa--three-col lo-shell--embedded select-none" id="kasaPanelRoot">
    @include('admin.partials.notification-sound-gate')
    {{-- JS uyumluluğu için gizli referanslar --}}
    <div id="kasaTableChip" class="sr-only" aria-hidden="true">
        <span id="kasaSelectedTableLabel">Masa seçin</span>
    </div>
    <p id="kasaHeadHint" class="sr-only">Soldan masa seçin</p>
    <p id="liveOrdersStatus" class="sr-only">Bağlanıyor…</p>

    <div class="lo-kasa-canvas">
        @if(isset($tables))
        {{-- Sol sütun: Masalar --}}
        <aside class="lo-kasa-col lo-kasa-col--tables" aria-label="Masalar">
            <div class="lo-kasa-col__scroll">
                <div class="lo-kasa-col__brand">
                    <span class="lo-kasa-col__venue">{{ strtoupper($settings['venue_name']) }}</span>
                    <h1 class="lo-kasa-col__title">Kasa Kontrol</h1>
                    <time id="liveOrdersClock" class="lo-kasa-col__clock" data-kasa-static>--:--</time>
                </div>

                <ul class="lo-kasa-legend">
                    <li><span class="lo-legend__dot lo-legend__dot--free"></span>Boş</li>
                    <li><span class="lo-legend__dot lo-legend__dot--busy"></span>Dolu</li>
                </ul>

                <div id="liveTableMapGrid" class="lo-kasa-table-grid" data-lo-grid="1" data-kasa-stable="1">
                    @foreach($tables as $t)
                    @php
                        $isBusy = $busyTableIds->contains($t->id);
                        $state = ! $t->is_active ? 'off' : ($isBusy ? 'busy' : 'free');
                        $stateLabel = ! $t->is_active ? 'Kapalı' : ($isBusy ? 'Dolu' : 'Boş');
                    @endphp
                    <button
                        type="button"
                        class="lo-table lo-table--{{ $state }}"
                        data-table-id="{{ $t->id }}"
                        data-table-number="{{ $t->number }}"
                        data-table-busy="{{ $isBusy ? '1' : '0' }}"
                        data-table-active="{{ $t->is_active ? '1' : '0' }}"
                        title="Masa {{ $t->number }} · {{ $stateLabel }}"
                    >
                        <span class="lo-table__num">Masa {{ $t->number }}</span>
                        <span class="lo-table__label">{{ $stateLabel }}</span>
                    </button>
                    @endforeach
                </div>
            </div>

            <div class="lo-kasa-col__foot">
                <button type="button" id="kasaCatalogToggle" class="lo-kasa-catalog-toggle" disabled aria-expanded="false">
                    + Manuel Sipariş
                </button>
                <form action="{{ route('admin.logout') }}" method="POST" class="lo-kasa-logout-form">
                    @csrf
                    <button type="submit" class="lo-kasa-logout-btn lo-kasa-touch" aria-label="Kasa oturumunu kapat">
                        <svg class="lo-kasa-logout-btn__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h10.5m0 0L18 15.75M19.5 12L18 8.25"/>
                        </svg>
                        <span class="lo-kasa-logout-btn__text">Çıkış Yap</span>
                    </button>
                </form>
            </div>
        </aside>
        @endif

        {{-- Orta sütun: POS Grid (manuel sipariş) veya sipariş detayları --}}
        <div class="lo-kasa-col lo-kasa-col--center" id="kasaCenterCol">
            <div id="kasaCatalogMount" class="lo-kasa-pos-mount" aria-hidden="true"></div>

            <template id="kasaCatalogTemplate">
            <section
                id="kasaPosGrid"
                class="lo-kasa-pos grid h-[calc(100vh-100px)] min-h-0 grid-cols-12 overflow-hidden select-none"
                aria-label="Manuel sipariş terminali"
            >
                <div id="kasaPosLock" class="lo-kasa-pos__lock">
                    <p id="kasaMenuRailLock">Sipariş için soldan masa seçin</p>
                </div>

                {{-- Sol: Kategoriler --}}
                <aside class="lo-kasa-pos__cats col-span-3 flex min-h-0 flex-col border-r border-zinc-900 bg-[#0A0A0A]" aria-label="Kategoriler">
                    <header class="lo-kasa-pos__cats-head shrink-0 border-b border-zinc-900 px-4 py-3">
                        <h2 class="text-xs font-bold uppercase tracking-[0.22em] text-[#C6A046]">Menü</h2>
                    </header>
                    <nav id="kasaPosCategoryList" class="lo-kasa-pos__cat-list lo-kasa-scroll-y flex min-h-0 flex-1 flex-col gap-2 overflow-y-auto p-3" aria-label="Kategori seçimi">
                        @foreach($categories as $cat)
                        @php
                            $slug = $cat->slug ?: '';
                            $icon = $cat->icon ?: ($categoryIcons[$slug] ?? '📋');
                        @endphp
                        <button
                            type="button"
                            class="lo-kasa-pos__cat-btn lo-kasa-touch"
                            data-category-id="{{ $cat->id }}"
                            data-category-slug="{{ $slug }}"
                            disabled
                        >
                            <span class="lo-kasa-pos__cat-icon" aria-hidden="true">{{ $icon }}</span>
                            <span class="lo-kasa-pos__cat-label">{{ $cat->getTranslation('name', 'tr') }}</span>
                        </button>
                        @endforeach
                    </nav>
                </aside>

                {{-- Orta: Ürün grid --}}
                <main class="lo-kasa-pos__products col-span-6 flex min-h-0 min-w-0 flex-col bg-[#111111]" aria-label="Ürün seçimi">
                    <div id="kasaProductGrid" class="lo-kasa-pos__product-grid lo-kasa-scroll-y grid min-h-0 flex-1 grid-cols-3 gap-4 overflow-y-auto p-4"></div>
                </main>

                {{-- Sağ: Adisyon özeti --}}
                <aside class="lo-kasa-pos__summary col-span-3 flex min-h-0 flex-col border-l border-zinc-900 bg-[#0A0A0A]" aria-label="Sipariş özeti">
                    <header class="lo-kasa-pos__summary-head shrink-0 border-b border-zinc-900 px-4 py-3">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-zinc-500">Adisyon</p>
                        <div class="mt-1 flex items-center justify-between gap-2">
                            <span class="text-sm font-semibold text-zinc-200">Masa <strong id="kasaPosTableLabel" class="text-[#C6A046]">—</strong></span>
                            <span id="kasaPosOrderStatus" class="lo-kasa-pos__status lo-kasa-pos__status--idle">Boş</span>
                        </div>
                    </header>
                    <ul id="kasaPosOrderItems" class="lo-kasa-pos__items lo-kasa-scroll-y min-h-0 flex-1 list-none overflow-y-auto p-3"></ul>
                    <footer class="lo-kasa-pos__summary-foot shrink-0 border-t border-zinc-900 p-4">
                        <div class="lo-kasa-pos__total mb-4 flex items-end justify-between gap-3">
                            <span class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Toplam</span>
                            <strong id="kasaPosTotalAmount" class="text-2xl font-extrabold tabular-nums text-[#C6A046]">0 ₺</strong>
                        </div>
                        <form id="kasaPosConfirmForm" class="lo-kasa-pos__confirm-form">
                            <button type="submit" id="kasaPosConfirmBtn" class="lo-kasa-pos__confirm lo-kasa-touch" disabled>
                                SİPARİŞİ ONAYLA
                            </button>
                        </form>
                    </footer>
                    <p id="kasaOrderError" class="lo-kasa-pos__error mx-4 mb-3 hidden shrink-0 text-xs text-red-400"></p>
                </aside>
            </section>
            </template>

            <div
                id="liveOrdersApp"
                class="lo-app lo-kasa-feed-wrap"
                data-api-url="{{ route('live-orders.api') }}"
                data-dismiss-completed-url="{{ url('/api/admin/live-orders/completed') }}"
                data-completed-retention-minutes="{{ (int) config('live_orders.completed_retention_minutes', 120) }}"
                data-status-url="{{ url('/api/admin/live-orders') }}"
                data-resolve-call-url="{{ url('/api/admin/call') }}"
                data-csrf="{{ csrf_token() }}"
                data-page-title="HSP Canlı Siparişler"
                data-has-sidebar="{{ $showSidebar ? '1' : '0' }}"
                data-reverb-key="{{ $reverbConnection['key'] ?? '' }}"
                data-reverb-host="{{ $reverbOptions['host'] ?? '127.0.0.1' }}"
                data-reverb-port="{{ (int) ($reverbOptions['port'] ?? 8080) }}"
                data-reverb-scheme="{{ $reverbOptions['scheme'] ?? 'http' }}"
                data-restaurant-id="{{ \App\Support\CurrentRestaurant::id() ?? session('admin_restaurant_id') ?? session('kiosk_restaurant_id') }}"
                data-default-tab="{{ $defaultStation ?? 'all' }}"
                data-show-pending-approval="{{ ($showPendingApproval ?? true) ? '1' : '0' }}"
                data-kasa-mode="1"
            >
                <header class="lo-kasa-chrome lo-kasa-chrome--hidden" aria-hidden="true">
                    <nav class="lo-tabs" id="liveOrdersTabs" role="tablist" aria-label="Sipariş filtreleri">
                        <button type="button" class="lo-tab is-active" data-tab="all" role="tab">Tümü</button>
                        <button type="button" class="lo-tab lo-tab--badge" data-tab="kitchen" role="tab">
                            Mutfak
                            <span class="lo-tab__badge hidden" data-badge="kitchen">0</span>
                        </button>
                        <button type="button" class="lo-tab lo-tab--badge" data-tab="bar" role="tab">
                            Bar
                            <span class="lo-tab__badge hidden" data-badge="bar">0</span>
                        </button>
                        <button type="button" class="lo-tab lo-tab--badge" data-tab="hookah" role="tab">
                            Nargile
                            <span class="lo-tab__badge hidden" data-badge="hookah">0</span>
                        </button>
                        <button type="button" class="lo-tab lo-tab--badge" data-tab="service" role="tab">
                            Servis
                            <span class="lo-tab__badge hidden" data-badge="service">0</span>
                        </button>
                        <button type="button" class="lo-tab" data-tab="assigned" role="tab">Garsona Atandı</button>
                        <button type="button" class="lo-tab" data-tab="served" role="tab">Teslim Edildi</button>
                        <button type="button" class="lo-tab" data-tab="prepared" role="tab">Hazır</button>
                        <button type="button" class="lo-tab" data-tab="completed" role="tab">Tamamlanan</button>
                        <button type="button" class="lo-tab lo-tab--badge lo-tab--alert" data-tab="calls" role="tab">
                            Çağrılar
                            <span class="lo-tab__badge hidden" data-badge="calls">0</span>
                        </button>
                    </nav>
                </header>

                <div class="lo-kasa-order-shell">
                    <header class="lo-kasa-order-shell__head">
                        <h2 id="kasaOrderDetailTitle" class="lo-kasa-order-shell__title">Sipariş Detayları</h2>
                        <span id="kasaOrderDetailStatus" class="lo-kasa-order-shell__status lo-kasa-order-shell__status--idle">Masa seçin</span>
                    </header>
                    <main id="liveOrdersGrid" class="lo-feed lo-kasa-feed lo-kasa-order-shell__list" data-kasa-stable="1">
                        <div class="lo-empty">
                            <span class="lo-empty__icon">⏳</span>
                            <p class="lo-empty__text">Siparişler yükleniyor…</p>
                        </div>
                    </main>
                </div>
            </div>
        </div>

        {{-- Sağ sütun: Adisyon + Ödeme --}}
        <aside class="lo-kasa-col lo-kasa-col--pay" id="kasaActionDock">
            <div class="lo-kasa-pay-card">
                <span class="lo-kasa-pay-card__label">Aktif Adisyon</span>
                <div id="kasaPayOrderCode" class="lo-kasa-pay-card__code">—</div>
                <div id="kasaDockTotal" class="sr-only" hidden>
                    <span id="kasaDockTotalAmount">0 ₺</span>
                </div>
            </div>

            <div class="lo-kasa-pay-total">
                <span class="lo-kasa-pay-total__label">Ödenecek Tutar</span>
                <span id="kasaPayTotalAmount" class="lo-kasa-pay-total__amount">0,00 ₺</span>
            </div>

            <div id="kasaPayActions" class="lo-kasa-pay-actions"></div>

            <div class="lo-kasa-pay-buttons">
                <button type="button" class="lo-kasa-pay-btn lo-kasa-pay-btn--cash" data-kasa-action="cash" disabled>
                    <span class="lo-kasa-dock__btn-text">Nakit Öde</span>
                </button>
                <button type="button" class="lo-kasa-pay-btn lo-kasa-pay-btn--pos" data-kasa-action="card" disabled>
                    <span class="lo-kasa-dock__btn-text">Manuel Kart Ödemesi</span>
                </button>
                <button type="button" class="lo-kasa-pay-btn lo-kasa-pay-btn--pos" data-kasa-action="pos" disabled>
                    <span class="lo-kasa-dock__btn-text">POS ile Öde</span>
                </button>
                <button type="button" class="lo-kasa-pay-btn lo-kasa-pay-btn--waiter sr-only" data-kasa-action="waiter" disabled aria-hidden="true">
                    Garson Çağır
                </button>
            </div>
        </aside>
    </div>
</div>
@include('admin.partials.kasa-product-options-modal')
@include('admin.partials.kasa-line-item-modal')
@else
@include('admin.partials.notification-sound-gate')
<div class="lo-shell {{ $fullscreen ? 'lo-shell--fullscreen' : 'lo-shell--embedded' }}">
    @if($showSidebar && isset($tables))
    <aside class="lo-sidebar" aria-label="Masa durumu">
        <div class="lo-sidebar__head">
            <h2 class="lo-sidebar__title">Masalar</h2>
            <ul class="lo-legend">
                <li><span class="lo-legend__dot lo-legend__dot--free"></span>Boş</li>
                <li><span class="lo-legend__dot lo-legend__dot--busy"></span>Dolu</li>
                <li><span class="lo-legend__dot lo-legend__dot--pay"></span>Ödeme</li>
            </ul>
        </div>
        <div id="liveTableMapGrid" class="lo-table-grid" data-lo-grid="1">
            @foreach($tables as $t)
            @php
                $isBusy = $busyTableIds->contains($t->id);
                $state = ! $t->is_active ? 'off' : ($isBusy ? 'busy' : 'free');
                $stateLabel = ! $t->is_active ? 'Kapalı' : ($isBusy ? 'Dolu' : 'Boş');
            @endphp
            <div
                class="lo-table lo-table--{{ $state }}"
                data-table-id="{{ $t->id }}"
                data-table-busy="{{ $isBusy ? '1' : '0' }}"
                data-table-active="{{ $t->is_active ? '1' : '0' }}"
                title="Masa {{ $t->number }} · {{ $stateLabel }}"
            >
                <span class="lo-table__num">{{ $t->number }}</span>
                <span class="lo-table__label">{{ $stateLabel }}</span>
            </div>
            @endforeach
        </div>
        <div class="lo-sidebar__actions">
            <button type="button" class="lo-action-btn lo-action-btn--gold" data-lo-tab-jump="calls">Çağrılar / Hesap</button>
            <button type="button" class="lo-action-btn lo-action-btn--gold" data-manual-order-trigger>+ Manuel Sipariş</button>
        </div>
    </aside>
    @endif

    <div class="lo-main">
        <div
            id="liveOrdersApp"
            class="lo-app"
            data-api-url="{{ route('live-orders.api') }}"
            data-dismiss-completed-url="{{ url('/api/admin/live-orders/completed') }}"
            data-completed-retention-minutes="{{ (int) config('live_orders.completed_retention_minutes', 120) }}"
            data-status-url="{{ url('/api/admin/live-orders') }}"
            data-resolve-call-url="{{ url('/api/admin/call') }}"
            data-csrf="{{ csrf_token() }}"
            data-page-title="HSP Canlı Siparişler"
            data-has-sidebar="{{ $showSidebar ? '1' : '0' }}"
            data-reverb-key="{{ $reverbConnection['key'] ?? '' }}"
            data-reverb-host="{{ $reverbOptions['host'] ?? '127.0.0.1' }}"
            data-reverb-port="{{ (int) ($reverbOptions['port'] ?? 8080) }}"
            data-reverb-scheme="{{ $reverbOptions['scheme'] ?? 'http' }}"
            data-restaurant-id="{{ \App\Support\CurrentRestaurant::id() ?? session('admin_restaurant_id') ?? session('kiosk_restaurant_id') }}"
            data-default-tab="{{ $defaultStation ?? 'all' }}"
            data-show-pending-approval="{{ ($showPendingApproval ?? true) ? '1' : '0' }}"
        >
            <header class="lo-toolbar">
                <div class="lo-toolbar__top">
                    <div class="lo-toolbar__brand">
                        <h1 class="lo-toolbar__title">{{ $panelTitle }}</h1>
                        <p id="liveOrdersStatus" class="lo-toolbar__status">Bağlanıyor…</p>
                    </div>
                    <time id="liveOrdersClock" class="lo-toolbar__clock">--:--</time>
                </div>
                <nav class="lo-tabs" id="liveOrdersTabs" role="tablist" aria-label="Sipariş filtreleri">
                    <button type="button" class="lo-tab is-active" data-tab="all" role="tab">Tümü</button>
                    <button type="button" class="lo-tab lo-tab--badge" data-tab="kitchen" role="tab">
                        Mutfak
                        <span class="lo-tab__badge hidden" data-badge="kitchen">0</span>
                    </button>
                    <button type="button" class="lo-tab lo-tab--badge" data-tab="bar" role="tab">
                        Bar
                        <span class="lo-tab__badge hidden" data-badge="bar">0</span>
                    </button>
                    <button type="button" class="lo-tab lo-tab--badge" data-tab="nargile" role="tab">
                        Nargile
                        <span class="lo-tab__badge hidden" data-badge="nargile">0</span>
                    </button>
                    <button type="button" class="lo-tab" data-tab="prepared" role="tab">Hazır</button>
                    <button type="button" class="lo-tab" data-tab="completed" role="tab">Tamamlanan</button>
                    <button type="button" class="lo-tab lo-tab--badge lo-tab--alert" data-tab="calls" role="tab">
                        Çağrılar
                        <span class="lo-tab__badge hidden" data-badge="calls">0</span>
                    </button>
                </nav>
            </header>

            <main id="liveOrdersGrid" class="lo-feed">
                <div class="lo-empty">
                    <span class="lo-empty__icon">⏳</span>
                    <p>Siparişler yükleniyor…</p>
                </div>
            </main>
        </div>
    </div>
</div>
@endif
