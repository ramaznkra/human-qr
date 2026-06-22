/**
 * Admin dashboard live operations feed (orders + table calls).
 */
export function initAdminLiveOps(config) {
    const {
        apiUrl,
        acknowledgeUrlTemplate,
        csrfToken,
        intervalMs = 4000,
        maxIntervalMs = 30000,
    } = config;

    const elOrders = document.getElementById('liveOrdersList');
    const elCalls = document.getElementById('liveCallsList');
    const elBadge = document.getElementById('liveOpsBadge');
    const elStatus = document.getElementById('liveOpsStatus');
    if (!elOrders || !elCalls) return;

    const baseTitle = document.title;
    let knownOrderIds = new Set();
    let knownCallIds = new Set();
    let titleAlertCount = 0;
    let initialized = false;
    let ordersSnapshot = '';
    let failCount = 0;
    let currentInterval = intervalMs;
    let timer = null;

    function playAlert() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.frequency.value = 880;
            gain.gain.setValueAtTime(0.08, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.25);
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.25);
        } catch {
            /* ses desteklenmiyorsa sessiz devam */
        }
        if (document.hidden && 'Notification' in window && Notification.permission === 'granted') {
            new Notification('Human — Yeni aktivite', { body: 'Masa çağrısı veya sipariş var.' });
        }
    }

    function renderOrders(orders) {
        if (!orders.length) {
            elOrders.innerHTML = '<p class="py-4 text-center text-sm text-gray-500">Aktif sipariş yok</p>';
            return;
        }
        elOrders.innerHTML = orders.map(o => `
            <a href="/admin/orders/${o.id}" class="flex items-center justify-between rounded-lg border border-gray-100 px-3 py-2.5 transition hover:border-[#E67E22]/30 hover:bg-orange-50/50">
                <div>
                    <span class="font-semibold text-gray-800">#${o.order_number}</span>
                    <span class="ml-2 text-xs text-gray-500">Masa ${o.table ?? '—'}</span>
                </div>
                <div class="text-right">
                    <span class="badge-status badge-${o.status}">${o.status_label}</span>
                    <span class="mt-0.5 block text-xs font-medium text-[#E67E22]">${Math.round(o.total)} ₺</span>
                </div>
            </a>
        `).join('');
    }

    function renderCalls(calls) {
        if (!calls.length) {
            elCalls.innerHTML = '<p class="py-4 text-center text-sm text-gray-500">Bekleyen çağrı yok</p>';
            return;
        }
        elCalls.innerHTML = calls.map(c => `
            <div class="flex items-center justify-between rounded-lg border border-[#E67E22]/20 bg-[#E67E22]/5 px-3 py-2.5" data-call-id="${c.id}">
                <div>
                    <span class="font-semibold text-gray-800">${c.headline || `Masa ${c.table ?? '—'}`}</span>
                    <span class="block text-xs text-gray-600">${c.type_label}</span>
                    <span class="text-xs text-gray-400">${c.created_at}</span>
                </div>
                <button type="button" class="btn btn-sm btn-primary acknowledge-call" data-id="${c.id}">${({ waiter: 'Garsonu Gönder', bill_cash: 'Hesabı Götür', bill_card: 'Pos Gönder' })[c.type] || 'Tamamlandı'}</button>
            </div>
        `).join('');

        elCalls.querySelectorAll('.acknowledge-call').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = btn.dataset.id;
                const url = acknowledgeUrlTemplate.replace('__ID__', id);
                btn.disabled = true;
                await fetch(url, {
                    method: 'PATCH',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        Accept: 'application/json',
                    },
                });
                tick();
            });
        });
    }

    function refreshDocumentTitle() {
        if (document.hidden && titleAlertCount > 0) {
            const label =
                titleAlertCount === 1 ? '1 Yeni Sipariş' : `${titleAlertCount} Yeni Sipariş`;
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

    function detectNew(orders, calls) {
        const newOrders = orders.filter((o) => !knownOrderIds.has(o.id));
        const newCalls = calls.filter((c) => !knownCallIds.has(c.id));

        if (!initialized) {
            initialized = true;
            knownOrderIds = new Set(orders.map((o) => o.id));
            knownCallIds = new Set(calls.map((c) => c.id));
            return { hasNew: false, newCount: 0 };
        }

        knownOrderIds = new Set(orders.map((o) => o.id));
        knownCallIds = new Set(calls.map((c) => c.id));

        const newCount = newOrders.length + newCalls.length;

        return { hasNew: newCount > 0, newCount };
    }

    function ordersFingerprint(orders) {
        return JSON.stringify(
            orders.map(o => [o.id, o.status, o.updated_at, o.total]),
        );
    }

    function updateBadge(orders, calls) {
        const total = orders.length + calls.length;
        if (elBadge) {
            elBadge.textContent = total;
            elBadge.classList.toggle('hidden', total === 0);
            elBadge.classList.toggle('animate-pulse', calls.length > 0);
        }
    }

    async function tick() {
        try {
            const res = await fetch(apiUrl, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!res.ok) throw new Error('fetch failed');
            const data = await res.json();

            const orders = data.orders || [];
            const calls = data.calls || [];

            const { hasNew, newCount } = detectNew(orders, calls);
            if (hasNew) {
                playAlert();
                if (document.hidden && newCount > 0) {
                    titleAlertCount += newCount;
                    refreshDocumentTitle();
                }
                elOrders.classList.add('ring-2', 'ring-[#E67E22]/30');
                setTimeout(() => elOrders.classList.remove('ring-2', 'ring-[#E67E22]/30'), 800);
            }

            const ordersFp = ordersFingerprint(orders);
            if (ordersFp !== ordersSnapshot) {
                renderOrders(orders);
                ordersSnapshot = ordersFp;
            }

            renderCalls(calls);
            updateBadge(orders, calls);

            if (elStatus) {
                elStatus.textContent = 'Canlı · ' + new Date().toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' });
                elStatus.classList.remove('text-red-500');
                elStatus.classList.add('text-gray-500');
            }

            failCount = 0;
            currentInterval = intervalMs;
        } catch {
            failCount += 1;
            currentInterval = Math.min(intervalMs * Math.pow(2, failCount), maxIntervalMs);
            if (elStatus) {
                elStatus.textContent = 'Bağlantı bekleniyor…';
                elStatus.classList.add('text-red-500');
            }
        }

        timer = setTimeout(tick, currentInterval);
    }

    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission().catch(() => {});
    }

    tick();

    return {
        stop: () => clearTimeout(timer),
    };
}
