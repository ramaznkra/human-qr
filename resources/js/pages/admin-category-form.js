import { initLocaleTabs } from './admin-locale-tabs.js';

function renderCategoryPreview(preview, url) {
    if (!preview) return;
    preview.innerHTML = `<img src="${url}" alt="" class="category-image-dropzone__img">`;
    preview.closest('[data-category-image-dropzone]')?.classList.add('is-filled');
}

function renderCategoryEmpty(preview) {
    if (!preview) return;
    preview.innerHTML = `
        <div class="category-image-dropzone__empty">
            <span class="category-image-dropzone__icon" aria-hidden="true">📸</span>
            <p class="category-image-dropzone__title">Banner görselini sürükleyin veya tıklayın</p>
            <p class="category-image-dropzone__hint">JPG, PNG, WEBP · Maks. 3MB</p>
        </div>
    `;
    preview.closest('[data-category-image-dropzone]')?.classList.remove('is-filled');
}

function syncStationRadioCards(root) {
    root?.querySelectorAll('[data-station-radio-grid]').forEach((grid) => {
        grid.querySelectorAll('[data-station-radio-card]').forEach((card) => {
            const radio = card.querySelector('input[type="radio"]');
            card.classList.toggle('is-selected', radio?.checked ?? false);
        });
    });
}

function initCategoryImageDropzone(root) {
    root.querySelectorAll('[data-category-image-dropzone]').forEach((zone) => {
        if (zone.dataset.bound === '1') return;
        zone.dataset.bound = '1';

        const input = zone.querySelector('[data-category-image-input]');
        const preview = zone.querySelector('[data-category-image-preview]');
        const removeCheckbox = root.querySelector('[data-category-remove-image]');
        if (!input || !preview) return;

        if (preview.querySelector('img')) {
            zone.classList.add('is-filled');
        }

        const showFile = (file) => {
            if (!file || !file.type.startsWith('image/')) return;
            const url = URL.createObjectURL(file);
            renderCategoryPreview(preview, url);
            if (removeCheckbox) removeCheckbox.checked = false;
        };

        input.addEventListener('change', () => {
            const file = input.files?.[0];
            if (file) showFile(file);
        });

        zone.addEventListener('dragover', (event) => {
            event.preventDefault();
            zone.classList.add('is-dragover');
        });

        zone.addEventListener('dragleave', () => zone.classList.remove('is-dragover'));

        zone.addEventListener('drop', (event) => {
            event.preventDefault();
            zone.classList.remove('is-dragover');
            const file = event.dataTransfer?.files?.[0];
            if (!file) return;
            const dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;
            showFile(file);
        });

        removeCheckbox?.addEventListener('change', () => {
            if (!removeCheckbox.checked) return;
            input.value = '';
            renderCategoryEmpty(preview);
        });
    });
}

function bindCategoryFormDelegation(root) {
    if (root.dataset.delegationBound === '1') return;
    root.dataset.delegationBound = '1';

    root.addEventListener('click', (event) => {
        const stationCard = event.target.closest('[data-station-radio-card]');
        if (stationCard && root.contains(stationCard)) {
            const radio = stationCard.querySelector('input[type="radio"]');
            if (radio) {
                radio.checked = true;
                radio.dispatchEvent(new Event('change', { bubbles: true }));
                syncStationRadioCards(root);
            }
        }
    });

    root.querySelectorAll('[data-station-radio-card] input[type="radio"]').forEach((radio) => {
        radio.addEventListener('change', () => syncStationRadioCards(root));
    });
}

export function initCategoryForm() {
    document.querySelectorAll('[data-category-form]').forEach((root) => {
        if (root.dataset.categoryFormBound === '1') return;
        root.dataset.categoryFormBound = '1';

        initLocaleTabs();
        initCategoryImageDropzone(root);
        bindCategoryFormDelegation(root);
        syncStationRadioCards(root);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCategoryForm);
} else {
    initCategoryForm();
}
