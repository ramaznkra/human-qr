import { initLocaleTabs } from './admin-locale-tabs.js';
import { bindAdminFormSubmit, imageTooLargeMessage, resolveMaxImageBytes } from './admin-form-bind.js';
import { initMenuHierarchyFields } from './admin-menu-hierarchy.js';

function initBadgeChips() {
    document.querySelectorAll('[data-badge-input]').forEach((input) => {
        const root = input.closest('.product-form-col, .admin-drawer-form, .product-form-section')?.closest('form')
            ?? input.closest('form')
            ?? document;
        const chips = root.querySelectorAll('[data-badge-chip]');
        if (!chips.length) return;

        function syncActive() {
            const current = input.value.trim();
            chips.forEach((chip) => {
                chip.classList.toggle('is-active', chip.dataset.badgeValue === current);
            });
        }

        chips.forEach((chip) => {
            chip.addEventListener('click', () => {
                const value = chip.dataset.badgeValue;
                input.value = input.value.trim() === value ? '' : value;
                syncActive();
            });
        });

        input.addEventListener('input', syncActive);
        syncActive();
    });
}

// Sunucudaki upload_max_filesize ile hizalı; aşılırsa PHP tüm POST'u
// boşaltıp "ad alanı gerekli" gibi yanıltıcı hatalar üretir.

function showImageSizeWarning(zone, message) {
    if (!zone) return;
    let note = zone.parentElement?.querySelector('[data-image-size-warning]');
    if (!note) {
        note = document.createElement('p');
        note.dataset.imageSizeWarning = '1';
        note.className = 'form-field-error';
        zone.insertAdjacentElement('afterend', note);
    }
    note.textContent = message;
}

function clearImageSizeWarning(zone) {
    zone?.parentElement?.querySelector('[data-image-size-warning]')?.remove();
}

function initImageDropzone() {
    document.querySelectorAll('[data-image-dropzone]').forEach((zone) => {
        if (zone.dataset.bound === '1') return;
        zone.dataset.bound = '1';

        const input = zone.querySelector('[data-image-input]');
        const preview = zone.querySelector('[data-image-preview]');
        if (!input || !preview) return;

        const showPreview = (file) => {
            if (!file || !file.type.startsWith('image/')) return;
            if (file.size > resolveMaxImageBytes()) {
                input.value = '';
                preview.innerHTML = '<span class="product-image-dropzone__placeholder" aria-hidden="true">📷</span>';
                zone.classList.remove('is-filled');
                showImageSizeWarning(zone, imageTooLargeMessage());
                return;
            }
            clearImageSizeWarning(zone);
            const url = URL.createObjectURL(file);
            preview.innerHTML = `<img src="${url}" alt="" class="product-image-dropzone__img">`;
            zone.classList.add('is-filled');
        };

        input.addEventListener('change', () => {
            const file = input.files?.[0];
            if (file) showPreview(file);
        });

        zone.addEventListener('dragover', (event) => {
            event.preventDefault();
            zone.classList.add('is-dragover');
        });

        zone.addEventListener('dragleave', () => {
            zone.classList.remove('is-dragover');
        });

        zone.addEventListener('drop', (event) => {
            event.preventDefault();
            zone.classList.remove('is-dragover');
            const file = event.dataTransfer?.files?.[0];
            if (!file) return;

            const dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;
            showPreview(file);
        });

        if (preview.querySelector('img')) {
            zone.classList.add('is-filled');
        }
    });
}

function initSearchableSelects() {
    document.querySelectorAll('[data-searchable-select]').forEach((root) => {
        if (root.dataset.bound === '1') return;
        root.dataset.bound = '1';

        const hidden = root.querySelector('[data-searchable-value]');
        const trigger = root.querySelector('[data-searchable-trigger]');
        const labelEl = root.querySelector('[data-searchable-label]');
        const panel = root.querySelector('[data-searchable-panel]');
        const filter = root.querySelector('[data-searchable-filter]');
        const options = root.querySelectorAll('[data-searchable-option]');

        if (!hidden || !trigger || !labelEl || !panel) return;

        const close = () => {
            panel.classList.add('hidden');
            trigger.setAttribute('aria-expanded', 'false');
            root.classList.remove('is-open');
        };

        const open = () => {
            document.querySelectorAll('[data-searchable-select].is-open').forEach((other) => {
                if (other !== root) {
                    other.querySelector('[data-searchable-panel]')?.classList.add('hidden');
                    other.classList.remove('is-open');
                }
            });
            panel.classList.remove('hidden');
            trigger.setAttribute('aria-expanded', 'true');
            root.classList.add('is-open');
            filter?.focus();
        };

        trigger.addEventListener('click', () => {
            if (panel.classList.contains('hidden')) open();
            else close();
        });

        options.forEach((option) => {
            option.addEventListener('click', () => {
                hidden.value = option.dataset.value ?? '';
                labelEl.textContent = option.dataset.label ?? option.textContent.trim();
                options.forEach((o) => o.classList.toggle('is-selected', o === option));
                close();
                hidden.dispatchEvent(new Event('change', { bubbles: true }));
                root.dispatchEvent(
                    new CustomEvent('searchable-select:change', {
                        bubbles: true,
                        detail: {
                            name: hidden.name,
                            value: hidden.value,
                            type: option.dataset.categoryType ?? null,
                            slug: option.dataset.categorySlug ?? null,
                        },
                    }),
                );
            });
        });

        filter?.addEventListener('input', () => {
            const q = filter.value.trim().toLowerCase();
            options.forEach((option) => {
                const text = (option.dataset.label ?? option.textContent).toLowerCase();
                option.closest('li').classList.toggle('hidden', q !== '' && !text.includes(q));
            });
        });

        document.addEventListener('click', (event) => {
            if (!root.contains(event.target)) close();
        });
    });
}

function updateVariationSummary() {
    const groupsList = document.querySelector('[data-option-groups-list]');
    const groupCountEl = document.querySelector('[data-variation-group-count]');
    const optionCountEl = document.querySelector('[data-variation-option-count]');
    if (!groupsList || !groupCountEl || !optionCountEl) return;

    const groups = groupsList.querySelectorAll('[data-option-group]').length;
    const options = groupsList.querySelectorAll('[data-option-row]').length;
    groupCountEl.textContent = String(groups);
    optionCountEl.textContent = String(options);
}

function isRetailCategory(type, slug = '') {
    if (type === 'retail') return true;
    const normalized = slug.toLowerCase();
    return ['biblo', 'figur', 'figür', 'retail', 'hediyelik'].some((key) => normalized.includes(key));
}

function isDrinksCategory(type, slug = '') {
    if (type === 'bar') return true;
    const normalized = slug.toLowerCase();
    return ['icecek', 'icecekler', 'drinks', 'bar'].includes(normalized);
}

function syncVariationUi(detail) {
    const retail = isRetailCategory(detail?.type, detail?.slug ?? '');
    const drinks = isDrinksCategory(detail?.type, detail?.slug ?? '');

    document.querySelector('[data-retail-variation-hint]')?.classList.toggle('hidden', !retail);
    window.HSP_PRODUCT_OPTIONS?.setRetailMode?.(retail);
    window.HSP_PRODUCT_OPTIONS?.setDrinksMode?.(drinks);
}

function syncDepartmentFromCategory(detail) {
    if (!detail || detail.name !== 'category_id') return;

    const typeRoot = [...document.querySelectorAll('[data-searchable-select]')].find((root) => {
        const hidden = root.querySelector('[data-searchable-value]');
        return hidden?.name === 'type';
    });
    const typeHidden = typeRoot?.querySelector('[data-searchable-value]');
    if (!typeHidden || !typeRoot || !detail.type) return;

    typeHidden.value = detail.type;
    const match = typeRoot.querySelector(`[data-searchable-option][data-value="${detail.type}"]`);
    const labelEl = typeRoot.querySelector('[data-searchable-label]');
    if (labelEl) {
        labelEl.textContent = match?.dataset.label ?? labelEl.textContent;
    }
    typeRoot.querySelectorAll('[data-searchable-option]').forEach((option) => {
        option.classList.toggle('is-selected', option.dataset.value === detail.type);
    });
}

function initCategoryStationSync() {
    document.addEventListener('searchable-select:change', (event) => {
        const detail = event.detail;
        syncDepartmentFromCategory(detail);
        syncVariationUi(detail);
    });

    const categoryRoot = [...document.querySelectorAll('[data-searchable-select]')].find((root) => {
        const hidden = root.querySelector('[data-searchable-value]');
        return hidden?.name === 'category_id';
    });
    if (!categoryRoot) return;

    const selected = categoryRoot.querySelector('[data-searchable-option].is-selected');
    if (selected) {
        syncVariationUi({
            type: selected.dataset.categoryType,
            slug: selected.dataset.categorySlug,
        });
        syncDepartmentFromCategory({
            name: 'category_id',
            type: selected.dataset.categoryType,
        });
    }
}

function initVariationsDrawer() {
    const drawer = document.querySelector('[data-product-variations-drawer]');
    if (!drawer || drawer.dataset.bound === '1') return;
    drawer.dataset.bound = '1';

    const open = () => {
        drawer.classList.add('is-open');
        drawer.removeAttribute('inert');
        drawer.setAttribute('aria-hidden', 'false');
        document.body.classList.add('product-variations-open');
        updateVariationSummary();
    };

    const close = () => {
        drawer.classList.remove('is-open');
        drawer.setAttribute('inert', '');
        drawer.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('product-variations-open');
        updateVariationSummary();
    };

    document.querySelectorAll('[data-open-variations-panel]').forEach((btn) => {
        btn.addEventListener('click', open);
    });

    drawer.querySelectorAll('[data-close-variations-panel]').forEach((btn) => {
        btn.addEventListener('click', close);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && drawer.classList.contains('is-open')) close();
    });

    document.addEventListener('product-variations:changed', updateVariationSummary);
}

export function initProductForm() {
    initLocaleTabs();
    initBadgeChips();
    initImageDropzone();
    initSearchableSelects();
    initCategoryStationSync();
    initMenuHierarchyFields();
    initVariationsDrawer();
    updateVariationSummary();

    document.querySelectorAll('[data-product-form]').forEach((form) => {
        bindAdminFormSubmit(form);
    });
}

export { initBadgeChips, initImageDropzone, initSearchableSelects, initVariationsDrawer };

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initProductForm);
} else {
    initProductForm();
}
