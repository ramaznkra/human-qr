const grid = document.getElementById('barOrders');
const statusEl = document.getElementById('barStatus');
const clockEl = document.getElementById('barClock');
const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

let snapshot = '';

function tickClock() {
    if (clockEl) {
        clockEl.textContent = new Date().toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' });
    }
}
tickClock();
setInterval(tickClock, 1000);

function renderOrders(orders) {
    if (!orders.length) {
        return '<p class="col-span-full py-20 text-center text-lg text-[#D4C5B9]">Bekleyen içecek siparişi yok ☕</p>';
    }

    return orders.map(o => {
        const items = (o.items || []).map(i => {
            const note = i.notes ? `<span class="block text-xs text-[#D4C5B9]">${i.notes}</span>` : '';
            return `<li class="text-lg font-medium text-gray-100">${i.quantity}× ${i.name}${note}</li>`;
        }).join('');

        return `<article class="flex flex-col rounded-2xl border border-white/5 bg-[#262220] p-5 shadow-xl transition-all duration-300" data-order-id="${o.id}">
            <div class="mb-3 flex items-start justify-between gap-2">
                <div>
                    <span class="text-2xl font-bold text-[#E67E22]">#${o.order_number}</span>
                    ${o.table ? `<span class="ml-2 rounded-full bg-[#E67E22]/15 px-3 py-1 text-sm font-semibold text-[#E67E22]">Masa ${o.table}</span>` : ''}
                </div>
                <span class="text-sm text-[#D4C5B9]">${o.created_at}</span>
            </div>
            <span class="mb-2 inline-block rounded-md bg-white/5 px-2 py-0.5 text-xs text-[#D4C5B9]">${o.status_label}</span>
            <ul class="mb-4 flex-1 space-y-2 border-t border-white/5 pt-3">${items}</ul>
            ${o.notes ? `<p class="mb-3 text-sm text-[#D4C5B9]">📝 ${o.notes}</p>` : ''}
            <button type="button" class="bar-ready-btn mt-auto w-full rounded-2xl bg-[#E67E22] py-5 text-lg font-bold text-white shadow-lg transition-all duration-300 active:scale-95 hover:bg-[#d35400]">
                ✓ Hazırlandı
            </button>
        </article>`;
    }).join('');
}

function bindButtons() {
    document.querySelectorAll('.bar-ready-btn').forEach(btn => {
        btn.onclick = async () => {
            const card = btn.closest('article');
            const id = card?.dataset.orderId;
            if (!id) return;
            btn.disabled = true;
            btn.textContent = '…';
            try {
                const res = await fetch(`/admin/api/bar/siparis/${id}/hazir`, {
                    method: 'PATCH',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                    },
                });
                if (res.ok) {
                    card.style.opacity = '0';
                    card.style.transform = 'scale(0.95)';
                    setTimeout(() => { card.remove(); fetchOrders(); }, 400);
                } else {
                    btn.disabled = false;
                    btn.textContent = '✓ Hazırlandı';
                }
            } catch {
                btn.disabled = false;
                btn.textContent = '✓ Hazırlandı';
            }
        };
    });
}

async function fetchOrders() {
    try {
        const res = await fetch('/admin/api/bar/siparisler', { headers: { Accept: 'application/json' } });
        if (!res.ok) throw new Error('fetch');
        const data = await res.json();
        const html = renderOrders(data.orders || []);
        if (html !== snapshot) {
            grid.innerHTML = html;
            snapshot = html;
            bindButtons();
        }
        if (statusEl) {
            statusEl.textContent = `${(data.orders || []).length} sipariş · ${new Date().toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' })}`;
        }
    } catch {
        if (statusEl) statusEl.textContent = 'Bağlantı hatası…';
    }
}

fetchOrders();
setInterval(fetchOrders, 5000);
