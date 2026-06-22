/**
 * Garson / kasa ürün kataloğu — tek açık kategori (accordion).
 */

export function categoryEmoji(cat) {
    const icon = String(cat?.icon || '').trim();
    if (icon && icon.length <= 4) return icon;
    const type = String(cat?.type || '');
    if (type === 'bar') return '🥤';
    if (type === 'nargile') return '💨';
    if (type === 'retail') return '🏛';
    if (type === 'kitchen') return '🍽';
    return '📋';
}

/**
 * @param {object} options
 * @param {object[]} options.categories
 * @param {number|null} options.activeCategoryId
 * @param {Map<number, object[]>|Record<number, object[]>} options.productsByCategory
 * @param {Set<number>} [options.loadingCategoryIds]
 * @param {(product: object) => number} [options.getProductQty]
 * @param {(n: number) => string} options.formatMoney
 * @param {(text: unknown) => string} options.escapeHtml
 * @param {(product: object) => boolean} [options.isConfigurable]
 */
export function renderStaffProductAccordion(container, options) {
    if (!container) return;

    const {
        categories = [],
        activeCategoryId = null,
        productsByCategory = new Map(),
        loadingCategoryIds = new Set(),
        getProductQty = () => 0,
        formatMoney,
        escapeHtml,
        isConfigurable = () => false,
    } = options;

    const map =
        productsByCategory instanceof Map
            ? productsByCategory
            : new Map(Object.entries(productsByCategory).map(([k, v]) => [Number(k), v]));

    if (!categories.length) {
        container.innerHTML =
            '<p class="staff-accordion__empty">Kategori bulunamadı</p>';
        return;
    }

    container.innerHTML = categories
        .map((cat) => {
            const open = Number(activeCategoryId) === Number(cat.id);
            const loading = loadingCategoryIds.has(Number(cat.id));
            const products = map.get(Number(cat.id)) || [];

            let panelBody = '';
            if (open) {
                if (loading) {
                    panelBody =
                        '<p class="staff-accordion__loading">Ürünler yükleniyor…</p>';
                } else if (!products.length) {
                    panelBody =
                        '<p class="staff-accordion__empty">Bu kategoride ürün yok</p>';
                } else {
                    panelBody = products
                        .map((p) => renderProductRow(p, { getProductQty, formatMoney, escapeHtml, isConfigurable }))
                        .join('');
                }
            }

            return `
        <section class="staff-accordion__item${open ? ' is-open' : ''}" data-accordion-cat="${cat.id}">
            <button
                type="button"
                class="staff-accordion__header"
                data-accordion-toggle="${cat.id}"
                aria-expanded="${open ? 'true' : 'false'}"
            >
                <span class="staff-accordion__icon" aria-hidden="true">${escapeHtml(categoryEmoji(cat))}</span>
                <span class="staff-accordion__name">${escapeHtml(cat.name)}</span>
                <span class="staff-accordion__chev" aria-hidden="true">${open ? '🔼' : '🔽'}</span>
            </button>
            <div class="staff-accordion__panel${open ? ' is-open' : ''}" data-accordion-panel="${cat.id}">
                <div class="staff-accordion__panel-inner">
                    ${panelBody}
                </div>
            </div>
        </section>`;
        })
        .join('');
}

function renderProductRow(product, { getProductQty, formatMoney, escapeHtml, isConfigurable }) {
    const configurable = isConfigurable(product);
    const qty = getProductQty(product.id);
    const optsHint = configurable
        ? '<span class="staff-accordion__opts" title="Seçenekli">◆</span>'
        : '';
    const priceHint = configurable
        ? `<span class="staff-accordion__price">${formatMoney(product.price)}'den</span>`
        : `<span class="staff-accordion__price">${formatMoney(product.price)}</span>`;

    return `
        <div class="staff-accordion__product" data-product-id="${product.id}">
            <div class="staff-accordion__product-main">
                <span class="staff-accordion__product-name">${escapeHtml(product.name)}${optsHint}</span>
                ${priceHint}
            </div>
            <div class="staff-accordion__qty" role="group" aria-label="Adet">
                <button
                    type="button"
                    class="staff-accordion__qty-btn staff-accordion__qty-btn--dec"
                    data-product-dec="${product.id}"
                    aria-label="Azalt"
                    ${qty <= 0 ? 'disabled' : ''}
                >−</button>
                <span class="staff-accordion__qty-value" data-product-qty="${product.id}">${qty}</span>
                <button
                    type="button"
                    class="staff-accordion__qty-btn staff-accordion__qty-btn--inc"
                    data-product-inc="${product.id}"
                    aria-label="Artır"
                >+</button>
            </div>
        </div>`;
}

/**
 * @param {HTMLElement} container
 * @param {{ onToggleCategory: (id: number) => void, onProductInc: (id: number, btn: HTMLElement) => void, onProductDec: (id: number, btn: HTMLElement) => void }} handlers
 */
export function bindStaffProductAccordion(container, handlers) {
    if (!container) return;

    if (container._accordionClickHandler) {
        container.removeEventListener('click', container._accordionClickHandler);
    }

    const handler = (event) => {
        const toggle = event.target.closest('[data-accordion-toggle]');
        if (toggle && container.contains(toggle)) {
            event.preventDefault();
            handlers.onToggleCategory(Number(toggle.dataset.accordionToggle));
            return;
        }

        const inc = event.target.closest('[data-product-inc]');
        if (inc && container.contains(inc)) {
            event.preventDefault();
            handlers.onProductInc(Number(inc.dataset.productInc), inc);
            return;
        }

        const dec = event.target.closest('[data-product-dec]');
        if (dec && container.contains(dec)) {
            event.preventDefault();
            handlers.onProductDec(Number(dec.dataset.productDec), dec);
        }
    };

    container._accordionClickHandler = handler;
    container.addEventListener('click', handler);
}
