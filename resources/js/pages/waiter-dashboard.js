import { showAdminToast } from '../admin-toast.js';
import { createEchoClient } from '../echo.js';

function escapeHtml(text) {
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function initWaiterDashboard() {
    const cfg = window.HSP_WAITER;
    if (!cfg || window.__HSP_WAITER_INIT__) return;
    window.__HSP_WAITER_INIT__ = true;

    const feedEl = document.getElementById('waiterFeed');
    const statusEl = document.getElementById('waiterFeedStatus');
    const feedBadgeEl = document.getElementById('waiterFeedBadge');
    const navRequestsBadgeEl = document.getElementById('waiterNavRequestsBadge');
    const clockEl = document.getElementById('waiterClock');
    const liveBadge = document.getElementById('waiterLiveBadge');
    const tableGridEl = document.getElementById('waiterTableGrid');
    const installBtn = document.getElementById('waiterInstallBtn');
    const soundGate = document.getElementById('waiterSoundGate');
    const soundEnableBtn = document.getElementById('waiterSoundEnableBtn');
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    let pollTimer = null;
    let feedPollTimer = null;
    let completing = false;
    let echoClient = null;
    let echoAvailable = false;
    let realtimeConnected = false;
    let audioCtx = null;
    let soundEnabled = localStorage.getItem('waiter_sound_enabled') === '1';
    let deferredInstallPrompt = null;
    let knownOrderIds = new Set();
    let knownCallIds = new Set();
    let hasBootstrappedOrders = false;
    let hasBootstrappedCalls = false;
    const readyAlertIds = new Set();
    const recentlyCompletedIds = new Set();
    /** @type {Set<string>} */
    const recentNotificationKeys = new Set();

    let lastTableGridFp = '';
    let lastTableGridData = null;

    function updateManualOrderFab() {
        const fab = document.querySelector('[data-manual-order-trigger]');
        if (!fab) return;

        const hasTable = cfg.selectedTableId != null;
        fab.dataset.tableId = hasTable ? String(cfg.selectedTableId) : '';
        fab.dataset.tableNumber = hasTable ? String(cfg.selectedTableNumber ?? '') : '';
        fab.setAttribute(
            'aria-label',
            hasTable
                ? `Masa ${cfg.selectedTableNumber} için sipariş gir`
                : 'Yeni sipariş gir — önce masa seçin',
        );
        fab.classList.toggle('manual-order-fab--armed', hasTable);
    }

    function setWaiterSelectedTable(id, number) {
        cfg.selectedTableId = id != null ? Number(id) : null;
        cfg.selectedTableNumber = number != null ? String(number) : null;
        updateManualOrderFab();
        if (lastTableGridData) {
            renderWaiterTableGrid(
                lastTableGridData.tables,
                lastTableGridData.calls,
                lastTableGridData.orders,
            );
        }
    }

    tableGridEl?.addEventListener('click', (event) => {
        const tile = event.target.closest('[data-waiter-table-id]');
        if (!tile || tile.dataset.waiterTableActive !== '1') return;

        const id = Number(tile.dataset.waiterTableId);
        const number = tile.dataset.waiterTableNumber;
        if (!Number.isFinite(id)) return;

        if (cfg.selectedTableId === id) {
            setWaiterSelectedTable(null, null);
            return;
        }

        setWaiterSelectedTable(id, number);
    });

    updateManualOrderFab();
    /** @type {Map<number, string>} */
    const lastOrderStatuses = new Map();

    function isBillCallType(type) {
        return ['bill_cash', 'bill_card', 'bill'].includes(String(type));
    }

    /** Aynı olay için Echo + poll çift bildirimini engeller. */
    function notifyOnce(key, toastOptions, ttlMs = 12000) {
        if (recentNotificationKeys.has(key)) return false;
        recentNotificationKeys.add(key);
        setTimeout(() => recentNotificationKeys.delete(key), ttlMs);
        if (toastOptions) {
            showAdminToast(toastOptions);
        }
        return true;
    }

    /** Kasa gibi: feed güncellenir; toast yalnızca garson çağrısı ve hazır siparişte. */
    function notifyImportant(key, toastOptions, sound = null) {
        if (sound === 'call') playCallBell();
        else if (sound === 'ready') playReadyRing();
        notifyOnce(key, toastOptions);
    }

    function shouldShowCallInFeed(call) {
        return waiterShouldShowCall(call);
    }

    function setLive(ok) {
        const dot = liveBadge?.querySelector('.waiter-pwa__live-dot');
        if (!dot) return;
        dot.classList.toggle('bg-emerald-500', ok);
        dot.classList.toggle('animate-pulse', ok);
        dot.classList.toggle('bg-red-500', !ok);
    }

    function tickWaiterClock() {
        if (!clockEl) return;
        const now = new Date();
        const text = now.toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' });
        clockEl.textContent = text;
        clockEl.setAttribute('datetime', now.toISOString());
    }

    function updateFeedBadge() {
        const count = feedEl?.querySelectorAll('[data-feed-kind]').length || 0;
        const hasItems = count > 0;
        feedBadgeEl?.classList.toggle('hidden', !hasItems);
        navRequestsBadgeEl?.classList.toggle('hidden', !hasItems);
    }

    // Garson yalnızca garson çağrılarını görür (hesap kasadan yönetilir).
    function waiterShouldShowCall(c) {
        return String(c?.type) === 'waiter';
    }

    const WAITER_VISIBLE_ORDER_STATUSES = new Set(['ready']);
    const WAITER_REMOVED_ORDER_STATUSES = new Set(['pending_approval', 'preparing', 'delivered', 'cancelled', 'pending']);

    function feedCardKey(kind, id) {
        return `${kind}:${id}`;
    }

    function ensureFeedEmptyState() {
        if (!feedEl) return;
        if (feedEl.querySelector('[data-feed-kind]')) return;
        if (feedEl.querySelector('.waiter-feed__empty')) return;
        feedEl.innerHTML = '<p class="waiter-feed__empty">Bekleyen çağrı veya sipariş yok ✨</p>';
        if (statusEl) statusEl.textContent = 'Şu an bekleyen iş yok';
    }

    function updateFeedStatusLine() {
        if (!feedEl || !statusEl) return;
        const total = feedEl.querySelectorAll('[data-feed-kind]').length;
        if (!total) {
            statusEl.textContent = 'Şu an bekleyen iş yok';
            updateFeedBadge();
            return;
        }
        statusEl.textContent = `${total} aktif kayıt · ${new Date().toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' })}`;
        updateFeedBadge();
    }

    function removeFeedCard(kind, id, { animate = true } = {}) {
        if (!feedEl) return false;
        const card = feedEl.querySelector(`[data-feed-kind="${kind}"][data-feed-id="${id}"]`);
        if (!card) return false;

        if (kind === 'order') {
            knownOrderIds.delete(Number(id));
            readyAlertIds.delete(Number(id));
        } else {
            knownCallIds.delete(Number(id));
        }

        const finalize = () => {
            card.remove();
            ensureFeedEmptyState();
            updateFeedStatusLine();
        };

        if (animate) {
            card.classList.add('waiter-req-card--out');
            setTimeout(finalize, 280);
        } else {
            finalize();
        }

        return true;
    }

    function orderPayloadFromStatusEvent(payload) {
        return {
            id: Number(payload.order_id),
            order_number: payload.order_number,
            status: 'ready',
            status_label: 'Masada',
            table: payload.table,
            total: payload.total ?? 0,
            created_at: payload.created_at ?? '',
            is_waiter_order: !!payload.is_waiter_order,
            items: (payload.items || []).map((i, idx) => ({
                id: i.id ?? idx,
                name: i.name,
                quantity: i.quantity,
            })),
        };
    }

    function upsertOrderCardInFeed(order) {
        if (!feedEl || !order?.id) return;
        if (!WAITER_VISIBLE_ORDER_STATUSES.has(String(order.status))) {
            removeFeedCard('order', order.id);
            return;
        }

        feedEl.querySelector('.waiter-feed__empty')?.remove();

        const existing = feedEl.querySelector(`[data-feed-kind="order"][data-feed-id="${order.id}"]`);
        const wrap = document.createElement('div');
        wrap.innerHTML = renderOrderCard(order).trim();
        const next = wrap.firstElementChild;
        if (!next) return;

        if (existing) {
            existing.replaceWith(next);
        } else {
            feedEl.prepend(next);
        }

        knownOrderIds.add(Number(order.id));
        updateFeedStatusLine();
    }

    function syncFeedDom(orders, calls, tasks = []) {
        if (!feedEl) return;

        const items = buildFeed(orders, calls, tasks);
        const visibleKeys = new Set(items.map((item) => feedCardKey(item.kind, item.data.id)));

        feedEl.querySelectorAll('[data-feed-kind]').forEach((card) => {
            const key = feedCardKey(card.dataset.feedKind, card.dataset.feedId);
            if (!visibleKeys.has(key)) {
                card.remove();
            }
        });

        items.forEach((item) => {
            if (item.kind === 'call') {
                updateCallCardInFeed(item.data);
            } else if (item.kind === 'task') {
                updateTaskCardInFeed(item.data);
            } else {
                upsertOrderCardInFeed(item.data);
            }
        });

        knownOrderIds = new Set(
            items.filter((i) => i.kind === 'order').map((i) => Number(i.data.id)).filter(Number.isFinite),
        );
        knownCallIds = new Set(
            items.filter((i) => i.kind === 'call').map((i) => Number(i.data.id)).filter(Number.isFinite),
        );

        ensureFeedEmptyState();
        updateFeedStatusLine();
    }

    function buildFeed(orders, calls, tasks = []) {
        const items = [];

        (calls || [])
            .filter(shouldShowCallInFeed)
            .forEach((c) => {
            items.push({
                kind: 'call',
                sort: c.sort_at || c.updated_at || '',
                data: c,
            });
        });

        (orders || [])
            .filter((o) => String(o.status) === 'ready')
            .forEach((o) => {
            items.push({
                kind: 'order',
                sort: o.updated_at || '',
                data: o,
            });
        });

        (tasks || [])
            .filter((task) => ['pending', 'assigned', 'accepted'].includes(String(task.status)))
            .forEach((task) => {
                items.push({
                    kind: 'task',
                    sort: task.sort_at || task.updated_at || '',
                    data: task,
                });
            });

        items.sort((a, b) => String(b.sort).localeCompare(String(a.sort)));

        return items;
    }

    function callWaiterName(call) {
        return call.waiter_name || call.assigned_user_name || 'Garson';
    }

    function callWaiterId(call) {
        return Number(call.waiter_id ?? call.assigned_user_id ?? 0);
    }

    function isCallClosed(call) {
        return call.status === 'completed' || call.status === 'resolved';
    }

    function isCallPending(call) {
        return call.status === 'pending' || call.status === 'active' || !call.status;
    }

    function pwaActionBtn(label, attrs, tone = 'emerald') {
        const tones = {
            emerald: 'bg-emerald-500 text-black shadow-md',
            amber: 'bg-amber-500 text-black shadow-md',
            gold: 'bg-[#C6A046] text-black shadow-md',
        };
        return `<button type="button" class="shrink-0 rounded-xl px-3 py-2 text-xs font-bold transition-all active:scale-95 ${tones[tone] || tones.emerald}" ${attrs}>${label}</button>`;
    }

    function callActionsHtml(call) {
        if (isCallClosed(call)) {
            return '';
        }

        return `<button type="button" class="waiter-call-resolve" data-resolve-call="1" aria-label="Tamamla">✓</button>`;
    }

    function renderCallCard(call) {
        const cardTone = 'bg-gradient-to-r from-[#2A1E0C] to-[#16120B] border-[#523B19]/40';
        const labelTone = 'text-amber-400';
        const label = 'Garson Çağrısı';
        const subtitle = call.headline || call.type_label || 'Müşteri masada garson bekliyor.';

        return `
            <article class="waiter-req-card border ${cardTone} rounded-2xl p-4 shadow-lg${isCallPending(call) ? ' waiter-req-card--pending' : ''}" data-feed-key="call-${call.id}" data-feed-kind="call" data-feed-id="${call.id}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <span class="text-xs font-bold uppercase tracking-wide ${labelTone}">${escapeHtml(label)}</span>
                        <h3 class="mt-0.5 text-lg font-bold text-zinc-200">Masa ${escapeHtml(call.table ?? '—')}</h3>
                        <p class="mt-1 text-xs text-zinc-400">${escapeHtml(subtitle)}</p>
                        ${call.created_at ? `<p class="mt-1 text-[10px] text-zinc-600">${escapeHtml(call.created_at)}</p>` : ''}
                    </div>
                    <div class="flex flex-col items-end gap-2">${callActionsHtml(call)}</div>
                </div>
            </article>`;
    }

    function updateCallCardInFeed(call) {
        if (!feedEl || !call?.id) return;
        const existing = feedEl.querySelector(`[data-feed-kind="call"][data-feed-id="${call.id}"]`);
        if (isCallClosed(call)) {
            existing?.remove();
            return;
        }
        const wrap = document.createElement('div');
        wrap.innerHTML = renderCallCard(call).trim();
        const next = wrap.firstElementChild;
        if (!next) return;
        if (existing) {
            existing.replaceWith(next);
        } else {
            feedEl.prepend(next);
        }
    }

    function taskActionsHtml(task) {
        const assignedToMe = Number(task.assigned_user_id ?? 0) === Number(cfg.userId ?? 0);
        if (task.status === 'pending') {
            return pwaActionBtn('Görevi Al', 'data-task-accept="1"', 'gold')
                + pwaActionBtn('Sorun Bildir', 'data-task-problem="1"', 'amber');
        }
        if (task.status === 'assigned' && assignedToMe) {
            return pwaActionBtn('Teslim Aldım', 'data-task-accept="1"', 'gold')
                + pwaActionBtn('Sorun Bildir', 'data-task-problem="1"', 'amber');
        }
        if (task.status === 'accepted' && assignedToMe) {
            return pwaActionBtn('Teslim Ettim', 'data-task-complete="1"', 'emerald')
                + pwaActionBtn('Sorun Bildir', 'data-task-problem="1"', 'amber');
        }

        return '<span class="text-xs font-bold text-zinc-500">Garsona Atandı</span>';
    }

    function renderTaskCard(task) {
        const title = task.type_label || 'Görev';
        const itemText = task.item_name
            ? `${task.quantity || 1}x ${escapeHtml(task.item_name)}`
            : 'Masa görevi';
        const pool = task.assigned_user_id ? (task.assigned_user_name || 'Atanmış görev') : 'Ortak görev havuzu';

        return `
            <article class="waiter-req-card border bg-gradient-to-r from-[#0F291E] to-[#121A16] border-[#1B4D36]/40 rounded-2xl p-4 shadow-lg" data-feed-key="task-${task.id}" data-feed-kind="task" data-feed-id="${task.id}" data-task-status="${escapeHtml(task.status)}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <span class="text-xs font-bold uppercase tracking-wide text-emerald-400">${escapeHtml(title)}</span>
                        <h3 class="mt-0.5 text-lg font-bold text-zinc-200">Masa ${escapeHtml(task.table ?? '—')}</h3>
                        <p class="mt-1 text-xs text-zinc-400">${itemText}</p>
                        <p class="mt-1 text-[10px] font-bold uppercase tracking-wide text-zinc-600">${escapeHtml(pool)}</p>
                    </div>
                    <div class="flex flex-col items-end gap-2">${taskActionsHtml(task)}</div>
                </div>
            </article>`;
    }

    function updateTaskCardInFeed(task) {
        if (!feedEl || !task?.id) return;
        const existing = feedEl.querySelector(`[data-feed-kind="task"][data-feed-id="${task.id}"]`);
        if (['completed', 'cancelled'].includes(String(task.status))) {
            existing?.remove();
            return;
        }
        const wrap = document.createElement('div');
        wrap.innerHTML = renderTaskCard(task).trim();
        const next = wrap.firstElementChild;
        if (!next) return;
        if (existing) {
            existing.replaceWith(next);
        } else {
            feedEl.prepend(next);
        }
    }

    function orderActionsHtml(order) {
        if (order.status === 'ready') {
            return pwaActionBtn('Teslim Et', 'data-complete="1"', 'emerald');
        }

        return '';
    }

    function renderOrderCard(order) {
        const items = (order.items || [])
            .slice(0, 4)
            .map((i) => `${i.quantity}x ${escapeHtml(i.name)}`)
            .join(', ');
        const more = (order.items || []).length > 4 ? '…' : '';

        const isReady = order.status === 'ready' || readyAlertIds.has(Number(order.id));

        let cardTone = 'bg-gradient-to-r from-[#0F291E] to-[#121A16] border-[#1B4D36]/40';
        let label = 'Mutfaktan Hazır';
        let labelTone = 'text-emerald-400';

        if (!isReady) {
            cardTone = 'bg-gradient-to-r from-[#1A1A2E] to-[#121218] border-indigo-500/30';
            label = 'Hazır Sipariş';
            labelTone = 'text-indigo-300';
        }

        const actions = orderActionsHtml(order);

        return `
            <article class="waiter-req-card border ${cardTone} rounded-2xl p-4 shadow-lg${isReady ? ' waiter-req-card--ready' : ''}" data-feed-key="order-${order.id}" data-feed-kind="order" data-feed-id="${order.id}" data-order-status="${escapeHtml(order.status)}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <span class="text-xs font-bold uppercase tracking-wide ${labelTone}">${escapeHtml(label)}</span>
                        <h3 class="mt-0.5 text-lg font-bold text-zinc-200">Masa ${escapeHtml(order.table ?? '—')}</h3>
                        <p class="mt-1 text-xs text-zinc-400">${items}${more}</p>
                        ${order.total ? `<p class="mt-1 text-xs font-mono text-zinc-500">${Math.round(order.total || 0).toLocaleString('tr-TR')} ₺</p>` : ''}
                    </div>
                    ${actions}
                </div>
            </article>`;
    }

    function tableHasBillCall(table, calls) {
        return (calls || []).some(
            (c) =>
                shouldShowCallInFeed(c) &&
                !['completed', 'resolved'].includes(String(c.status)) &&
                isBillCallType(c?.type) &&
                Number(c.table) === Number(table.number),
        );
    }

    function tableOrderMeta(table, orders) {
        const tableOrders = (orders || []).filter((o) => Number(o.table) === Number(table.number));
        const active = tableOrders.find((o) =>
            ['pending_approval', 'ready', 'preparing', 'pending'].includes(String(o.status)),
        ) || tableOrders.find((o) => o.status === 'delivered' && !o.payment_method);

        if (!active) {
            return { total: null, minutes: null };
        }

        const total = active.total != null ? Math.round(Number(active.total)).toLocaleString('tr-TR') + ' ₺' : null;
        const rawTime = active.created_at_iso || active.updated_at || active.created_at;
        let minutes = null;
        if (rawTime) {
            const parsed = Date.parse(String(rawTime).replace(' ', 'T'));
            if (Number.isFinite(parsed)) {
                minutes = Math.max(0, Math.floor((Date.now() - parsed) / 60000));
            }
        }

        return { total, minutes };
    }

    function waiterTableState(table, calls) {
        if (!table.is_active) {
            return { mod: 'off', label: 'Kapalı' };
        }
        if (tableHasBillCall(table, calls)) {
            return { mod: 'bill', label: 'Hesap' };
        }
        if (table.is_busy) {
            return { mod: 'busy', label: 'Dolu' };
        }
        return { mod: 'free', label: 'Boş' };
    }

    function renderWaiterTableGrid(tables, calls, orders = []) {
        if (!tableGridEl) return;

        lastTableGridData = { tables, calls, orders };

        const fp = JSON.stringify({
            tables: (tables || []).map((t) => [t.id, t.is_busy, t.is_active, t.status]),
            calls: (calls || [])
                .filter((c) => shouldShowCallInFeed(c))
                .map((c) => [c.id, c.table, c.type, c.status]),
            orders: (orders || [])
                .filter((o) => Number.isFinite(Number(o.table)))
                .map((o) => [o.id, o.table, o.status, o.total, o.updated_at]),
            selected: cfg.selectedTableId,
        });
        if (fp === lastTableGridFp) return;
        lastTableGridFp = fp;

        const active = (tables || []).filter((t) => t.is_active);
        if (!active.length) {
            tableGridEl.innerHTML = '<p class="col-span-2 py-6 text-center text-sm text-zinc-500">Aktif masa yok</p>';
            return;
        }

        tableGridEl.innerHTML = active
            .map((t) => {
                const state = waiterTableState(t, calls || []);
                const meta = tableOrderMeta(t, orders);
                const isBill = state.mod === 'bill';
                const isBusy = state.mod === 'busy';
                const isFree = state.mod === 'free';
                const isOff = state.mod === 'off';

                const isSelected = cfg.selectedTableId === t.id;

                let borderClass = 'border border-zinc-900';
                let opacityClass = '';
                let titleClass = 'text-xl font-black text-zinc-100';

                if (isSelected) {
                    borderClass = 'border-2 border-[#C6A046]';
                } else if (isBill) {
                    borderClass = 'border-2 border-amber-500/50';
                } else if (isBusy) {
                    borderClass = 'border border-emerald-500/30';
                } else if (isFree) {
                    opacityClass = 'opacity-60';
                    titleClass = 'text-xl font-bold text-zinc-400';
                } else if (isOff) {
                    opacityClass = 'opacity-40';
                    titleClass = 'text-xl font-bold text-zinc-600';
                }

                const badge = isBill
                    ? '<div class="absolute right-0 top-0 rounded-bl-xl bg-amber-500 px-2 py-0.5 text-[9px] font-black uppercase text-black">Hesap</div>'
                    : '';

                const amountText = isFree || isOff ? 'BOŞ' : meta.total || '—';
                const amountClass = isFree || isOff ? 'text-xs text-zinc-600' : 'text-xs font-mono text-zinc-400';
                const timeText = meta.minutes != null ? `${meta.minutes} dk` : '--';
                const timeClass = isFree || isOff ? 'text-[10px] text-zinc-600' : 'text-[10px] text-zinc-500';

                return `
                <button type="button"
                    class="waiter-table-tile relative flex h-28 w-full flex-col justify-between overflow-hidden rounded-2xl bg-[#111111] p-4 text-left transition-all active:scale-[0.98] ${borderClass} ${opacityClass} ${isSelected ? 'ring-2 ring-[#C6A046]/40' : ''}"
                    data-waiter-table-id="${t.id}"
                    data-waiter-table-number="${escapeHtml(String(t.number))}"
                    data-waiter-table-active="${t.is_active ? '1' : '0'}"
                    title="Masa ${escapeHtml(String(t.number))} · ${escapeHtml(state.label)}${isSelected ? ' · Seçili' : ''}"
                >
                    ${badge}
                    <span class="${titleClass}">Masa ${escapeHtml(String(t.number))}</span>
                    <div class="flex items-end justify-between">
                        <span class="${amountClass}">${escapeHtml(amountText)}</span>
                        <span class="${timeClass}">${escapeHtml(timeText)}</span>
                    </div>
                </button>`;
            })
            .join('');
    }

    function initWaiterTabs() {
        const tabs = document.querySelectorAll('[data-waiter-tab]');
        const homePanel = document.getElementById('waiterPanelHome');
        const accountPanel = document.getElementById('waiterPanelAccount');
        const tablesBlock = document.getElementById('waiterTablesBlock');
        const mainEl = document.getElementById('waiterMain');

        tabs.forEach((btn) => {
            btn.addEventListener('click', () => {
                const target = btn.dataset.waiterTab;

                tabs.forEach((t) => {
                    const active = t === btn;
                    t.classList.toggle('waiter-pwa-nav__btn--active', active);
                    t.classList.toggle('text-[#C6A046]', active);
                    t.classList.toggle('text-zinc-500', !active);
                    t.setAttribute('aria-current', active ? 'page' : 'false');
                });

                if (target === 'account') {
                    homePanel.hidden = true;
                    accountPanel.hidden = false;
                } else {
                    homePanel.hidden = false;
                    accountPanel.hidden = true;
                    if (tablesBlock) {
                        tablesBlock.hidden = target === 'requests';
                    }
                }

                mainEl?.scrollTo({ top: 0, behavior: 'smooth' });
            });
        });
    }

    function renderFeed(orders, calls) {
        syncFeedDom(orders, calls);
    }

    function trackOrderStatusTransitions(allOrders, pollNotifies) {
        if (!hasBootstrappedOrders || !pollNotifies) {
            allOrders.forEach((o) => {
                const id = Number(o.id);
                if (Number.isFinite(id)) {
                    lastOrderStatuses.set(id, String(o.status));
                }
            });
            return;
        }

        allOrders.forEach((o) => {
            const id = Number(o.id);
            if (!Number.isFinite(id)) return;

            const prev = lastOrderStatuses.get(id);
            const status = String(o.status);

            if (prev === 'preparing' && status === 'ready') {
                handleOrderReadyRealtime({
                    order_id: id,
                    order_number: o.order_number,
                    table: o.table,
                    total: o.total,
                    items: (o.items || []).map((i) => ({
                        name: i.name,
                        quantity: i.quantity,
                    })),
                });
            }

            lastOrderStatuses.set(id, status);
        });
    }

    function handleOrderReadyRealtime(payload) {
        const orderId = Number(payload?.order_id);
        if (!Number.isFinite(orderId)) return;

        readyAlertIds.add(orderId);
        knownOrderIds.add(orderId);

        const order = orderPayloadFromStatusEvent(payload);
        upsertOrderCardInFeed(order);

        notifyImportant(
            `order:${orderId}:ready`,
            {
                title: 'Hazır / Tezgahta',
                message: `Masa ${payload?.table ?? '—'} · #${payload?.order_number ?? orderId}`,
                type: 'success',
                durationMs: 2800,
            },
            'ready',
        );
    }

    function handleOrderRemovedRealtime(payload, status) {
        const orderId = Number(payload?.order_id);
        if (!Number.isFinite(orderId)) return;

        readyAlertIds.delete(orderId);
        const hadCard = !!feedEl?.querySelector(`[data-feed-kind="order"][data-feed-id="${orderId}"]`);
        recentlyCompletedIds.delete(orderId);

        if (hadCard) {
            removeFeedCard('order', orderId, { animate: true });
        } else {
            knownOrderIds.delete(orderId);
        }
    }

    function initSoundGate() {
        if (!soundGate || !soundEnableBtn) return;

        const showGate = () => {
            soundGate.hidden = false;
        };

        const hideGate = () => {
            soundGate.hidden = true;
        };

        if (!soundEnabled) {
            showGate();
        } else {
            hideGate();
            bindAudioUnlockOnGesture();
        }

        soundEnableBtn.addEventListener('click', async () => {
            const ok = await initAudioContext(true);
            if (!ok) return;
            soundEnabled = true;
            localStorage.setItem('waiter_sound_enabled', '1');
            unbindAudioUnlockOnGesture();
            playCallBell();
            hideGate();
            showAdminToast({
                title: 'Sesli bildirimler açık',
                message: 'Yeni masa çağrılarında zil çalacak.',
                type: 'success',
                durationMs: 2800,
            });
        });
    }

    let audioUnlockHandler = null;

    function bindAudioUnlockOnGesture() {
        if (audioUnlockHandler) return;

        audioUnlockHandler = async () => {
            if (!soundEnabled) return;
            const ok = await initAudioContext(false);
            if (ok) {
                unbindAudioUnlockOnGesture();
            }
        };

        document.addEventListener('pointerdown', audioUnlockHandler, { capture: true });
        document.addEventListener('keydown', audioUnlockHandler, { capture: true });
    }

    function unbindAudioUnlockOnGesture() {
        if (!audioUnlockHandler) return;
        document.removeEventListener('pointerdown', audioUnlockHandler, { capture: true });
        document.removeEventListener('keydown', audioUnlockHandler, { capture: true });
        audioUnlockHandler = null;
    }

    async function initAudioContext(playTest = false) {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) return false;

        if (!audioCtx) {
            audioCtx = new AudioCtx();
        }

        if (audioCtx.state === 'suspended') {
            try {
                await audioCtx.resume();
            } catch {
                return false;
            }
        }

        if (playTest && audioCtx.state !== 'running') {
            return false;
        }

        return audioCtx.state === 'running';
    }

    /** Yüksek sesli zil — yalnızca garson çağrıları için */
    function playCallBell() {
        if (!soundEnabled) return;

        try {
            if (!audioCtx || audioCtx.state !== 'running') return;
            const t = audioCtx.currentTime;
            const pattern = [
                { freq: 880, type: 'square', gain: 0.2, dur: 0.22 },
                { freq: 1174, type: 'square', gain: 0.17, dur: 0.18, offset: 0.24 },
                { freq: 880, type: 'square', gain: 0.2, dur: 0.22, offset: 0.46 },
                { freq: 1318, type: 'square', gain: 0.22, dur: 0.28, offset: 0.72 },
            ];

            pattern.forEach(({ freq, type, gain, dur, offset = 0 }) => {
                const start = t + offset;
                const osc = audioCtx.createOscillator();
                const g = audioCtx.createGain();
                osc.type = type;
                osc.frequency.setValueAtTime(freq, start);
                g.gain.setValueAtTime(0.0001, start);
                g.gain.exponentialRampToValueAtTime(gain, start + 0.02);
                g.gain.exponentialRampToValueAtTime(0.0001, start + dur);
                osc.connect(g);
                g.connect(audioCtx.destination);
                osc.start(start);
                osc.stop(start + dur + 0.02);
            });
        } catch {
            // autoplay / audio blocked
        }

        try {
            if (navigator.vibrate) {
                navigator.vibrate([200, 80, 200, 80, 320]);
            }
        } catch {
            // no-op
        }
    }

    function playReadyRing() {
        if (!soundEnabled) return;

        try {
            if (!audioCtx || audioCtx.state !== 'running') return;
            const tones = [1244, 1567, 1244];
            tones.forEach((freq, index) => {
                const startAt = audioCtx.currentTime + index * 0.16;
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(freq, startAt);
                gain.gain.setValueAtTime(0.0001, startAt);
                gain.gain.exponentialRampToValueAtTime(0.1, startAt + 0.015);
                gain.gain.exponentialRampToValueAtTime(0.0001, startAt + 0.14);
                osc.connect(gain);
                gain.connect(audioCtx.destination);
                osc.start(startAt);
                osc.stop(startAt + 0.15);
            });
        } catch {
            // no-op
        }

        try {
            if (navigator.vibrate) {
                navigator.vibrate([100, 50, 100]);
            }
        } catch {
            // no-op
        }
    }

    function prependRealtimeOrder(order) {
        upsertOrderCardInFeed(order);
    }

    function prependRealtimeCall(call) {
        if (!feedEl || !call?.id) return;

        const existing = feedEl.querySelector(`[data-feed-kind="call"][data-feed-id="${call.id}"]`);
        existing?.remove();

        const empty = feedEl.querySelector('.waiter-feed__empty');
        empty?.remove();

        const wrap = document.createElement('div');
        wrap.innerHTML = renderCallCard(call).trim();
        const card = wrap.firstElementChild;
        if (!card) return;
        feedEl.prepend(card);

        const total = feedEl.querySelectorAll('[data-feed-kind]').length;
        if (statusEl) {
            statusEl.textContent = `${total} aktif kayıt · ${new Date().toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' })}`;
        }
    }

    function initRealtimeOrders() {
        echoClient = createEchoClient(cfg.reverb || {});
        echoAvailable = !!echoClient;
        if (!echoClient) return;

        const ordersChannelName = cfg.restaurantId ? `orders.${cfg.restaurantId}` : 'orders';
        const channel = echoClient.channel(ordersChannelName);
        const connection = echoClient.connector?.pusher?.connection;

        if (connection) {
            connection.bind('connected', () => {
                realtimeConnected = true;
            });
            connection.bind('disconnected', () => {
                realtimeConnected = false;
            });
            if (connection.state === 'connected') {
                realtimeConnected = true;
            }
        }

        channel.listen('.TableCallReceived', (payload) => {
            const call = payload?.call;
            if (!call?.id) return;
            if (String(call.type) !== 'waiter') return;
            knownCallIds.add(Number(call.id));

            prependRealtimeCall(call);
            notifyImportant(
                `call:${call.id}:received`,
                {
                    title: 'Garson çağrısı',
                    message: call.headline || `Masa ${call.table ?? '—'} · ${call.type_label ?? ''}`,
                    type: 'warning',
                    durationMs: 3000,
                },
                'call',
            );
        });

        // Kasa çağrıyı garsona yönlendirdiğinde (hesap veya garson çağrısı).
        channel.listen('.TableCallForwarded', (payload) => {
            const call = payload?.call;
            if (!call?.id) return;
            if (String(call.type) !== 'waiter') return;
            knownCallIds.add(Number(call.id));
            prependRealtimeCall(call);
            notifyImportant(
                `call:${call.id}:forwarded`,
                {
                    title: 'Garson Çağrısı · Kasa',
                    message: `Masa ${call.table ?? '—'} · Kasa yönlendirdi, masaya gidin`,
                    type: 'warning',
                    durationMs: 3200,
                },
                'call',
            );
        });

        channel.listen('.TableCallUpdated', (payload) => {
            const call = payload?.call;
            if (!call?.id) return;
            if (isCallClosed(call)) {
                knownCallIds.delete(Number(call.id));
                updateCallCardInFeed(call);
                if (!feedEl?.querySelector('[data-feed-kind]')) {
                    feedEl.innerHTML = '<p class="waiter-feed__empty">Bekleyen çağrı veya sipariş yok ✨</p>';
                    if (statusEl) statusEl.textContent = 'Şu an bekleyen iş yok';
                }
                return;
            }
            knownCallIds.add(Number(call.id));
            updateCallCardInFeed(call);
        });

        channel.listen('.OrderCreated', (payload) => {
            const order = payload?.order;
            if (!order?.id) return;
            if (payload?.silent) return;
            if (!WAITER_VISIBLE_ORDER_STATUSES.has(String(order.status))) return;
            knownOrderIds.add(Number(order.id));
            prependRealtimeOrder(order);
        });

        channel.listen('.OrderStatusUpdated', (payload) => {
            const orderId = Number(payload?.order_id);
            const status = String(payload?.status || '');
            if (!Number.isFinite(orderId) || !status) return;

            if (status === 'ready') {
                handleOrderReadyRealtime(payload);
                return;
            }

            if (WAITER_REMOVED_ORDER_STATUSES.has(status)) {
                handleOrderRemovedRealtime(payload, status);
            }
        });
    }

    function initInstallPrompt() {
        if (!installBtn) return;

        const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent);
        const isStandalone =
            window.matchMedia('(display-mode: standalone)').matches ||
            window.navigator.standalone === true;

        if (isIos && !isStandalone) {
            installBtn.hidden = false;
            installBtn.textContent = '📲 Ana Ekrana Ekle (iPhone)';
            installBtn.addEventListener('click', () => {
                showAdminToast({
                    title: 'iPhone / iPad',
                    message: 'Safari alt menü → Paylaş (kare ok) → Ana Ekrana Ekle',
                    type: 'info',
                    durationMs: 6000,
                });
            });
            return;
        }

        window.addEventListener('beforeinstallprompt', (event) => {
            // Chrome'un varsayılan PWA banner'ını gizle; kurulum yalnızca "Uygulamayı Yükle" ile.
            // Konsoldaki "Banner not shown…" uyarısı bu yüzden normaldir, hata değildir.
            event.preventDefault();
            deferredInstallPrompt = event;
            installBtn.hidden = false;
        });

        window.addEventListener('appinstalled', () => {
            deferredInstallPrompt = null;
            installBtn.hidden = true;
        });

        installBtn.addEventListener('click', async () => {
            if (!deferredInstallPrompt) return;
            deferredInstallPrompt.prompt();
            await deferredInstallPrompt.userChoice;
            deferredInstallPrompt = null;
            installBtn.hidden = true;
            showAdminToast({
                title: 'Kurulum',
                message: 'Uygulama yükleme istegi gonderildi.',
                type: 'info',
                durationMs: 2400,
            });
        });
    }

    async function pollFeedOnly() {
        if (!feedEl) return;

        try {
            const res = await fetch(cfg.feedUrl, { headers: { Accept: 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();
            const orders = (data.orders || []).filter((o) =>
                WAITER_VISIBLE_ORDER_STATUSES.has(String(o.status)),
            );
            const calls = (data.calls || []).filter(shouldShowCallInFeed);
            syncFeedDom(orders, data.calls || [], data.delivery_tasks || []);
        } catch {
            /* sessiz yedek senkron */
        }
    }

    async function poll() {
        try {
            const res = await fetch(cfg.feedUrl, { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error('feed');
            const data = await res.json();
            const allOrders = data.orders || [];
            const orders = allOrders.filter((o) => String(o.status) === 'ready');
            const nextOrderIds = new Set(orders.map((o) => Number(o.id)).filter((id) => Number.isFinite(id)));

            const calls = (data.calls || []).filter(shouldShowCallInFeed);
            const nextCallIds = new Set(calls.map((c) => Number(c.id)).filter((id) => Number.isFinite(id)));

            // Reverb açıksa bildirimler yalnızca Echo'dan; poll yalnızca yedek senkron.
            const pollNotifies = !echoAvailable || !realtimeConnected;

            trackOrderStatusTransitions(allOrders, pollNotifies);

            if (pollNotifies && hasBootstrappedOrders) {
                const newOrders = orders.filter((o) => !knownOrderIds.has(Number(o.id)));
                const newReady = newOrders.filter((o) => String(o.status) === 'ready');
                const newPending = newOrders.filter((o) => String(o.status) !== 'ready');

                newReady.forEach((o) => {
                    handleOrderReadyRealtime({
                        order_id: o.id,
                        order_number: o.order_number,
                        table: o.table,
                        total: o.total,
                        items: (o.items || []).map((i) => ({
                            name: i.name,
                            quantity: i.quantity,
                        })),
                    });
                });

                if (newPending.length) {
                    newPending.forEach((o) => knownOrderIds.add(Number(o.id)));
                }
            }

            if (pollNotifies && hasBootstrappedCalls) {
                const newCalls = calls.filter((c) => !knownCallIds.has(Number(c.id)));
                if (newCalls.length) {
                    const first = newCalls[0];
                    notifyImportant(
                        `call:${first.id}:received`,
                        {
                            title: 'Masa çağrısı',
                            message: first.headline || `Masa ${first.table ?? '—'} · ${first.type_label ?? ''}`,
                            type: 'warning',
                            durationMs: 3000,
                        },
                        'call',
                    );
                }
            }

            knownOrderIds = nextOrderIds;
            knownCallIds = nextCallIds;
            hasBootstrappedOrders = true;
            hasBootstrappedCalls = true;

            syncFeedDom(orders, data.calls || [], data.delivery_tasks || []);
            renderWaiterTableGrid(data.tables || [], data.calls || [], allOrders);
            initTableTransfer(data.tables || []);
            setLive(true);
        } catch {
            setLive(false);
            if (statusEl) statusEl.textContent = 'Bağlantı koptu — yeniden deneniyor…';
        }
    }

    async function completeItem(kind, id, btn, paymentMethod = null) {
        if (completing) return;
        completing = true;
        const card = btn.closest('[data-feed-kind]');
        card?.querySelectorAll('[data-complete]').forEach((b) => {
            b.disabled = true;
        });
        const original = btn.textContent;
        btn.textContent = '…';

        const payload = { type: kind, id };
        if (paymentMethod) payload.payment_method = paymentMethod;

        try {
            const res = await fetch(cfg.completeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                    Accept: 'application/json',
                },
                body: JSON.stringify(payload),
            });

            const data = await res.json();

            if (!res.ok || !data.success) {
                showAdminToast({
                    title: 'İşlem yapılamadı',
                    message: data?.message || 'Tekrar deneyin.',
                    type: 'error',
                });
                card?.querySelectorAll('[data-complete]').forEach((b) => {
                    b.disabled = false;
                    if (b === btn) b.textContent = original;
                });
                completing = false;
                return;
            }

            if (kind === 'order') {
                readyAlertIds.delete(Number(id));
                // Realtime 'delivered' yayını geri geldiğinde çift bildirim olmasın.
                recentlyCompletedIds.add(Number(id));
                setTimeout(() => recentlyCompletedIds.delete(Number(id)), 10000);
            }
            removeFeedCard(kind, id, { animate: true });

            await poll();
        } catch {
            showAdminToast({
                title: 'Bağlantı hatası',
                message: 'İnternet bağlantısını kontrol edin.',
                type: 'error',
            });
            card?.querySelectorAll('[data-complete]').forEach((b) => {
                b.disabled = false;
                if (b === btn) b.textContent = original;
            });
        }

        completing = false;
    }

    async function resolveCall(callId, btn) {
        if (!cfg.resolveCallUrl) return;
        btn.disabled = true;

        try {
            const res = await fetch(`${cfg.resolveCallUrl}/${callId}/resolve`, {
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': csrf(),
                    Accept: 'application/json',
                },
            });
            const data = await res.json().catch(() => ({}));

            if (res.ok && data.success) {
                removeFeedCard('call', callId, { animate: true });
                showAdminToast({
                    title: 'Tamamlandı',
                    message: data.message || 'Çağrı kapatıldı.',
                    type: 'success',
                    durationMs: 2400,
                });
                return;
            }

            showAdminToast({
                title: 'Tamamlanamadı',
                message: data.message || 'Tekrar deneyin.',
                type: 'error',
            });
        } catch {
            showAdminToast({
                title: 'Bağlantı hatası',
                message: 'Tekrar deneyin.',
                type: 'error',
            });
        } finally {
            btn.disabled = false;
        }
    }

    async function claimCall(callId, btn) {
        if (!cfg.claimCallUrl) return;
        btn.disabled = true;
        const original = btn.textContent;
        btn.textContent = '…';

        try {
            const res = await fetch(`${cfg.claimCallUrl}/${callId}/claim`, {
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': csrf(),
                    Accept: 'application/json',
                },
            });
            const data = await res.json().catch(() => ({}));

            if (res.ok && data.success) {
                if (data.call) {
                    updateCallCardInFeed(data.call);
                }
                showAdminToast({
                    title: 'Çağrı üstlenildi',
                    message: data.message || 'İlgileniyorsunuz.',
                    type: 'success',
                    durationMs: 2400,
                });
                return;
            }

            if (data.call) {
                updateCallCardInFeed(data.call);
            }

            const isConflict = res.status === 409 || data.conflict;
            showAdminToast({
                title: 'Üstlenilemedi',
                message: data.message
                    || (isConflict
                        ? 'Bu çağrı başka bir personel tarafından zaten kabul edildi!'
                        : 'Başka bir garson ilgileniyor.'),
                type: 'warning',
                durationMs: 4000,
            });
        } catch {
            showAdminToast({
                title: 'Bağlantı hatası',
                message: 'Tekrar deneyin.',
                type: 'error',
            });
        } finally {
            btn.disabled = false;
            btn.textContent = original;
        }
    }

    async function acceptTask(taskId, btn) {
        if (!cfg.taskAcceptUrl) return;
        btn.disabled = true;
        const original = btn.textContent;
        btn.textContent = '…';

        try {
            const res = await fetch(`${cfg.taskAcceptUrl}/${taskId}/accept`, {
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': csrf(),
                    Accept: 'application/json',
                },
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok && data.success) {
                if (data.task) updateTaskCardInFeed(data.task);
                showAdminToast({
                    title: 'Görev Alındı',
                    message: data.message || 'Teslim görevi size atandı.',
                    type: 'success',
                    durationMs: 2200,
                });
                return;
            }
            showAdminToast({
                title: 'Görev alınamadı',
                message: data.message || 'Tekrar deneyin.',
                type: 'error',
            });
        } catch {
            showAdminToast({
                title: 'Bağlantı hatası',
                message: 'Tekrar deneyin.',
                type: 'error',
            });
        } finally {
            btn.disabled = false;
            btn.textContent = original;
        }
    }

    async function completeTask(taskId, btn) {
        if (!cfg.taskCompleteUrl) return;
        btn.disabled = true;
        const original = btn.textContent;
        btn.textContent = '…';

        try {
            const res = await fetch(`${cfg.taskCompleteUrl}/${taskId}/complete`, {
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': csrf(),
                    Accept: 'application/json',
                },
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok && data.success) {
                removeFeedCard('task', taskId, { animate: true });
                showAdminToast({
                    title: 'Teslim Edildi',
                    message: data.message || 'Görev tamamlandı.',
                    type: 'success',
                    durationMs: 2200,
                });
                await poll();
                return;
            }
            showAdminToast({
                title: 'Tamamlanamadı',
                message: data.message || 'Tekrar deneyin.',
                type: 'error',
            });
        } catch {
            showAdminToast({
                title: 'Bağlantı hatası',
                message: 'Tekrar deneyin.',
                type: 'error',
            });
        } finally {
            btn.disabled = false;
            btn.textContent = original;
        }
    }

    async function approveOrder(orderId, btn) {
        if (!cfg.approveOrderUrl) return;
        btn.disabled = true;
        const original = btn.textContent;
        btn.textContent = '…';

        try {
            const res = await fetch(`${cfg.approveOrderUrl}/${orderId}/approve`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf(),
                    Accept: 'application/json',
                },
            });
            const data = await res.json().catch(() => ({}));

            if (res.ok && data.success) {
                removeFeedCard('order', orderId, { animate: true });
                await poll();
                return;
            }

            const isConflict = res.status === 409 || data.conflict;
            if (isConflict) {
                removeFeedCard('order', orderId, { animate: true });
                await poll();
            }

            showAdminToast({
                title: 'Onaylanamadı',
                message: data.message
                    || (isConflict
                        ? 'Bu sipariş başka bir personel tarafından zaten kabul edildi!'
                        : 'Tekrar deneyin.'),
                type: isConflict ? 'warning' : 'error',
                durationMs: 4000,
            });
        } catch {
            showAdminToast({ title: 'Bağlantı hatası', message: 'Tekrar deneyin.', type: 'error' });
        } finally {
            btn.disabled = false;
            btn.textContent = original;
        }
    }

    async function transferTable(fromId, toId) {
        if (!cfg.transferTableUrl || !fromId || !toId) return;
        const res = await fetch(cfg.transferTableUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf(),
                Accept: 'application/json',
            },
            body: JSON.stringify({ from_table_id: fromId, to_table_id: toId }),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.success) {
            throw new Error(parseApiError(data, 'Transfer başarısız.'));
        }
        return data;
    }

    function parseApiError(data, fallback) {
        if (data?.errors) {
            const lines = Object.values(data.errors).flat().filter(Boolean);
            if (lines.length) return lines.join(' ');
        }
        if (data?.message && !/^the given data was invalid/i.test(data.message)) {
            return data.message;
        }
        return fallback;
    }

    let transferTablesCache = [];
    let transferTablesFp = '';
    let transferUiBound = false;

    function renderTransferOptions() {
        const fromSel = document.getElementById('transferFromTable');
        const toSel = document.getElementById('transferToTable');
        if (!fromSel || !toSel) return;

        const prevFrom = fromSel.value;
        const prevTo = toSel.value;

        const busyTables = transferTablesCache
            .filter((t) => t.is_active && t.is_busy)
            .sort((a, b) => Number(a.number) - Number(b.number) || String(a.number).localeCompare(String(b.number), 'tr'));

        const fromId = prevFrom;
        const emptyTables = transferTablesCache
            .filter(
                (t) =>
                    t.is_active &&
                    !t.is_busy &&
                    String(t.id) !== String(fromId),
            )
            .sort((a, b) => Number(a.number) - Number(b.number) || String(a.number).localeCompare(String(b.number), 'tr'));

        fromSel.innerHTML =
            `<option value="">Kaynak masa (dolu)</option>` +
            busyTables
                .map(
                    (t) =>
                        `<option value="${t.id}">Masa ${escapeHtml(String(t.number))}</option>`,
                )
                .join('');

        toSel.innerHTML =
            `<option value="">Hedef masa (boş)</option>` +
            emptyTables
                .map(
                    (t) =>
                        `<option value="${t.id}">Masa ${escapeHtml(String(t.number))}</option>`,
                )
                .join('');

        if (prevFrom && [...fromSel.options].some((o) => o.value === prevFrom)) {
            fromSel.value = prevFrom;
        }
        if (prevTo && [...toSel.options].some((o) => o.value === prevTo && o.value !== fromSel.value)) {
            toSel.value = prevTo;
        }
    }

    function initTableTransfer(tables) {
        const fromSel = document.getElementById('transferFromTable');
        const toSel = document.getElementById('transferToTable');
        const btn = document.getElementById('transferTableBtn');
        if (!fromSel || !toSel || !btn) return;

        transferTablesCache = tables || [];

        const fp = JSON.stringify(
            transferTablesCache.map((t) => [t.id, t.is_busy, t.is_active, t.number]),
        );
        if (fp !== transferTablesFp) {
            transferTablesFp = fp;
            renderTransferOptions();
        }

        if (!transferUiBound) {
            transferUiBound = true;

            fromSel.addEventListener('change', () => {
                const selectedFrom = fromSel.value;
                const prevTo = toSel.value;
                const emptyTables = transferTablesCache
                    .filter(
                        (t) =>
                            t.is_active &&
                            !t.is_busy &&
                            String(t.id) !== String(selectedFrom),
                    )
                    .sort(
                        (a, b) =>
                            Number(a.number) - Number(b.number) ||
                            String(a.number).localeCompare(String(b.number), 'tr'),
                    );

                toSel.innerHTML =
                    `<option value="">Hedef masa (boş)</option>` +
                    emptyTables
                        .map(
                            (t) =>
                                `<option value="${t.id}">Masa ${escapeHtml(String(t.number))}</option>`,
                        )
                        .join('');

                if (prevTo && [...toSel.options].some((o) => o.value === prevTo)) {
                    toSel.value = prevTo;
                }
            });

            btn.addEventListener('click', async () => {
                const fromId = Number(fromSel.value);
                const toId = Number(toSel.value);
                if (!fromId || !toId) {
                    showAdminToast({
                        title: 'Eksik seçim',
                        message: 'Dolu kaynak masa ve boş hedef masa seçin.',
                        type: 'warning',
                        durationMs: 2800,
                    });
                    return;
                }
                if (fromId === toId) {
                    showAdminToast({
                        title: 'Geçersiz seçim',
                        message: 'Kaynak ve hedef masa farklı olmalı.',
                        type: 'warning',
                        durationMs: 2800,
                    });
                    return;
                }

                const fromLabel = fromSel.selectedOptions[0]?.textContent || 'kaynak';
                const toLabel = toSel.selectedOptions[0]?.textContent || 'hedef';
                const ok = window.confirm(`${fromLabel} → ${toLabel}\n\nAktif sipariş ve çağrılar taşınsın mı?`);
                if (!ok) return;

                btn.disabled = true;
                const original = btn.textContent;
                btn.textContent = 'Aktarılıyor…';
                try {
                    const data = await transferTable(fromId, toId);
                    fromSel.value = '';
                    toSel.value = '';
                    showAdminToast({
                        title: 'Masa taşındı',
                        message: data.message || 'Aktarım tamamlandı.',
                        type: 'success',
                        durationMs: 2800,
                    });
                    await poll();
                } catch (err) {
                    showAdminToast({
                        title: 'Transfer',
                        message: err.message || 'Başarısız.',
                        type: 'error',
                        durationMs: 4000,
                    });
                } finally {
                    btn.disabled = false;
                    btn.textContent = original;
                }
            });
        }
    }

    feedEl?.addEventListener('click', (e) => {
        const resolveBtn = e.target.closest('[data-resolve-call]');
        if (resolveBtn) {
            const card = resolveBtn.closest('[data-feed-kind]');
            if (card?.dataset.feedKind === 'call') {
                resolveCall(Number(card.dataset.feedId), resolveBtn);
            }
            return;
        }

        const claimBtn = e.target.closest('[data-claim]');
        if (claimBtn) {
            const card = claimBtn.closest('[data-feed-kind]');
            if (card?.dataset.feedKind === 'call') {
                claimCall(Number(card.dataset.feedId), claimBtn);
            }
            return;
        }

        const taskAcceptBtn = e.target.closest('[data-task-accept]');
        if (taskAcceptBtn) {
            const card = taskAcceptBtn.closest('[data-feed-kind]');
            if (card?.dataset.feedKind === 'task') {
                acceptTask(Number(card.dataset.feedId), taskAcceptBtn);
            }
            return;
        }

        const taskCompleteBtn = e.target.closest('[data-task-complete]');
        if (taskCompleteBtn) {
            const card = taskCompleteBtn.closest('[data-feed-kind]');
            if (card?.dataset.feedKind === 'task') {
                completeTask(Number(card.dataset.feedId), taskCompleteBtn);
            }
            return;
        }

        const taskProblemBtn = e.target.closest('[data-task-problem]');
        if (taskProblemBtn) {
            showAdminToast({
                title: 'Sorun Bildirildi',
                message: 'Durum kasaya sözlü olarak iletilmelidir.',
                type: 'warning',
                durationMs: 2400,
            });
            return;
        }

        const btn = e.target.closest('[data-complete]');
        if (!btn) return;
        const card = btn.closest('[data-feed-kind]');
        if (!card) return;
        completeItem(card.dataset.feedKind, Number(card.dataset.feedId), btn, btn.dataset.payment || null);
    });

    poll();
    pollTimer = setInterval(poll, cfg.pollMs || 4000);
    feedPollTimer = setInterval(pollFeedOnly, cfg.feedPollMs || 10000);
    tickWaiterClock();
    setInterval(tickWaiterClock, 1000);
    initSoundGate();
    initRealtimeOrders();
    initInstallPrompt();
    initWaiterTabs();

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            clearInterval(pollTimer);
            clearInterval(feedPollTimer);
        } else {
            poll();
            pollFeedOnly();
            pollTimer = setInterval(poll, cfg.pollMs || 4000);
            feedPollTimer = setInterval(pollFeedOnly, cfg.feedPollMs || 10000);
        }
    });

    window.addEventListener('waiter:refresh', () => poll());
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initWaiterDashboard);
} else {
    initWaiterDashboard();
}
