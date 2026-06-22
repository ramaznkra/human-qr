/**
 * Kitchen board — smart diff polling instead of full page reload.
 */
export function initKitchenPoll(config) {
    const { apiUrl, intervalMs = 8000 } = config;
    const grid = document.getElementById('ordersGrid');
    if (!grid) return;

    let htmlSnapshot = grid.innerHTML;

    async function tick() {
        try {
            const res = await fetch(apiUrl, { headers: { Accept: 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();
            const orders = data.orders || [];

            if (!orders.length) {
                const empty = '<div class="col-span-full py-20 text-center text-[#D4C5B9]">Bekleyen sipariş yok ✨</div>';
                if (grid.innerHTML !== empty) grid.innerHTML = empty;
                return;
            }

            const nextHtml = orders.map(o => buildCard(o)).join('');
            if (nextHtml !== htmlSnapshot) {
                grid.innerHTML = nextHtml;
                htmlSnapshot = nextHtml;
                bindStatusButtons();
            }
        } catch {
            /* silent — next tick */
        }
    }

    function buildCard(o) {
        const border = o.status === 'preparing' ? 'border-l-emerald-500' : (o.status === 'ready' ? 'border-l-[#E67E22]' : 'border-l-[#E67E22]/60');
        const items = (o.items || []).map(i => `<div class="mb-1 text-sm text-gray-100">${i.quantity}× ${i.name}</div>`).join('');
        const notes = o.notes ? `<div class="mt-2 text-xs font-light text-[#D4C5B9]">📝 ${o.notes}</div>` : '';
        const table = o.table ? `<div class="mb-2 text-sm font-semibold text-[#E67E22]">Masa ${o.table}</div>` : '';

        return `<div class="rounded-2xl border border-white/5 bg-[#262220] p-5 border-l-4 ${border} transition-all duration-500 ease-in-out" data-id="${o.id}">
            <div class="mb-2 flex justify-between text-sm"><strong class="text-gray-100">#${o.order_number}</strong><span class="text-[#D4C5B9]">${o.created_at}</span></div>
            ${table}${items}${notes}
            <div class="mt-3 flex flex-wrap gap-2">
                <button type="button" data-id="${o.id}" data-status="preparing" class="kitchen-status-btn rounded-lg border border-white/10 px-3 py-1.5 text-xs text-[#D4C5B9] hover:border-[#E67E22] hover:text-[#E67E22]">Hazırlanıyor</button>
                <button type="button" data-id="${o.id}" data-status="ready" class="kitchen-status-btn rounded-lg border border-white/10 px-3 py-1.5 text-xs text-[#D4C5B9] hover:border-[#E67E22] hover:text-[#E67E22]">Hazır</button>
                <button type="button" data-id="${o.id}" data-status="delivered" class="kitchen-status-btn rounded-lg bg-[#E67E22]/15 px-3 py-1.5 text-xs font-medium text-[#E67E22] hover:bg-[#E67E22] hover:text-white">Teslim</button>
            </div>
        </div>`;
    }

    function bindStatusButtons() {
        document.querySelectorAll('.kitchen-status-btn').forEach(btn => {
            btn.onclick = async () => {
                await fetch(`/api/mutfak/${btn.dataset.id}/durum`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    body: JSON.stringify({ status: btn.dataset.status }),
                });
                tick();
            };
        });
    }

    bindStatusButtons();
    setInterval(tick, intervalMs);
}
