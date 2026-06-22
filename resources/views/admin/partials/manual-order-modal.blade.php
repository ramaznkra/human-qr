<div id="manualOrderModal" class="manual-order-modal" aria-hidden="true" inert>
    <div class="manual-order-modal__backdrop bg-black/60 backdrop-blur-sm" data-manual-order-close></div>

    <div class="manual-order-modal__panel relative flex h-[100dvh] flex-col overflow-hidden bg-[#0A0A0A] font-sans text-zinc-100" role="dialog" aria-labelledby="manualOrderTitle">

        {{-- Masa seçimi --}}
        <div id="manualOrderTableStep" class="manual-order-step flex min-h-0 flex-1 flex-col">
            <header class="flex shrink-0 items-center justify-between border-b border-zinc-900 bg-[#111111] px-4 py-3">
                <button type="button" class="flex items-center gap-1 text-sm text-zinc-400 transition-all active:scale-95" data-manual-order-close>
                    ✕ Kapat
                </button>
                <h2 id="manualOrderTitle" class="text-base font-black text-zinc-200">Masa Seç</h2>
                <div class="w-12" aria-hidden="true"></div>
            </header>
            <div class="flex-1 overflow-y-auto p-4">
                <p class="mb-3 text-xs text-zinc-500">Sipariş gireceğiniz masayı seçin.</p>
                <div id="manualOrderTables" class="grid grid-cols-2 gap-3">
                    <p class="col-span-2 py-6 text-center text-sm text-zinc-500">Masalar yükleniyor…</p>
                </div>
            </div>
        </div>

        {{-- Ürün kataloğu --}}
        <div id="manualOrderCatalogStep" class="manual-order-step relative flex hidden min-h-0 flex-1 flex-col overflow-hidden" hidden>
            <header class="flex shrink-0 items-center justify-between border-b border-zinc-900 bg-[#111111] px-4 py-3">
                <button type="button" id="manualOrderBackToTables" class="flex items-center gap-1 text-sm text-zinc-400 transition-all active:scale-95">
                    ❮ Masalar
                </button>
                <div class="text-center">
                    <h2 id="manualOrderCatalogTitle" class="text-base font-black text-zinc-200">Sipariş Gir</h2>
                </div>
                <div class="w-12" aria-hidden="true"></div>
            </header>

            <div id="manualOrderActiveBanner" class="manual-order-active-banner hidden shrink-0 border-b border-amber-500/20 bg-amber-500/10 px-4 py-3" hidden>
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-amber-400/80">Mutfağa giden sipariş</p>
                        <p id="manualOrderActiveSummary" class="mt-0.5 truncate text-sm font-bold text-amber-100">#— hazırlanıyor</p>
                        <ul id="manualOrderActiveItems" class="mt-1 space-y-0.5 text-xs text-amber-200/70"></ul>
                    </div>
                    <button type="button" id="manualOrderCancelActive" class="shrink-0 rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-2 text-[10px] font-black uppercase tracking-wide text-red-300 transition-all active:scale-95">
                        İptal Et
                    </button>
                </div>
            </div>

            <main id="manualOrderProductGrid" class="manual-order-modal__body min-h-0 flex-1 overflow-y-auto p-4 pb-28">
                <div id="manualOrderProductAccordion" class="staff-product-accordion manual-order-accordion" aria-label="Ürün kategorileri">
                    <p class="staff-accordion__empty">Kategoriler yükleniyor…</p>
                </div>
            </main>

            <div class="manual-order-bottom-bar fixed bottom-0 left-0 right-0 z-30 flex items-center justify-between border-t border-zinc-900 bg-[#111111]/95 p-4 backdrop-blur-md">
                <button type="button" id="manualOrderCartOpen" class="min-w-0 flex-1 text-left transition-all active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-40" disabled aria-label="Sepeti düzenle">
                    <span class="block text-[10px] font-medium uppercase text-zinc-500">Eklenecek Sipariş</span>
                    <span id="manualOrderCartSummary" class="text-lg font-black text-[#C6A046]">0 Ürün • 0 ₺</span>
                </button>
                <button type="button" id="manualOrderSubmit" class="ml-3 shrink-0 rounded-xl bg-[#C6A046] px-5 py-3 text-xs font-black text-black shadow-lg transition-all active:scale-95 disabled:cursor-not-allowed disabled:opacity-40" disabled>
                    MUTFAĞA GÖNDER 🚀
                </button>
            </div>
        </div>

        {{-- Sepet bottom sheet --}}
        <div id="manualOrderCartSheet" class="manual-order-cart-sheet" aria-hidden="true" inert>
            <div class="manual-order-cart-sheet__backdrop" data-manual-order-cart-close></div>
            <div class="manual-order-cart-sheet__panel">
                <div class="mx-auto -mt-2 mb-4 h-1 w-12 rounded-full bg-zinc-700"></div>

                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-black text-zinc-200">Sepet</h2>
                        <p id="manualOrderCartSheetHint" class="mt-0.5 text-xs text-zinc-500">Göndermeden önce kontrol edin.</p>
                    </div>
                    <button type="button" class="flex h-7 w-7 items-center justify-center rounded-full border border-zinc-800 bg-[#161616] text-sm font-bold text-zinc-500 transition-all active:scale-95" data-manual-order-cart-close aria-label="Kapat">✕</button>
                </div>

                <div id="manualOrderCartLines" class="manual-order-cart-lines mt-4 max-h-[45vh] space-y-2 overflow-y-auto"></div>

                <div class="mt-4 flex items-center justify-between border-t border-zinc-800 pt-4">
                    <span class="text-xs font-bold uppercase tracking-wider text-zinc-500">Toplam</span>
                    <span id="manualOrderCartTotal" class="text-xl font-black text-[#C6A046]">0 ₺</span>
                </div>

                <div class="mt-4 flex gap-2">
                    <button type="button" id="manualOrderCartClear" class="rounded-xl border border-zinc-800 px-4 py-3 text-xs font-bold text-zinc-400 transition-all active:scale-95 disabled:cursor-not-allowed disabled:opacity-40" disabled>
                        Temizle
                    </button>
                    <button type="button" id="manualOrderCartSubmit" class="flex-1 rounded-xl bg-[#C6A046] py-3 text-xs font-black text-black shadow-md transition-all active:scale-95 disabled:cursor-not-allowed disabled:opacity-40" disabled>
                        MUTFAĞA GÖNDER 🚀
                    </button>
                </div>
            </div>
        </div>

        {{-- Varyasyon bottom sheet --}}
        <div id="manualOrderOptionsSheet" class="manual-order-options-sheet" aria-hidden="true" inert>
            <div class="manual-order-options-sheet__backdrop" data-manual-order-options-close></div>
            <div class="manual-order-options-sheet__panel">
                <div class="mx-auto -mt-2 mb-4 h-1 w-12 rounded-full bg-zinc-700"></div>

                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 id="manualOrderOptionsTitle" class="text-lg font-black text-zinc-200">Ürün Özellikleri</h2>
                        <p id="manualOrderOptionsHint" class="mt-0.5 text-xs text-zinc-500">Lütfen porsiyon ve ekstra malzemeleri seçin.</p>
                    </div>
                    <button type="button" class="flex h-7 w-7 items-center justify-center rounded-full border border-zinc-800 bg-[#161616] text-sm font-bold text-zinc-500 transition-all active:scale-95" data-manual-order-options-close aria-label="Kapat">✕</button>
                </div>

                <div id="manualOrderOptionsBody" class="space-y-6"></div>
                <p id="manualOrderOptionsError" class="hidden text-center text-xs font-medium text-red-400"></p>

                <button type="button" id="manualOrderOptionsConfirm" class="manual-order-options-confirm w-full rounded-xl bg-[#C6A046] py-4 text-sm font-black tracking-wide text-black shadow-md transition-all active:scale-95">
                    SEÇİMLERİ ADİSYONA EKLE
                </button>
            </div>
        </div>

        <p id="manualOrderError" class="manual-order-error pointer-events-none fixed bottom-24 left-4 right-4 z-40 hidden rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-2 text-center text-xs font-medium text-red-300"></p>

        <div id="manualOrderSuccess" class="manual-order-success pointer-events-none absolute inset-0 z-[60] flex hidden flex-col items-center justify-center bg-[#0A0A0A]/95 px-6 text-center opacity-0 transition-opacity" aria-hidden="true">
            <div class="manual-order-success__ring mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-emerald-500/10 ring-4 ring-emerald-500/20">
                <span class="manual-order-success__icon text-4xl">👨‍🍳</span>
            </div>
            <p class="manual-order-success__title text-xl font-black text-zinc-100">Hazırlanıyor</p>
            <p id="manualOrderSuccessMsg" class="manual-order-success__message mt-2 text-sm text-zinc-400">Sipariş mutfağa iletildi</p>
            <p class="manual-order-success__hint mt-1 text-xs text-zinc-600">Canlı sipariş ekranında görünecek</p>
        </div>
    </div>
</div>

@once
<script>
    window.HSP_MANUAL_ORDER = {
        bootstrapUrl: @json(route('admin.manual-order.bootstrap', [], false)),
        productsUrl: @json(route('admin.manual-order.products', [], false)),
        storeUrl: @json(route('admin.manual-order.store', [], false)),
        activeTableUrl: @json(url('/admin/api/admin/manual-order/table')),
        cancelOrderUrl: @json(url('/admin/api/admin/manual-order/order')),
    };
</script>
@endonce
