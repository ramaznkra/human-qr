import { showKasaFlashBar } from '../admin-toast.js';
import {
    buildSelectedOptions,
    defaultSelections,
    hasProductOptions,
    needsOptionPicker,
    productOptionsPendingLoad,
    toApiOptionPayload,
    unitPriceFromOptions,
    validateSelections,
} from '../lib/product-options.js';

/**
 * Kasa paneli: masa seçimi, gizlenebilir katalog, doğrudan adisyona ekleme, ödeme.
 */
export function initKasaPanel(liveOps) {
    const root = document.getElementById('kasaPanelRoot');
    const cfg = window.HSP_KASA || window.HSP_MANUAL_ORDER;
    if (!root || !cfg?.selectTableUrl) return;

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const fetchOpts = { credentials: 'same-origin', headers: { Accept: 'application/json' } };

    const tableLabel = document.getElementById('kasaSelectedTableLabel');
    const tableChip = document.getElementById('kasaTableChip');
    const headHint = document.getElementById('kasaHeadHint');
    const catalogMount = document.getElementById('kasaCatalogMount');
    const catalogTemplate = document.getElementById('kasaCatalogTemplate');
    const catalogToggle = document.getElementById('kasaCatalogToggle');
    const centerCol = document.getElementById('kasaCenterCol');
    const feedWrap = document.getElementById('liveOrdersApp');
    const dock = document.getElementById('kasaActionDock');
    const dockTotalWrap = document.getElementById('kasaDockTotal');
    const dockTotalAmount = document.getElementById('kasaDockTotalAmount');
    const tableMap = document.getElementById('liveTableMapGrid');

    let posGrid = null;
    let posLock = null;
    let categoryList = null;
    let productGrid = null;
    let posOrderItems = null;
    let posTotalAmount = null;
    let posOrderStatus = null;
    let posTableLabel = null;
    let posConfirmBtn = null;
    let posConfirmForm = null;
    let errorEl = null;
    let catalogEventsBound = false;

    let selectedTableId = null;
    let selectedTableNumber = null;
    /** Kilitli adisyon — poll/WS bu ID dışına geçemez */
    let activeOrderId = null;
    let selectedCategoryId = null;
    let currency = '₺';
    let showCatalog = false;
    /** @type {{ can_approve?: boolean, can_pay?: boolean, order?: object|null }|null} */
    let tablePayload = null;
    let selectingTable = false;
    let tableSelectGeneration = 0;
    let tableSyncTimer = null;
    /** Kasiyer kendi eklediği ürünlerde bildirim/ses susturma */
    let kasaLocalOpDepth = 0;
    /** Manuel giriş onaylandı (Hazırlanıyor) — Garsona İlet aşamasına geçer */
    let entryConfirmedOrderId = null;
    /** @type {Map<number, object>} */
    const productsCache = new Map();

    const optModal = document.getElementById('kasaProductOptionsModal');
    const optTitle = document.getElementById('kasaProductOptionsTitle');
    const optBasePrice = document.getElementById('kasaProductOptionsBasePrice');
    const optBody = document.getElementById('kasaProductOptionsBody');
    const optError = document.getElementById('kasaProductOptionsError');
    const optConfirm = document.getElementById('kasaProductOptionsConfirm');

    /** @type {object|null} */
    let activeOptionProduct = null;
    /** @type {Record<number, number|number[]>} */
    let modalSelections = {};
    /** @type {((options: object[]) => void)|null} */
    let onOptionsConfirm = null;

    if (optModal && optModal.parentElement !== document.body) {
        document.body.appendChild(optModal);
    }

    const lineModal = document.getElementById('kasaLineItemModal');
    const lineTitle = document.getElementById('kasaLineItemTitle');
    const linePrice = document.getElementById('kasaLineItemPrice');
    const lineQtyEl = document.getElementById('kasaLineItemQty');
    const lineMinus = document.getElementById('kasaLineItemMinus');
    const linePlus = document.getElementById('kasaLineItemPlus');
    const lineRemove = document.getElementById('kasaLineItemRemove');
    const lineError = document.getElementById('kasaLineItemError');
    const feedGrid = document.getElementById('liveOrdersGrid');

    /** @type {{ id: number, name: string, quantity: number, unit_price: number }|null} */
    let activeLineItem = null;
    let lineModalQty = 1;
    let lineItemSaving = false;

    if (lineModal && lineModal.parentElement !== document.body) {
        document.body.appendChild(lineModal);
    }

    function lockActiveOrder(orderId) {
        activeOrderId = orderId != null ? Number(orderId) : null;
        window.HSP_KASA.activeOrderId = activeOrderId;
    }

    function clearTableOrder() {
        lockActiveOrder(null);
        applyServerPayload({
            can_approve: false,
            can_pay: false,
            order: null,
            table: tablePayload?.table ?? null,
        });
    }

    window.HSP_KASA = {
        ...(window.HSP_KASA || {}),
        selectedTableId: null,
        selectedTableNumber: null,
        activeOrderId: null,
        setSelectedTable,
        lockActiveOrder,
        closeCatalog,
        refreshDock,
        applyServerPayload,
        patchTableOrder,
        clearTableOrder,
        approveOrder,
        getOrderTotal: () => Number(tablePayload?.order?.total ?? 0),
        getTablePayload: () => tablePayload,
        formatMoney,
        isLocalOp: () => kasaLocalOpDepth > 0,
        mergeOrderQuiet,
        refreshTableState: () => syncTableFromServer(),
        isEntryConfirmed(orderId) {
            return entryConfirmedOrderId != null && Number(entryConfirmedOrderId) === Number(orderId);
        },
        confirmEntry(orderId) {
            entryConfirmedOrderId = orderId != null ? Number(orderId) : null;
        },
        clearEntryConfirmed() {
            entryConfirmedOrderId = null;
        },
    };

    function beginKasaLocalOp() {
        kasaLocalOpDepth += 1;
        liveOps?.holdKasaRefresh?.(12000);
    }

    function endKasaLocalOp() {
        kasaLocalOpDepth = Math.max(0, kasaLocalOpDepth - 1);
    }

    function mergeOrderQuiet(orderPayload) {
        if (!orderPayload || !tablePayload) return;
        applyServerPayload(
            {
                can_approve: false,
                can_pay: tablePayload.can_pay,
                table: tablePayload.table,
                order: orderPayload,
            },
            { establishLock: true },
        );
    }

    function syncTableFromPayload(data) {
        const table = data?.table;
        const order = data?.order;
        if (!table?.id) return;

        if (!order) {
            const status = String(table.status || 'available');
            const busy = status !== 'available';
            liveOps?.markTableBusy?.(table.id, busy, busy ? status : 'available');
            return;
        }

        liveOps?.markTableBusy?.(table.id, true, table.status || 'occupied');
    }

    function syncCatalogRefs() {
        posGrid = catalogMount?.querySelector('#kasaPosGrid') ?? null;
        posLock = catalogMount?.querySelector('#kasaPosLock') ?? null;
        categoryList = catalogMount?.querySelector('#kasaPosCategoryList') ?? null;
        productGrid = catalogMount?.querySelector('#kasaProductGrid') ?? null;
        posOrderItems = catalogMount?.querySelector('#kasaPosOrderItems') ?? null;
        posTotalAmount = catalogMount?.querySelector('#kasaPosTotalAmount') ?? null;
        posOrderStatus = catalogMount?.querySelector('#kasaPosOrderStatus') ?? null;
        posTableLabel = catalogMount?.querySelector('#kasaPosTableLabel') ?? null;
        posConfirmBtn = catalogMount?.querySelector('#kasaPosConfirmBtn') ?? null;
        posConfirmForm = catalogMount?.querySelector('#kasaPosConfirmForm') ?? null;
        errorEl = catalogMount?.querySelector('#kasaOrderError') ?? null;
    }

    function firstCategoryId() {
        const btn = categoryList?.querySelector('[data-category-id]');
        return btn?.dataset.categoryId ? String(btn.dataset.categoryId) : null;
    }

    function ensureSelectedCategory() {
        if (selectedCategoryId && categoryList?.querySelector(`[data-category-id="${selectedCategoryId}"]`)) {
            return;
        }
        selectedCategoryId = firstCategoryId();
    }

    function markSelectedCategory() {
        categoryList?.querySelectorAll('[data-category-id]').forEach((btn) => {
            const active = String(btn.dataset.categoryId) === String(selectedCategoryId);
            btn.classList.toggle('is-selected', active);
        });
    }

    function mountCatalog() {
        if (!catalogMount || !catalogTemplate) return;
        if (catalogMount.querySelector('#kasaPosGrid')) {
            syncCatalogRefs();
            return;
        }

        catalogMount.appendChild(catalogTemplate.content.cloneNode(true));
        catalogMount.classList.add('is-mounted');
        catalogMount.setAttribute('aria-hidden', 'false');
        syncCatalogRefs();
        ensureSelectedCategory();
        markSelectedCategory();
        setMenuToolsEnabled(!!selectedTableId);
        renderPosOrderSummary();

        if (!catalogEventsBound) {
            bindCatalogEvents();
            catalogEventsBound = true;
        }
    }

    function unmountCatalog() {
        productsCache.clear();
        catalogMount?.replaceChildren();
        catalogMount?.classList.remove('is-mounted');
        catalogMount?.setAttribute('aria-hidden', 'true');
        posGrid = null;
        posLock = null;
        categoryList = null;
        productGrid = null;
        posOrderItems = null;
        posTotalAmount = null;
        posOrderStatus = null;
        posTableLabel = null;
        posConfirmBtn = null;
        posConfirmForm = null;
        errorEl = null;
    }

    function bindCatalogEvents() {
        catalogMount?.addEventListener('click', (event) => {
            const catBtn = event.target.closest('#kasaPosCategoryList [data-category-id]');
            if (catBtn) {
                if (catBtn.disabled) return;
                selectedCategoryId = catBtn.dataset.categoryId || null;
                markSelectedCategory();
                loadProducts();
                return;
            }

            const productBtn = event.target.closest('[data-product-id]');
            if (productBtn) {
                const product = productsCache.get(Number(productBtn.dataset.productId));
                if (product) void handleProductClick(product, productBtn);
                return;
            }

            const lineRow = event.target.closest('[data-kasa-order-item]');
            if (lineRow && posOrderItems?.contains(lineRow)) {
                event.preventDefault();
                openLineItemModal(Number(lineRow.dataset.kasaOrderItem));
            }
        });

        catalogMount?.addEventListener('submit', (event) => {
            if (event.target?.id !== 'kasaPosConfirmForm') return;
            event.preventDefault();
            void handlePosConfirm();
        });
    }

    function posStatusClass(status) {
        const map = {
            idle: 'lo-kasa-pos__status--idle',
            pending_approval: 'lo-kasa-pos__status--pending',
            pending: 'lo-kasa-pos__status--pending',
            preparing: 'lo-kasa-pos__status--preparing',
            ready: 'lo-kasa-pos__status--ready',
            delivered: 'lo-kasa-pos__status--delivered',
        };
        return map[status] || 'lo-kasa-pos__status--idle';
    }

    function renderPosOrderSummary() {
        if (!posOrderItems) return;

        if (posTableLabel) {
            posTableLabel.textContent = selectedTableNumber ? String(selectedTableNumber) : '—';
        }

        posLock?.classList.toggle('is-hidden', !!selectedTableId);

        const order = tablePayload?.order;
        const items = order?.items || [];
        const total = Number(order?.total ?? 0);

        if (!selectedTableId) {
            posOrderItems.innerHTML =
                '<li class="lo-kasa-pos__empty">Soldan masa seçerek siparişe başlayın</li>';
            if (posTotalAmount) posTotalAmount.textContent = formatMoney(0);
            if (posOrderStatus) {
                posOrderStatus.textContent = 'Boş';
                posOrderStatus.className = 'lo-kasa-pos__status lo-kasa-pos__status--idle';
            }
            if (posConfirmBtn) posConfirmBtn.disabled = true;
            return;
        }

        if (!items.length) {
            posOrderItems.innerHTML =
                '<li class="lo-kasa-pos__empty">Ürün eklemek için ortadan seçin</li>';
        } else {
            posOrderItems.innerHTML = items
                .map((item) => {
                    const lineTotal = Number(item.unit_price ?? 0) * Number(item.quantity ?? 1);
                    return `
                <li class="lo-kasa-pos-row lo-kasa-adisyon__row lo-kasa-adisyon__row--clickable lo-kasa-touch" data-kasa-order-item="${item.id}" role="button" tabindex="0" title="Düzenlemek için dokunun">
                    <div class="lo-kasa-pos-row__main">
                        <span class="lo-kasa-pos-row__qty">${item.quantity}×</span>
                        <span class="lo-kasa-pos-row__name">${escapeHtml(item.name)}</span>
                    </div>
                    <span class="lo-kasa-pos-row__price">${formatMoney(lineTotal)}</span>
                </li>`;
                })
                .join('');
        }

        if (posTotalAmount) posTotalAmount.textContent = formatMoney(total);

        if (posOrderStatus) {
            if (!order) {
                posOrderStatus.textContent = 'Yeni adisyon';
                posOrderStatus.className = 'lo-kasa-pos__status lo-kasa-pos__status--idle';
            } else {
                posOrderStatus.textContent = order.status_label || order.status;
                posOrderStatus.className = `lo-kasa-pos__status ${posStatusClass(order.status)}`;
            }
        }

        const canConfirm = !!tablePayload?.can_approve || items.length > 0;
        if (posConfirmBtn) {
            posConfirmBtn.disabled = !canConfirm;
            posConfirmBtn.classList.toggle('lo-kasa-pos__confirm--approve', !!tablePayload?.can_approve);
            posConfirmBtn.classList.toggle('lo-kasa-pos__confirm--submit', !tablePayload?.can_approve);
            posConfirmBtn.textContent = tablePayload?.can_approve
                ? 'SİPARİŞİ ONAYLA'
                : 'SİPARİŞİ ONAYLA';
        }
    }

    async function handlePosConfirm() {
        if (!selectedTableId || posConfirmBtn?.disabled) return;

        hideError();

        if (tablePayload?.can_approve) {
            await approveOrder();
            renderPosOrderSummary();
            return;
        }

        const order = tablePayload?.order;
        if (!order?.items?.length) return;

        if (isKasaManualOrder(order)) {
            entryConfirmedOrderId = order.id != null ? Number(order.id) : null;
        }

        closeCatalog();
        showKasaFlashBar('Sipariş onaylandı');
        refreshDock();
    }

    function formatMoney(value) {
        const n = Number(value) || 0;
        return `${Math.round(n).toLocaleString('tr-TR')} ${currency}`;
    }

    function setMenuToolsEnabled(enabled) {
        posLock?.classList.toggle('is-hidden', enabled);
        categoryList?.querySelectorAll('[data-category-id]').forEach((btn) => {
            btn.disabled = !enabled;
        });
        if (catalogToggle) catalogToggle.disabled = !enabled;
    }

    function setShowCatalog(open) {
        showCatalog = open && !!selectedTableId;

        catalogToggle?.classList.toggle('is-active', showCatalog);
        catalogToggle?.setAttribute('aria-expanded', showCatalog ? 'true' : 'false');

        if (catalogToggle) {
            catalogToggle.textContent = showCatalog ? '− Siparişi Kapat' : '+ Manuel Sipariş';
        }

        centerCol?.classList.toggle('is-pos-mode', showCatalog);
        feedWrap?.classList.toggle('hidden', showCatalog);
        feedWrap?.toggleAttribute('inert', showCatalog);

        if (showCatalog) {
            mountCatalog();
            ensureSelectedCategory();
            markSelectedCategory();
            loadProducts();
            renderPosOrderSummary();
        } else {
            unmountCatalog();
        }

        refreshDock();
    }

    async function toggleCatalog() {
        if (!selectedTableId) return;

        setShowCatalog(!showCatalog);
    }

    function clearTableSelection() {
        ++tableSelectGeneration;
        selectingTable = false;
        clearTimeout(tableSyncTimer);
        tableSyncTimer = null;
        setShowCatalog(false);
        entryConfirmedOrderId = null;
        lockActiveOrder(null);
        tablePayload = null;
        setSelectedTable(null, null);
        liveOps?.onTableSwitch?.();
        liveOps?.onTableSelected?.();
    }

    function setSelectedTable(id, number) {
        selectedTableId = id;
        selectedTableNumber = number;
        window.HSP_KASA.selectedTableId = id;
        window.HSP_KASA.selectedTableNumber = number;

        if (tableLabel) {
            tableLabel.textContent = id ? `Masa ${number}` : 'Masa seçin';
        }

        tableChip?.classList.toggle('is-selected', !!id);
        headHint?.classList.toggle('hidden', !!id);

        window.HSP_KASA?.syncOrderChrome?.(tablePayload?.order ?? null, number);
        renderPosOrderSummary();

        setMenuToolsEnabled(!!id);

        tableMap?.querySelectorAll('[data-table-id]').forEach((el) => {
            el.classList.toggle('lo-table--selected', Number(el.dataset.tableId) === id);
        });

        if (id) {
            setShowCatalog(false);
        } else {
            setShowCatalog(false);
            tablePayload = null;
            entryConfirmedOrderId = null;
            lockActiveOrder(null);
        }

        refreshDock();
        liveOps?.onTableSelected?.();
    }

    function tablePayloadVisualKey(payload) {
        const order = payload?.order;
        if (!order) return 'empty';
        return JSON.stringify([
            payload.can_approve,
            payload.can_pay,
            order.id,
            order.status,
            order.payment_method,
            order.total,
            order.items?.length ?? 0,
        ]);
    }

    function applyServerPayload(data, options = {}) {
        const { establishLock = false } = options;
        let payload = data;

        if (establishLock) {
            lockActiveOrder(payload.order?.id ?? null);
        } else if (activeOrderId && payload.order?.id && Number(payload.order.id) !== activeOrderId) {
            if (tablePayload?.order?.id === activeOrderId) {
                payload = {
                    ...payload,
                    order: tablePayload.order,
                    can_approve: tablePayload.can_approve,
                    can_pay: tablePayload.can_pay,
                };
            } else {
                lockActiveOrder(payload.order.id);
            }
        } else if (!activeOrderId && payload.order?.id) {
            lockActiveOrder(payload.order.id);
        } else if (!payload.order) {
            lockActiveOrder(null);
        }

        if (
            payload.order &&
            tablePayload?.order &&
            Number(payload.order.id) === Number(tablePayload.order.id)
        ) {
            const incomingItems = payload.order.items?.length ?? 0;
            const currentItems = tablePayload.order.items?.length ?? 0;
            if (incomingItems < currentItems) {
                payload = {
                    ...payload,
                    order: {
                        ...payload.order,
                        items: tablePayload.order.items,
                        total: tablePayload.order.total ?? payload.order.total,
                    },
                };
            }
        }

        const next = {
            can_approve: payload.can_approve,
            can_pay: payload.can_pay,
            order: payload.order ?? null,
            table: payload.table ?? tablePayload?.table ?? null,
        };
        const prevKey = tablePayloadVisualKey(tablePayload);
        const nextKey = tablePayloadVisualKey(next);
        const prevStatus = tablePayload?.order?.status;

        tablePayload = next;
        if (next.order?.status && next.order.status !== 'preparing') {
            entryConfirmedOrderId = null;
        }
        const closingCatalog =
            next.order?.status === 'delivered' && showCatalog && prevStatus !== 'delivered';
        if (closingCatalog) {
            setShowCatalog(false);
        } else {
            refreshDock();
        }

        if (prevKey !== nextKey) {
            liveOps?.onTablePayloadUpdated?.(tablePayload);
        }

        renderPosOrderSummary();

        if (next.table?.id) {
            syncTableFromPayload({ table: next.table, order: next.order });
        }
    }

    function patchTableOrder(patch) {
        if (!tablePayload?.order || !patch) return;

        applyServerPayload({
            can_approve: tablePayload.can_approve,
            can_pay: tablePayload.can_pay,
            order: {
                ...tablePayload.order,
                ...patch,
            },
        });
    }

    function hideLineError() {
        lineError?.classList.add('hidden');
    }

    function showLineError(msg) {
        if (!lineError) return;
        lineError.textContent = msg;
        lineError.classList.remove('hidden');
    }

    function renderLineModalQty() {
        if (lineQtyEl) lineQtyEl.textContent = String(lineModalQty);
        if (lineMinus) lineMinus.disabled = lineItemSaving || lineModalQty <= 1;
        if (linePlus) linePlus.disabled = lineItemSaving || lineModalQty >= 99;
        if (lineRemove) lineRemove.disabled = lineItemSaving;
    }

    function closeLineItemModal() {
        if (!lineModal) return;
        lineModal.classList.remove('is-open');
        lineModal.setAttribute('aria-hidden', 'true');
        lineModal.setAttribute('inert', '');
        activeLineItem = null;
        lineModalQty = 1;
        hideLineError();
        liveOps?.schedulePaint?.(true);
        refreshDock();
    }

    function openLineItemModal(itemId) {
        if (!lineModal || !selectedTableId || lineItemSaving) return;

        const item = tablePayload?.order?.items?.find((i) => Number(i.id) === Number(itemId));
        if (!item) return;

        activeLineItem = item;
        lineModalQty = Number(item.quantity) || 1;

        if (lineTitle) lineTitle.textContent = item.name;
        if (linePrice) {
            linePrice.textContent = `Birim · ${formatMoney(item.unit_price)} · Satır ${formatMoney(Number(item.unit_price) * lineModalQty)}`;
        }

        hideLineError();
        renderLineModalQty();

        lineModal.classList.add('is-open');
        lineModal.removeAttribute('inert');
        lineModal.setAttribute('aria-hidden', 'false');
        lineMinus?.focus();
    }

    async function saveLineItemUpdate({ quantity = null, remove = false } = {}) {
        if (!activeLineItem || !selectedTableId || !cfg.updateItemUrl || lineItemSaving) return;

        lineItemSaving = true;
        hideLineError();
        beginKasaLocalOp();
        renderLineModalQty();

        try {
            const body = {
                table_id: selectedTableId,
                item_id: activeLineItem.id,
                remove,
            };

            if (activeOrderId) {
                body.order_id = activeOrderId;
            }

            if (!remove && quantity != null) {
                body.quantity = quantity;
            }

            const data = await kasaPost(cfg.updateItemUrl, body);
            applyServerPayload(data, { establishLock: true });
            syncTableFromPayload(data);
            liveOps?.schedulePaint?.(true);
            refreshDock();

            if (remove || !data.order?.items?.some((i) => Number(i.id) === Number(activeLineItem.id))) {
                closeLineItemModal();
                return;
            }

            const refreshed = data.order.items.find((i) => Number(i.id) === Number(activeLineItem.id));
            if (refreshed) {
                activeLineItem = refreshed;
                lineModalQty = Number(refreshed.quantity) || 1;
                if (linePrice) {
                    linePrice.textContent = `Birim · ${formatMoney(refreshed.unit_price)} · Satır ${formatMoney(Number(refreshed.unit_price) * lineModalQty)}`;
                }
            }
            renderLineModalQty();
        } catch (err) {
            showLineError(err.message || 'Güncellenemedi.');
        } finally {
            lineItemSaving = false;
            renderLineModalQty();
            endKasaLocalOp();
        }
    }

    function changeLineQty(delta) {
        if (!activeLineItem || lineItemSaving) return;
        const next = Math.max(1, Math.min(99, lineModalQty + delta));
        if (next === lineModalQty) return;
        lineModalQty = next;
        renderLineModalQty();
        if (linePrice && activeLineItem) {
            linePrice.textContent = `Birim · ${formatMoney(activeLineItem.unit_price)} · Satır ${formatMoney(Number(activeLineItem.unit_price) * lineModalQty)}`;
        }
        void saveLineItemUpdate({ quantity: next });
    }

    feedGrid?.addEventListener('click', (event) => {
        const row = event.target.closest('[data-kasa-order-item]');
        if (!row || !selectedTableId) return;
        event.preventDefault();
        openLineItemModal(Number(row.dataset.kasaOrderItem));
    });

    feedGrid?.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== ' ') return;
        const row = event.target.closest('[data-kasa-order-item]');
        if (!row) return;
        event.preventDefault();
        openLineItemModal(Number(row.dataset.kasaOrderItem));
    });

    lineModal?.querySelectorAll('[data-kasa-line-close]').forEach((el) => {
        el.addEventListener('click', closeLineItemModal);
    });

    lineMinus?.addEventListener('click', () => changeLineQty(-1));
    linePlus?.addEventListener('click', () => changeLineQty(1));
    lineRemove?.addEventListener('click', () => {
        if (!activeLineItem || lineItemSaving) return;
        void saveLineItemUpdate({ remove: true });
    });

    function closeCatalog() {
        setShowCatalog(false);
    }

    function isKasaManualOrder(order = tablePayload?.order) {
        return String(order?.source ?? '') === 'kasa';
    }

    function canPayFromPayload(payload = tablePayload) {
        const order = payload?.order;
        const orderTotal = Number(order?.total ?? 0);
        const tableStatus = payload?.table?.status ?? tablePayload?.table?.status;

        if (!selectedTableId || !order || orderTotal <= 0 || order.payment_method) {
            return false;
        }

        if (tableStatus === 'payment_processing') {
            return false;
        }

        if (isKasaManualOrder(order)) {
            return (
                !showCatalog &&
                ['preparing', 'ready', 'delivered'].includes(String(order.status))
            );
        }

        if (order.status === 'preparing') {
            return false;
        }

        return true;
    }

    function refreshDock(payload = tablePayload) {
        if (!dock) return;

        const hasTable = !!selectedTableId;
        const canPay = canPayFromPayload(payload);
        const orderTotal = Number(payload?.order?.total ?? 0);
        const tableNum = selectedTableNumber;
        const calls = liveOps?.getCallsForTable?.(tableNum) ?? [];
        const waiterCall = calls.find((c) => !liveOps?.isBillCall?.(c));
        const billCall = calls.find((c) => liveOps?.isBillCall?.(c));

        window.HSP_KASA?.syncPayPanel?.(payload?.order ?? null);
        window.HSP_KASA?.syncOrderChrome?.(payload?.order ?? null, selectedTableNumber);
        renderPosOrderSummary();

        if (dockTotalWrap && dockTotalAmount) {
            const showTotal = hasTable && orderTotal > 0;
            dockTotalWrap.toggleAttribute('hidden', !showTotal);
            dockTotalAmount.textContent = formatMoney(orderTotal);
        }

        dock.querySelectorAll('[data-kasa-action]').forEach((btn) => {
            const action = btn.dataset.kasaAction;
            const textEl = btn.querySelector('.lo-kasa-dock__btn-text');

            if (action === 'waiter') {
                btn.disabled = !hasTable || (!waiterCall && !billCall);
            } else if (action === 'cash') {
                btn.disabled = !hasTable || !canPay;
                btn.classList.toggle('lo-kasa-pay-btn--ready', canPay);
                if (textEl) {
                    textEl.textContent = canPay
                        ? `Nakit Öde - ${formatMoney(orderTotal)}`
                        : 'Nakit Öde';
                }
            } else if (action === 'card') {
                btn.disabled = !hasTable || !canPay;
                btn.classList.toggle('lo-kasa-pay-btn--ready', canPay);
                if (textEl) {
                    textEl.textContent = canPay
                        ? `Manuel Kart Ödemesi - ${formatMoney(orderTotal)}`
                        : 'Manuel Kart Ödemesi';
                }
            } else if (action === 'pos') {
                btn.disabled = !hasTable || !canPay;
                btn.classList.toggle('lo-kasa-pay-btn--ready', canPay);
                if (textEl) {
                    textEl.textContent = canPay
                        ? `POS ile Öde - ${formatMoney(orderTotal)}`
                        : 'POS ile Öde';
                }
            }
        });
    }

    async function kasaPost(url, body) {
        const res = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf(),
                Accept: 'application/json',
            },
            body: JSON.stringify(body),
        });
        const data = await res.json().catch(() => ({}));

        if (!res.ok || data.success === false) {
            throw new Error(data.message || 'İşlem başarısız.');
        }

        return data;
    }

    async function selectTableApi(id, number) {
        if (selectingTable) return;
        selectingTable = true;
        const selectGen = ++tableSelectGeneration;
        beginKasaLocalOp();

        lockActiveOrder(null);
        entryConfirmedOrderId = null;
        tablePayload = null;
        setSelectedTable(id, number);
        liveOps?.onTableSwitch?.();

        try {
            const data = await kasaPost(cfg.selectTableUrl, { table_id: id });
            if (selectGen !== tableSelectGeneration) return;
            applyServerPayload(data, { establishLock: true });
        } catch (err) {
            if (selectGen !== tableSelectGeneration) return;
            showError(err.message || 'Masa seçilemedi.');
            liveOps?.onTableSwitch?.();
        } finally {
            selectingTable = false;
            endKasaLocalOp();
        }
    }

    function formatOptionPrice(price) {
        const n = Number(price) || 0;
        if (n <= 0) return '';
        return `+${Math.round(n).toLocaleString('tr-TR')} ${currency}`;
    }

    function closeOptionsModal() {
        if (!optModal) return;

        const hadFocus = optModal.contains(document.activeElement);
        if (hadFocus) {
            document.activeElement?.blur();
        }

        optModal.classList.remove('is-open');
        optModal.setAttribute('aria-hidden', 'true');
        optModal.setAttribute('inert', '');
        activeOptionProduct = null;
        modalSelections = {};
        onOptionsConfirm = null;
        if (optBody) optBody.innerHTML = '';
        optError?.classList.add('hidden');

        if (hadFocus) {
            catalogToggle?.focus?.();
        }
    }

    function renderOptionsModalBody() {
        if (!activeOptionProduct || !optBody) return;

        const groups = activeOptionProduct.option_groups || [];

        if (!groups.length) {
            optBody.innerHTML =
                '<p class="lo-kasa-opt-modal__empty">Bu ürün için seçenek bulunamadı. Sayfayı yenileyip tekrar deneyin.</p>';
            return;
        }

        optBody.innerHTML = groups
            .map((group) => {
                const options = Array.isArray(group.options) ? group.options : [];
                const requiredHint = group.required
                    ? '<span class="product-option-group__hint">*</span>'
                    : '';

                if (!options.length) {
                    return `
                <section class="product-option-group lo-kasa-opt-group" data-group-id="${group.id}">
                    <h3 class="product-option-group__title">${escapeHtml(group.name)}${requiredHint}</h3>
                    <p class="lo-kasa-opt-modal__empty">Seçenek listesi yüklenemedi.</p>
                </section>`;
                }

                const choices = options
                    .map((option) => {
                        const inputType = group.type === 'single' ? 'radio' : 'checkbox';
                        const inputName = `kasa-option-group-${group.id}`;
                        const isChecked =
                            group.type === 'single'
                                ? modalSelections[group.id] === option.id
                                : (modalSelections[group.id] || []).includes(option.id);
                        const priceLabel = formatOptionPrice(option.price);

                        return `
                        <label class="product-option-choice lo-kasa-opt-choice ${isChecked ? 'is-selected' : ''}">
                            <span class="product-option-choice__left">
                                <input
                                    type="${inputType}"
                                    name="${escapeHtml(inputName)}"
                                    data-group-id="${group.id}"
                                    data-option-id="${option.id}"
                                    data-group-type="${group.type}"
                                    ${isChecked ? 'checked' : ''}
                                >
                                <span class="product-option-choice__label">${escapeHtml(option.name)}</span>
                            </span>
                            ${priceLabel ? `<span class="product-option-choice__price">${escapeHtml(priceLabel)}</span>` : ''}
                        </label>`;
                    })
                    .join('');

                return `
                <section class="product-option-group lo-kasa-opt-group" data-group-id="${group.id}">
                    <h3 class="product-option-group__title">${escapeHtml(group.name)}${requiredHint}</h3>
                    <div class="product-option-list">${choices}</div>
                </section>`;
            })
            .join('');

        optBody.querySelectorAll('input[data-group-id]').forEach((input) => {
            input.addEventListener('change', onOptionsModalChange);
        });
    }

    function updateOptionsModalPrice() {
        if (!activeOptionProduct || !optConfirm) return;

        const groups = activeOptionProduct.option_groups || [];
        const selected = buildSelectedOptions(groups, modalSelections);
        const total = unitPriceFromOptions(activeOptionProduct.price, selected);
        const valid = validateSelections(groups, modalSelections);

        optConfirm.textContent = `Adisyona Ekle · ${formatMoney(total)}`;
        if (valid) {
            optConfirm.disabled = false;
            optConfirm.removeAttribute('disabled');
        } else {
            optConfirm.disabled = true;
            optConfirm.setAttribute('disabled', 'disabled');
        }

        if (optError) {
            optError.classList.toggle('hidden', valid);
            if (!valid) optError.textContent = 'Zorunlu seçenekleri işaretleyin.';
        }
    }

    function onOptionsModalChange(event) {
        const input = event.target;
        const groupId = Number(input.dataset.groupId);
        const optionId = Number(input.dataset.optionId);
        const groupType = input.dataset.groupType;

        if (groupType === 'single') {
            modalSelections[groupId] = optionId;
        } else {
            const current = new Set(modalSelections[groupId] || []);
            if (input.checked) current.add(optionId);
            else current.delete(optionId);
            modalSelections[groupId] = Array.from(current);
        }

        optBody?.querySelectorAll(`input[data-group-id="${groupId}"]`).forEach((el) => {
            el.closest('.product-option-choice')?.classList.toggle('is-selected', el.checked);
        });

        updateOptionsModalPrice();
    }

    function openOptionsModal(product, onConfirm) {
        if (!optModal) {
            showError('Seçenek penceresi yüklenemedi. Sayfayı yenileyin.');
            return false;
        }

        if (!hasProductOptions(product)) {
            showError('Ürün seçenekleri yüklenemedi. Tekrar deneyin.');
            return false;
        }

        activeOptionProduct = product;
        onOptionsConfirm = onConfirm;
        modalSelections = defaultSelections(product.option_groups);

        if (optTitle) optTitle.textContent = product.name;
        if (optBasePrice) {
            optBasePrice.textContent = `Taban fiyat · ${formatMoney(product.price)}`;
        }

        renderOptionsModalBody();
        updateOptionsModalPrice();

        optModal.classList.add('is-open');
        optModal.removeAttribute('inert');
        optModal.setAttribute('aria-hidden', 'false');

        requestAnimationFrame(() => {
            const firstInput = optBody?.querySelector('input[data-group-id]:not([disabled])');
            if (firstInput instanceof HTMLElement) {
                firstInput.focus();
            } else {
                optConfirm?.focus();
            }
        });
        return true;
    }

    async function fetchProductWithOptions(productId) {
        const url = `${cfg.productsUrl}?product_id=${encodeURIComponent(productId)}`;
        const res = await fetch(url, fetchOpts);
        const data = await res.json().catch(() => ({}));

        if (!res.ok || !Array.isArray(data.products) || !data.products.length) {
            throw new Error(data.message || 'Ürün seçenekleri yüklenemedi.');
        }

        return data.products[0];
    }

    async function ensureProductWithOptions(product) {
        if (hasProductOptions(product)) {
            return product;
        }

        if (!productOptionsPendingLoad(product)) {
            return product;
        }

        const fresh = await fetchProductWithOptions(product.id);
        productsCache.set(fresh.id, fresh);
        return fresh;
    }

    async function handleProductClick(product, btn) {
        btn?.setAttribute('disabled', 'disabled');

        try {
            const resolved = await ensureProductWithOptions(product);

            if (hasProductOptions(resolved)) {
                openOptionsModal(resolved, (apiOptions) => addItemApi(resolved.id, btn, apiOptions));
                return;
            }

            await addItemApi(resolved.id, btn, []);
        } catch (err) {
            showError(err.message || 'Ürün açılamadı.');
        } finally {
            btn?.removeAttribute('disabled');
        }
    }

    async function loadBootstrap() {
        try {
            const res = await fetch(cfg.bootstrapUrl, fetchOpts);
            const data = await res.json().catch(() => ({}));
            if (res.ok) {
                currency = data.currency || '₺';
            }
        } catch {
            /* sessiz */
        }
    }

    function renderProducts(products) {
        if (!productGrid || !selectedTableId) return;

        productsCache.clear();
        products.forEach((p) => productsCache.set(p.id, p));

        if (!products.length) {
            productGrid.innerHTML =
                '<p class="lo-kasa-pos__product-empty">Bu kategoride ürün bulunamadı</p>';
            return;
        }

        productGrid.innerHTML = products
            .map((p) => {
                const optsBadge = needsOptionPicker(p)
                    ? '<span class="lo-kasa-pos-tile__opts" title="Seçenekli ürün">◆</span>'
                    : '';

                return `
            <button type="button" class="lo-kasa-pos-tile lo-kasa-touch${needsOptionPicker(p) ? ' lo-kasa-pos-tile--options' : ''}" data-product-id="${p.id}">
                <span class="lo-kasa-pos-tile__name">${escapeHtml(p.name)}${optsBadge}</span>
                <span class="lo-kasa-pos-tile__price">${formatMoney(p.price)}</span>
            </button>`;
            })
            .join('');
    }

    async function loadProducts() {
        if (!selectedTableId || !selectedCategoryId) {
            if (productGrid) {
                productGrid.innerHTML =
                    '<p class="lo-kasa-pos__product-empty">Kategori seçin</p>';
            }
            return;
        }

        try {
            const params = new URLSearchParams({ category_id: selectedCategoryId });
            const url = `${cfg.productsUrl}?${params.toString()}`;
            const res = await fetch(url, fetchOpts);
            const data = await res.json().catch(() => ({}));

            if (!res.ok) {
                renderProducts([]);
                return;
            }

            renderProducts(data.products || []);
        } catch {
            if (productGrid) {
                productGrid.innerHTML =
                    '<p class="lo-kasa-pos__product-empty lo-kasa-pos__product-empty--error">Ürünler yüklenemedi</p>';
            }
        }
    }

    async function addItemApi(productId, btn, options = []) {
        if (!selectedTableId) return;

        btn?.setAttribute('disabled', 'disabled');
        hideError();
        beginKasaLocalOp();

        try {
            const payload = {
                table_id: selectedTableId,
                product_id: productId,
            };

            if (activeOrderId) {
                payload.order_id = activeOrderId;
            }

            if (options.length) {
                payload.options = options;
            }

            const data = await kasaPost(cfg.addItemUrl, payload);
            applyServerPayload(data, { establishLock: true });
            syncTableFromPayload(data);
            refreshDock();
            renderPosOrderSummary();
            closeOptionsModal();
            liveOps?.schedulePaint?.(true);
        } catch (err) {
            const message = err.message || 'Ürün eklenemedi.';

            if (/seçimi zorunlu|seçenek|zorunlu/i.test(message) && productsCache.has(productId)) {
                try {
                    const resolved = await fetchProductWithOptions(productId);
                    productsCache.set(resolved.id, resolved);
                    if (openOptionsModal(resolved, (apiOptions) => addItemApi(productId, btn, apiOptions))) {
                        if (optError) {
                            optError.textContent = message;
                            optError.classList.remove('hidden');
                        }
                        return;
                    }
                } catch {
                    /* fall through */
                }
            }

            if (activeOptionProduct) {
                if (optError) {
                    optError.textContent = message;
                    optError.classList.remove('hidden');
                }
            } else {
                showError(message);
            }
        } finally {
            btn?.removeAttribute('disabled');
            optConfirm?.removeAttribute('disabled');
            endKasaLocalOp();
            if (activeOptionProduct) {
                updateOptionsModalPrice();
            }
        }
    }

    function hideError() {
        errorEl?.classList.add('hidden');
    }

    function showError(msg) {
        if (!errorEl) return;
        errorEl.textContent = msg;
        errorEl.classList.remove('hidden');
    }

    async function notifyWaiterForTable() {
        const order = tablePayload?.order;
        if (!selectedTableId || !order?.id || order.status !== 'preparing' || !cfg.notifyWaiterUrl) {
            return;
        }

        hideError();
        beginKasaLocalOp();
        liveOps?.holdKasaRefresh?.(8000);

        const btn = root.querySelector('[data-kasa-notify-waiter]');
        btn?.setAttribute('disabled', 'disabled');

        const previousPayload = tablePayload;

        patchTableOrder({
            status: 'ready',
            status_label: 'Masada',
        });
        closeCatalog();

        try {
            const body = { table_id: selectedTableId };
            if (activeOrderId) {
                body.order_id = activeOrderId;
            }

            const data = await kasaPost(cfg.notifyWaiterUrl, body);
            applyServerPayload(data, { establishLock: true });
            syncTableFromPayload(data);
            showKasaFlashBar(data.message || 'Garsona bildirildi');
            liveOps?.schedulePaint?.(true);
            refreshDock();
        } catch (err) {
            if (previousPayload) {
                applyServerPayload(previousPayload);
            }
            showError(err.message || 'Garsona bildirilemedi.');
            liveOps?.schedulePoll?.(true);
        } finally {
            btn?.removeAttribute('disabled');
            endKasaLocalOp();
        }
    }

    async function approveOrder() {
        if (!selectedTableId || !tablePayload?.can_approve) return;

        hideError();
        liveOps?.holdKasaRefresh?.();

        const previousPayload = tablePayload;
        if (previousPayload?.order) {
            applyServerPayload({
                can_approve: false,
                can_pay: previousPayload.can_pay,
                order: {
                    ...previousPayload.order,
                    status: 'preparing',
                    status_label: 'Hazırlanıyor',
                },
            });
        }

        const btn = document.querySelector('[data-kasa-approve-order]');
        btn?.setAttribute('disabled', 'disabled');

        try {
            const data = await kasaPost(cfg.approveOrderUrl, { table_id: selectedTableId });
            applyServerPayload(data);
        } catch (err) {
            if (previousPayload) {
                applyServerPayload(previousPayload);
            }
            showError(err.message || 'Sipariş onaylanamadı.');
            liveOps?.schedulePoll?.(true);
        } finally {
            btn?.removeAttribute('disabled');
        }
    }

    async function handleDockAction(action) {
        if (!selectedTableId) return;

        if (action === 'waiter') {
            await handleWaiterDock();
            return;
        }

        if (action === 'cash') {
            await payWithCash();
            return;
        }

        if (action === 'card') {
            await payWithManualCard();
            return;
        }

        if (action === 'pos') {
            await payWithPos();
        }
    }

    async function handleWaiterDock() {
        if (!selectedTableNumber || !liveOps) return;

        const tableNum = selectedTableNumber;
        const calls = liveOps.getCallsForTable(tableNum);
        const waiterCall = calls.find((c) => !liveOps.isBillCall(c));
        const billCall = calls.find((c) => liveOps.isBillCall(c));

        if (billCall && !billCall.forwarded_to_waiter) {
            await liveOps.forwardCall(billCall.id);
            return;
        }
        if (waiterCall) {
            await liveOps.resolveCall(waiterCall.id);
            return;
        }
        /* sessiz: aktif çağrı yoksa veya zaten iletildiyse UI yeterli */
    }

    async function payWithCash() {
        if (!selectedTableId || !canPayFromPayload()) return;

        const btn = dock?.querySelector('[data-kasa-action="cash"]');
        btn?.setAttribute('disabled', 'disabled');

        try {
            await kasaPost(cfg.payCashUrl, {
                table_id: selectedTableId,
                idempotency_key: paymentIdempotencyKey('cash'),
            });
            showKasaFlashBar('Ödeme Başarılı');
            clearTableSelection();
            liveOps?.schedulePoll?.(true);
        } catch (err) {
            showKasaFlashBar(err.message || 'Ödeme Başarısız', 'error');
            refreshDock();
        }
    }

    async function payWithManualCard() {
        if (!selectedTableId || !canPayFromPayload()) return;

        const btn = dock?.querySelector('[data-kasa-action="card"]');
        btn?.setAttribute('disabled', 'disabled');

        try {
            await kasaPost(cfg.payCardUrl, {
                table_id: selectedTableId,
                idempotency_key: paymentIdempotencyKey('manual_card'),
            });
            showKasaFlashBar('Manuel Kart Ödemesi Başarılı');
            clearTableSelection();
            liveOps?.schedulePoll?.(true);
        } catch (err) {
            showKasaFlashBar(err.message || 'Manuel Kart Ödemesi Başarısız', 'error');
            refreshDock();
        }
    }

    async function payWithPos() {
        if (!selectedTableId || !canPayFromPayload()) return;

        const btn = dock?.querySelector('[data-kasa-action="pos"]');
        btn?.setAttribute('disabled', 'disabled');

        try {
            const data = await kasaPost(cfg.payPosUrl, {
                table_id: selectedTableId,
                idempotency_key: paymentIdempotencyKey('pos'),
            });
            showKasaFlashBar(data.message || 'POS işlemi başlatıldı', data.success === false ? 'error' : 'success');
            if (data.success !== false) {
                clearTableSelection();
            }
            liveOps?.schedulePoll?.(true);
        } catch (err) {
            showKasaFlashBar(err.message || 'Ödeme Başarısız', 'error');
            refreshDock();
        }
    }

    function paymentIdempotencyKey(kind) {
        const orderId = tablePayload?.order?.id ?? activeOrderId ?? 'none';
        const total = tablePayload?.order?.total ?? '0';

        return `kasa:${kind}:${orderId}:${total}`;
    }

    tableMap?.addEventListener('click', (event) => {
        const tile = event.target.closest('[data-table-id]');
        if (!tile || tile.dataset.tableActive !== '1') return;

        const id = Number(tile.dataset.tableId);
        const number = Number(tile.dataset.tableNumber);

        if (selectedTableId === id) {
            clearTableSelection();
            return;
        }

        selectTableApi(id, number);
    });

    catalogToggle?.addEventListener('click', (event) => {
        event.preventDefault();
        toggleCatalog();
    });

    optModal?.querySelectorAll('[data-kasa-opt-close]').forEach((el) => {
        el.addEventListener('click', closeOptionsModal);
    });

    optConfirm?.addEventListener('click', () => {
        if (!activeOptionProduct || optConfirm.disabled) return;

        const selected = buildSelectedOptions(activeOptionProduct.option_groups, modalSelections);
        const apiOptions = toApiOptionPayload(selected);
        optConfirm.setAttribute('disabled', 'disabled');
        onOptionsConfirm?.(apiOptions);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && optModal?.classList.contains('is-open')) {
            closeOptionsModal();
            return;
        }
        if (event.key === 'Escape' && lineModal?.classList.contains('is-open')) {
            closeLineItemModal();
        }
    });

    dock?.querySelectorAll('[data-kasa-action]').forEach((btn) => {
        btn.addEventListener('click', (event) => {
            event.preventDefault();
            if (!btn.disabled) handleDockAction(btn.dataset.kasaAction);
        });
    });

    root.addEventListener('click', (event) => {
        if (event.target.closest('[data-kasa-notify-waiter]')) {
            event.preventDefault();
            void notifyWaiterForTable();
            return;
        }
        if (event.target.closest('[data-kasa-approve-order]')) {
            event.preventDefault();
            approveOrder();
        }
    });

    document.querySelectorAll('[data-lo-tab-jump]').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.querySelector(`.lo-tab[data-tab="${btn.dataset.loTabJump}"]`)?.click();
        });
    });

    loadBootstrap();
    setMenuToolsEnabled(false);
    setShowCatalog(false);

    liveOps?.onDataRefresh?.(() => {
        refreshDock();
        scheduleTableSync();
    });

    function scheduleTableSync() {
        if (!selectedTableId || liveOps?.isKasaRefreshPaused?.()) return;
        clearTimeout(tableSyncTimer);
        tableSyncTimer = setTimeout(() => {
            void syncTableFromServer();
        }, 2800);
    }

    async function syncTableFromServer() {
        if (!selectedTableId || selectingTable) return;

        const stateUrl = cfg.tableStateUrl || cfg.selectTableUrl;
        const isGet = stateUrl === cfg.tableStateUrl;

        try {
            let data;

            if (isGet) {
                const params = new URLSearchParams({ table_id: String(selectedTableId) });
                if (activeOrderId) {
                    params.set('order_id', String(activeOrderId));
                }
                const url = `${stateUrl}?${params.toString()}`;
                const res = await fetch(url, fetchOpts);
                data = await res.json().catch(() => ({}));
                if (!res.ok || data.success === false) return;

                const incoming = data.order;
                const current = tablePayload?.order;
                if (
                    incoming &&
                    current &&
                    Number(incoming.id) === Number(current.id) &&
                    Array.isArray(incoming.items) &&
                    Array.isArray(current.items) &&
                    incoming.items.length < current.items.length
                ) {
                    return;
                }
            } else {
                const body = { table_id: selectedTableId };
                if (activeOrderId) {
                    body.order_id = activeOrderId;
                }
                data = await kasaPost(cfg.selectTableUrl, body);
            }

            if (selectingTable) return;

            applyServerPayload(data);
        } catch {
            /* sessiz */
        }
    }
}

function escapeHtml(text) {
    const el = document.createElement('div');
    el.textContent = text ?? '';
    return el.innerHTML;
}
