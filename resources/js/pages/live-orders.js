import { showAdminToast } from '../admin-toast.js';
import { createEchoClient } from '../echo.js';
import { initNotificationAudio, playCallAlert, playOrderDing } from '../notification-audio.js';
import { initKasaPanel } from './kasa-panel.js';

/**
 * Birleşik sipariş + masa çağrıları (tek API, istemci filtreleme).
 */
function escapeHtml(text) {
    const el = document.createElement('div');
    el.textContent = text;
    return el.innerHTML;
}

function isSubtleSound() {
    return document.getElementById('liveOrdersApp')?.dataset.kasaMode === '1';
}

function dingOrder() {
    playOrderDing(isSubtleSound());
}

function dingCall() {
    playCallAlert(isSubtleSound());
}

const OVERDUE_MINUTES = 15;

/** Admin birleşik ekranda onay bekleyen siparişler görünür; mutfak/bar tableti görmez. */
let liveOpsShowPendingApproval = true;

function parseIso(iso) {
    if (!iso) return null;
    const d = new Date(iso);
    return Number.isNaN(d.getTime()) ? null : d;
}

function formatRelativeAge(iso) {
    const d = parseIso(iso);
    if (!d) return '';
    const mins = Math.floor((Date.now() - d.getTime()) / 60000);
    if (mins < 1) return 'Az önce';
    if (mins < 60) return `${mins} dk önce`;
    const hrs = Math.floor(mins / 60);
    return `${hrs} sa önce`;
}

function isOverdueOrder(order) {
    if (order.status === 'pending_approval') return false;
    if (!['pending', 'preparing'].includes(order.status)) return false;
    const d = parseIso(order.created_at_iso || order.updated_at);
    if (!d) return false;
    return (Date.now() - d.getTime()) / 60000 >= OVERDUE_MINUTES;
}

function orderStationFlagsFromItems(items) {
    const types = new Set((items || []).map((i) => i.type));
    return {
        has_kitchen: types.has('kitchen'),
        has_bar: types.has('bar'),
        has_hookah: types.has('hookah') || types.has('nargile'),
        has_nargile: types.has('hookah') || types.has('nargile'),
        has_service: types.has('service') || types.has('retail'),
        has_retail: types.has('service') || types.has('retail'),
    };
}

function mergeOrderStationFlags(order, raw = {}) {
    const flags = orderStationFlagsFromItems(order.items);
    return {
        ...order,
        has_kitchen: raw.has_kitchen ?? flags.has_kitchen,
        has_bar: raw.has_bar ?? flags.has_bar,
        has_nargile: raw.has_nargile ?? flags.has_nargile,
        has_retail: raw.has_retail ?? flags.has_retail,
    };
}

function filterOrders(orders, tab) {
    if (tab === 'calls') return orders;
    const visible = tab === 'all' && liveOpsShowPendingApproval
        ? orders
        : orders.filter((o) => o.status !== 'pending_approval');
    if (tab === 'all') return visible;
    if (tab === 'kitchen') return visible.filter((o) => o.has_kitchen);
    if (tab === 'bar') return visible.filter((o) => o.has_bar);
    if (tab === 'hookah' || tab === 'nargile') return visible.filter((o) => o.has_hookah || o.has_nargile);
    if (tab === 'service') return visible.filter((o) => o.has_service || o.has_retail);
    if (tab === 'prepared') return visible.filter((o) => o.status === 'ready');
    if (tab === 'assigned') return visible.filter((o) => (o.items || []).some((i) => i.delivery_task_status === 'assigned' || i.delivery_task_status === 'accepted'));
    if (tab === 'served') return visible.filter((o) => (o.items || []).some((i) => i.preparation_status === 'served'));
    return visible;
}

function itemsForTab(order, tab) {
    if (tab === 'all') return order.items;
    if (tab === 'kitchen' || tab === 'bar') {
        return order.items.filter((i) => i.type === tab);
    }
    if (tab === 'hookah' || tab === 'nargile') {
        return order.items.filter((i) => i.type === 'hookah' || i.type === 'nargile');
    }
    if (tab === 'service') {
        return order.items.filter((i) => i.type === 'service' || i.type === 'retail');
    }
    return order.items;
}

function itemTypeClass(type) {
    if (type === 'bar') return 'lo-card__item--bar';
    if (type === 'hookah' || type === 'nargile') return 'lo-card__item--nargile';
    if (type === 'service' || type === 'retail') return 'lo-card__item--retail';
    return 'lo-card__item--kitchen';
}

function statusActions(status, paymentMethod = null, isWaiterOrder = false, options = {}) {
    const { kasaMode = false } = options;
    const buttons = [];
    const pill = kasaMode ? ' lo-btn--pill' : '';

    if (kasaMode) {
        return buttons;
    }

    if (status === 'pending_approval') {
        buttons.push({
            status: 'pending_approval',
            label: 'Kasa Onayı Bekleniyor',
            cls: `lo-btn lo-btn--ghost${pill}`,
            disabled: true,
        });
        return buttons;
    }

    if (status === 'pending') {
        buttons.push({
            status: 'preparing',
            label: 'Kabul Et · Hazırlanıyor',
            cls: `lo-btn lo-btn--primary${pill}`,
        });
    }
    if (status === 'preparing') {
        buttons.push({
            status: 'ready',
            label: kasaMode ? 'Hazır' : 'Mutfakta Hazır',
            cls: `lo-btn lo-btn--primary${pill}`,
        });
    }
    if (status === 'ready') {
        buttons.push({
            status: 'ready',
            label: 'Garson Teslim Edecek',
            cls: `lo-btn lo-btn--ghost${pill}`,
            disabled: true,
        });
    }
    if (status === 'delivered' && !paymentMethod && !kasaMode) {
        buttons.push({
            status: 'delivered',
            payment_method: 'cash',
            payment_only: true,
            label: '💵 Nakit · Kapat',
            cls: `lo-btn lo-btn--success${pill}`,
        });
        buttons.push({
            status: 'delivered',
            payment_method: 'card',
            payment_only: true,
            label: '💳 Kart · Kapat',
            cls: `lo-btn lo-btn--sky${pill}`,
        });
    }
    return buttons;
}

function buildOrdersFingerprint(orders, tab) {
    const filtered = filterOrders(orders, tab);
    return JSON.stringify(
        filtered.map((o) => ({
            id: o.id,
            status: o.status,
            updated_at: o.updated_at,
            items: o.items.map((i) => [i.id, i.quantity, i.type]),
        })),
    );
}

function buildCallsFingerprint(calls) {
    return JSON.stringify(
        calls.map((c) => [c.id, c.updated_at, c.type, c.forwarded_to_waiter, c.status, c.waiter_id]),
    );
}

function buildKasaViewFingerprint(orders, calls, tab) {
    const order = pickKasaPrimaryOrder(filterOrders(orders, 'all'));
    const lockedId = window.HSP_KASA?.activeOrderId ?? null;
    const entryConfirmed =
        order &&
        !isKasaManualOrder(order) &&
        window.HSP_KASA?.isEntryConfirmed?.(order.id) &&
        order.status === 'preparing'
            ? 1
            : 0;
    const orderFp = order
        ? [
              order.id,
              order.order_number,
              order.status,
              order.payment_method,
              entryConfirmed,
              order.items?.map((i) => [i.id, i.quantity, i.unit_price, i.notes]) ?? [],
          ]
        : null;
    const callsFp = calls.map((c) => [c.id, c.status, c.forwarded_to_waiter, c.waiter_id]);
    const payloadOrder = window.HSP_KASA?.getTablePayload?.()?.order;
    const payloadFp = payloadOrder
        ? [
              payloadOrder.id,
              payloadOrder.status,
              payloadOrder.total,
              (payloadOrder.items || []).map((i) => [i.id, i.quantity, i.unit_price]),
          ]
        : null;
    const canApprove = window.HSP_KASA?.getTablePayload?.()?.can_approve ? 1 : 0;

    return `kasa:${tab}:${lockedId}:${canApprove}:${JSON.stringify(orderFp)}:${JSON.stringify(payloadFp)}:${JSON.stringify(callsFp)}`;
}

function buildViewFingerprint(orders, calls, completedOrders, tab) {
    if (tab === 'calls') {
        return `calls:${buildCallsFingerprint(calls)}`;
    }
    if (tab === 'completed') {
        return `completed:${JSON.stringify(completedOrders.map((o) => [o.id, o.status, o.payment_method, o.updated_at]))}`;
    }
    if (tab === 'all') {
        const feed = buildMixedFeed(filterOrders(orders, 'all'), calls);
        return `all:${JSON.stringify(feed.map((f) => [f.kind, f.sort_at, f.data.id, f.data.updated_at ?? f.data.status, f.data.forwarded_to_waiter]))}`;
    }
    return `orders:${buildOrdersFingerprint(orders, tab)}`;
}

function buildMixedFeed(orders, calls) {
    const items = [
        ...orders.map((o) => ({
            kind: 'order',
            sort_at: o.updated_at,
            data: o,
        })),
        ...calls.map((c) => ({
            kind: 'call',
            sort_at: c.sort_at || c.updated_at,
            data: c,
        })),
    ];
    return items.sort((a, b) => String(b.sort_at).localeCompare(String(a.sort_at)));
}

function renderOrderCard(order, tab, options = {}) {
    const { dismissible = false, kasaMode = false, hero = false } = options;
    const items = itemsForTab(order, tab);
    if (!items.length) return '';

    const itemsHtml = items
        .map((i) => {
            const lineTotal = Number(i.unit_price ?? 0) * Number(i.quantity ?? 1);
            const priceHtml =
                kasaMode || hero
                    ? `<span class="lo-kasa-adisyon__row-price">${formatKasaMoney(lineTotal)}</span>`
                    : '';

            const rowClass =
                kasaMode || hero
                    ? 'lo-kasa-adisyon__row lo-kasa-adisyon__row--clickable'
                    : `lo-card__item ${itemTypeClass(i.type)}`;
            const rowAttrs =
                kasaMode || hero
                    ? ` data-kasa-order-item="${i.id}" role="button" tabindex="0" title="Düzenlemek için tıklayın"`
                    : '';

            const optionsHtml = formatOrderItemOptionsHtml(i.options);

            return `
        <li class="${rowClass}"${rowAttrs}>
            <span class="lo-kasa-adisyon__row-name-wrap">
                <span class="lo-kasa-adisyon__row-name">${i.quantity}× ${escapeHtml(i.name)}</span>
                ${optionsHtml}
            </span>
            ${priceHtml}
            ${i.notes ? `<span class="lo-kasa-adisyon__row-note">${escapeHtml(i.notes)}</span>` : ''}
        </li>`;
        })
        .join('');

    const actions = statusActions(order.status, order.payment_method, order.is_waiter_order, { kasaMode })
        .map(
            (a) =>
                `<button type="button" class="live-ops-status-btn ${a.cls}" data-order-id="${order.id}" data-status="${a.status}"${a.payment_method ? ` data-payment-method="${a.payment_method}"` : ''}${a.payment_only ? ' data-payment-only="1"' : ''}${a.disabled ? ' disabled' : ''}>${a.label}</button>`,
        )
        .join('');

    const dismissBtn = dismissible
        ? `<button type="button" class="live-ops-dismiss-completed lo-btn lo-btn--ghost lo-btn--pill" data-order-id="${order.id}">Listeden Kaldır</button>`
        : '';

    const paymentLabel = order.payment_method
        ? `<span class="lo-tag-payment">${order.payment_method === 'card' ? 'Kart' : 'Nakit'}</span>`
        : '';

    const waiterBadge =
        order.source === 'kasa'
            ? '<span class="lo-tag-waiter lo-tag-waiter--kasa">Kasa</span>'
            : order.is_waiter_order
              ? '<span class="lo-tag-waiter">Garson siparişi</span>'
              : '';

    const overdueClass = isOverdueOrder(order) ? ' lo-card--overdue' : '';
    const waiterClass = order.is_waiter_order ? ' lo-card--waiter' : '';
    const createdIso = order.created_at_iso || order.updated_at || '';
    const total =
        order.total ??
        order.items.reduce((sum, i) => sum + Number(i.unit_price ?? 0) * Number(i.quantity ?? 1), 0);

    const approveBtn = !kasaMode ? '' : buildKasaAdisyonActionsHtml(order);

    if (hero || kasaMode) {
        return `
        <article class="lo-kasa-adisyon live-ops-order-card${waiterClass}${overdueClass}" data-order-id="${order.id}" data-kasa-adisyon-root="1" data-created-at="${escapeHtml(createdIso)}" data-status="${order.status}">
            <header class="lo-kasa-adisyon__head">
                <div class="lo-kasa-adisyon__head-main">
                    <p class="lo-kasa-adisyon__label">Aktif Adisyon</p>
                    <div class="lo-kasa-adisyon__head-row">
                        <div class="lo-kasa-adisyon__head-id">
                            <h2 class="lo-kasa-adisyon__num">#${order.order_number}</h2>
                            ${order.table ? `<span class="lo-kasa-adisyon__table">Masa ${order.table}</span>` : ''}
                        </div>
                        <div class="lo-kasa-adisyon__meta">
                            <span class="lo-kasa-adisyon__status">${order.status_label}</span>
                            <span class="live-ops-age lo-kasa-adisyon__age">${formatRelativeAge(createdIso) || order.created_at}</span>
                        </div>
                    </div>
                    ${waiterBadge || paymentLabel ? `<div class="lo-kasa-adisyon__head-tags">${waiterBadge}${paymentLabel}</div>` : ''}
                </div>
            </header>
            <div class="lo-kasa-adisyon__body">
                <ul class="lo-kasa-adisyon__items">${itemsHtml}</ul>
                ${order.notes ? `<p class="lo-kasa-adisyon__note">📝 ${escapeHtml(order.notes)}</p>` : ''}
            </div>
            ${buildKasaAdisyonFootHtml(order, total)}
        </article>`;
    }

    return `
    <article class="lo-card lo-card--order${waiterClass}${overdueClass} live-ops-order-card${waiterClass ? ' live-ops-order-card--waiter' : ''}${overdueClass ? ' live-ops-order-card--overdue' : ''}" data-order-id="${order.id}" data-created-at="${escapeHtml(createdIso)}" data-status="${order.status}">
        <div class="lo-card__head">
            <div>
                <span class="lo-card__num">#${order.order_number}</span>
                ${order.table ? `<span class="lo-card__badge">Masa ${order.table}</span>` : ''}
                ${waiterBadge}
                ${paymentLabel}
            </div>
            <div class="lo-card__meta">
                <span class="live-ops-age">${formatRelativeAge(createdIso) || order.created_at}</span>
                <span class="lo-card__status">${order.status_label}</span>
            </div>
        </div>
        <ul class="lo-card__items">${itemsHtml}</ul>
        ${order.notes ? `<p class="lo-card__note">📝 ${escapeHtml(order.notes)}</p>` : ''}
        <div class="lo-card__actions">${actions}${dismissBtn}</div>
    </article>`;
}

function formatKasaMoney(value) {
    if (window.HSP_KASA?.formatMoney) {
        return window.HSP_KASA.formatMoney(value);
    }
    const n = Number(value) || 0;
    return `${Math.round(n).toLocaleString('tr-TR')} ₺`;
}

function formatKasaMoneyDecimal(value) {
    const n = Number(value) || 0;
    return `${n.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ₺`;
}

function kasaOrderTotal(order) {
    if (!order) return 0;
    return (
        order.total ??
        (order.items || []).reduce(
            (sum, i) => sum + Number(i.unit_price ?? 0) * Number(i.quantity ?? 1),
            0,
        )
    );
}

function syncKasaOrderChrome(order, tableNum = window.HSP_KASA?.selectedTableNumber) {
    const titleEl = document.getElementById('kasaOrderDetailTitle');
    const statusEl = document.getElementById('kasaOrderDetailStatus');

    if (titleEl) {
        titleEl.textContent = tableNum
            ? `Sipariş Detayları (Masa ${tableNum})`
            : 'Sipariş Detayları';
    }

    if (!statusEl) return;

    if (!tableNum) {
        statusEl.textContent = 'Masa seçin';
        statusEl.className = 'lo-kasa-order-shell__status lo-kasa-order-shell__status--idle';
        return;
    }

    if (!order) {
        statusEl.textContent = 'Adisyon yok';
        statusEl.className = 'lo-kasa-order-shell__status lo-kasa-order-shell__status--idle';
        return;
    }

    statusEl.textContent = order.status_label || order.status;
    statusEl.className = `lo-kasa-order-shell__status lo-kasa-order-shell__status--${order.status}`;
}

function syncKasaPayPanel(order) {
    const codeEl = document.getElementById('kasaPayOrderCode');
    const totalEl = document.getElementById('kasaPayTotalAmount');
    const actionsEl = document.getElementById('kasaPayActions');
    const dockTotalAmount = document.getElementById('kasaDockTotalAmount');
    const total = kasaOrderTotal(order);
    const hasOrder = !!order && total > 0;

    if (codeEl) codeEl.textContent = order?.order_number ? `#${order.order_number}` : '—';
    if (totalEl) totalEl.textContent = hasOrder ? formatKasaMoneyDecimal(total) : '0,00 ₺';
    if (dockTotalAmount) dockTotalAmount.textContent = formatKasaMoney(total);
    if (actionsEl) actionsEl.innerHTML = order ? buildKasaAdisyonActionsHtml(order) : '';
}

function orderFromKasaPayload(payload, tableNumber) {
    const items = (payload.items || []).map((i) => ({
        id: i.id,
        name: i.name,
        quantity: i.quantity,
        unit_price: i.unit_price,
        notes: i.notes ?? null,
        type: i.type ?? 'kitchen',
        station: i.station ?? i.type ?? 'kitchen',
        preparation_status: i.preparation_status ?? 'waiting',
        preparation_status_label: i.preparation_status_label ?? 'Bekliyor',
    }));

    return {
        id: payload.id,
        order_number: payload.order_number,
        status: payload.status,
        status_label: payload.status_label ?? payload.status,
        source: payload.source ?? 'waiter',
        source_label: payload.source_label ?? 'Kasa',
        is_waiter_order: payload.is_waiter_order ?? false,
        payment_method: payload.payment_method ?? null,
        table: payload.table ?? tableNumber,
        kasa_served_through_item_id: payload.kasa_served_through_item_id ?? null,
        notes: payload.notes ?? null,
        total: payload.total,
        created_at:
            payload.created_at ??
            new Date().toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' }),
        created_at_iso: payload.created_at_iso ?? new Date().toISOString(),
        updated_at: payload.updated_at ?? new Date().toISOString(),
        has_kitchen: items.some((i) => i.type === 'kitchen'),
        has_bar: items.some((i) => i.type === 'bar'),
        has_hookah: items.some((i) => i.type === 'hookah' || i.type === 'nargile'),
        has_nargile: items.some((i) => i.type === 'hookah' || i.type === 'nargile'),
        has_service: items.some((i) => i.type === 'service' || i.type === 'retail'),
        has_retail: items.some((i) => i.type === 'service' || i.type === 'retail'),
        items,
    };
}

function kasaStatusRank(status) {
    const ranks = {
        pending_approval: 0,
        pending: 1,
        preparing: 2,
        ready: 3,
        delivered: 4,
        cancelled: -1,
    };

    return ranks[status] ?? 0;
}

function formatOrderItemOptionsHtml(options) {
    if (!Array.isArray(options) || !options.length) {
        return '';
    }

    const labels = options.map((row) => row?.name).filter(Boolean);
    if (!labels.length) {
        return '';
    }

    return `<span class="order-item-options">${escapeHtml(labels.join(' · '))}</span>`;
}

function preparationStatusLabel(status) {
    const labels = {
        waiting: 'Bekliyor',
        preparing: 'Hazırlanıyor',
        ready: 'Hazır',
        served: 'Teslim Edildi',
        cancelled: 'İptal',
    };
    return labels[status] || labels.waiting;
}

function buildKasaItemPrepActions(item) {
    if (item.preparation_status === 'served' || item.preparation_status === 'cancelled') {
        return '<span class="lo-tag-forwarded">' + escapeHtml(preparationStatusLabel(item.preparation_status)) + '</span>';
    }

    const preparingDisabled = item.preparation_status === 'preparing' ? ' disabled' : '';
    const readyDisabled = item.preparation_status === 'ready' ? ' disabled' : '';

    return '<span class="lo-kasa-item-status">' + escapeHtml(preparationStatusLabel(item.preparation_status)) + '</span>'
        + '<button type="button" class="live-ops-item-status lo-btn lo-btn--ghost lo-btn--sm" data-item-id="' + item.id + '" data-item-status="preparing"' + preparingDisabled + '>Hazırlanıyor</button>'
        + '<button type="button" class="live-ops-item-status lo-btn lo-btn--primary lo-btn--sm" data-item-id="' + item.id + '" data-item-status="ready"' + readyDisabled + '>Hazır</button>';
}

function buildKasaAdisyonItemRow(item) {
    const lineTotal = Number(item.unit_price ?? 0) * Number(item.quantity ?? 1);
    const optionsHtml = formatOrderItemOptionsHtml(item.options);
    return `
        <li class="lo-kasa-pos-row lo-kasa-adisyon__row lo-kasa-adisyon__row--clickable" data-kasa-order-item="${item.id}" role="button" tabindex="0" title="Düzenlemek için tıklayın">
            <div class="lo-kasa-pos-row__main">
                <span class="lo-kasa-pos-row__qty">${item.quantity}×</span>
                <span class="lo-kasa-pos-row__name-wrap">
                    <span class="lo-kasa-pos-row__name">${escapeHtml(item.name)}</span>
                    ${optionsHtml}
                </span>
            </div>
            <span class="lo-kasa-pos-row__price">${formatKasaMoney(lineTotal)}</span>
            <span class="lo-kasa-adisyon__row-actions">${buildKasaItemPrepActions(item)}</span>
            ${item.notes ? `<span class="lo-kasa-adisyon__row-note">${escapeHtml(item.notes)}</span>` : ''}
        </li>`;
}

function buildKasaOrderItemsHtml(order) {
    const threshold = Number(order?.kasa_served_through_item_id ?? 0);
    let dividerShown = false;

    return (order?.items || [])
        .map((item) => {
            let divider = '';
            if (threshold > 0 && Number(item.id) > threshold && !dividerShown) {
                dividerShown = true;
                divider =
                    '<li class="lo-kasa-order-round-divider" aria-hidden="true"><span>Yeni sipariş</span></li>';
            }
            return divider + buildKasaAdisyonItemRow(item);
        })
        .join('');
}

function isKasaManualOrder(order) {
    return String(order?.source ?? '') === 'kasa';
}

function isWaiterManualOrder(order) {
    return String(order?.source ?? '') === 'waiter' || order?.is_waiter_order === true;
}

function isQrOrder(order) {
    return String(order?.source ?? '') === 'qr';
}

function canNotifyWaiterFromKasa(order) {
    if (!order || order.payment_method || order.status !== 'preparing') {
        return false;
    }

    return (order.items?.length ?? 0) > 0;
}

function buildKasaAdisyonActionsHtml(order) {
    if (!order?.items?.length || order.payment_method) {
        return '';
    }

    if (canNotifyWaiterFromKasa(order)) {
        const label = 'Hazır · Garsona Bildir';
        const hint = isQrOrder(order)
            ? 'QR sipariş hazır — garson masaya götürsün'
            : 'Mutfak hazır — garsona teslim bildirimi gönder';

        return `<button type="button" class="lo-kasa-pay-action-btn lo-btn lo-btn--primary lo-kasa-phase-btn" data-kasa-notify-waiter title="${escapeHtml(hint)}">${escapeHtml(label)}</button>`;
    }

    if (order.status === 'pending_approval' && window.HSP_KASA?.getTablePayload?.()?.can_approve) {
        return `<button type="button" class="lo-kasa-pay-action-btn lo-btn lo-btn--primary lo-kasa-phase-btn" data-kasa-approve-order>Kabul Et · Hazırlanıyor</button>`;
    }

    return '';
}

function renderKasaCenterPanel(order) {
    const items = order?.items || [];
    if (!items.length) {
        return '';
    }

    const createdIso = order.created_at_iso || order.updated_at || '';
    const itemsHtml = buildKasaOrderItemsHtml(order);

    return `
        <section
            class="lo-kasa-order-detail live-ops-order-card"
            data-order-id="${order.id}"
            data-kasa-adisyon-root="1"
            data-created-at="${escapeHtml(createdIso)}"
            data-status="${order.status}"
        >
            <ul class="lo-kasa-order-detail__items">${itemsHtml}</ul>
            ${order.notes ? `<p class="lo-kasa-adisyon__note">📝 ${escapeHtml(order.notes)}</p>` : ''}
        </section>`;
}

function buildKasaAdisyonFootHtml(order, total) {
    const approveHtml = buildKasaAdisyonActionsHtml(order);
    const actionsBlock = approveHtml
        ? `<div class="lo-kasa-adisyon__actions">${approveHtml}</div>`
        : '';

    return `
            <footer class="lo-kasa-adisyon__foot">
                <div class="lo-kasa-adisyon__total-wrap">
                    <span class="lo-kasa-adisyon__total-label">Toplam</span>
                    <span class="lo-kasa-adisyon__total">${formatKasaMoney(total)}</span>
                </div>
                ${actionsBlock}
            </footer>`;
}

function mergeKasaOrderRecords(primary, secondary) {
    if (!primary && !secondary) return null;
    if (!primary) return secondary;
    if (!secondary) return primary;

    const primaryItems = primary.items?.length ?? 0;
    const secondaryItems = secondary.items?.length ?? 0;
    const rich = primaryItems >= secondaryItems ? primary : secondary;
    const other = rich === primary ? secondary : primary;

    return {
        ...other,
        ...rich,
        items: rich.items?.length ? rich.items : (other.items ?? []),
        total: rich.total ?? other.total ?? 0,
        status_label: rich.status_label ?? other.status_label,
    };
}

function pickKasaPrimaryOrder(orders) {
    const tableNum = window.HSP_KASA?.selectedTableNumber;
    const lockedId = window.HSP_KASA?.activeOrderId;
    const payloadOrder = window.HSP_KASA?.getTablePayload?.()?.order;

    if (!tableNum) return null;

    const fromPayload = payloadOrder?.id ? orderFromKasaPayload(payloadOrder, tableNum) : null;

    let fromPoll = null;
    if (lockedId) {
        fromPoll = orders.find((o) => Number(o.id) === Number(lockedId)) ?? null;
    }
    if (!fromPoll) {
        fromPoll =
            orders.find(
                (o) =>
                    Number(o.table) === Number(tableNum) &&
                    o.status !== 'cancelled' &&
                    (o.items?.length ?? 0) > 0,
            ) ?? null;
    }

    if (fromPayload && fromPoll && Number(fromPayload.id) !== Number(fromPoll.id)) {
        if (lockedId && Number(fromPoll.id) === Number(lockedId)) {
            return mergeKasaOrderRecords(fromPoll, fromPayload);
        }
        return mergeKasaOrderRecords(fromPayload, fromPoll);
    }

    return mergeKasaOrderRecords(fromPayload, fromPoll);
}

function renderKasaAdisyonView(orders, calls) {
    const order = pickKasaPrimaryOrder(filterOrders(orders, 'all'));
    const scopedCalls = calls.filter((c) => !isBillCall(c));
    const tableNum = window.HSP_KASA?.selectedTableNumber ?? null;
    const parts = [];
    const hasLineItems = (order?.items?.length ?? 0) > 0;

    syncKasaOrderChrome(order, tableNum);
    syncKasaPayPanel(order);

    if (hasLineItems) {
        parts.push(renderKasaCenterPanel(order));
    } else {
        parts.push(
            loEmpty(
                'Bu masada aktif adisyon yok',
                '📋',
                'Katalogu açıp ürün ekleyebilirsiniz.',
            ),
        );
    }

    if (scopedCalls.length) {
        parts.push(
            `<div class="lo-kasa-adisyon-calls"><p class="lo-kasa-adisyon-calls__title">Garson Çağrıları</p><div class="lo-feed-grid lo-feed-grid--calls">${scopedCalls.map((c) => renderCallCard(c, true)).join('')}</div></div>`,
        );
    }

    return `<div class="lo-kasa-adisyon-wrap" data-kasa-adisyon-wrap="1">${parts.join('')}</div>`;
}

function isBillCall(call) {
    return call.is_bill || call.type === 'bill_cash' || call.type === 'bill_card';
}

function callAssigneeHtml(call) {
    if (call.status === 'in_progress' && call.waiter_name) {
        const table = call.table ?? '—';
        return `<span class="lo-tag-assignee live-ops-call-assignee">👤 ${escapeHtml(call.waiter_name)} · Masa ${escapeHtml(String(table))} ile ilgileniyor</span>`;
    }
    return '';
}

function callActionsHtml(call, kasaMode = false) {
    const id = call.id;
    const assignee = callAssigneeHtml(call);

    if (kasaMode && !isBillCall(call)) {
        const forwardBtn =
            call.status === 'pending' && !call.forwarded_to_waiter
                ? `<button type="button" class="live-ops-forward-call lo-btn lo-btn--primary lo-btn--lg" data-call-id="${id}">Garsona Yönlendir</button>`
                : call.forwarded_to_waiter && call.status === 'pending'
                  ? `<span class="lo-tag-forwarded">✓ Garsona iletildi</span>`
                  : '';
        const closeBtn = `<button type="button" class="live-ops-resolve-call lo-btn lo-btn--success lo-btn--pill" data-call-id="${id}">Kapat</button>`;
        return `<div class="lo-card__actions">${assignee}${forwardBtn}${closeBtn}</div>`;
    }

    if (!isBillCall(call)) {
        return `<div class="lo-card__actions">${assignee}<button type="button" class="live-ops-resolve-call lo-btn lo-btn--success lo-btn--lg" data-call-id="${id}">Garsonu Gönder</button></div>`;
    }

    const forwardBtn = call.forwarded_to_waiter
        ? `<span class="lo-tag-forwarded">✓ Garsona iletildi</span>`
        : `<button type="button" class="live-ops-forward-call lo-btn lo-btn--primary lo-btn--lg" data-call-id="${id}">Garsona Yönlendir</button>`;

    const closeBtns = `<button type="button" class="live-ops-resolve-call lo-btn lo-btn--success" data-call-id="${id}">Kasaya Al?nd?</button>`;

    return `<div class="lo-card__actions">${assignee}${forwardBtn}${closeBtns}</div>`;
}

function renderCallCard(call, kasaMode = false) {
    const forwarded = (isBillCall(call) || call.type === 'waiter') && call.forwarded_to_waiter;
    const pulseClass = forwarded ? ' lo-card--call-done' : ' lo-card--call-pulse';
    const handling =
        call.status === 'in_progress' && call.waiter_name
            ? ` · ${escapeHtml(call.waiter_name)} masayla ilgileniyor`
            : '';

    return `
    <article class="lo-card lo-card--call${pulseClass}" data-call-id="${call.id}">
        <p class="lo-card__call-title">${escapeHtml(call.headline || `MASA ${call.table ?? '?'}`)}</p>
        <p class="lo-card__call-sub">Masa ${call.table ?? '—'} · ${escapeHtml(call.type_label || '')} · ${escapeHtml(call.created_at || '')}${forwarded ? ' · Garsona iletildi' : ''}${handling}</p>
        ${callActionsHtml(call, kasaMode)}
    </article>`;
}

function loEmpty(message, icon = '✨', hint = 'Yeni siparişler ve masa çağrıları burada anında görünür.') {
    return `<div class="lo-empty"><span class="lo-empty__icon">${icon}</span><p class="lo-empty__text">${message}</p><p class="lo-empty__hint">${hint}</p></div>`;
}

function completedRetentionHint(minutes) {
    if (!minutes || minutes <= 0) {
        return 'Elle kaldırılana kadar listede kalır';
    }
    if (minutes < 60) {
        return `${minutes} dakika`;
    }
    const hours = Math.round(minutes / 60);

    return hours === 1 ? '1 saat' : `${hours} saat`;
}

function renderCompletedToolbar(completedOrders, retentionMinutes) {
    if (!completedOrders.length) {
        return '';
    }

    const hint = completedRetentionHint(retentionMinutes);

    return `
    <div class="lo-completed-toolbar">
        <p class="lo-completed-toolbar__hint">${retentionMinutes > 0 ? `Kapalı adisyonlar ${hint} sonra canlı listeden kaldırılır · admin arşivinde kalır.` : 'Kapalı adisyonlar elle kaldırılana kadar listede kalır · admin arşivinde saklanır.'}</p>
        <button type="button" class="live-ops-dismiss-all-completed lo-btn lo-btn--outline lo-btn--sm">Listeden Temizle</button>
    </div>`;
}

function renderGrid(orders, calls, completedOrders, tab, options = {}) {
    const { kasaMode = false } = options;

    if (tab === 'calls') {
        const scopedCalls = kasaMode ? calls.filter((c) => !isBillCall(c)) : calls;
        if (!scopedCalls.length) {
            const msg = kasaMode ? 'Bu masada garson çağrısı yok' : 'Aktif masa çağrısı yok';
            const hint = kasaMode
                ? 'Müşteri QR menüden garson çağırdığında burada görünür.'
                : 'Müşteriler QR menüden garson veya hesap çağırabilir.';
            return loEmpty(msg, '🛎️', hint);
        }
        return `<div class="lo-feed-grid lo-feed-grid--calls">${scopedCalls.map((c) => renderCallCard(c, kasaMode)).join('')}</div>`;
    }

    if (tab === 'completed') {
        if (!completedOrders.length) {
            return loEmpty('Henüz tamamlanan sipariş yok', '✓', 'Tamamlanan siparişler bu sekmede listelenir.');
        }
        const cards = completedOrders.map((o) => renderOrderCard(o, 'all', { dismissible: true })).filter(Boolean).join('');
        const toolbar = renderCompletedToolbar(completedOrders, options.retentionMinutes ?? 0);

        return `${toolbar}<div class="lo-feed-grid">${cards}</div>`;
    }

    if (tab === 'all' && kasaMode) {
        return renderKasaAdisyonView(orders, calls);
    }

    if (tab === 'all') {
        const feed = buildMixedFeed(filterOrders(orders, 'all'), calls);
        if (!feed.length) {
            return loEmpty('Aktif sipariş veya çağrı yok', '✨', 'Yeni siparişler ve masa çağrıları burada anında görünür.');
        }
        const html = feed
            .map((item) =>
                item.kind === 'call' ? renderCallCard(item.data) : renderOrderCard(item.data, 'all'),
            )
            .filter(Boolean)
            .join('');
        return `<div class="lo-feed-grid">${html}</div>`;
    }

    const filtered = filterOrders(orders, tab);
    const cards = filtered.map((o) => renderOrderCard(o, tab)).filter(Boolean).join('');

    if (!cards) {
        const emptyMsg =
            tab === 'bar'
                ? 'Bekleyen içecek siparişi yok'
                : tab === 'kitchen'
                  ? 'Bekleyen mutfak siparişi yok'
                  : tab === 'nargile'
                    ? 'Bekleyen nargile siparişi yok'
                    : 'Aktif sipariş yok';
        const icon = tab === 'bar' ? '☕' : tab === 'kitchen' ? '🍽️' : tab === 'nargile' ? '💨' : '✨';
        const hint =
            tab === 'bar'
                ? 'Bar siparişleri hazırlandığında burada görünür.'
                : tab === 'kitchen'
                  ? 'Mutfak siparişleri burada anlık olarak listelenir.'
                  : tab === 'nargile'
                    ? 'Nargile siparişleri yalnızca bu panelde listelenir.'
                    : 'Siparişler geldiğinde bu alan otomatik güncellenir.';
        return loEmpty(emptyMsg, icon, hint);
    }

    return `<div class="lo-feed-grid">${cards}</div>`;
}

const root = document.getElementById('liveOrdersApp');
if (root) {
    const apiUrl = root.dataset.apiUrl;
    const dismissCompletedUrl = root.dataset.dismissCompletedUrl || '';
    const completedRetentionMinutes = Number(root.dataset.completedRetentionMinutes || 0);
    const statusUrlBase = root.dataset.statusUrl;
    const resolveCallUrlBase = root.dataset.resolveCallUrl;
    const csrf = root.dataset.csrf;
    const baseTitle = root.dataset.pageTitle || document.title;
    liveOpsShowPendingApproval = root.dataset.showPendingApproval !== '0';
    const restaurantId = root.dataset.restaurantId || '';
    const ordersChannelName = restaurantId ? `orders.${restaurantId}` : 'orders';
    const reverbCfg = {
        key: root.dataset.reverbKey || '',
        host: root.dataset.reverbHost || '127.0.0.1',
        port: Number(root.dataset.reverbPort || 8080),
        scheme: root.dataset.reverbScheme || 'http',
    };
    const grid = document.getElementById('liveOrdersGrid');
    const tableMapGrid = document.getElementById('liveTableMapGrid');
    const statusEl = document.getElementById('liveOrdersStatus');
    const clockEl = document.getElementById('liveOrdersClock');
    const tabs = document.querySelectorAll('.lo-tab');
    const badges = {
        kitchen: document.querySelector('[data-badge="kitchen"]'),
        bar: document.querySelector('[data-badge="bar"]'),
        nargile: document.querySelector('[data-badge="nargile"]'),
        calls: document.querySelector('[data-badge="calls"]'),
    };

    let activeTab = root.dataset.defaultTab || 'all';
    let ordersState = [];
    let completedOrdersState = [];
    let callsState = [];
    let dataFingerprint = '';
    let knownOrderIds = new Set();
    let knownCallIds = new Set();
    /** @type {Map<number, { kitchen: boolean, bar: boolean, nargile: boolean, kitchenDismissed: boolean, barDismissed: boolean, nargileDismissed: boolean }>} */
    const orderNotifications = new Map();
    /** @type {Set<number>} */
    const callNotifications = new Set();
    let tabBadges = { kitchen: 0, bar: 0, hookah: 0, service: 0, nargile: 0, calls: 0 };
    let titleAlertCount = 0;
    let initialized = false;
    let pollTimer = null;
    let failCount = 0;
    const intervalMs = root.dataset.kasaMode === '1' ? 8000 : 4000;
    const maxIntervalMs = 30000;
    let currentInterval = intervalMs;
    let lastStatusLine = '';
    let tablesState = [];
    let tablesFingerprint = '';
    let tableStructureFingerprint = '';
    let knownBusyTableIds = new Set();
    let echoClient = null;
    let realtimeConnected = false;
    const fallbackIntervalMs = root.dataset.kasaMode === '1' ? 50000 : 30000;
    let paintRaf = null;
    let pollDebounceTimer = null;
    let wsRefreshTimer = null;
    let kasaLocalOpsUntil = 0;

    function isKasaMode() {
        return root.dataset.kasaMode === '1';
    }

    /** Kasa ekranında toast gösterme; admin canlı panelde normal bildirim. */
    function toastOps(options) {
        if (isKasaMode()) return;
        showAdminToast(options);
    }

    function buildApiUrl() {
        if (!isKasaMode()) {
            return apiUrl;
        }

        const url = new URL(apiUrl, window.location.origin);
        url.searchParams.set('kasa', '1');
        url.searchParams.set('live_limit', '10');
        url.searchParams.set('calls_limit', '12');
        url.searchParams.set('completed_limit', activeTab === 'completed' ? '10' : '0');

        const focusTableId = window.HSP_KASA?.selectedTableId;
        if (focusTableId) {
            url.searchParams.set('focus_table_id', String(focusTableId));
        }

        const focusOrderId = window.HSP_KASA?.activeOrderId;
        if (focusOrderId) {
            url.searchParams.set('focus_order_id', String(focusOrderId));
        }

        return url.toString();
    }

    function holdKasaRefresh(ms = 4000) {
        if (!isKasaMode()) return;
        kasaLocalOpsUntil = Date.now() + ms;
    }

    function kasaRefreshPaused() {
        return isKasaMode() && Date.now() < kasaLocalOpsUntil;
    }

    function shouldPreferKasaOrderState(existing, incoming) {
        const existingItems = existing.items?.length ?? 0;
        const incomingItems = incoming.items?.length ?? 0;

        if (Number(existing.id) === Number(incoming.id)) {
            if (incoming.status === 'preparing' && ['delivered', 'ready'].includes(existing.status)) {
                return true;
            }

            if (
                existing.status === 'preparing' &&
                ['delivered', 'ready'].includes(incoming.status) &&
                incomingItems <= existingItems
            ) {
                return false;
            }
        }

        if (incomingItems > existingItems) {
            return true;
        }

        if (incomingItems < existingItems) {
            return false;
        }

        const existingRank = kasaStatusRank(existing.status);
        const incomingRank = kasaStatusRank(incoming.status);

        if (incomingRank !== existingRank) {
            return incomingRank >= existingRank;
        }

        return incomingItems >= existingItems;
    }

    function isExternalOrderForKasa(order, payload = {}) {
        if (!isKasaMode()) return true;
        if (payload?.silent) return false;
        if (kasaRefreshPaused() || window.HSP_KASA?.isLocalOp?.()) return false;
        if (!order) return true;

        if (order.source === 'qr' || order.status === 'pending_approval') {
            return true;
        }

        if (order.source === 'kasa') {
            return false;
        }

        if (order.source === 'waiter' && isKasaLockedTableOrder(order)) {
            return false;
        }

        return true;
    }

    function isKasaLockedTableOrder(order) {
        if (!isKasaMode() || !order) return false;

        const tableNum = window.HSP_KASA?.selectedTableNumber;
        const lockedId = window.HSP_KASA?.activeOrderId;

        if (lockedId && Number(order.id) === Number(lockedId)) {
            return true;
        }

        return !!(tableNum && Number(order.table) === Number(tableNum));
    }

    function enforceKasaOrderLock() {
        if (!isKasaMode()) return;

        const tableNum = window.HSP_KASA?.selectedTableNumber;
        const lockedId = window.HSP_KASA?.activeOrderId;
        const payload = window.HSP_KASA?.getTablePayload?.()?.order;

        if (!tableNum) return;

        if (lockedId) {
            ordersState = ordersState.filter(
                (o) => Number(o.table) !== Number(tableNum) || Number(o.id) === Number(lockedId),
            );
        }

        preserveKasaFocusedOrder();

        if (lockedId && payload?.id && Number(payload.id) === Number(lockedId)) {
            const normalized = orderFromKasaPayload(payload, tableNum);
            const idx = ordersState.findIndex((o) => Number(o.id) === Number(lockedId));
            if (idx >= 0) {
                ordersState[idx] = mergeKasaOrderRecords(ordersState[idx], normalized);
            } else {
                upsertOrder(normalized);
            }
        }
    }

    function patchKasaAdisyonDom(order, calls) {
        if (!grid || !order) return false;

        const card = grid.querySelector('[data-kasa-adisyon-root]');
        if (!card || Number(card.dataset.orderId) !== Number(order.id)) {
            return false;
        }

        card.dataset.status = order.status;

        const itemsEl = card.querySelector('.lo-kasa-order-detail__items, .lo-kasa-adisyon__items');
        if (itemsEl) {
            itemsEl.innerHTML = buildKasaOrderItemsHtml(order);
        }

        const noteEl = card.querySelector('.lo-kasa-adisyon__note');
        if (order.notes) {
            const noteText = `📝 ${order.notes}`;
            if (noteEl) {
                noteEl.textContent = noteText;
            } else {
                const p = document.createElement('p');
                p.className = 'lo-kasa-adisyon__note';
                p.textContent = noteText;
                card.appendChild(p);
            }
        } else {
            noteEl?.remove();
        }

        syncKasaOrderChrome(order, window.HSP_KASA?.selectedTableNumber ?? null);
        syncKasaPayPanel(order);

        const wrap = grid.querySelector('[data-kasa-adisyon-wrap]');
        const callsBlock = wrap?.querySelector('.lo-kasa-adisyon-calls');
        const scopedCalls = calls.filter((c) => !isBillCall(c));

        if (scopedCalls.length) {
            const callsHtml = `<div class="lo-kasa-adisyon-calls"><p class="lo-kasa-adisyon-calls__title">Garson Çağrıları</p><div class="lo-feed-grid lo-feed-grid--calls">${scopedCalls.map((c) => renderCallCard(c, true)).join('')}</div></div>`;
            if (callsBlock) {
                callsBlock.outerHTML = callsHtml;
            } else if (wrap) {
                wrap.insertAdjacentHTML('beforeend', callsHtml);
            }
        } else if (callsBlock) {
            callsBlock.remove();
        }

        return true;
    }

    function preserveKasaFocusedOrder() {
        if (!isKasaMode()) return;

        const tableNum = window.HSP_KASA?.selectedTableNumber;
        const lockedId = window.HSP_KASA?.activeOrderId;
        const payload = window.HSP_KASA?.getTablePayload?.()?.order;
        if (!tableNum) return;

        if (!payload?.id) {
            if (lockedId) {
                ordersState = ordersState.filter((o) => Number(o.id) !== Number(lockedId));
            } else {
                ordersState = ordersState.filter((o) => Number(o.table) !== Number(tableNum));
            }
            return;
        }

        if (lockedId && Number(payload.id) !== Number(lockedId)) {
            return;
        }

        const normalized = orderFromKasaPayload(payload, tableNum);
        const idx = ordersState.findIndex((o) => Number(o.id) === Number(normalized.id));

        if (idx >= 0) {
            const existing = ordersState[idx];
            if (!shouldPreferKasaOrderState(existing, normalized)) {
                return;
            }
            ordersState[idx] = mergeKasaOrderRecords(existing, normalized);
        } else {
            upsertOrder(normalized);
        }
    }

    function schedulePaint(force = false) {
        if (force) {
            dataFingerprint = '';
        }
        cancelAnimationFrame(paintRaf);
        paintRaf = requestAnimationFrame(() => {
            paint();
        });
    }

    function schedulePoll(forcePaint = false) {
        clearTimeout(pollDebounceTimer);
        const delay = isKasaMode() && !forcePaint ? 200 : 0;
        pollDebounceTimer = setTimeout(() => {
            if (kasaRefreshPaused() && !forcePaint) {
                return;
            }
            void poll(forcePaint);
        }, delay);
    }

    function scheduleWsRefresh(forcePaint = true) {
        if (kasaRefreshPaused()) {
            syncNotificationBadges();
            return;
        }

        if (isKasaMode() && window.HSP_KASA?.selectedTableId && !forcePaint) {
            syncNotificationBadges();
            return;
        }

        clearTimeout(wsRefreshTimer);
        wsRefreshTimer = setTimeout(() => {
            schedulePoll(forcePaint);
        }, isKasaMode() ? 250 : 150);
    }

    function kasaOrderVisualKey(order) {
        if (!order) return 'none';
        return JSON.stringify([
            order.id,
            order.status,
            order.payment_method,
            order.items?.map((i) => [i.id, i.quantity, i.unit_price, i.notes]) ?? [],
        ]);
    }

    function markTableBusy(tableId, busy, tableStatus = 'occupied') {
        if (!tableId) return;
        const idx = tablesState.findIndex((t) => Number(t.id) === Number(tableId));
        if (idx < 0) return;

        tablesState[idx] = {
            ...tablesState[idx],
            is_busy: !!busy,
            pending_approval: busy ? tablesState[idx].pending_approval : false,
            status: busy ? tableStatus : 'available',
            awaiting_payment: busy ? tablesState[idx].awaiting_payment : false,
        };
        tablesFingerprint = '';
        paintTableMap(tablesState);
        updateStatusLine();
    }

    function markTablePendingApproval(tableId, pending = true) {
        if (!tableId) return;
        const idx = tablesState.findIndex((t) => Number(t.id) === Number(tableId));
        if (idx < 0) return;

        tablesState[idx] = {
            ...tablesState[idx],
            is_busy: true,
            pending_approval: !!pending,
        };
        tablesFingerprint = '';
        paintTableMap(tablesState);
        updateStatusLine();
    }

    function markTableAwaitingPayment(tableId, awaiting = true) {
        if (!tableId) return;
        const idx = tablesState.findIndex((t) => Number(t.id) === Number(tableId));
        if (idx < 0) return;

        tablesState[idx] = {
            ...tablesState[idx],
            is_busy: true,
            awaiting_payment: !!awaiting,
        };
        tablesFingerprint = '';
        paintTableMap(tablesState);
        updateStatusLine();
    }

    function handleTableSwitch() {
        if (!isKasaMode()) return;

        const tableNum = window.HSP_KASA?.selectedTableNumber;
        if (tableNum) {
            ordersState = ordersState.filter((o) => Number(o.table) === Number(tableNum));
        }

        dataFingerprint = '';
        schedulePaint(true);
        window.HSP_KASA?.refreshDock?.();
    }

    function mergeKasaTableOrder(orderPayload, tableNumber) {
        if (!tableNumber) return;

        if (orderPayload?.table != null && Number(orderPayload.table) !== Number(tableNumber)) {
            return;
        }

        if (!orderPayload) {
            const hadOrder = ordersState.some((o) => Number(o.table) === Number(tableNumber));
            ordersState = ordersState.filter((o) => Number(o.table) !== Number(tableNumber));
            if (!hadOrder) return;
            dataFingerprint = '';
            schedulePaint(true);
            return;
        }

        const normalized = orderFromKasaPayload(orderPayload, tableNumber);
        const idx = ordersState.findIndex((o) => Number(o.id) === Number(normalized.id));

        if (idx >= 0 && !shouldPreferKasaOrderState(ordersState[idx], normalized)) {
            return;
        }

        const prevKey = idx >= 0 ? kasaOrderVisualKey(ordersState[idx]) : 'none';
        const nextKey = kasaOrderVisualKey(normalized);

        if (idx >= 0) {
            ordersState[idx] = mergeKasaOrderRecords(ordersState[idx], normalized);
        } else {
            upsertOrder(normalized);
        }

        if (prevKey === nextKey) return;

        dataFingerprint = '';
        schedulePaint(true);
    }

    function normalizeRealtimeOrder(raw) {
        if (!raw?.id) return null;

        const items = (raw.items || []).map((i) => ({
            id: i.id,
            name: i.name,
            quantity: i.quantity,
            notes: i.notes ?? null,
            type: i.type ?? 'kitchen',
        }));
        const types = new Set(items.map((i) => i.type));

        return mergeOrderStationFlags({
            id: raw.id,
            order_number: raw.order_number,
            status: raw.status,
            status_label: raw.status_label,
            source: raw.source,
            source_label: raw.source_label,
            is_waiter_order: !!raw.is_waiter_order,
            payment_method: raw.payment_method ?? null,
            table: raw.table ?? null,
            notes: raw.notes ?? null,
            total: raw.total,
            created_at: raw.created_at,
            created_at_iso: raw.created_at_iso ?? raw.updated_at,
            updated_at: raw.updated_at ?? new Date().toISOString(),
            items,
        }, raw);
    }

    function upsertOrder(order) {
        const idx = ordersState.findIndex((o) => o.id === order.id);
        if (idx >= 0) {
            ordersState[idx] = { ...ordersState[idx], ...order };
        } else {
            ordersState.unshift(order);
        }
        ordersState.sort((a, b) => String(b.updated_at).localeCompare(String(a.updated_at)));
    }

    function applyOrderStatusUpdate(payload) {
        const orderId = Number(payload?.order_id);
        const status = String(payload?.status || '');
        if (!Number.isFinite(orderId) || !status) return false;

        const order = ordersState.find((o) => o.id === orderId);
        if (!order) return false;

        order.status = status;
        order.updated_at = new Date().toISOString();
        if (payload?.status_label) {
            order.status_label = payload.status_label;
        }
        if (payload?.payment_method) {
            order.payment_method = payload.payment_method;
        }

        if (status === 'preparing' && isKasaMode()) {
            const tableNum = Number(payload?.table);
            const tableRow = tablesState.find((t) => Number(t.number) === tableNum);
            if (tableRow?.id) {
                markTablePendingApproval(tableRow.id, false);
                markTableBusy(tableRow.id, true, 'occupied');
            }
            const tableId = window.HSP_KASA?.selectedTableId;
            if (tableId) {
                markTableAwaitingPayment(tableId, false);
            }
            paintTableMap(tablesState);
        }

        if (status === 'delivered' || status === 'cancelled') {
            const keepForKasaPayment =
                isKasaMode() && status === 'delivered' && !payload?.payment_method && !order.payment_method;

            if (keepForKasaPayment) {
                if (payload?.status_label) {
                    order.status_label = payload.status_label;
                }
            } else {
                ordersState = ordersState.filter((o) => o.id !== orderId);
                orderNotifications.delete(orderId);
            }
        }

        return true;
    }

    function handleOrderCreated(payload) {
        const order = normalizeRealtimeOrder(payload?.order);
        if (!order) {
            if (!isKasaMode() || isExternalOrderForKasa(null, payload)) {
                scheduleWsRefresh(true);
            }
            return;
        }

        if (!isExternalOrderForKasa(order, payload)) {
            upsertOrder(order);
            knownOrderIds.add(order.id);
            if (order.id) {
                window.HSP_KASA?.lockActiveOrder?.(order.id);
            }

            if (isKasaMode()) {
                const tableNum = Number(order.table);
                const selectedNum = Number(window.HSP_KASA?.selectedTableNumber);
                if (tableNum && tableNum === selectedNum && payload?.order) {
                    window.HSP_KASA?.mergeOrderQuiet?.(payload.order);
                }
                const tableRow = tablesState.find((t) => Number(t.number) === tableNum);
                if (tableRow?.id) {
                    markTableBusy(tableRow.id, true);
                }
            }

            dataFingerprint = '';
            schedulePaint(true);
            updateStatusLine();
            return;
        }

        if (order.status === 'pending_approval') {
            if (!liveOpsShowPendingApproval) {
                return;
            }
            if (order.source === 'waiter' || order.source === 'kasa' || payload?.silent) {
                upsertOrder(order);
                knownOrderIds.add(order.id);
                dataFingerprint = '';
                schedulePaint(true);
                updateStatusLine();
                return;
            }
            upsertOrder(order);
            knownOrderIds.add(order.id);
            dataFingerprint = '';
            schedulePaint(true);
            updateStatusLine();

            const tableNum = Number(order.table);
            const tableRow = tablesState.find((t) => Number(t.number) === tableNum);
            if (tableRow?.id) {
                markTablePendingApproval(tableRow.id, true);
            }

            if (initialized && !payload?.silent && order.source === 'qr') {
                dingOrder();
            }

            if (!payload?.silent) {
                toastOps({
                    title: 'Yeni QR Sipariş',
                    message: `#${order.order_number} · Masa ${order.table ?? '—'} · Kasa onayı bekliyor`,
                    type: 'warning',
                    durationMs: 4000,
                });
            }
            return;
        }

        upsertOrder(order);
        knownOrderIds.add(order.id);
        orderNotifications.set(order.id, {
            kitchen: !!order.has_kitchen,
            bar: !!order.has_bar,
            nargile: !!order.has_nargile,
            kitchenDismissed: activeTab === 'kitchen',
            barDismissed: activeTab === 'bar',
            nargileDismissed: activeTab === 'nargile',
        });

        if (initialized && !payload?.silent && order.source !== 'waiter' && order.source !== 'kasa') {
            dingOrder();
        }

        syncNotificationBadges();
        dataFingerprint = '';
        schedulePaint(true);
        updateStatusLine();

        if (!payload?.silent && order.source !== 'waiter' && order.source !== 'kasa') {
            toastOps({
                title: 'Yeni Sipariş',
                message: `#${order.order_number} · Masa ${order.table ?? '—'}`,
                type: 'success',
                durationMs: 2500,
            });
        }
    }

    function setRealtimeInterval(connected) {
        realtimeConnected = connected;
        currentInterval = connected ? fallbackIntervalMs : intervalMs;
        lastStatusLine = '';
        updateStatusLine();
    }

    if (tableMapGrid) {
        tableMapGrid.querySelectorAll('[data-table-id]').forEach((chip) => {
            if (chip.dataset.tableBusy === '1') {
                knownBusyTableIds.add(Number(chip.dataset.tableId));
            }
        });
    }

    function tableHasBillCall(table) {
        return callsState.some(
            (c) =>
                !['completed', 'resolved'].includes(String(c.status)) &&
                ['bill_cash', 'bill_card', 'bill'].includes(String(c.type)) &&
                Number(c.table) === Number(table.number),
        );
    }

    function tableAwaitingPayment(table) {
        if (!table) return false;
        if (table.awaiting_payment) return true;

        return ordersState.some(
            (o) =>
                Number(o.table) === Number(table.number) &&
                o.status === 'delivered' &&
                !o.payment_method,
        );
    }

    function loTableState(table) {
        if (!table.is_active) {
            return { mod: 'off', label: 'Kapalı' };
        }
        if (isKasaMode()) {
            if (table.pending_approval) {
                return { mod: 'pending', label: 'Onay Bekliyor' };
            }
            if (table.is_busy) {
                return { mod: 'busy', label: 'Dolu' };
            }
            return { mod: 'free', label: 'Boş' };
        }
        if (table.status === 'payment_processing' || tableHasBillCall(table) || tableAwaitingPayment(table)) {
            return { mod: 'pay', label: 'Ödeme' };
        }
        if (table.is_busy) {
            return { mod: 'busy', label: 'Dolu' };
        }
        return { mod: 'free', label: 'Boş' };
    }

    function tableChipTitle(table) {
        const state = loTableState(table);
        return `Masa ${table.number} · ${state.label}`;
    }

    function sortTablesByNumber(tables) {
        return [...tables].sort((a, b) => {
            const na = Number.parseInt(String(a.number).replace(/\D/g, ''), 10) || 0;
            const nb = Number.parseInt(String(b.number).replace(/\D/g, ''), 10) || 0;
            if (na !== nb) return na - nb;
            return String(a.number).localeCompare(String(b.number), 'tr');
        });
    }

    function patchTableMap(tables) {
        if (!tableMapGrid) return;

        const kasaMode = isKasaMode();
        const selectedId = window.HSP_KASA?.selectedTableId ?? null;

        tables.forEach((t) => {
            const chip = tableMapGrid.querySelector(`[data-table-id="${t.id}"]`);
            if (!chip) return;

            const state = loTableState(t);
            const selected = kasaMode && selectedId === t.id ? ' lo-table--selected' : '';
            chip.className = `lo-table lo-table--${state.mod}${selected}`;
            chip.dataset.tableBusy = t.is_busy ? '1' : '0';
            chip.dataset.tablePending = t.pending_approval ? '1' : '0';
            chip.dataset.tableStatus = String(t.status ?? '');
            chip.dataset.tableActive = t.is_active ? '1' : '0';
            chip.title = tableChipTitle(t);
            const labelEl = chip.querySelector('.lo-table__label');
            const numEl = chip.querySelector('.lo-table__num');
            if (labelEl) labelEl.textContent = state.label;
            if (numEl) numEl.textContent = `Masa ${t.number}`;
        });
    }

    function renderTableMap(tables) {
        if (!tableMapGrid || !tables?.length) return;

        tables = sortTablesByNumber(tables);

        const useLoGrid = tableMapGrid.classList.contains('lo-table-grid') || tableMapGrid.dataset.loGrid === '1';
        const kasaMode = root.dataset.kasaMode === '1';
        const selectedId = window.HSP_KASA?.selectedTableId ?? null;

        if (useLoGrid) {
            tableMapGrid.innerHTML = tables
                .map((t) => {
                    const state = loTableState(t);
                    const selected = selectedId === t.id ? ' lo-table--selected' : '';
                    const tag = kasaMode ? 'button' : 'div';
                    const btnType = kasaMode ? ' type="button"' : '';

                    return `
            <${tag}${btnType}
                class="lo-table lo-table--${state.mod}${selected}"
                data-table-id="${t.id}"
                data-table-number="${t.number}"
                data-table-busy="${t.is_busy ? '1' : '0'}"
                data-table-pending="${t.pending_approval ? '1' : '0'}"
                data-table-status="${escapeHtml(String(t.status ?? ''))}"
                data-table-active="${t.is_active ? '1' : '0'}"
                title="${escapeHtml(tableChipTitle(t))}"
            >
                <span class="lo-table__num">Masa ${escapeHtml(String(t.number))}</span>
                <span class="lo-table__label">${escapeHtml(state.label)}</span>
            </${tag}>`;
                })
                .join('');
            return;
        }

        tableMapGrid.innerHTML = tables
            .map((t) => {
                const state = loTableState(t);
                const chipMod = state.mod === 'pay' ? 'busy' : state.mod === 'free' ? 'on' : state.mod;
                return `
            <div
                class="live-table-chip flex flex-col items-center justify-center rounded-xl border px-1 py-2 text-center transition live-table-chip--${chipMod === 'on' ? 'on' : chipMod}"
                data-table-id="${t.id}"
                data-table-busy="${t.is_busy ? '1' : '0'}"
                data-table-pending="${t.pending_approval ? '1' : '0'}"
                data-table-active="${t.is_active ? '1' : '0'}"
                title="${escapeHtml(tableChipTitle(t))}"
            >
                <span class="text-[10px] font-medium uppercase tracking-wide opacity-70">Masa</span>
                <span class="text-lg font-bold leading-none">${escapeHtml(String(t.number))}</span>
            </div>`;
            })
            .join('');
    }

    function paintTableMap(tables, flashNewBusy = false) {
        if (!tableMapGrid) return;

        const fp = JSON.stringify({
            tables: tables.map((t) => [t.id, t.is_busy, t.is_active, t.status]),
            payments: callsState
                .filter(
                    (c) =>
                        !['completed', 'resolved'].includes(String(c.status)) &&
                        ['bill_cash', 'bill_card', 'bill'].includes(String(c.type)),
                )
                .map((c) => [c.id, c.table, c.status]),
        });
        if (fp === tablesFingerprint && !flashNewBusy) return;

        const structureFp = JSON.stringify(tables.map((t) => t.id));
        const canPatch =
            isKasaMode() &&
            (tableMapGrid.classList.contains('lo-table-grid') ||
                tableMapGrid.classList.contains('lo-kasa-table-grid') ||
                tableMapGrid.dataset.loGrid === '1') &&
            tableMapGrid.children.length === tables.length &&
            structureFp === tableStructureFingerprint;

        const newBusy = tables.filter((t) => t.is_busy).map((t) => t.id);
        const newlyBusy = flashNewBusy
            ? newBusy.filter((id) => !knownBusyTableIds.has(id))
            : [];

        if (canPatch) {
            patchTableMap(tables);
        } else {
            renderTableMap(tables);
            tableStructureFingerprint = structureFp;
        }

        tablesFingerprint = fp;
        knownBusyTableIds = new Set(newBusy);

        if (newlyBusy.length > 0) {
            newlyBusy.forEach((id) => {
                const chip = tableMapGrid.querySelector(`[data-table-id="${id}"]`);
                chip?.classList.add('lo-table--flash');
                setTimeout(() => chip?.classList.remove('lo-table--flash'), 700);
            });
        }
    }

    function refreshDocumentTitle() {
        if (document.hidden && titleAlertCount > 0) {
            const label =
                titleAlertCount === 1 ? '1 Yeni Bildirim' : `${titleAlertCount} Yeni Bildirim`;
            document.title = `(${label}!) ${baseTitle}`;
        } else {
            document.title = baseTitle;
        }
    }

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            titleAlertCount = 0;
            document.title = baseTitle;
        } else {
            refreshDocumentTitle();
        }
    });

    function tickClock() {
        const text = new Date().toLocaleTimeString('tr-TR', {
            hour: '2-digit',
            minute: '2-digit',
        });
        if (clockEl) clockEl.textContent = text;
    }
    tickClock();
    setInterval(tickClock, 1000);

    function updateBadgesUI() {
        ['kitchen', 'bar', 'hookah', 'service', 'nargile', 'calls'].forEach((key) => {
            const el = badges[key];
            if (!el) return;
            const n = tabBadges[key];
            el.textContent = n > 99 ? '99+' : String(n);
            el.classList.toggle('hidden', n <= 0);
        });
    }

    function dismissNotificationsForTab(tab) {
        if (tab === 'kitchen') {
            for (const entry of orderNotifications.values()) {
                entry.kitchenDismissed = true;
            }
        } else if (tab === 'bar') {
            for (const entry of orderNotifications.values()) {
                entry.barDismissed = true;
            }
        } else if (tab === 'hookah' || tab === 'nargile') {
            for (const entry of orderNotifications.values()) {
                entry.nargileDismissed = true;
                entry.hookahDismissed = true;
            }
        } else if (tab === 'service') {
            for (const entry of orderNotifications.values()) {
                entry.serviceDismissed = true;
            }
        } else if (tab === 'calls') {
            callNotifications.clear();
        }
        syncNotificationBadges();
    }

    function syncNotificationBadges() {
        let kitchen = 0;
        let bar = 0;
        let hookah = 0;
        let service = 0;
        let nargile = 0;
        let calls = 0;

        for (const entry of orderNotifications.values()) {
            if (entry.kitchen && !entry.kitchenDismissed) {
                kitchen += 1;
            }
            if (entry.bar && !entry.barDismissed) {
                bar += 1;
            }
            if (entry.nargile && !entry.nargileDismissed) {
                nargile += 1;
            }
            if (entry.hookah && !entry.hookahDismissed) {
                hookah += 1;
            }
            if (entry.service && !entry.serviceDismissed) {
                service += 1;
            }
        }

        if (activeTab !== 'calls') {
            calls = callNotifications.size;
        }

        tabBadges = { kitchen, bar, hookah, service, nargile, calls };
        updateBadgesUI();

        if (document.hidden) {
            titleAlertCount = kitchen + bar + hookah + service + nargile + calls;
            refreshDocumentTitle();
        } else if (titleAlertCount > 0) {
            titleAlertCount = 0;
            refreshDocumentTitle();
        }
    }

    function processUpdates(orders, calls) {
        const currentOrderIds = new Set(orders.map((o) => o.id));
        const currentCallIds = new Set(calls.map((c) => c.id));

        for (const id of orderNotifications.keys()) {
            if (!currentOrderIds.has(id)) {
                orderNotifications.delete(id);
            }
        }

        for (const id of callNotifications) {
            if (!currentCallIds.has(id)) {
                callNotifications.delete(id);
            }
        }

        let newOrders = 0;
        let newCalls = 0;

        for (const order of orders) {
            if (!knownOrderIds.has(order.id)) {
                if (isKasaMode() && !isExternalOrderForKasa(order)) {
                    knownOrderIds.add(order.id);
                    continue;
                }
                newOrders += 1;
                orderNotifications.set(order.id, {
                    kitchen: !!order.has_kitchen,
                    bar: !!order.has_bar,
                    hookah: !!(order.has_hookah || order.has_nargile),
                    service: !!(order.has_service || order.has_retail),
                    nargile: !!(order.has_hookah || order.has_nargile),
                    kitchenDismissed: activeTab === 'kitchen',
                    barDismissed: activeTab === 'bar',
                    hookahDismissed: activeTab === 'hookah' || activeTab === 'nargile',
                    serviceDismissed: activeTab === 'service',
                    nargileDismissed: activeTab === 'hookah' || activeTab === 'nargile',
                });
            } else {
                const entry = orderNotifications.get(order.id);
                if (entry) {
                    entry.kitchen = !!order.has_kitchen;
                    entry.bar = !!order.has_bar;
                    entry.nargile = !!order.has_nargile;
                }
            }
        }

        for (const call of calls) {
            if (!knownCallIds.has(call.id)) {
                newCalls += 1;
                callNotifications.add(call.id);
            }
        }

        if (initialized) {
            if (newCalls > 0) {
                dingCall();
            } else if (newOrders > 0 && !(isKasaMode() && kasaRefreshPaused())) {
                dingOrder();
            }
        }

        knownOrderIds = currentOrderIds;
        knownCallIds = currentCallIds;
        initialized = true;
        syncNotificationBadges();
    }

    function updateStatusLine() {
        const busyCount = tablesState.filter((t) => t.is_busy).length;
        const wsPrefix = realtimeConnected ? 'WebSocket · ' : '';
        const line = `${wsPrefix}${busyCount} aktif masa · ${ordersState.length} canlı · ${completedOrdersState.length} tamamlanan · ${callsState.length} çağrı · ${new Date().toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' })}`;
        if (line === lastStatusLine || !statusEl) return;
        lastStatusLine = line;
        statusEl.textContent = line;
    }

    function refreshOrderAgeLabels() {
        if (!grid) return;
        grid.querySelectorAll('.live-ops-order-card[data-created-at]').forEach((card) => {
            const iso = card.dataset.createdAt;
            const status = card.dataset.status;
            const ageEl = card.querySelector('.live-ops-age');
            if (ageEl) {
                ageEl.textContent = formatRelativeAge(iso) || ageEl.textContent;
            }
            const overdue =
                ['pending', 'preparing'].includes(status) &&
                parseIso(iso) &&
                (Date.now() - parseIso(iso).getTime()) / 60000 >= OVERDUE_MINUTES;
            card.classList.toggle('live-ops-order-card--overdue', !!overdue);
        });
    }

    function handleCallUpdated(payload) {
        const call = payload?.call;
        if (!call?.id) {
            scheduleWsRefresh(true);
            return;
        }

        if (call.status === 'completed') {
            callsState = callsState.filter((c) => c.id !== call.id);
            callNotifications.delete(Number(call.id));
        } else {
            const idx = callsState.findIndex((c) => c.id === call.id);
            const prev = idx >= 0 ? callsState[idx] : null;
            if (idx >= 0) {
                callsState[idx] = { ...callsState[idx], ...call };
            } else {
                callsState.unshift(call);
                if (initialized) {
                    dingCall();
                }
                callNotifications.add(Number(call.id));
            }

            if (
                initialized &&
                isKasaMode() &&
                call.status === 'in_progress' &&
                call.waiter_name &&
                prev?.status !== 'in_progress'
            ) {
                toastOps({
                    title: 'Garson Masa Başında',
                    message: `${call.waiter_name} · Masa ${call.table ?? '—'} ile ilgileniyor`,
                    type: 'info',
                    durationMs: 4000,
                });
            }
        }

        syncNotificationBadges();
        dataFingerprint = '';
        schedulePaint(true);
        updateStatusLine();
    }

    function scopeDataForKasa(orders, calls, completed) {
        const tableNum = window.HSP_KASA?.selectedTableNumber;
        if (!tableNum) {
            return { orders: [], calls: [], completed: [] };
        }

        const matchTable = (row) => Number(row.table) === Number(tableNum);

        return {
            orders: orders.filter(matchTable),
            calls: calls.filter(matchTable),
            completed: completed.filter(matchTable),
        };
    }

    function paint() {
        const kasaMode = root.dataset.kasaMode === '1';
        let viewOrders = ordersState;
        let viewCalls = callsState;
        let viewCompleted = completedOrdersState;

        if (kasaMode) {
            if (!window.HSP_KASA?.selectedTableNumber) {
                dataFingerprint = `kasa-empty:${activeTab}`;
                grid.innerHTML = loEmpty(
                    'Lütfen işlem yapmak için soldan bir masa seçiniz',
                    '🪑',
                    '',
                );
                syncKasaOrderChrome(null, null);
                syncKasaPayPanel(null);
                bindButtons();
                refreshOrderAgeLabels();
                return true;
            }

            const scoped = scopeDataForKasa(ordersState, callsState, completedOrdersState);
            viewOrders = scoped.orders;
            viewCalls = scoped.calls;
            viewCompleted = scoped.completed;
        }

        const fp = kasaMode
            ? buildKasaViewFingerprint(viewOrders, viewCalls, activeTab)
            : buildViewFingerprint(viewOrders, viewCalls, viewCompleted, activeTab);
        if (fp === dataFingerprint) {
            refreshOrderAgeLabels();
            return false;
        }

        if (kasaMode && activeTab === 'all' && window.HSP_KASA?.selectedTableNumber) {
            const order = pickKasaPrimaryOrder(filterOrders(viewOrders, 'all'));
            if (!order?.items?.length) {
                dataFingerprint = '';
            } else if (patchKasaAdisyonDom(order, viewCalls)) {
                dataFingerprint = fp;
                bindButtons();
                refreshOrderAgeLabels();
                return true;
            }
        }

        dataFingerprint = fp;
        grid.innerHTML = renderGrid(viewOrders, viewCalls, viewCompleted, activeTab, {
            kasaMode,
            retentionMinutes: completedRetentionMinutes,
        });
        bindButtons();
        refreshOrderAgeLabels();
        return true;
    }

    const dataRefreshCallbacks = [];

    function onDataRefresh(fn) {
        dataRefreshCallbacks.push(fn);
    }

    function notifyDataRefresh() {
        dataRefreshCallbacks.forEach((fn) => fn());
    }

    function getCallsForTable(tableNum) {
        if (!tableNum) return [];
        return callsState.filter(
            (c) =>
                Number(c.table) === Number(tableNum) &&
                !['completed', 'resolved'].includes(String(c.status)),
        );
    }

    function getUnpaidOrderForTable(tableNum) {
        if (!tableNum) return null;
        return (
            ordersState.find(
                (o) =>
                    Number(o.table) === Number(tableNum) &&
                    o.status === 'delivered' &&
                    !o.payment_method,
            ) ?? null
        );
    }

    async function forwardCall(callId) {
        const res = await fetch(`${resolveCallUrlBase}/${callId}/forward`, {
            method: 'PATCH',
            headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
        });
        const data = await res.json().catch(() => ({}));
        if (res.ok && data.success !== false) {
            toastOps({
                title: 'Garsona Yönlendirildi',
                message: data.message || 'Garson paneline bildirim gönderildi',
                type: 'success',
            });
            await poll(true);
        } else {
            toastOps({ title: 'Yönlendirilemedi', message: data.message || 'Tekrar deneyin', type: 'error' });
        }
    }

    async function resolveCallById(callId) {
        const res = await fetch(`${resolveCallUrlBase}/${callId}/resolve`, {
            method: 'PATCH',
            headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
        });
        if (res.ok) await poll(true);
    }

    async function closeCallWithPayment(callId, paymentMethod) {
        const res = await fetch(`${resolveCallUrlBase}/${callId}/resolve`, {
            method: 'PATCH',
            headers: {
                'X-CSRF-TOKEN': csrf,
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ payment_method: paymentMethod }),
        });
        const data = await res.json().catch(() => ({}));
        if (res.ok && data.success !== false) {
            toastOps({
                title: 'Hesap Kapatıldı',
                message: `${paymentMethod === 'card' ? 'Kart' : 'Nakit'} · ${data.message || ''}`.trim(),
                type: 'info',
            });
            await poll(true);
        } else {
            toastOps({ title: 'Kapatılamadı', message: data.message || 'Tekrar deneyin', type: 'error' });
        }
    }

    async function patchOrderStatus(orderId, status) {
        const res = await fetch(`${statusUrlBase}/${orderId}/status`, {
            method: 'PATCH',
            headers: {
                'X-CSRF-TOKEN': csrf,
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ status }),
        });
        const data = await res.json().catch(() => ({}));

        if (!res.ok || data.success === false) {
            throw new Error(data.message || 'Durum güncellenemedi.');
        }

        if (isKasaMode()) {
            holdKasaRefresh(6000);
            const order = ordersState.find((o) => String(o.id) === String(orderId));
            const nextStatus = data.status ?? status;
            const nextLabel = data.status_label ?? order?.status_label ?? nextStatus;
            if (order) {
                order.status = nextStatus;
                order.status_label = nextLabel;
                order.updated_at = new Date().toISOString();
            }
            window.HSP_KASA?.patchTableOrder?.({
                status: nextStatus,
                status_label: nextLabel,
            });
            dataFingerprint = '';
            tablesFingerprint = '';
            schedulePaint(true);
            paintTableMap(tablesState);
        }

        return data;
    }

    async function closeOrderWithPayment(orderId, paymentMethod) {
        const res = await fetch(`${statusUrlBase}/${orderId}/status`, {
            method: 'PATCH',
            headers: {
                'X-CSRF-TOKEN': csrf,
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                status: 'delivered',
                payment_method: paymentMethod,
                payment_only: true,
            }),
        });
        const data = await res.json().catch(() => ({}));
        if (res.ok && data.success !== false) {
            toastOps({
                title: 'Sipariş kapandı',
                message: paymentMethod === 'card' ? 'Kart ile ödendi' : 'Nakit ile ödendi',
                type: 'info',
            });
            await poll(true);
        } else {
            toastOps({ title: 'Ödeme kaydedilemedi', message: data.message || 'Tekrar deneyin', type: 'error' });
        }
    }

    function bindButtons() {
        grid.querySelectorAll('.live-ops-status-btn').forEach((btn) => {
            btn.onclick = async () => {
                const orderId = btn.dataset.orderId;
                const status = btn.dataset.status;
                const payload = { status };
                if (btn.dataset.paymentMethod) {
                    payload.payment_method = btn.dataset.paymentMethod;
                }
                if (btn.dataset.paymentOnly === '1') {
                    payload.payment_only = true;
                }
                btn.disabled = true;
                if (isKasaMode()) {
                    holdKasaRefresh(6000);
                }
                try {
                    const res = await fetch(`${statusUrlBase}/${orderId}/status`, {
                        method: 'PATCH',
                        headers: {
                            'X-CSRF-TOKEN': csrf,
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(payload),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (res.ok && data.success !== false) {
                        const order = ordersState.find((o) => String(o.id) === String(orderId));
                        if (isKasaMode() && !payload.payment_only) {
                            holdKasaRefresh(6000);
                            const nextStatus = data.status ?? status;
                            const nextLabel = data.status_label ?? order?.status_label ?? nextStatus;
                            if (order) {
                                order.status = nextStatus;
                                order.status_label = nextLabel;
                                order.updated_at = new Date().toISOString();
                            }
                            window.HSP_KASA?.patchTableOrder?.({
                                status: nextStatus,
                                status_label: nextLabel,
                            });
                            dataFingerprint = '';
                            schedulePaint(true);
                            return;
                        }
                        if (payload.payment_only) {
                            orderNotifications.delete(Number(orderId));
                            syncNotificationBadges();
                            toastOps({
                                title: 'Sipariş kapandı',
                                message: order
                                    ? `#${order.order_number} · ${payload.payment_method === 'card' ? 'Kart' : 'Nakit'}`
                                    : data.message || 'Ödeme kaydedildi',
                                type: 'info',
                            });
                        } else if (status === 'preparing') {
                            toastOps({
                                title: 'Hazırlanıyor',
                                message: order
                                    ? `#${order.order_number}${order.table ? ` · Masa ${order.table}` : ''}`
                                    : 'Sipariş mutfağa iletildi',
                                hint: 'Mutfak ve bar ekranında bildirim düşer',
                                type: 'success',
                            });
                        } else if (status === 'ready') {
                            toastOps({
                                title: 'Garsona Bildirildi',
                                message: order
                                    ? `#${order.order_number} · Masa ${order.table ?? '—'}`
                                    : 'Sipariş hazır bildirimi gönderildi',
                                hint: 'Garson ekranında masa ve ürün bildirimi açıldı',
                                type: 'success',
                            });
                        } else if (status === 'delivered') {
                            toastOps({
                                title: 'Afiyet Olsun',
                                message: order ? `#${order.order_number} masaya gitti` : 'Teslim edildi',
                                hint: 'Ödeme için Nakit veya Kart seçin',
                                type: 'success',
                            });
                        }
                        await poll(true);
                    } else {
                        toastOps({
                            title: 'İşlem yapılamadı',
                            message: data.message || 'Tekrar deneyin',
                            type: 'error',
                        });
                    }
                } finally {
                    btn.disabled = false;
                }
            };
        });

        grid.querySelectorAll('.live-ops-item-status').forEach((btn) => {
            btn.onclick = async (event) => {
                event.stopPropagation();
                const itemId = btn.dataset.itemId;
                const status = btn.dataset.itemStatus;
                const url = window.HSP_KASA?.itemStatusUrl;
                if (!url || !itemId || !status) return;
                btn.disabled = true;
                try {
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrf,
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ item_id: itemId, status }),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (res.ok && data.success !== false) {
                        toastOps({
                            title: status === 'ready' ? 'Ürün Hazır' : 'Hazırlanıyor',
                            message: data.message || 'Kalem durumu güncellendi.',
                            type: 'success',
                            durationMs: 1800,
                        });
                        await poll(true);
                    } else {
                        toastOps({
                            title: 'İşlem yapılamadı',
                            message: data.message || 'Tekrar deneyin',
                            type: 'error',
                        });
                    }
                } finally {
                    btn.disabled = false;
                }
            };
        });

        grid.querySelectorAll('.live-ops-resolve-call').forEach((btn) => {
            btn.onclick = async () => {
                const callId = btn.dataset.callId;
                btn.disabled = true;
                try {
                    const res = await fetch(`${resolveCallUrlBase}/${callId}/resolve`, {
                        method: 'PATCH',
                        headers: {
                            'X-CSRF-TOKEN': csrf,
                            Accept: 'application/json',
                        },
                    });
                    if (res.ok) {
                        await poll(true);
                    }
                } finally {
                    btn.disabled = false;
                }
            };
        });

        grid.querySelectorAll('.live-ops-forward-call').forEach((btn) => {
            btn.onclick = async () => {
                const callId = btn.dataset.callId;
                btn.disabled = true;
                try {
                    const res = await fetch(`${resolveCallUrlBase}/${callId}/forward`, {
                        method: 'PATCH',
                        headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                    });
                    const data = await res.json().catch(() => ({}));
                    if (res.ok && data.success !== false) {
                        toastOps({
                            title: 'Garsona Yönlendirildi',
                            message: 'POS / masa bildirimi gönderildi',
                            type: 'success',
                            durationMs: 2200,
                        });
                        await poll(true);
                    } else {
                        toastOps({
                            title: 'Yönlendirilemedi',
                            message: data.message || 'Tekrar deneyin',
                            type: 'error',
                        });
                    }
                } finally {
                    btn.disabled = false;
                }
            };
        });

        grid.querySelectorAll('.live-ops-close-call').forEach((btn) => {
            btn.onclick = async () => {
                const callId = btn.dataset.callId;
                const paymentMethod = btn.dataset.paymentMethod || 'cash';
                btn.disabled = true;
                try {
                    const res = await fetch(`${resolveCallUrlBase}/${callId}/resolve`, {
                        method: 'PATCH',
                        headers: {
                            'X-CSRF-TOKEN': csrf,
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ payment_method: paymentMethod }),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (res.ok && data.success !== false) {
                        callNotifications.delete(Number(callId));
                        syncNotificationBadges();
                        toastOps({
                            title: 'Hesap Kapatıldı',
                            message: `${paymentMethod === 'card' ? 'Kart' : 'Nakit'} · ${data.message || ''}`.trim(),
                            type: 'info',
                            durationMs: 2400,
                        });
                        await poll(true);
                    } else {
                        toastOps({
                            title: 'Kapatılamadı',
                            message: data.message || 'Tekrar deneyin',
                            type: 'error',
                        });
                    }
                } finally {
                    btn.disabled = false;
                }
            };
        });

        grid.querySelectorAll('.live-ops-dismiss-completed').forEach((btn) => {
            btn.onclick = async () => {
                if (!dismissCompletedUrl) return;
                const orderId = btn.dataset.orderId;
                btn.disabled = true;
                try {
                    const res = await fetch(`${dismissCompletedUrl}/${orderId}`, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                    });
                    const data = await res.json().catch(() => ({}));
                    if (res.ok && data.success !== false) {
                        completedOrdersState = completedOrdersState.filter(
                            (o) => String(o.id) !== String(orderId),
                        );
                        dataFingerprint = '';
                        paint();
                        toastOps({
                            title: 'Listeden kaldırıldı',
                            message: data.message || 'Admin arşivinde görünmeye devam eder',
                            type: 'info',
                            durationMs: 2000,
                        });
                    } else {
                        toastOps({
                            title: 'Kaldırılamadı',
                            message: data.message || 'Tekrar deneyin',
                            type: 'error',
                        });
                    }
                } finally {
                    btn.disabled = false;
                }
            };
        });

        const dismissAllBtn = grid.querySelector('.live-ops-dismiss-all-completed');
        if (dismissAllBtn) {
            dismissAllBtn.onclick = async () => {
                if (!dismissCompletedUrl) return;
                if (!window.confirm('Listedeki tamamlanan adisyonlar canlı panelden kaldırılsın mı? (Admin arşivinde kalır)')) {
                    return;
                }
                dismissAllBtn.disabled = true;
                try {
                    const kasaMode = root.dataset.kasaMode === '1';
                    const tableId = window.HSP_KASA?.selectedTableId;
                    let url = dismissCompletedUrl;
                    if (kasaMode && tableId) {
                        url = `${dismissCompletedUrl}?table_id=${tableId}`;
                    }

                    const res = await fetch(url, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                    });
                    const data = await res.json().catch(() => ({}));
                    if (res.ok && data.success !== false) {
                        completedOrdersState = [];
                        dataFingerprint = '';
                        paint();
                        toastOps({
                            title: 'Liste temizlendi',
                            message: data.message || 'Adisyonlar admin arşivinde duruyor',
                            type: 'success',
                        });
                        await poll(true);
                    } else {
                        toastOps({
                            title: 'Temizlenemedi',
                            message: data.message || 'Tekrar deneyin',
                            type: 'error',
                        });
                    }
                } finally {
                    dismissAllBtn.disabled = false;
                }
            };
        }
    }

    function initRealtimeListeners() {
        echoClient = createEchoClient(reverbCfg);
        if (!echoClient) return;

        const connection = echoClient.connector?.pusher?.connection;
        if (connection) {
            connection.bind('connected', () => setRealtimeInterval(true));
            connection.bind('disconnected', () => {
                setRealtimeInterval(false);
                schedulePoll(true);
            });
            if (connection.state === 'connected') {
                setRealtimeInterval(true);
            }
        }

        echoClient.channel(ordersChannelName).listen('.OrderCreated', (payload) => {
            handleOrderCreated(payload);
        });

        echoClient.channel(ordersChannelName).listen('.OrderStatusUpdated', (payload) => {
            const status = String(payload?.status || '');
            const orderId = Number(payload?.order_id);

            if (isKasaMode()) {
                const lockedId = window.HSP_KASA?.activeOrderId;

                if (lockedId && orderId === Number(lockedId)) {
                    applyOrderStatusUpdate(payload);
                    if (status === 'cancelled') {
                        window.HSP_KASA?.clearTableOrder?.();
                    } else {
                        window.HSP_KASA?.patchTableOrder?.({
                            status: payload.status,
                            status_label: payload.status_label ?? status,
                            source: payload.source ?? window.HSP_KASA?.getTablePayload?.()?.order?.source,
                        });
                        window.HSP_KASA?.refreshDock?.();
                    }
                    if (status === 'delivered') {
                        window.HSP_KASA?.closeCatalog?.();
                        window.HSP_KASA?.refreshDock?.();
                    }
                    return;
                }

                if (kasaRefreshPaused() || window.HSP_KASA?.isLocalOp?.()) {
                    return;
                }

                if (!isExternalOrderForKasa({ id: orderId, table: payload?.table, source: payload?.source, status }, payload)) {
                    return;
                }

                const selectedNum = Number(window.HSP_KASA?.selectedTableNumber);
                if (status === 'preparing' && selectedNum && Number(payload?.table) === selectedNum) {
                    window.HSP_KASA?.refreshTableState?.();
                }

                scheduleWsRefresh(false);
                return;
            }

            if (status === 'ready') {
                toastOps({
                    title: 'Mutfakta Hazır',
                    message: `#${payload?.order_number ?? payload?.order_id ?? ''} · Masa ${payload?.table ?? '—'}`,
                    type: 'success',
                    durationMs: 2500,
                });
            }
            if (status === 'delivered') {
                toastOps({
                    title: 'Teslim Edildi',
                    message: `#${payload?.order_number ?? payload?.order_id ?? ''} kapatılıyor`,
                    type: 'info',
                    durationMs: 2200,
                });
            }

            const updated = applyOrderStatusUpdate(payload);

            if (!updated) {
                scheduleWsRefresh(true);
                if (status === 'preparing' && !liveOpsShowPendingApproval && initialized) {
                    dingOrder();
                }
                return;
            }

            if (status === 'preparing' && !liveOpsShowPendingApproval && initialized) {
                dingOrder();
            }

            if (status === 'delivered' || status === 'cancelled') {
                scheduleWsRefresh(true);
                return;
            }

            syncNotificationBadges();
            dataFingerprint = '';
            schedulePaint(true);
            updateStatusLine();
        });

        echoClient.channel(ordersChannelName).listen('.TableCallReceived', (payload) => {
            const call = payload?.call;
            if (!call) {
                scheduleWsRefresh(true);
                return;
            }

            if (isBillCall(call)) {
                dingCall();
                toastOps({
                    title: 'Hesap İsteniyor',
                    message: `Masa ${call.table ?? '—'} · ${call.type_label ?? ''} · POS hazırla`,
                    type: 'warning',
                    durationMs: 3000,
                });
            } else if (String(call.type) === 'waiter') {
                dingCall();
                toastOps({
                    title: 'Garson Çağrısı',
                    message: `Masa ${call.table ?? '—'} · Müşteri garson bekliyor`,
                    type: 'warning',
                    durationMs: 3200,
                });
            }

            scheduleWsRefresh(true);
        });

        echoClient.channel(ordersChannelName).listen('.TableCallForwarded', () => {
            scheduleWsRefresh(false);
        });

        echoClient.channel(ordersChannelName).listen('.TableCallUpdated', (payload) => {
            handleCallUpdated(payload);
        });
    }

    tabs.forEach((tab) => {
        tab.classList.toggle('is-active', tab.dataset.tab === activeTab);
        tab.addEventListener('click', () => {
            activeTab = tab.dataset.tab;
            tabs.forEach((t) => t.classList.toggle('is-active', t === tab));
            dismissNotificationsForTab(activeTab);
            dataFingerprint = '';
            if (isKasaMode()) {
                schedulePoll(true);
            } else {
                schedulePaint(true);
            }
        });
    });

    setInterval(refreshOrderAgeLabels, 30000);

    async function poll(forcePaint = false) {
        if (kasaRefreshPaused() && !forcePaint) {
            const waitMs = Math.max(400, kasaLocalOpsUntil - Date.now());
            pollTimer = setTimeout(() => poll(false), waitMs);
            return;
        }

        try {
            const res = await fetch(buildApiUrl(), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!res.ok) throw new Error('fetch');
            const data = await res.json();
            ordersState = data.orders || [];
            enforceKasaOrderLock();
            completedOrdersState = data.completed_orders || [];
            callsState = data.calls || [];
            tablesState = sortTablesByNumber(data.tables || tablesState);
            const wasInitialized = initialized;
            processUpdates(ordersState, callsState);

            if (forcePaint) {
                dataFingerprint = '';
            }
            schedulePaint(forcePaint);
            paintTableMap(tablesState, wasInitialized);
            updateStatusLine();
            notifyDataRefresh();

            failCount = 0;
            currentInterval = intervalMs;
        } catch {
            failCount += 1;
            currentInterval = Math.min(intervalMs * 2 ** failCount, maxIntervalMs);
            if (statusEl && lastStatusLine !== 'err') {
                lastStatusLine = 'err';
                statusEl.textContent = 'Bağlantı bekleniyor…';
            }
        }

        pollTimer = setTimeout(() => poll(false), currentInterval);
    }

    initRealtimeListeners();
    void initNotificationAudio();

    if (root.dataset.kasaMode === '1') {
        window.HSP_KASA = {
            ...(window.HSP_KASA || {}),
            syncPayPanel: syncKasaPayPanel,
            syncOrderChrome: syncKasaOrderChrome,
        };

        initKasaPanel({
            poll,
            schedulePoll,
            schedulePaint,
            patchOrderStatus,
            holdKasaRefresh,
            isKasaRefreshPaused: kasaRefreshPaused,
            isBillCall,
            getCallsForTable,
            getUnpaidOrderForTable,
            forwardCall,
            resolveCall: resolveCallById,
            closeCallWithPayment,
            closeOrderWithPayment,
            onDataRefresh,
            onTablePayloadUpdated(tablePayload) {
                mergeKasaTableOrder(
                    tablePayload?.order ?? null,
                    window.HSP_KASA?.selectedTableNumber ?? null,
                );
                dataFingerprint = '';
                schedulePaint(true);
            },
            onTableSwitch: handleTableSwitch,
            markTableBusy(tableId, busy, tableStatus = 'occupied') {
                markTableBusy(tableId, busy, tableStatus);
            },
            markTableAwaitingPayment(tableId, awaiting = true) {
                markTableAwaitingPayment(tableId, awaiting);
            },
            onTableSelected() {
                dataFingerprint = '';
                schedulePaint(true);
            },
        });
    }

    poll(false);
}
