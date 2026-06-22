/**
 * Admin kategoriler — aktif/pasif toggle + sıra güncelleme (drag-and-drop hazırlığı).
 */
function initCategoryActiveToggles() {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    document.addEventListener('change', async (event) => {
        const input = event.target.closest('[data-category-toggle]');
        if (!input) return;

        const url = input.dataset.toggleUrl;
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
                alert(data?.message || 'Kategori durumu güncellenemedi.');
                return;
            }

            document
                .querySelectorAll(`[data-category-toggle][data-toggle-url="${url}"]`)
                .forEach((el) => {
                    el.checked = data.is_active;
                    applyCategoryToggleState(el, data.is_active);
                });
        } catch {
            input.checked = prev;
            alert('Bağlantı hatası.');
        } finally {
            input.disabled = false;
        }
    });

    function applyCategoryToggleState(input, isActive) {
        const item = input.closest('[data-category-item]');
        if (!item) return;

        const dot = item.querySelector('[data-category-dot]');
        if (dot) {
            dot.classList.toggle('table-status-dot--on', isActive);
            dot.classList.toggle('table-status-dot--off', !isActive);
        }

        if (item.matches('tr')) {
            item.classList.toggle('opacity-50', !isActive);
        } else {
            item.classList.toggle('admin-tray-card--hidden', !isActive);
            item.classList.toggle('category-kpi-card--inactive', !isActive);
        }
    }
}

function initCategorySortOrder() {
    const cfg = window.HSP_ADMIN_CATEGORIES;
    const saveBtn = document.querySelector('[data-save-category-sort]');
    if (!cfg?.sortUrl || !saveBtn) return;

    const inputs = () => document.querySelectorAll('[data-category-sort-input]');

    inputs().forEach((input) => {
        input.addEventListener('input', () => {
            saveBtn.classList.remove('hidden');
        });
    });

    saveBtn.addEventListener('click', async () => {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        const categories = [];
        const seen = new Set();

        document.querySelectorAll('[data-category-item]').forEach((item) => {
            const id = item.dataset.categoryId;
            const input = item.querySelector('[data-category-sort-input]');
            if (!id || !input || seen.has(id)) return;
            seen.add(id);
            categories.push({ id: Number(id), sort_order: Number(input.value) || 0 });
        });

        if (!categories.length) return;

        saveBtn.disabled = true;
        const original = saveBtn.textContent;
        saveBtn.textContent = 'Kaydediliyor…';

        try {
            const res = await fetch(cfg.sortUrl, {
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ categories }),
            });
            const data = await res.json();
            if (!res.ok || !data.success) {
                alert(data?.message || 'Sıra güncellenemedi.');
                return;
            }
            saveBtn.classList.add('hidden');
        } catch {
            alert('Bağlantı hatası.');
        } finally {
            saveBtn.disabled = false;
            saveBtn.textContent = original;
        }
    });
}

function initAdminCategories() {
    initCategoryActiveToggles();
    initCategorySortOrder();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAdminCategories);
} else {
    initAdminCategories();
}
