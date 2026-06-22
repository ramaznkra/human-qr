function initProductOptionsEditor() {
    const root = document.querySelector('[data-product-options]');
    if (!root) return;
    if (root.dataset.optionsEditorBound === '1') return;
    root.dataset.optionsEditorBound = '1';

    const groupsList = root.querySelector('[data-option-groups-list]');
    const emptyHint = root.querySelector('[data-option-groups-empty]');
    const groupTemplate = root.querySelector('[data-option-group-template]');
    const optionTemplate = root.querySelector('[data-option-row-template]');

    const DRINK_TEMPLATES = {
        temperature: {
            name: { tr: 'Sıcaklık', en: 'Temperature', ru: 'Температура' },
            type: 'single',
            required: true,
            options: [
                { name: { tr: 'Sıcak', en: 'Hot', ru: 'Горячий' }, price_modifier: 0, is_default: true },
                { name: { tr: 'Buzlu', en: 'Iced', ru: 'Со льдом' }, price_modifier: 0, is_default: false },
            ],
        },
        milk: {
            name: { tr: 'Süt Değişimi', en: 'Milk', ru: 'Молоко' },
            type: 'single',
            required: false,
            options: [
                { name: { tr: 'Normal Süt', en: 'Regular Milk', ru: 'Обычное' }, price_modifier: 0, is_default: true },
                { name: { tr: 'Yağsız Süt', en: 'Non-Fat Milk', ru: 'Обезжиренное' }, price_modifier: 0, is_default: false },
                { name: { tr: 'Laktozsuz Süt', en: 'Lactose-Free', ru: 'Без лактозы' }, price_modifier: 0, is_default: false },
                { name: { tr: 'Soya Sütü', en: 'Soy Milk', ru: 'Соевое' }, price_modifier: 15, is_default: false },
                { name: { tr: 'Yulaf Sütü', en: 'Oat Milk', ru: 'Овсяное' }, price_modifier: 15, is_default: false },
                { name: { tr: 'Badem Sütü', en: 'Almond Milk', ru: 'Миндальное' }, price_modifier: 15, is_default: false },
            ],
        },
        shot: {
            name: { tr: 'Shot', en: 'Shot', ru: 'Шот' },
            type: 'single',
            required: false,
            options: [
                { name: { tr: 'Single', en: 'Single', ru: 'Single' }, price_modifier: 0, is_default: true },
                { name: { tr: 'Double', en: 'Double', ru: 'Double' }, price_modifier: 15, is_default: false },
                { name: { tr: 'Triple', en: 'Triple', ru: 'Triple' }, price_modifier: 25, is_default: false },
                { name: { tr: 'Quadruple', en: 'Quadruple', ru: 'Quadruple' }, price_modifier: 35, is_default: false },
            ],
        },
    };

    function toggleEmptyHint() {
        if (!emptyHint || !groupsList) return;
        emptyHint.hidden = groupsList.children.length > 0;
    }

    function replacePlaceholders(html, gi, oi = null) {
        let out = html.replaceAll('__GI__', String(gi));
        if (oi !== null) {
            out = out.replaceAll('__OI__', String(oi));
        }
        return out;
    }

    function reindexGroups() {
        if (!groupsList) return;

        groupsList.querySelectorAll('[data-option-group]').forEach((groupEl, gi) => {
            groupEl.querySelectorAll('[name]').forEach((input) => {
                input.name = input.name
                    .replace(/option_groups\[\d+\]/, `option_groups[${gi}]`)
                    .replace(/option_groups\[__GI__\]/, `option_groups[${gi}]`);
            });

            const sortInput = groupEl.querySelector('[data-group-sort-order]');
            if (sortInput) sortInput.value = String(gi);

            groupEl.querySelectorAll('[data-option-row]').forEach((rowEl, oi) => {
                rowEl.querySelectorAll('[name]').forEach((input) => {
                    input.name = input.name
                        .replace(/option_groups\[\d+\]\[options\]\[\d+\]/, `option_groups[${gi}][options][${oi}]`)
                        .replace(/option_groups\[__GI__\]\[options\]\[__OI__\]/, `option_groups[${gi}][options][${oi}]`);
                });

                const optSort = rowEl.querySelector('[data-option-sort-order]');
                if (optSort) optSort.value = String(oi);
            });
        });
    }

    function updateDefaultHints(groupEl) {
        const type = groupEl.querySelector('[data-group-type-select]')?.value || 'single';
        groupEl.dataset.groupType = type;

        groupEl.querySelectorAll('[data-default-hint]').forEach((hint) => {
            hint.textContent = type === 'single' ? 'Seçili gelsin' : 'Önceden işaretli';
        });
    }

    function syncOptionActiveState(row, isActive) {
        row.classList.toggle('product-option-row--inactive', !isActive);
        const badge = row.querySelector('.product-option-row__status');
        if (badge) {
            badge.textContent = isActive ? 'Aktif' : 'Pasif';
            badge.classList.toggle('product-option-row__status--on', isActive);
            badge.classList.toggle('product-option-row__status--off', !isActive);
        }
    }

    function fillOptionRow(row, optionData) {
        if (!row || !optionData) return;

        const trInput = row.querySelector('[name*="[name][tr]"]');
        if (trInput && optionData.name?.tr != null) trInput.value = optionData.name.tr;

        const enInput = row.querySelector('[name*="[name][en]"]');
        if (enInput && optionData.name?.en != null) enInput.value = optionData.name.en;

        const ruInput = row.querySelector('[name*="[name][ru]"]');
        if (ruInput && optionData.name?.ru != null) ruInput.value = optionData.name.ru;

        const priceInput = row.querySelector('[name*="[price_modifier]"]');
        if (priceInput && optionData.price_modifier != null) {
            priceInput.value = String(optionData.price_modifier);
        }

        const defaultInput = row.querySelector('[data-option-default]');
        if (defaultInput) defaultInput.checked = !!optionData.is_default;

        const activeToggle = row.querySelector('[data-option-active-toggle]');
        const isActive = optionData.is_active !== false;
        if (activeToggle) activeToggle.checked = isActive;
        syncOptionActiveState(row, isActive);
    }

    function bindOptionRowEvents(groupEl, row) {
        row.querySelector('[data-remove-option]')?.addEventListener('click', () => {
            row.remove();
            reindexGroups();
            document.dispatchEvent(new CustomEvent('product-variations:changed'));
        });

        row.querySelector('[data-option-default]')?.addEventListener('change', (e) => {
            enforceSingleDefault(groupEl, e.target);
        });

        row.querySelector('[data-option-active-toggle]')?.addEventListener('change', (e) => {
            syncOptionActiveState(row, e.target.checked);
        });
    }

    function enforceSingleDefault(groupEl, activeInput) {
        const type = groupEl.querySelector('[data-group-type-select]')?.value || 'single';
        if (type !== 'single' || !activeInput?.checked) return;

        groupEl.querySelectorAll('[data-option-default]').forEach((input) => {
            if (input !== activeInput) input.checked = false;
        });
    }

    function bindGroupEvents(groupEl) {
        groupEl.querySelector('[data-remove-option-group]')?.addEventListener('click', () => {
            groupEl.remove();
            reindexGroups();
            toggleEmptyHint();
            document.dispatchEvent(new CustomEvent('product-variations:changed'));
        });

        groupEl.querySelector('[data-group-type-select]')?.addEventListener('change', () => {
            updateDefaultHints(groupEl);
        });

        groupEl.querySelector('[data-add-option]')?.addEventListener('click', () => {
            addOptionRow(groupEl);
        });

        groupEl.querySelectorAll('[data-option-row]').forEach((row) => {
            bindOptionRowEvents(groupEl, row);
        });

        updateDefaultHints(groupEl);
    }

    function addOptionRow(groupEl, optionData = null) {
        if (!optionTemplate || !groupEl) return;

        const optionsList = groupEl.querySelector('[data-options-list]');
        if (!optionsList) return;

        const gi = Array.from(groupsList.children).indexOf(groupEl);
        const oi = optionsList.querySelectorAll('[data-option-row]').length;
        const html = replacePlaceholders(optionTemplate.innerHTML, gi, oi);
        const wrap = document.createElement('div');
        wrap.innerHTML = html.trim();
        const row = wrap.firstElementChild;
        if (!row) return;

        optionsList.appendChild(row);
        bindOptionRowEvents(groupEl, row);

        if (optionData) {
            fillOptionRow(row, optionData);
        }

        reindexGroups();
        document.dispatchEvent(new CustomEvent('product-variations:changed'));
    }

    function addGroup(groupData = null) {
        if (!groupTemplate || !groupsList) return null;

        const gi = groupsList.children.length;
        const html = replacePlaceholders(groupTemplate.innerHTML, gi);
        const wrap = document.createElement('div');
        wrap.innerHTML = html.trim();
        const groupEl = wrap.firstElementChild;
        if (!groupEl) return null;

        groupsList.appendChild(groupEl);

        if (groupData) {
            const trName = groupEl.querySelector('[name*="[name][tr]"]');
            if (trName && groupData.name?.tr != null) trName.value = groupData.name.tr;

            const enName = groupEl.querySelector('[name*="[name][en]"]');
            if (enName && groupData.name?.en != null) enName.value = groupData.name.en;

            const ruName = groupEl.querySelector('[name*="[name][ru]"]');
            if (ruName && groupData.name?.ru != null) ruName.value = groupData.name.ru;

            const typeSelect = groupEl.querySelector('[data-group-type-select]');
            if (typeSelect && groupData.type) typeSelect.value = groupData.type;

            const requiredCheckbox = groupEl.querySelector('[name*="[required]"][value="1"]');
            if (requiredCheckbox) requiredCheckbox.checked = !!groupData.required;

            const optionsList = groupEl.querySelector('[data-options-list]');
            if (optionsList) optionsList.innerHTML = '';

            if (Array.isArray(groupData.options) && groupData.options.length) {
                groupData.options.forEach((optionData) => addOptionRow(groupEl, optionData));
            }
        } else {
            addOptionRow(groupEl);
        }

        bindGroupEvents(groupEl);
        reindexGroups();
        toggleEmptyHint();
        document.dispatchEvent(new CustomEvent('product-variations:changed'));

        return groupEl;
    }

    function findGroupByTrName(trName) {
        if (!groupsList || !trName) return null;
        return [...groupsList.querySelectorAll('[data-option-group]')].find((groupEl) => {
            const input = groupEl.querySelector('[name*="[name][tr]"]');
            return input?.value.trim() === trName;
        }) ?? null;
    }

    function applyDrinkTemplateGroup(groupData) {
        if (!groupData?.name?.tr) return;

        const existing = findGroupByTrName(groupData.name.tr);
        if (existing) return;

        addGroup(groupData);
    }

    function applyDrinkTemplate(templateKey) {
        if (!groupsList || !groupTemplate) return;

        const keys =
            templateKey === 'full'
                ? ['temperature', 'milk', 'shot']
                : [templateKey];

        if (
            templateKey === 'full' &&
            groupsList.children.length > 0 &&
            !window.confirm('Mevcut gruplar korunur; eksik şablon grupları eklenecek. Devam?')
        ) {
            return;
        }

        keys.forEach((key) => {
            const groupData = DRINK_TEMPLATES[key];
            if (groupData) applyDrinkTemplateGroup(groupData);
        });

        reindexGroups();
        toggleEmptyHint();
        document.dispatchEvent(new CustomEvent('product-variations:changed'));
    }

    function applyRetailBoyTemplate() {
        if (!groupsList || !groupTemplate) return;

        if (
            groupsList.children.length > 0 &&
            !window.confirm('Mevcut varyasyon grupları temizlenip Biblo/Figür boy şablonu uygulansın mı?')
        ) {
            return;
        }

        groupsList.innerHTML = '';

        const groupEl = addGroup({
            name: { tr: 'Boy', en: 'Size' },
            type: 'single',
            required: true,
        });

        if (!groupEl) return;

        addOptionRow(groupEl, {
            name: { tr: 'Küçük Boy', en: 'Small' },
            price_modifier: 150,
            is_default: true,
        });
        addOptionRow(groupEl, {
            name: { tr: 'Büyük Boy', en: 'Large' },
            price_modifier: 300,
        });

        reindexGroups();
        toggleEmptyHint();
        document.dispatchEvent(new CustomEvent('product-variations:changed'));
    }

    root.querySelector('[data-add-option-group]')?.addEventListener('click', () => addGroup());

    document.querySelectorAll('[data-apply-retail-boy-template]').forEach((btn) => {
        btn.addEventListener('click', applyRetailBoyTemplate);
    });

    document.querySelectorAll('[data-apply-drink-template]').forEach((btn) => {
        btn.addEventListener('click', () => {
            applyDrinkTemplate(btn.dataset.applyDrinkTemplate || '');
            document.querySelector('[data-product-variations-drawer]')?.classList.add('is-open');
            document.querySelector('[data-product-variations-drawer]')?.removeAttribute('inert');
            document.querySelector('[data-product-variations-drawer]')?.setAttribute('aria-hidden', 'false');
            document.body.classList.add('product-variations-open');
        });
    });

    groupsList?.querySelectorAll('[data-option-group]').forEach((groupEl) => {
        bindGroupEvents(groupEl);
    });

    toggleEmptyHint();

    window.HSP_PRODUCT_OPTIONS = {
        applyRetailBoyTemplate,
        applyDrinkTemplate,
        setRetailMode: (enabled) => {
            root.querySelector('[data-retail-variation-banner]')?.classList.toggle('hidden', !enabled);
        },
        setDrinksMode: (enabled) => {
            document.querySelector('[data-drinks-variation-panel]')?.classList.toggle('hidden', !enabled);
            document.querySelector('[data-variation-default-hint]')?.classList.toggle('hidden', enabled);
            document.querySelector('[data-variation-drinks-hint]')?.classList.toggle('hidden', !enabled);
        },
    };
}

export { initProductOptionsEditor };

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initProductOptionsEditor);
} else {
    initProductOptionsEditor();
}
