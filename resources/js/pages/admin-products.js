/**
 * Admin ürün listesi — menü/stok toggle, kategori sekmeleri, arama, satır içi hızlı güncelleme.
 */
function bindProductToggle(selector, labelSelector, onSuccess) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    document.querySelectorAll(selector).forEach((input) => {
        if (input.dataset.bound === '1') return;
        input.dataset.bound = '1';

        input.addEventListener('change', async (event) => {
            event.stopPropagation();
            const url = input.dataset.toggleUrl;
            const row = input.closest('[data-product-item]');
            const label = row?.querySelector(labelSelector);
            const prev = !input.checked;

            input.disabled = true;
            row?.querySelectorAll(selector).forEach((el) => {
                if (el !== input) el.disabled = true;
            });

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
                    alert(data?.message || 'Güncellenemedi.');
                    return;
                }

                row?.querySelectorAll(selector).forEach((el) => {
                    el.checked = input.checked;
                });

                onSuccess({ input, row, label, data });
            } catch {
                input.checked = prev;
                alert('Bağlantı hatası.');
            } finally {
                input.disabled = false;
                row?.querySelectorAll(selector).forEach((el) => {
                    el.disabled = false;
                });
            }
        });
    });
}

function initProductToggles() {
    bindProductToggle('[data-product-toggle]', '[data-availability-label]', ({ row, label, data }) => {
        if (label) {
            label.textContent = data.label;
            label.classList.toggle('text-[#C6A046]', data.is_available);
            label.classList.toggle('text-zinc-500', !data.is_available);
        }
        row?.classList.toggle('admin-tray-card--hidden', !data.is_available);
        row?.classList.toggle('admin-products-table__row--hidden', !data.is_available);
        if (row?.tagName === 'TR') {
            row.classList.toggle('opacity-60', !data.is_available);
        }
    });

    bindProductToggle('[data-product-stock-toggle]', '[data-stock-label]', ({ row, label, data }) => {
        if (label) {
            label.textContent = data.in_stock ? 'STOKTA' : 'TÜKENDİ';
            label.classList.toggle('product-stock-pill--in', data.in_stock);
            label.classList.toggle('product-stock-pill--out', !data.in_stock);
        }

        row?.classList.toggle('admin-tray-card--sold-out', !data.in_stock);
        row?.classList.toggle('admin-product-row--sold-out', !data.in_stock);
        row?.querySelector('.admin-tray-card__media')?.classList.toggle('admin-tray-card__media--sold-out', !data.in_stock);

        row?.querySelectorAll('.admin-tray-card__img, .product-avatar').forEach((img) => {
            img.classList.toggle('grayscale', !data.in_stock);
        });
    });
}

function initProductFilters() {
    const root = document.querySelector('[data-admin-products]');
    if (!root) return;

    const tabsRoot = root.querySelector('[data-category-tabs]');
    const searchInput = root.querySelector('[data-product-search]');
    let activeCategory = tabsRoot?.querySelector('[data-category-tab].is-active')?.dataset.categoryTab ?? '';

    const getItems = () => root.querySelectorAll('[data-product-item]');
    const filterEmpty = root.querySelector('[data-products-filter-empty]');
    const tableWrap = root.querySelector('.admin-products-table-wrap');

    function applyFilters() {
        const query = (searchInput?.value || '').trim().toLowerCase();
        let visible = 0;

        getItems().forEach((item) => {
            const catId = item.dataset.categoryId || '';
            const name = item.dataset.productName || '';
            const catMatch = activeCategory === '' || catId === activeCategory;
            const searchMatch = query === '' || name.includes(query);
            const show = catMatch && searchMatch;

            item.classList.toggle('hidden', !show);
            if (show) visible += 1;
        });

        if (filterEmpty && tableWrap) {
            const isListVisible = !root.querySelector('[data-view-panel="list"]')?.classList.contains('hidden');
            filterEmpty.classList.toggle('hidden', visible > 0 || !isListVisible);
            tableWrap.querySelector('table')?.classList.toggle('hidden', visible === 0 && isListVisible && getItems().length > 0);
        }
    }

    tabsRoot?.addEventListener('click', (event) => {
        const tab = event.target.closest('[data-category-tab]');
        if (!tab || !tabsRoot.contains(tab)) return;

        activeCategory = tab.dataset.categoryTab ?? '';
        tabsRoot.querySelectorAll('[data-category-tab]').forEach((t) => {
            const active = t === tab;
            t.classList.toggle('is-active', active);
            t.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        applyFilters();
    });

    searchInput?.addEventListener('input', applyFilters);
}

function initQuickEdit() {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    document.querySelectorAll('[data-product-item][data-quick-update-url]').forEach((row) => {
        const toggleBtn = row.querySelector('[data-quick-edit-toggle]');
        const saveBtn = row.querySelector('[data-quick-edit-save]');
        const cancelBtn = row.querySelector('[data-quick-edit-cancel]');
        const nameDisplay = row.querySelector('[data-product-name-display]');
        const nameInput = row.querySelector('[data-product-name-input]');
        const priceDisplay = row.querySelector('[data-product-price-display]');
        const priceInput = row.querySelector('[data-product-price-input]');

        if (!toggleBtn || !saveBtn || !cancelBtn) return;

        let snapshot = { name: '', price: '' };

        function setEditing(on) {
            row.classList.toggle('admin-products-table__row--editing', on);
            toggleBtn.classList.toggle('hidden', on);
            saveBtn.classList.toggle('hidden', !on);
            cancelBtn.classList.toggle('hidden', !on);
            nameDisplay?.classList.toggle('hidden', on);
            nameInput?.classList.toggle('hidden', !on);
            priceDisplay?.classList.toggle('hidden', on);
            priceInput?.classList.toggle('hidden', !on);
        }

        toggleBtn.addEventListener('click', () => {
            snapshot = {
                name: nameInput?.value ?? '',
                price: priceInput?.value ?? '',
            };
            setEditing(true);
            nameInput?.focus();
        });

        cancelBtn.addEventListener('click', () => {
            if (nameInput) nameInput.value = snapshot.name;
            if (priceInput) priceInput.value = snapshot.price;
            setEditing(false);
        });

        saveBtn.addEventListener('click', async () => {
            const url = row.dataset.quickUpdateUrl;
            const name = nameInput?.value.trim() ?? '';
            const price = priceInput?.value ?? '';

            if (!name) {
                alert('Ürün adı boş olamaz.');
                return;
            }

            saveBtn.disabled = true;

            try {
                const res = await fetch(url, {
                    method: 'PATCH',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ name, price: Number(price) }),
                });

                const data = await res.json();

                if (!res.ok || !data.success) {
                    alert(data?.message || 'Güncellenemedi.');
                    return;
                }

                if (nameDisplay) nameDisplay.textContent = data.name;
                if (nameInput) nameInput.value = data.name;
                if (priceDisplay) priceDisplay.textContent = data.price_formatted;
                if (priceInput) priceInput.value = String(data.price);
                row.dataset.productName = data.name.toLowerCase();
                setEditing(false);
            } catch {
                alert('Bağlantı hatası.');
            } finally {
                saveBtn.disabled = false;
            }
        });
    });
}

function initProductListSync() {
    const root = document.querySelector('[data-admin-products]');
    if (!root) return;

    const STORAGE_KEY = 'hsp:products-version';

    const flash = document.querySelector('[data-admin-flash][data-admin-flash-type="success"]');
    const flashMessage = flash?.dataset.adminFlashMessage || '';
    if (flashMessage.includes('eklendi') || flashMessage.includes('güncellendi') || flashMessage.includes('silindi')) {
        localStorage.setItem(STORAGE_KEY, String(Date.now()));
    }

    window.addEventListener('storage', (event) => {
        if (event.key !== STORAGE_KEY || event.newValue === null) return;
        window.location.reload();
    });

    window.addEventListener('pageshow', (event) => {
        if (!event.persisted) return;
        window.location.reload();
    });
}

function initAdminProducts() {
    initProductListSync();
    initProductToggles();
    initProductFilters();
    initQuickEdit();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAdminProducts);
} else {
    initAdminProducts();
}
