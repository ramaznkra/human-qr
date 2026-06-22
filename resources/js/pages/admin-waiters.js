/**
 * Admin garsonlar — aktif / pasif toggle (AJAX).
 */
function initWaiterActiveToggles() {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    document.querySelectorAll('[data-waiter-toggle]').forEach((input) => {
        input.addEventListener('change', async () => {
            const url = input.dataset.toggleUrl;
            const row = input.closest('[data-waiter-item]');
            const label = row?.querySelector('[data-waiter-status-label]');
            const prev = !input.checked;

            input.disabled = true;

            try {
                const res = await fetch(url, {
                    method: 'PATCH',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                    },
                });

                const data = await res.json();

                if (!res.ok || !data.success) {
                    input.checked = prev;
                    alert(data?.message || 'Durum güncellenemedi.');
                    return;
                }

                input.checked = data.is_active;
                row?.classList.toggle('bg-gray-50/60', !data.is_active);

                if (label) {
                    label.textContent = data.label;
                    label.classList.toggle('text-emerald-600', data.is_active);
                    label.classList.toggle('text-gray-400', !data.is_active);
                }
            } catch {
                input.checked = prev;
                alert('Bağlantı hatası.');
            } finally {
                input.disabled = false;
            }
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initWaiterActiveToggles);
} else {
    initWaiterActiveToggles();
}
