/**
 * Admin masalar — aktif / pasif toggle (AJAX, kalıcı).
 * Değişiklik anında DB'ye yazılır; F5 sonrası durum korunur.
 */
function initTableActiveToggles() {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    document.querySelectorAll('[data-table-toggle]').forEach((input) => {
        input.addEventListener('change', async () => {
            const url = input.dataset.toggleUrl;
            const card = input.closest('[data-table-item]');
            const dot = card?.querySelector('[data-table-dot]');
            const number = card?.querySelector('[data-table-number]');
            const label = card?.querySelector('[data-table-status-label]');
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
                    alert(data?.message || 'Masa durumu güncellenemedi.');
                    return;
                }

                applyState(card, dot, number, label, input, data.is_active, data.label);
            } catch {
                input.checked = prev;
                alert('Bağlantı hatası.');
            } finally {
                input.disabled = false;
            }
        });
    });

    function applyState(card, dot, number, label, input, isActive, labelText) {
        input.checked = isActive;

        if (card) {
            card.classList.toggle('table-map-card--on', isActive);
            card.classList.toggle('table-map-card--off', !isActive);
            card.title = labelText;
        }
        if (dot) {
            dot.classList.toggle('table-status-dot--on', isActive);
            dot.classList.toggle('table-status-dot--off', !isActive);
        }
        if (number) {
            number.classList.toggle('text-gray-800', isActive);
            number.classList.toggle('text-gray-400', !isActive);
        }
        if (label) {
            label.textContent = labelText;
            label.classList.toggle('text-emerald-600', isActive);
            label.classList.toggle('text-gray-400', !isActive);
        }
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTableActiveToggles);
} else {
    initTableActiveToggles();
}
