import { showAdminToast } from '../admin-toast.js';
import {
    buildSelectedOptions,
    defaultSelections,
    displayNameWithOptions,
    hasProductOptions,
    needsOptionPicker,
    toApiOptionPayload,
    unitPriceFromOptions,
    validateSelections,
} from '../lib/product-options.js';
import {
    bindStaffProductAccordion,
    renderStaffProductAccordion,
} from '../lib/staff-product-accordion.js';

function escapeHtml(text) {
    return String(text ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

/**
 * Garson / kasa: hızlı manuel sipariş — mobil PWA akışı.
 */
function initManualOrder() {
    if (window.__HSP_MANUAL_ORDER_INIT__) return;

    const cfg = window.HSP_MANUAL_ORDER;
    const modal = document.getElementById('manualOrderModal');
    if (!cfg || !modal) return;

    window.__HSP_MANUAL_ORDER_INIT__ = true;

    const tableStepEl = document.getElementById('manualOrderTableStep');
    const catalogStepEl = document.getElementById('manualOrderCatalogStep');
    const tablesEl = document.getElementById('manualOrderTables');
    const accordionEl = document.getElementById('manualOrderProductAccordion');
    const catalogTitleEl = document.getElementById('manualOrderCatalogTitle');
    const cartSummaryEl = document.getElementById('manualOrderCartSummary');
    const cartOpenBtn = document.getElementById('manualOrderCartOpen');
    const submitBtn = document.getElementById('manualOrderSubmit');
    const errorEl = document.getElementById('manualOrderError');
    const successEl = document.getElementById('manualOrderSuccess');
    const successMsgEl = document.getElementById('manualOrderSuccessMsg');
    const panelEl = modal.querySelector('.manual-order-modal__panel');
    const backToTablesBtn = document.getElementById('manualOrderBackToTables');

    const activeBannerEl = document.getElementById('manualOrderActiveBanner');
    const activeSummaryEl = document.getElementById('manualOrderActiveSummary');
    const activeItemsEl = document.getElementById('manualOrderActiveItems');
    const cancelActiveBtn = document.getElementById('manualOrderCancelActive');

    const cartSheetEl = document.getElementById('manualOrderCartSheet');
    const cartLinesEl = document.getElementById('manualOrderCartLines');
    const cartTotalEl = document.getElementById('manualOrderCartTotal');
    const cartClearBtn = document.getElementById('manualOrderCartClear');
    const cartSubmitBtn = document.getElementById('manualOrderCartSubmit');
    const cartSheetHintEl = document.getElementById('manualOrderCartSheetHint');

    const optionsSheetEl = document.getElementById('manualOrderOptionsSheet');
    const optionsSheetPanel = optionsSheetEl?.querySelector('.manual-order-options-sheet__panel');
    const optionsTitleEl = document.getElementById('manualOrderOptionsTitle');
    const optionsHintEl = document.getElementById('manualOrderOptionsHint');
    const optionsBodyEl = document.getElementById('manualOrderOptionsBody');
    const optionsErrorEl = document.getElementById('manualOrderOptionsError');
    const optionsConfirmBtn = document.getElementById('manualOrderOptionsConfirm');
    const productGridScrollEl = document.getElementById('manualOrderProductGrid');

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    const fetchOpts = {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    };

    let tables = [];
    let categories = [];
    let currency = '₺';
    let selectedTableId = null;
    let selectedTableNumber = null;
    let activeCategoryId = null;
    /** @type {Map<number, object[]>} */
    const productsByCategory = new Map();
    /** @type {Set<number>} */
    const loadingCategoryIds = new Set();
    /** @type {Map<string, { lineKey: string, productId: number, name: string, unitPrice: number, qty: number, options: object[] }>} */
    const cart = new Map();
    let bootstrapLoaded = false;
    /** @type {HTMLElement | null} */
    let lastTrigger = null;
    /** @type {object|null} */
    let activeOptionProduct = null;
    /** @type {Record<number, number|number[]>} */
    let modalSelections = {};
    /** @type {Map<number, object>} */
    const productsCache = new Map();
    /** @type {{ id: number, order_number: string, status: string, status_label: string, total: number, items: object[] } | null} */
    let activeTableOrder = null;

    function mountOptionsSheet() {
        if (optionsSheetEl && optionsSheetEl.parentElement !== document.body) {
            document.body.appendChild(optionsSheetEl);
        }
    }

    function mountCartSheet() {
        if (cartSheetEl && cartSheetEl.parentElement !== document.body) {
            document.body.appendChild(cartSheetEl);
        }
    }

    function makeLineKey(productId, options) {
        const optionIds = options.map((o) => o.option_id).sort((a, b) => a - b);
        return `${productId}:${optionIds.join(',')}`;
    }

    function formatMoney(n) {
        return `${Math.round(n).toLocaleString('tr-TR')} ${currency}`;
    }

    function formatOptionPrice(price) {
        const n = Number(price) || 0;
        if (n <= 0) return '+0 ₺';
        return `+${Math.round(n).toLocaleString('tr-TR')} ${currency}`;
    }

    function qtyInCartForProduct(productId) {
        let sum = 0;
        cart.forEach((line) => {
            if (Number(line.productId) === Number(productId)) {
                sum += line.qty;
            }
        });
        return sum;
    }

    function decrementProductInCart(productId) {
        const lines = [...cart.values()].filter((line) => Number(line.productId) === Number(productId));
        if (!lines.length) return;
        changeCartQty(lines[lines.length - 1].lineKey, -1);
    }

    function renderProductAccordion() {
        renderStaffProductAccordion(accordionEl, {
            categories,
            activeCategoryId,
            productsByCategory,
            loadingCategoryIds,
            getProductQty: qtyInCartForProduct,
            formatMoney,
            escapeHtml,
            isConfigurable: (p) => p.has_options || needsOptionPicker(p),
        });
    }

    async function toggleCategory(categoryId) {
        const next = activeCategoryId === categoryId ? null : categoryId;
        activeCategoryId = next;
        renderProductAccordion();

        if (next && !productsByCategory.has(next) && !loadingCategoryIds.has(next)) {
            await loadCategoryProducts(next);
        }
    }

    async function loadCategoryProducts(categoryId) {
        if (!bootstrapLoaded || !categoryId) return;

        loadingCategoryIds.add(categoryId);
        renderProductAccordion();

        try {
            const params = new URLSearchParams({ category_id: String(categoryId) });
            const res = await fetch(`${cfg.productsUrl}?${params}`, fetchOpts);
            const data = await res.json().catch(() => ({}));

            if (!res.ok) {
                productsByCategory.set(categoryId, []);
                return;
            }

            (data.products || []).forEach((p) => productsCache.set(p.id, p));
            productsByCategory.set(categoryId, data.products || []);
        } catch {
            productsByCategory.set(categoryId, []);
        } finally {
            loadingCategoryIds.delete(categoryId);
            renderProductAccordion();
        }
    }

    function showStep(step) {
        const isTable = step === 'table';
        tableStepEl?.classList.toggle('hidden', !isTable);
        if (tableStepEl) tableStepEl.hidden = !isTable;
        catalogStepEl?.classList.toggle('hidden', isTable);
        if (catalogStepEl) catalogStepEl.hidden = isTable;
    }

    function resolvePresetTable(trigger) {
        const tableId = trigger?.dataset?.tableId;
        if (tableId) {
            return {
                id: Number(tableId),
                number: String(trigger.dataset.tableNumber ?? ''),
            };
        }

        if (!document.body.classList.contains('waiter-body')) {
            return null;
        }

        const waiter = window.HSP_WAITER;
        if (waiter?.selectedTableId) {
            return {
                id: Number(waiter.selectedTableId),
                number: String(waiter.selectedTableNumber ?? ''),
            };
        }

        return null;
    }

    function openModal(trigger) {
        lastTrigger = trigger ?? lastTrigger;
        modal.classList.add('is-open');
        modal.removeAttribute('inert');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('manual-order-open');
        hideError();
        successEl?.classList.add('hidden');
        successEl?.classList.remove('is-visible');
        panelEl?.classList.remove('manual-order-modal__panel--success');
        closeOptionsSheet(false);
        closeCartSheet(false);

        const preset = resolvePresetTable(trigger);
        if (preset?.id) {
            selectedTableId = preset.id;
            selectedTableNumber = preset.number;
            updateCatalogTitle();
        } else {
            selectedTableId = null;
            selectedTableNumber = null;
            updateCatalogTitle();
        }

        if (!bootstrapLoaded) {
            void loadBootstrap();
        } else if (selectedTableId) {
            showStep('catalog');
            renderProductAccordion();
            renderTables();
            void loadActiveTableOrder();
        } else {
            showStep('table');
            renderTables();
        }
    }

    function closeModal() {
        if (modal.contains(document.activeElement)) {
            if (typeof lastTrigger?.focus === 'function') {
                lastTrigger.focus();
            } else {
                document.activeElement?.blur();
            }
        }
        closeOptionsSheet(false);
        closeCartSheet(false);
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        modal.setAttribute('inert', '');
        document.body.classList.remove('manual-order-open');
        hideError();
        successEl?.classList.add('hidden');
        successEl?.classList.remove('is-visible');
        panelEl?.classList.remove('manual-order-modal__panel--success');
    }

    function showSuccessOverlay(message) {
        if (successMsgEl) successMsgEl.textContent = message;
        panelEl?.classList.add('manual-order-modal__panel--success');
        successEl?.classList.remove('hidden');
        requestAnimationFrame(() => successEl?.classList.add('is-visible'));
    }

    function showError(msg) {
        if (!errorEl) return;
        errorEl.textContent = msg;
        errorEl.classList.remove('hidden');
    }

    function hideError() {
        errorEl?.classList.add('hidden');
        optionsErrorEl?.classList.add('hidden');
    }

    function updateSubmitState() {
        const ok = selectedTableId && cart.size > 0;
        if (submitBtn) submitBtn.disabled = !ok;
        if (cartOpenBtn) cartOpenBtn.disabled = !ok;
        if (cartSubmitBtn) cartSubmitBtn.disabled = !ok;
        if (cartClearBtn) cartClearBtn.disabled = cart.size === 0;
    }

    function cartTotals() {
        let total = 0;
        let qty = 0;
        cart.forEach((line) => {
            total += line.unitPrice * line.qty;
            qty += line.qty;
        });
        return { total, qty };
    }

    function updateCatalogTitle() {
        if (!catalogTitleEl) return;
        catalogTitleEl.textContent = selectedTableNumber
            ? `Masa ${selectedTableNumber} - Sipariş Gir`
            : 'Sipariş Gir';
    }

    function selectTable(id, number) {
        selectedTableId = id;
        selectedTableNumber = number;
        updateCatalogTitle();
        showStep('catalog');
        renderTables();
        productsByCategory.clear();
        activeCategoryId = null;
        renderProductAccordion();
        void loadActiveTableOrder();
    }

    async function loadActiveTableOrder() {
        activeTableOrder = null;
        renderActiveOrderBanner();

        if (!cfg.activeTableUrl || !selectedTableId) return;

        try {
            const res = await fetch(`${cfg.activeTableUrl}/${selectedTableId}/active`, fetchOpts);
            const data = await res.json().catch(() => ({}));
            if (!res.ok) return;
            activeTableOrder = data.order ?? null;
        } catch {
            activeTableOrder = null;
        }

        renderActiveOrderBanner();
    }

    function renderActiveOrderBanner() {
        if (!activeBannerEl) return;

        if (!activeTableOrder) {
            activeBannerEl.classList.add('hidden');
            activeBannerEl.hidden = true;
            if (activeItemsEl) activeItemsEl.innerHTML = '';
            return;
        }

        const total = Number(activeTableOrder.total || 0);

        activeBannerEl.classList.remove('hidden');
        activeBannerEl.hidden = false;

        if (activeSummaryEl) {
            activeSummaryEl.textContent = `#${activeTableOrder.order_number} · ${activeTableOrder.status_label || 'Hazırlanıyor'} · ${formatMoney(total)}`;
        }

        if (activeItemsEl) {
            const preview = (activeTableOrder.items || []).slice(0, 4);
            activeItemsEl.innerHTML = preview
                .map(
                    (item) =>
                        `<li>${escapeHtml(String(item.quantity))}× ${escapeHtml(item.name)}</li>`,
                )
                .join('');
            if ((activeTableOrder.items || []).length > 4) {
                activeItemsEl.innerHTML += `<li>+${activeTableOrder.items.length - 4} ürün daha</li>`;
            }
        }

        if (cancelActiveBtn) {
            cancelActiveBtn.disabled = !activeTableOrder.can_cancel;
        }

        if (cartSheetHintEl && cart.size > 0) {
            cartSheetHintEl.textContent = `Masa ${selectedTableNumber} · #${activeTableOrder.order_number} zaten mutfakta. Yeni sipariş ek bir kayıt oluşturur.`;
        }
    }

    async function cancelActiveOrder() {
        if (!activeTableOrder?.id || !activeTableOrder.can_cancel) return;

        const ok = window.confirm(
            `#${activeTableOrder.order_number} siparişi iptal edilsin mi?\n\nMutfaktaki hazırlık durur; sepeti yeniden oluşturabilirsiniz.`,
        );
        if (!ok) return;

        hideError();
        if (cancelActiveBtn) cancelActiveBtn.disabled = true;

        try {
            const res = await fetch(`${cfg.cancelOrderUrl}/${activeTableOrder.id}/cancel`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf(),
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
            });
            const data = await res.json().catch(() => ({}));

            if (!res.ok || data.success === false) {
                showError(data.message || 'Sipariş iptal edilemedi.');
                return;
            }

            if (!document.body.classList.contains('waiter-body')) {
                showAdminToast({
                    title: 'Sipariş iptal',
                    message: data.message || 'Sipariş iptal edildi.',
                    type: 'success',
                });
            }

            window.dispatchEvent(new CustomEvent('waiter:refresh'));
            await loadActiveTableOrder();
        } catch {
            showError('Bağlantı hatası. Tekrar deneyin.');
        } finally {
            if (cancelActiveBtn) cancelActiveBtn.disabled = !activeTableOrder?.can_cancel;
        }
    }

    function renderTables() {
        if (!tablesEl) return;
        if (!tables.length) {
            tablesEl.innerHTML = '<p class="col-span-2 py-6 text-center text-sm text-amber-400">Aktif masa yok.</p>';
            return;
        }

        tablesEl.innerHTML = tables
            .map((t) => {
                const selected = selectedTableId === t.id;
                return `
            <button type="button"
                class="manual-order-table-tile flex h-28 flex-col justify-between rounded-2xl border p-4 transition-all active:scale-[0.98] ${selected ? 'border-[#C6A046]/60 bg-[#C6A046]/10' : 'border-zinc-900 bg-[#111111] active:border-[#C6A046]/50'}"
                data-table-id="${t.id}"
                data-table-number="${escapeHtml(String(t.number))}">
                <span class="text-xl font-black text-zinc-100">Masa ${escapeHtml(String(t.number))}</span>
                <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-500">Seç</span>
            </button>`;
            })
            .join('');

        tablesEl.querySelectorAll('[data-table-id]').forEach((btn) => {
            btn.addEventListener('click', () => {
                selectTable(Number(btn.dataset.tableId), btn.dataset.tableNumber);
            });
        });
    }

    function renderCartSummary() {
        const { total, qty } = cartTotals();

        if (cartSummaryEl) {
            cartSummaryEl.textContent =
                cart.size === 0 ? `0 Ürün • ${formatMoney(0)}` : `${qty} Ürün • ${formatMoney(total)}`;
        }

        if (cartTotalEl) cartTotalEl.textContent = formatMoney(total);
        renderCartSheetLines();
        renderActiveOrderBanner();
        updateSubmitState();
        renderProductAccordion();
    }

    function renderCartSheetLines() {
        if (!cartLinesEl) return;

        if (cart.size === 0) {
            cartLinesEl.innerHTML =
                '<p class="py-6 text-center text-sm text-zinc-500">Sepet boş. Ürün ekleyerek başlayın.</p>';
            return;
        }

        cartLinesEl.innerHTML = [...cart.values()]
            .map(
                (line) => `
            <div class="manual-order-cart-line flex items-center gap-3 rounded-xl border border-zinc-800 bg-[#141414] p-3" data-cart-line="${escapeHtml(line.lineKey)}">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold leading-tight text-zinc-200">${escapeHtml(line.name)}</p>
                    <p class="mt-0.5 text-xs text-zinc-500">${formatMoney(line.unitPrice)} / adet</p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg border border-zinc-700 bg-[#1A1A1A] text-sm font-bold text-zinc-300" data-cart-dec="${escapeHtml(line.lineKey)}" aria-label="Azalt">−</button>
                    <span class="w-6 text-center text-sm font-black text-zinc-100">${line.qty}</span>
                    <button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg border border-[#C6A046]/30 bg-[#C6A046]/10 text-sm font-bold text-[#C6A046]" data-cart-inc="${escapeHtml(line.lineKey)}" aria-label="Artır">+</button>
                    <button type="button" class="ml-1 flex h-8 w-8 items-center justify-center rounded-lg border border-red-500/20 bg-red-500/10 text-xs font-bold text-red-300" data-cart-remove="${escapeHtml(line.lineKey)}" aria-label="Kaldır">✕</button>
                </div>
            </div>`,
            )
            .join('');
    }

    function changeCartQty(lineKey, delta) {
        const line = cart.get(lineKey);
        if (!line) return;

        line.qty += delta;
        if (line.qty <= 0) {
            cart.delete(lineKey);
        }

        renderCartSummary();
    }

    function removeCartLine(lineKey) {
        cart.delete(lineKey);
        renderCartSummary();
    }

    function clearCart() {
        if (cart.size === 0) return;
        const ok = window.confirm('Sepetteki tüm ürünler silinsin mi?');
        if (!ok) return;
        cart.clear();
        renderCartSummary();
    }

    function releaseSheetFocus(sheetEl, fallbackEl) {
        const active = document.activeElement;
        if (active instanceof HTMLElement && sheetEl?.contains(active)) {
            active.blur();
        }
        if (fallbackEl instanceof HTMLElement && typeof fallbackEl.focus === 'function') {
            fallbackEl.focus({ preventScroll: true });
        }
    }

    function openCartSheet() {
        if (!cartSheetEl || cart.size === 0) return;

        closeOptionsSheet(false);
        renderCartSheetLines();

        if (cartSheetHintEl) {
            cartSheetHintEl.textContent = activeTableOrder
                ? `Masa ${selectedTableNumber} · #${activeTableOrder.order_number} zaten mutfakta. Yeni sipariş ek bir kayıt oluşturur.`
                : `Masa ${selectedTableNumber} · Göndermeden önce kontrol edin.`;
        }

        cartSheetEl.classList.add('is-open');
        cartSheetEl.removeAttribute('inert');
        cartSheetEl.setAttribute('aria-hidden', 'false');
        document.body.classList.add('manual-order-cart-open');
        requestAnimationFrame(() => cartSubmitBtn?.focus({ preventScroll: true }));
    }

    function closeCartSheet(animate = true) {
        if (!cartSheetEl || !cartSheetEl.classList.contains('is-open')) return;

        const finalize = () => {
            releaseSheetFocus(cartSheetEl, submitBtn || cartOpenBtn);
            cartSheetEl.classList.remove('is-open');
            cartSheetEl.setAttribute('aria-hidden', 'true');
            cartSheetEl.setAttribute('inert', '');
            document.body.classList.remove('manual-order-cart-open');
        };

        if (animate) {
            cartSheetEl.classList.remove('is-open');
            setTimeout(finalize, 300);
        } else {
            finalize();
        }
    }

    function handleCartSheetClick(event) {
        const dec = event.target.closest('[data-cart-dec]');
        if (dec) {
            changeCartQty(dec.dataset.cartDec, -1);
            return;
        }

        const inc = event.target.closest('[data-cart-inc]');
        if (inc) {
            changeCartQty(inc.dataset.cartInc, 1);
            return;
        }

        const remove = event.target.closest('[data-cart-remove]');
        if (remove) {
            removeCartLine(remove.dataset.cartRemove);
        }
    }

    function addToCart(product, selectedOptions) {
        const options = selectedOptions || [];
        const apiOptions = toApiOptionPayload(options);
        const lineKey = makeLineKey(product.id, apiOptions);
        const unitPrice = unitPriceFromOptions(product.price, options);
        const name = displayNameWithOptions(product.name, options);

        const existing = cart.get(lineKey);
        if (existing) {
            existing.qty += 1;
        } else {
            cart.set(lineKey, {
                lineKey,
                productId: product.id,
                name,
                unitPrice,
                qty: 1,
                options,
            });
        }

        renderCartSummary();
    }

    async function loadProductWithOptions(productId) {
        const params = new URLSearchParams({ product_id: String(productId) });
        const res = await fetch(`${cfg.productsUrl}?${params.toString()}`, fetchOpts);
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.message || 'Ürün seçenekleri yüklenemedi.');
        const product = (data.products || [])[0];
        if (!product) throw new Error('Ürün bulunamadı.');
        productsCache.set(product.id, product);
        return product;
    }

    async function handleProductClick(product) {
        hideError();
        let full = product;

        if (full.has_options || needsOptionPicker(full)) {
            try {
                full = await loadProductWithOptions(full.id);
            } catch (err) {
                showError(err.message || 'Seçenekler yüklenemedi.');
                return;
            }
        }

        if (hasProductOptions(full)) {
            openOptionsSheet(full);
            return;
        }

        addToCart(full, []);
    }

    function openOptionsSheet(product) {
        if (!optionsSheetEl || !optionsSheetPanel) return;

        activeOptionProduct = product;
        modalSelections = defaultSelections(product.option_groups || []);
        if (optionsTitleEl) optionsTitleEl.textContent = `${product.name} Özellikleri`;
        if (optionsHintEl) {
            optionsHintEl.textContent = 'Lütfen porsiyon ve ekstra malzemeleri seçin.';
        }

        renderOptionsBody();
        updateOptionsConfirmLabel();

        optionsSheetEl.classList.add('is-open');
        optionsSheetEl.removeAttribute('inert');
        optionsSheetEl.setAttribute('aria-hidden', 'false');
        document.body.classList.add('manual-order-options-open');
        requestAnimationFrame(() => optionsConfirmBtn?.focus({ preventScroll: true }));
    }

    function closeOptionsSheet(animate = true) {
        if (!optionsSheetEl || !optionsSheetPanel) return;
        if (!optionsSheetEl.classList.contains('is-open')) return;

        const finalize = () => {
            releaseSheetFocus(optionsSheetEl, submitBtn || cartOpenBtn);
            optionsSheetEl.classList.remove('is-open');
            optionsSheetEl.setAttribute('aria-hidden', 'true');
            optionsSheetEl.setAttribute('inert', '');
            document.body.classList.remove('manual-order-options-open');
            activeOptionProduct = null;
            modalSelections = {};
            if (optionsBodyEl) optionsBodyEl.innerHTML = '';
            optionsErrorEl?.classList.add('hidden');
        };

        if (animate) {
            optionsSheetEl.classList.remove('is-open');
            setTimeout(finalize, 300);
        } else {
            finalize();
        }
    }

    function returnToProductMenu() {
        closeOptionsSheet(true);
        productGridScrollEl?.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function renderOptionsBody() {
        if (!optionsBodyEl || !activeOptionProduct) return;

        const groups = activeOptionProduct.option_groups || [];
        if (!groups.length) {
            optionsBodyEl.innerHTML =
                '<p class="text-sm text-zinc-500">Bu ürün için seçenek bulunamadı.</p>';
            return;
        }

        optionsBodyEl.innerHTML = groups
            .map((group) => {
                const groupId = Number(group.id);
                const options = Array.isArray(group.options) ? group.options : [];
                const title = escapeHtml(group.name);
                const requiredMark = group.required ? ' *' : '';

                if (group.type === 'single') {
                    const cards = options
                        .map((option) => {
                            const optionId = Number(option.id);
                            const selected = Number(modalSelections[groupId]) === optionId;
                            return `
                        <button type="button"
                            class="manual-order-opt-card flex flex-col justify-between rounded-xl border p-3 text-left transition-all active:scale-[0.98] ${selected ? 'relative border-2 border-[#C6A046] bg-[#161616]' : 'border border-zinc-800 bg-[#141414] opacity-60'}"
                            data-opt-group="${groupId}"
                            data-opt-type="single"
                            data-opt-id="${optionId}">
                            <span class="text-sm font-bold ${selected ? 'text-zinc-200' : 'text-zinc-300'}">${escapeHtml(option.name)}</span>
                            <span class="mt-1 text-xs font-medium ${selected ? 'text-[#C6A046]' : 'text-zinc-400'}">${formatOptionPrice(option.price)}</span>
                        </button>`;
                        })
                        .join('');

                    return `
                <div class="space-y-2" data-opt-group-wrap="${groupId}">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-zinc-400">${title}${requiredMark}</h4>
                    <div class="grid grid-cols-2 gap-3">${cards}</div>
                </div>`;
                }

                const rows = options
                    .map((option) => {
                        const optionId = Number(option.id);
                        const selected = (modalSelections[groupId] || []).map(Number).includes(optionId);
                        return `
                    <button type="button"
                        class="manual-order-opt-row flex w-full items-center justify-between rounded-xl border p-3 text-left transition-all active:scale-[0.98] ${selected ? 'border-[#C6A046]/60 bg-[#161616]' : 'border-zinc-800 bg-[#141414]'}"
                        data-opt-group="${groupId}"
                        data-opt-type="multiple"
                        data-opt-id="${optionId}">
                        <span class="text-sm ${selected ? 'font-medium text-zinc-200' : 'text-zinc-300'}">${escapeHtml(option.name)}</span>
                        <span class="text-xs font-bold text-[#C6A046]">${formatOptionPrice(option.price)}</span>
                    </button>`;
                    })
                    .join('');

                return `
            <div class="space-y-2" data-opt-group-wrap="${groupId}">
                <h4 class="text-xs font-bold uppercase tracking-wider text-zinc-400">${title}${requiredMark}</h4>
                <div class="space-y-2">${rows}</div>
            </div>`;
            })
            .join('');
    }

    function handleOptionsBodyClick(event) {
        const btn = event.target.closest('[data-opt-group][data-opt-id]');
        if (!btn || !activeOptionProduct || !optionsBodyEl?.contains(btn)) return;

        const groupId = Number(btn.dataset.optGroup);
        const optionId = Number(btn.dataset.optId);
        const group = (activeOptionProduct.option_groups || []).find((g) => Number(g.id) === groupId);
        if (!group) return;

        if (btn.dataset.optType === 'single') {
            modalSelections[groupId] = optionId;
        } else {
            const current = new Set((modalSelections[groupId] || []).map(Number));
            if (current.has(optionId)) {
                current.delete(optionId);
            } else {
                current.add(optionId);
            }
            modalSelections[groupId] = [...current];
        }

        optionsErrorEl?.classList.add('hidden');
        renderOptionsBody();
        updateOptionsConfirmLabel();
    }

    function updateOptionsConfirmLabel() {
        if (!optionsConfirmBtn || !activeOptionProduct) return;
        const groups = activeOptionProduct.option_groups || [];
        const selected = buildSelectedOptions(groups, modalSelections);
        const total = unitPriceFromOptions(activeOptionProduct.price, selected);
        optionsConfirmBtn.textContent = `SEÇİMLERİ ADİSYONA EKLE (+${Math.round(total).toLocaleString('tr-TR')} ${currency})`;
    }

    function confirmOptionsSelection() {
        if (!activeOptionProduct) return;

        const groups = activeOptionProduct.option_groups || [];
        if (!validateSelections(groups, modalSelections)) {
            if (optionsErrorEl) {
                optionsErrorEl.textContent = 'Zorunlu seçenekleri işaretleyin.';
                optionsErrorEl.classList.remove('hidden');
            }
            return;
        }

        const selected = buildSelectedOptions(groups, modalSelections);
        addToCart(activeOptionProduct, selected);
        returnToProductMenu();
        if (!document.body.classList.contains('waiter-body')) {
            showAdminToast({
                title: 'Sepete eklendi',
                message: displayNameWithOptions(activeOptionProduct.name, selected),
                type: 'success',
                durationMs: 2200,
            });
        }
    }

    async function loadBootstrap() {
        if (tablesEl) {
            tablesEl.innerHTML = '<p class="col-span-2 py-6 text-center text-sm text-zinc-500">Masalar yükleniyor…</p>';
        }
        if (accordionEl) {
            accordionEl.innerHTML = '<p class="staff-accordion__empty">Kategoriler yükleniyor…</p>';
        }

        try {
            const res = await fetch(cfg.bootstrapUrl, fetchOpts);
            const data = await res.json().catch(() => ({}));

            if (!res.ok) {
                throw new Error(data.message || `Masalar yüklenemedi (${res.status})`);
            }

            tables = data.tables || [];
            categories = data.categories || [];
            currency = data.currency || '₺';
            activeCategoryId = null;
            productsByCategory.clear();
            bootstrapLoaded = true;

            renderTables();
            renderProductAccordion();
            if (selectedTableId) {
                updateCatalogTitle();
                showStep('catalog');
                void loadActiveTableOrder();
            } else {
                showStep('table');
            }
        } catch (error) {
            bootstrapLoaded = false;
            const message = error instanceof Error ? error.message : 'Masalar yüklenemedi.';
            if (tablesEl) {
                tablesEl.innerHTML = `<p class="col-span-2 py-6 text-center text-sm text-red-400">${escapeHtml(message)}</p>`;
            }
            if (accordionEl) {
                accordionEl.innerHTML =
                    '<p class="staff-accordion__empty text-red-400">Ürünler yüklenemedi. Sayfayı yenileyip tekrar deneyin.</p>';
            }
        }
    }

    function resetForm() {
        activeTableOrder = null;
        activeCategoryId = null;
        productsByCategory.clear();
        cart.clear();
        productsCache.clear();

        const preset = resolvePresetTable(null);
        if (preset?.id && document.body.classList.contains('waiter-body')) {
            selectedTableId = preset.id;
            selectedTableNumber = preset.number;
        } else {
            selectedTableId = null;
            selectedTableNumber = null;
        }

        renderTables();
        renderCartSummary();
        renderActiveOrderBanner();
        updateCatalogTitle();
        showStep(selectedTableId ? 'catalog' : 'table');
        if (selectedTableId) {
            void loadActiveTableOrder();
        }
    }

    async function submitOrder() {
        if (!selectedTableId || cart.size === 0) return;

        const isWaiterPwa = document.body.classList.contains('waiter-body');
        if (!isWaiterPwa) {
            const { qty } = cartTotals();
            let confirmMsg = `Masa ${selectedTableNumber} · ${qty} ürün mutfağa gönderilsin mi?`;
            if (activeTableOrder) {
                confirmMsg += `\n\nNot: #${activeTableOrder.order_number} zaten hazırlanıyor. Bu yeni bir sipariş oluşturur.`;
            }
            if (!window.confirm(confirmMsg)) return;
        }

        hideError();
        submitBtn.disabled = true;
        if (cartSubmitBtn) cartSubmitBtn.disabled = true;
        const originalLabel = submitBtn.textContent;
        const originalCartLabel = cartSubmitBtn?.textContent ?? '';
        submitBtn.textContent = 'Gönderiliyor…';
        if (cartSubmitBtn) cartSubmitBtn.textContent = 'Gönderiliyor…';

        const items = [...cart.values()].map((line) => ({
            product_id: line.productId,
            quantity: line.qty,
            options: toApiOptionPayload(line.options),
        }));

        try {
            const res = await fetch(cfg.storeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    table_id: selectedTableId,
                    items,
                }),
            });

            const data = await res.json().catch(() => ({}));

            if (!res.ok || data.success === false) {
                const msg =
                    data.message ||
                    (data.errors ? Object.values(data.errors).flat().join(' ') : null) ||
                    'Sipariş kaydedilemedi.';
                showError(msg);
                return;
            }

            const msg = data.message || `Sipariş #${data.order?.order_number ?? ''} mutfağa iletildi.`;
            showSuccessOverlay(msg);
            if (!document.body.classList.contains('waiter-body')) {
                showAdminToast({
                    title: 'Hazırlanıyor',
                    message: msg,
                    hint: 'Garson siparişi · Canlı panelde görünecek',
                    type: 'success',
                });
            }

            window.dispatchEvent(new CustomEvent('waiter:refresh'));
            closeCartSheet(false);
            setTimeout(() => {
                closeModal();
                resetForm();
            }, 1400);
        } catch {
            showError('Bağlantı hatası. Tekrar deneyin.');
        } finally {
            submitBtn.textContent = originalLabel || 'MUTFAĞA GÖNDER 🚀';
            if (cartSubmitBtn) cartSubmitBtn.textContent = originalCartLabel || 'MUTFAĞA GÖNDER 🚀';
            updateSubmitState();
        }
    }

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-manual-order-trigger]');
        if (!trigger) return;
        event.preventDefault();
        openModal(trigger);
    });

    window.HSP_openManualOrder = (trigger, tableId = null, tableNumber = null) => {
        if (tableId != null) {
            const el = trigger instanceof HTMLElement ? trigger : null;
            if (el) {
                el.dataset.tableId = String(tableId);
                el.dataset.tableNumber = String(tableNumber ?? '');
            }
            openModal(el);
            return;
        }
        openModal(trigger ?? null);
    };

    modal.querySelectorAll('[data-manual-order-close]').forEach((el) => {
        el.addEventListener('click', closeModal);
    });

    optionsSheetEl?.querySelectorAll('[data-manual-order-options-close]').forEach((el) => {
        el.addEventListener('click', () => closeOptionsSheet(true));
    });

    backToTablesBtn?.addEventListener('click', () => {
        activeTableOrder = null;
        renderActiveOrderBanner();
        showStep('table');
    });

    document.addEventListener('keydown', (e) => {
        if (!modal.classList.contains('is-open')) return;
        if (e.key === 'Escape') {
            if (cartSheetEl?.classList.contains('is-open')) {
                closeCartSheet(true);
            } else if (optionsSheetEl?.classList.contains('is-open')) {
                closeOptionsSheet(true);
            } else {
                closeModal();
            }
        }
    });

    optionsConfirmBtn?.addEventListener('click', confirmOptionsSelection);
    optionsBodyEl?.addEventListener('click', handleOptionsBodyClick);
    cartOpenBtn?.addEventListener('click', openCartSheet);
    submitBtn?.addEventListener('click', submitOrder);
    cartSubmitBtn?.addEventListener('click', submitOrder);
    cartClearBtn?.addEventListener('click', clearCart);
    cartLinesEl?.addEventListener('click', handleCartSheetClick);
    cancelActiveBtn?.addEventListener('click', cancelActiveOrder);

    cartSheetEl?.querySelectorAll('[data-manual-order-cart-close]').forEach((el) => {
        el.addEventListener('click', () => closeCartSheet(true));
    });

    bindStaffProductAccordion(accordionEl, {
        onToggleCategory: (id) => {
            void toggleCategory(id);
        },
        onProductInc: (productId) => {
            const product = productsCache.get(productId);
            if (product) void handleProductClick(product);
        },
        onProductDec: (productId) => {
            decrementProductInCart(productId);
        },
    });

    mountOptionsSheet();
    mountCartSheet();
}

function bootManualOrder() {
    initManualOrder();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootManualOrder);
} else {
    bootManualOrder();
}

window.addEventListener('load', bootManualOrder);
