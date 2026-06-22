/**
 * QR menü: sepet (localStorage), ürün varyasyon modalı, sipariş, garson çağrı.
 */
import { createRestaurantCartStorage } from '../lib/restaurant-cart-storage.js';
import { createEchoClient } from '../echo.js';

function initMenuCart() {
    const cfg = window.HSP_MENU;
    if (!cfg) return;

    const cart = {};
    let callStatusTimer = null;
    let activeProduct = null;
    let modalSelections = {};

    const cartStorage = createRestaurantCartStorage({
        restaurantId: cfg.restaurantId,
        tableToken: cfg.tableToken,
        locale: cfg.locale,
    });

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const t = cfg.i18n || {};
    const cartItemsLabel = (count) =>
        (t.cartItems || ':count items').replace(':count', String(count));

    function formatPrice(amount) {
        const formatted = Number(amount).toLocaleString('tr-TR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
        return `${formatted} ${cfg.currency}`;
    }

    function addToCartLabel(amount) {
        const tpl = t.addToCartPrice || 'Add to Cart (:price :currency)';
        return tpl
            .replace(':price', String(Math.round(amount)))
            .replace(':currency', cfg.currency);
    }

    function callQueryParams() {
        const params = new URLSearchParams();
        if (cfg.tableToken) params.set('table_token', cfg.tableToken);
        if (cfg.locale) params.set('lang', cfg.locale);
        return params;
    }

    /* ── Garson çağır ── */
    const callStatusUrl = document.getElementById('menuActionBar')?.dataset.callStatusUrl;

    const CALL_COOLDOWN_MS = 2 * 60 * 1000;
    let callCooldownTimer = null;

    const callCooldownKey = () => `hsp_call_cd_${cfg.tableToken || 'guest'}`;

    function formatCooldown(ms) {
        const totalSec = Math.max(0, Math.ceil(ms / 1000));
        const min = Math.floor(totalSec / 60);
        const sec = totalSec % 60;
        return `${min}:${String(sec).padStart(2, '0')}`;
    }

    function readCallCooldown() {
        try {
            const raw = localStorage.getItem(callCooldownKey());
            if (!raw) return null;
            const data = JSON.parse(raw);
            if (!data?.until || Date.now() >= data.until) {
                localStorage.removeItem(callCooldownKey());
                return null;
            }
            return data;
        } catch {
            return null;
        }
    }

    function startCallCooldown(message) {
        const until = Date.now() + CALL_COOLDOWN_MS;
        try {
            localStorage.setItem(callCooldownKey(), JSON.stringify({ until, message }));
        } catch {
            /* gizli mod */
        }
        applyCallCooldownUI(message);
    }

    function applyCallCooldownUI(message) {
        const data = readCallCooldown();
        const hint = document.getElementById('callCooldownHint');
        const buttons = document.getElementById('callActionButtons');
        const msg = document.getElementById('callSuccessMsg');
        const waiter = document.getElementById('callWaiter');

        if (!data) {
            if (callCooldownTimer) {
                clearInterval(callCooldownTimer);
                callCooldownTimer = null;
            }
            hint?.classList.add('hidden');
            return false;
        }

        buttons?.classList.add('hidden');
        if (msg) {
            msg.textContent = message || data.message || t.callCooldown || t.callWaiterSent || '';
            msg.classList.remove('hidden');
        }

        const remaining = data.until - Date.now();
        const timerTpl = t.callCooldownTimer || ':time';
        if (hint) {
            hint.textContent = timerTpl.replace(':time', formatCooldown(remaining));
            hint.classList.remove('hidden');
        }

        if (waiter) waiter.disabled = true;

        if (!callCooldownTimer) {
            callCooldownTimer = setInterval(() => {
                const active = readCallCooldown();
                if (!active) {
                    clearInterval(callCooldownTimer);
                    callCooldownTimer = null;
                    hint?.classList.add('hidden');
                    syncCallBarOnLoad();
                    return;
                }
                const left = active.until - Date.now();
                if (hint) {
                    hint.textContent = timerTpl.replace(':time', formatCooldown(left));
                }
            }, 1000);
        }

        return true;
    }

    function resetCallButtons() {
        if (readCallCooldown()) return;
        document.getElementById('callActionButtons')?.classList.remove('hidden');
        document.getElementById('callSuccessMsg')?.classList.add('hidden');
        document.getElementById('callCooldownHint')?.classList.add('hidden');
        const waiter = document.getElementById('callWaiter');
        if (waiter) waiter.disabled = false;
    }

    function showCallSent(message) {
        startCallCooldown(message);
        startCallStatusPoll();
    }

    function stopCallStatusPoll() {
        if (callStatusTimer) {
            clearInterval(callStatusTimer);
            callStatusTimer = null;
        }
    }

    function pendingCallMessage() {
        return t.callWaiterSent || t.callCooldown || '';
    }

    function updateCallStatusMessage(message) {
        const msg = document.getElementById('callSuccessMsg');
        if (msg && message) {
            msg.textContent = message;
            msg.classList.remove('hidden');
        }

        const stored = readCallCooldown();
        if (stored && message) {
            stored.message = message;
            try {
                localStorage.setItem(callCooldownKey(), JSON.stringify(stored));
            } catch {
                /* gizli mod */
            }
        }
    }

    function applyServerCallStatus(data) {
        if (!data?.active) {
            stopCallStatusPoll();
            if (!readCallCooldown()) {
                resetCallButtons();
                const msg = document.getElementById('callSuccessMsg');
                if (msg) {
                    msg.textContent = t.callWaiterActive || '';
                    msg.classList.remove('hidden');
                    setTimeout(() => msg.classList.add('hidden'), 5000);
                }
            }
            return;
        }

        const message =
            data.message ||
            (data.status === 'in_progress' && data.waiter_name
                ? (t.waiterOnTheWay || 'Garson :name masanıza geliyor').replace(':name', data.waiter_name)
                : pendingCallMessage());

        if (readCallCooldown()) {
            updateCallStatusMessage(message);
        } else {
            showCallSent(message);
        }
    }

    function callMatchesTable(call) {
        if (!call || !cfg.tableToken) return false;
        const token = String(cfg.tableToken);
        return (
            String(call.table_uuid ?? '') === token ||
            String(call.table_token ?? '') === token
        );
    }

    function initCallRealtime() {
        if (!cfg.reverb?.key || !cfg.restaurantId || !cfg.tableToken) return;

        const echoClient = createEchoClient(cfg.reverb);
        if (!echoClient) return;

        echoClient.channel(`orders.${cfg.restaurantId}`).listen('.TableCallUpdated', (payload) => {
            const call = payload?.call;
            if (!callMatchesTable(call)) return;

            if (call.status === 'completed' || call.status === 'resolved') {
                applyServerCallStatus({ active: false });
                return;
            }

            applyServerCallStatus({
                active: true,
                type: call.type,
                status: call.status,
                waiter_name: call.waiter_name,
                message:
                    call.status === 'in_progress' && call.waiter_name
                        ? (t.waiterOnTheWay || 'Garson :name masanıza geliyor').replace(
                              ':name',
                              call.waiter_name,
                          )
                        : pendingCallMessage(),
            });
        });
    }

    async function checkCallStatus() {
        if (!callStatusUrl || !cfg.tableToken) return;
        try {
            const res = await fetch(`${callStatusUrl}?${callQueryParams()}`, { headers: { Accept: 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();
            applyServerCallStatus(data);
        } catch {
            /* sessiz */
        }
    }

    function startCallStatusPoll() {
        stopCallStatusPoll();
        checkCallStatus();
        callStatusTimer = setInterval(checkCallStatus, 4000);
    }

    async function sendTableCall() {
        if (!cfg.tableToken || readCallCooldown()) return false;
        const payload = {
            type: 'waiter',
            table_token: cfg.tableToken,
        };
        if (cfg.locale) payload.lang = cfg.locale;
        try {
            const res = await fetch(cfg.callApiUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                    Accept: 'application/json',
                },
                body: JSON.stringify(payload),
            });
            let data;
            try {
                data = await res.json();
            } catch {
                data = null;
            }
            if (res.ok && data?.success) {
                showCallSent(data.message);
                return true;
            }
            const msg =
                data?.message ||
                (data?.errors ? Object.values(data.errors).flat().join('\n') : null) ||
                t.callFail ||
                'Garson çağrısı gönderilemedi.';
            alert(msg);
        } catch {
            alert(t.connection || '');
        }
        return false;
    }

    async function syncCallBarOnLoad() {
        if (applyCallCooldownUI()) {
            if (callStatusUrl && cfg.tableToken) {
                checkCallStatus();
            }
            return;
        }
        if (!callStatusUrl || !cfg.tableToken) return;
        try {
            const res = await fetch(`${callStatusUrl}?${callQueryParams()}`, { headers: { Accept: 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();
            if (data.active) {
                applyServerCallStatus(data);
            }
        } catch {
            /* sessiz */
        }
    }

    if (callStatusUrl) {
        syncCallBarOnLoad();
        initCallRealtime();
    }

    document.getElementById('callWaiter')?.addEventListener('click', async () => {
        const btn = document.getElementById('callWaiter');
        btn.disabled = true;
        btn.classList.add('is-glowing');
        const ok = await sendTableCall();
        if (!ok && btn) {
            btn.disabled = false;
            btn.classList.remove('is-glowing');
        }
    });

    /* ── Dinamik kategori hub ↔ ürün listesi ── */
    const dynamicZone = document.getElementById('menuDynamicZone');
    let currentCategoryId = null;

    function queryCategoryHub() {
        return dynamicZone?.querySelector('#menuCategoryHub') ?? document.getElementById('menuCategoryHub');
    }

    function queryProductBrowse() {
        return dynamicZone?.querySelector('#menuProductBrowse') ?? document.getElementById('menuProductBrowse');
    }

    function queryCategoryPanels() {
        return dynamicZone?.querySelectorAll('[data-category-panel]')
            ?? document.querySelectorAll('[data-category-panel]');
    }

    function queryHubCategories() {
        return dynamicZone?.querySelectorAll('.menu-hub-cat')
            ?? document.querySelectorAll('.menu-hub-cat');
    }

    function showCategoryPanel(catId) {
        queryCategoryPanels().forEach((panel) => {
            panel.classList.toggle('hidden', panel.dataset.categoryPanel !== String(catId));
        });
    }

    function withZoneTransition(updateFn) {
        if (!dynamicZone) {
            updateFn();
            return;
        }

        dynamicZone.classList.add('is-transitioning');
        window.setTimeout(() => {
            updateFn();
            dynamicZone.classList.remove('is-transitioning');
        }, 180);
    }

    function showCategoryHub() {
        withZoneTransition(() => {
            currentCategoryId = null;
            const categoryHub = queryCategoryHub();
            const productBrowse = queryProductBrowse();
            dynamicZone?.classList.remove('is-browsing');
            categoryHub?.classList.remove('hidden');
            productBrowse?.classList.add('hidden');
            productBrowse?.setAttribute('aria-hidden', 'true');
            queryCategoryPanels().forEach((panel) => panel.classList.add('hidden'));
            queryHubCategories().forEach((btn) => btn.classList.remove('is-active'));
        });
    }

    function showCategoryBrowse(catId) {
        withZoneTransition(() => {
            currentCategoryId = catId;
            const categoryHub = queryCategoryHub();
            const productBrowse = queryProductBrowse();
            dynamicZone?.classList.add('is-browsing');
            categoryHub?.classList.add('hidden');
            productBrowse?.classList.remove('hidden');
            productBrowse?.removeAttribute('aria-hidden');
            showCategoryPanel(catId);
            queryHubCategories().forEach((btn) => {
                btn.classList.toggle('is-active', btn.dataset.categoryId === String(catId));
            });
        });
    }

    function markInteractiveProducts() {
        document.querySelectorAll('.product-item[data-has-options="1"]:not(.drinks-list-row)').forEach((card) => {
            card.classList.add('cursor-pointer');
        });
    }

    dynamicZone?.addEventListener('click', (event) => {
        const hubBtn = event.target.closest('.menu-hub-cat');
        if (hubBtn && dynamicZone.contains(hubBtn)) {
            showCategoryBrowse(hubBtn.dataset.categoryId);
            return;
        }

        if (event.target.closest('#menuBackBtn')) {
            showCategoryHub();
            dynamicZone.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });

    queryCategoryPanels().forEach((panel) => panel.classList.add('hidden'));
    markInteractiveProducts();

    /* ── Sepet (localStorage) ── */
    let orderSubmitting = false;

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function parseProductOptions(card) {
        try {
            return JSON.parse(card.dataset.options || '[]');
        } catch {
            return [];
        }
    }

    function makeLineKey(productId, options) {
        const optionIds = options.map((o) => o.option_id).sort((a, b) => a - b);
        return `${productId}:${optionIds.join(',')}`;
    }

    function buildSelectedOptions(groups, selections) {
        const resolved = [];

        groups.forEach((group) => {
            const selected = selections[group.id];
            if (group.type === 'single') {
                if (!selected) return;
                const option = group.options.find((o) => o.id === selected);
                if (!option) return;
                resolved.push({
                    group_id: group.id,
                    group_name: group.name,
                    option_id: option.id,
                    name: option.name,
                    price: Number(option.price) || 0,
                });
            } else {
                const ids = Array.isArray(selected) ? selected : [];
                ids.forEach((optionId) => {
                    const option = group.options.find((o) => o.id === optionId);
                    if (!option) return;
                    resolved.push({
                        group_id: group.id,
                        group_name: group.name,
                        option_id: option.id,
                        name: option.name,
                        price: Number(option.price) || 0,
                    });
                });
            }
        });

        return resolved;
    }

    function unitPriceFromOptions(basePrice, options) {
        const extras = options.reduce((sum, o) => sum + (Number(o.price) || 0), 0);
        return Number(basePrice) + extras;
    }

    function displayNameWithOptions(name, options) {
        if (!options.length) return name;
        return `${name} (${options.map((o) => o.name).join(', ')})`;
    }

    function defaultSelections(groups) {
        const selections = {};
        groups.forEach((group) => {
            if (group.type === 'single') {
                const defaults = group.options.filter((o) => o.default);
                const pick = defaults[0] ?? group.options[0];
                if (pick) selections[group.id] = pick.id;
            } else {
                selections[group.id] = group.options.filter((o) => o.default).map((o) => o.id);
            }
        });
        return selections;
    }

    function validateSelections(groups, selections) {
        for (const group of groups) {
            if (!group.required) continue;
            const selected = selections[group.id];
            if (group.type === 'single') {
                if (!selected) return false;
            } else if (!Array.isArray(selected) || selected.length === 0) {
                return false;
            }
        }
        return true;
    }

    function saveCartToStorage() {
        const notes = document.getElementById('orderNotes')?.value ?? '';
        cartStorage.save(cart, notes);
    }

    function hydrateCartFromStorage() {
        const stored = cartStorage.load();
        if (!stored) {
            return;
        }

        Object.keys(cart).forEach((key) => delete cart[key]);

        Object.entries(stored.items).forEach(([key, item]) => {
            if (!item || !item.productId) {
                return;
            }

            cart[key] = {
                lineKey: key,
                productId: String(item.productId),
                name: item.name,
                basePrice: Number(item.basePrice) || 0,
                price: Number(item.price) || 0,
                options: Array.isArray(item.options) ? item.options : [],
                qty: Number(item.qty) || 1,
            };
        });

        const notesEl = document.getElementById('orderNotes');
        if (notesEl && stored.orderNotes) {
            notesEl.value = stored.orderNotes;
        }
    }

    function clearCartStorage() {
        cartStorage.clear();
    }

    function persistCart() {
        saveCartToStorage();
        updateCartUI();
    }

    function setOrderFormLocked(locked) {
        const submitBtn = document.getElementById('submitOrder');
        const closeBtn = document.getElementById('closeCart');
        const notes = document.getElementById('orderNotes');
        if (submitBtn) submitBtn.disabled = locked;
        if (closeBtn) closeBtn.disabled = locked;
        if (notes) notes.disabled = locked;
        document.querySelectorAll('.cart-qty-btn, .cart-remove-btn, .add-btn, .menu-product-card__add, #cartBar, #productModalAdd').forEach((el) => {
            el.disabled = locked;
        });
    }

    function cartTotals() {
        const items = Object.values(cart);
        const count = items.reduce((s, i) => s + i.qty, 0);
        const total = items.reduce((s, i) => s + i.price * i.qty, 0);
        return { items, count, total };
    }

    function setCartQty(lineKey, qty) {
        if (!cart[lineKey]) return;
        if (qty <= 0) {
            delete cart[lineKey];
        } else {
            cart[lineKey].qty = qty;
        }
        persistCart();
    }

    function addCartLine(productId, name, basePrice, options, qty = 1) {
        const lineKey = makeLineKey(productId, options);
        const unitPrice = unitPriceFromOptions(basePrice, options);
        const displayName = displayNameWithOptions(name, options);

        if (!cart[lineKey]) {
            cart[lineKey] = {
                lineKey,
                productId: String(productId),
                name: displayName,
                basePrice: Number(basePrice),
                price: unitPrice,
                options,
                qty: 0,
            };
        }

        cart[lineKey].qty += qty;
        persistCart();
        return lineKey;
    }

    function updateCartUI() {
        const { count, total } = cartTotals();
        const bar = document.getElementById('cartBar');
        if (!bar) return;

        const countEl = document.getElementById('cartCount');
        const totalEl = document.getElementById('cartTotal');
        if (countEl) countEl.textContent = cartItemsLabel(count);
        if (totalEl) totalEl.textContent = formatPrice(total);

        const modalTotal = document.getElementById('cartModalTotal');
        if (modalTotal) modalTotal.textContent = formatPrice(total);

        bar.classList.toggle('visible', count > 0);

        const modal = document.getElementById('cartModal');
        if (modal?.classList.contains('open') && count === 0) {
            modal.classList.remove('open');
        }
    }

    function renderCartModal() {
        const list = document.getElementById('cartItems');
        if (!list) return;

        const { items, total } = cartTotals();
        if (!items.length) {
            list.innerHTML = '';
            updateCartUI();
            return;
        }

        list.innerHTML = items
            .map((i) => {
                const optionsHtml = i.options?.length
                    ? `<p class="cart-line__options">${escapeHtml(i.options.map((o) => o.name).join(' · '))}</p>`
                    : '';

                return `
                <div class="cart-line" data-cart-id="${escapeHtml(i.lineKey)}">
                    <div class="cart-line__top">
                        <span class="cart-line__name">${escapeHtml(i.name)}</span>
                        <span class="cart-line__subtotal">${formatPrice(i.price * i.qty)}</span>
                    </div>
                    ${optionsHtml}
                    <div class="cart-line__actions">
                        <div class="cart-line__qty">
                            <button type="button" class="cart-qty-btn" data-cart-action="dec" data-id="${escapeHtml(i.lineKey)}" aria-label="${escapeHtml(t.cartDecrease || '-')}">−</button>
                            <span class="cart-qty-value">${i.qty}</span>
                            <button type="button" class="cart-qty-btn" data-cart-action="inc" data-id="${escapeHtml(i.lineKey)}" aria-label="${escapeHtml(t.cartIncrease || '+')}">+</button>
                        </div>
                        <button type="button" class="cart-remove-btn" data-cart-action="remove" data-id="${escapeHtml(i.lineKey)}">${escapeHtml(t.cartRemove || 'Remove')}</button>
                    </div>
                </div>`;
            })
            .join('');

        const modalTotal = document.getElementById('cartModalTotal');
        if (modalTotal) modalTotal.textContent = formatPrice(total);
    }

    function animateProductAdded(card) {
        card.classList.remove('animate-fade-in-up');
        void card.offsetWidth;
        card.classList.add('animate-fade-in-up');

        const bar = document.getElementById('cartBar');
        bar?.classList.remove('animate-cart-pop');
        void bar?.offsetWidth;
        bar?.classList.add('animate-cart-pop');
    }

    function flashAddButton(btn) {
        btn.textContent = '✓';
        setTimeout(() => {
            btn.textContent = '+';
        }, 1200);
    }

    function quickAddProduct(card, btn) {
        addCartLine(card.dataset.id, card.dataset.name, card.dataset.price, [], 1);
        animateProductAdded(card);
        flashAddButton(btn);
    }

    /* ── Ürün modalı ── */
    const productModal = document.getElementById('productModal');
    const productModalTitle = document.getElementById('productModalTitle');
    const productModalBasePrice = document.getElementById('productModalBasePrice');
    const productModalOptions = document.getElementById('productModalOptions');
    const productModalAdd = document.getElementById('productModalAdd');
    const productModalError = document.getElementById('productModalError');
    const productModalDesc = document.getElementById('productModalDesc');

    function formatOptionPrice(price) {
        if (!price || Number(price) <= 0) return '';
        return `+${Math.round(price)} ${cfg.currency}`;
    }

    function isSegmentedGroup(group) {
        return group.type === 'single' && group.options.length === 2;
    }

    function renderSegmentedGroup(group) {
        const requiredHint = group.required
            ? `<span class="product-option-group__hint">*</span>`
            : '';

        const buttons = group.options
            .map((option) => {
                const isSelected = modalSelections[group.id] === option.id;
                const priceLabel = formatOptionPrice(option.price);

                return `
                <label class="drinks-segmented__btn ${isSelected ? 'is-selected' : ''}">
                    <input
                        type="radio"
                        name="option-group-${group.id}"
                        data-group-id="${group.id}"
                        data-option-id="${option.id}"
                        data-group-type="single"
                        ${isSelected ? 'checked' : ''}
                    >
                    <span>${escapeHtml(option.name)}</span>
                    ${priceLabel ? `<span class="block text-[10px] opacity-80">${escapeHtml(priceLabel)}</span>` : ''}
                </label>`;
            })
            .join('');

        return `
        <section class="product-option-group drinks-option-group--premium" data-group-id="${group.id}">
            <h3 class="product-option-group__title">${escapeHtml(group.name)}${requiredHint}</h3>
            <div class="drinks-segmented">${buttons}</div>
        </section>`;
    }

    function renderProductModalOptions() {
        if (!activeProduct || !productModalOptions) return;

        productModalOptions.innerHTML = activeProduct.groups
            .map((group) => {
                if (isSegmentedGroup(group)) {
                    return renderSegmentedGroup(group);
                }

                const requiredHint = group.required
                    ? `<span class="product-option-group__hint">*</span>`
                    : '';

                const choices = group.options
                    .map((option) => {
                        const inputType = group.type === 'single' ? 'radio' : 'checkbox';
                        const inputName = `option-group-${group.id}`;
                        const isChecked =
                            group.type === 'single'
                                ? modalSelections[group.id] === option.id
                                : (modalSelections[group.id] || []).includes(option.id);
                        const priceLabel = formatOptionPrice(option.price);

                        return `
                        <label class="product-option-choice ${isChecked ? 'is-selected' : ''}">
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
                <section class="product-option-group drinks-option-group--premium" data-group-id="${group.id}">
                    <h3 class="product-option-group__title">${escapeHtml(group.name)}${requiredHint}</h3>
                    <div class="product-option-list">${choices}</div>
                </section>`;
            })
            .join('');

        productModalOptions.querySelectorAll('input[data-group-id]').forEach((input) => {
            input.addEventListener('change', onProductOptionChange);
        });
    }

    function updateProductModalPrice() {
        if (!activeProduct || !productModalAdd) return;

        const options = buildSelectedOptions(activeProduct.groups, modalSelections);
        const total = unitPriceFromOptions(activeProduct.basePrice, options);
        const hasGroups = activeProduct.groups.length > 0;
        const valid = hasGroups ? validateSelections(activeProduct.groups, modalSelections) : true;

        productModalAdd.textContent = hasGroups
            ? addToCartLabel(total)
            : (t.addToCart || 'Sepete Ekle');
        productModalAdd.disabled = !valid;

        if (productModalError) {
            productModalError.classList.toggle('hidden', valid);
            if (!valid) productModalError.textContent = t.optionRequired || '';
        }
    }

    function onProductOptionChange(event) {
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

        productModalOptions?.querySelectorAll(`input[data-group-id="${groupId}"]`).forEach((el) => {
            el.closest('.product-option-choice, .drinks-segmented__btn')?.classList.toggle('is-selected', el.checked);
        });

        updateProductModalPrice();
    }


    function openProductModal(card) {
        if (!productModal) return;

        const groups = parseProductOptions(card);
        activeProduct = {
            card,
            productId: card.dataset.id,
            name: card.dataset.name,
            basePrice: Number(card.dataset.price) || 0,
            groups,
        };
        modalSelections = defaultSelections(groups);

        if (productModalTitle) productModalTitle.textContent = activeProduct.name;

        const desc = card.dataset.desc?.trim();
        if (productModalDesc) {
            if (desc) {
                productModalDesc.textContent = desc;
                productModalDesc.classList.remove('hidden');
            } else {
                productModalDesc.textContent = '';
                productModalDesc.classList.add('hidden');
            }
        }

        if (productModalBasePrice) {
            const baseTpl = t.basePrice || 'Base: :price :currency';
            productModalBasePrice.textContent = baseTpl
                .replace(':price', String(Math.round(activeProduct.basePrice)))
                .replace(':currency', cfg.currency);
        }

        renderProductModalOptions();
        updateProductModalPrice();
        productModal.classList.add('open');
    }

    function closeProductModal() {
        productModal?.classList.remove('open');
        activeProduct = null;
        modalSelections = {};
        if (productModalError) productModalError.classList.add('hidden');
    }

    productModal?.addEventListener('click', (e) => {
        if (e.target === productModal) closeProductModal();
    });
    document.getElementById('productModalClose')?.addEventListener('click', closeProductModal);

    productModalAdd?.addEventListener('click', () => {
        if (!activeProduct || activeProduct.card?.dataset.inStock === '0') return;

        const hasGroups = activeProduct.groups.length > 0;
        if (hasGroups && !validateSelections(activeProduct.groups, modalSelections)) {
            if (productModalError) {
                productModalError.textContent = t.optionRequired || '';
                productModalError.classList.remove('hidden');
            }
            return;
        }

        const options = hasGroups
            ? buildSelectedOptions(activeProduct.groups, modalSelections)
            : [];
        addCartLine(activeProduct.productId, activeProduct.name, activeProduct.basePrice, options, 1);
        animateProductAdded(activeProduct.card);

        const btn = activeProduct.card.querySelector('.add-btn');
        if (btn) flashAddButton(btn);

        closeProductModal();
    });

    document.addEventListener('click', (e) => {
        const addBtn = e.target.closest('.add-btn');
        if (addBtn) {
            e.stopPropagation();
            const card = addBtn.closest('.product-item');
            if (!card || card.dataset.inStock === '0') return;

            const hasOptions = card.dataset.hasOptions === '1';
            if (hasOptions) {
                openProductModal(card);
                return;
            }

            quickAddProduct(card, addBtn);
            return;
        }

        const drinksRow = e.target.closest('.drinks-list-row');
        if (drinksRow && !drinksRow.disabled && drinksRow.dataset.inStock !== '0') {
            openProductModal(drinksRow);
            return;
        }

        const card = e.target.closest('.product-item[data-has-options="1"]');
        if (!card || e.target.closest('.add-btn')) return;
        if (card.classList.contains('drinks-list-row')) return;
        if (card.dataset.inStock === '0') return;
        openProductModal(card);
    });

    markInteractiveProducts();

    document.getElementById('cartItems')?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-cart-action]');
        if (!btn) return;

        const id = btn.dataset.id;
        const action = btn.dataset.cartAction;
        if (!id || !cart[id]) return;

        if (action === 'inc') {
            setCartQty(id, cart[id].qty + 1);
        } else if (action === 'dec') {
            setCartQty(id, cart[id].qty - 1);
        } else if (action === 'remove') {
            delete cart[id];
            persistCart();
        }

        renderCartModal();
    });

    document.getElementById('cartBar')?.addEventListener('click', () => {
        if (!cartTotals().count) return;
        renderCartModal();
        document.getElementById('cartModal')?.classList.add('open');
    });

    document.getElementById('closeCart')?.addEventListener('click', () => {
        document.getElementById('cartModal')?.classList.remove('open');
    });

    document.getElementById('cartModal')?.addEventListener('click', (e) => {
        if (e.target.id === 'cartModal') e.target.classList.remove('open');
    });

    document.getElementById('submitOrder')?.addEventListener('click', async () => {
        if (orderSubmitting) return;

        const items = Object.values(cart).map((i) => ({
            product_id: parseInt(i.productId, 10),
            quantity: i.qty,
            options: (i.options || []).map((o) => ({
                group_id: o.group_id,
                option_id: o.option_id,
            })),
        }));
        if (!items.length) return;

        const btn = document.getElementById('submitOrder');
        orderSubmitting = true;
        setOrderFormLocked(true);
        btn.textContent = t.sending || '...';

        try {
            const res = await fetch(cfg.orderStoreUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    table_token: cfg.tableToken,
                    lang: cfg.locale || 'tr',
                    notes: document.getElementById('orderNotes')?.value ?? '',
                    items,
                }),
            });

            let data;
            try {
                data = await res.json();
            } catch {
                data = null;
            }

            if (res.ok && data?.success) {
                Object.keys(cart).forEach((key) => delete cart[key]);
                clearCartStorage();
                window.location.href = data.redirect;
                return;
            }

            const msg =
                data?.message ||
                (data?.errors ? Object.values(data.errors).flat().join('\n') : null) ||
                `Sipariş gönderilemedi (${res.status}).`;
            alert(msg);
        } catch {
            alert(t.connection || '');
        }

        orderSubmitting = false;
        setOrderFormLocked(false);
        btn.textContent = t.send || 'Send';
    });

    hydrateCartFromStorage();
    updateCartUI();

    document.getElementById('orderNotes')?.addEventListener('input', () => {
        saveCartToStorage();
    });

    window.addEventListener('beforeunload', () => {
        saveCartToStorage();
    });

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') {
            saveCartToStorage();
        }
    });

    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            hydrateCartFromStorage();
            updateCartUI();
        }
    });

    window.addEventListener('storage', (event) => {
        if (event.key !== cartStorage.key) {
            return;
        }
        hydrateCartFromStorage();
        updateCartUI();
        const modal = document.getElementById('cartModal');
        if (modal?.classList.contains('open')) {
            renderCartModal();
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMenuCart);
} else {
    initMenuCart();
}
