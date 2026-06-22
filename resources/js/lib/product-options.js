/**
 * QR menü ve kasa paneli için ortak ürün seçenekleri yardımcıları.
 */

export function defaultSelections(groups) {
    const selections = {};
    groups.forEach((group) => {
        const groupId = Number(group.id);
        if (group.type === 'single') {
            const defaults = group.options.filter((o) => o.default);
            const pick = defaults[0] ?? group.options[0];
            if (pick) selections[groupId] = Number(pick.id);
        } else {
            selections[groupId] = group.options.filter((o) => o.default).map((o) => Number(o.id));
        }
    });
    return selections;
}

export function validateSelections(groups, selections) {
    for (const group of groups) {
        if (!group.required) continue;
        const selected = selections[Number(group.id)];
        if (group.type === 'single') {
            if (!selected) return false;
        } else if (!Array.isArray(selected) || selected.length === 0) {
            return false;
        }
    }
    return true;
}

export function buildSelectedOptions(groups, selections) {
    const resolved = [];

    groups.forEach((group) => {
        const selected = selections[Number(group.id)];
        if (group.type === 'single') {
            if (!selected) return;
            const option = group.options.find((o) => o.id === selected);
            if (!option) return;
            resolved.push({
                group_id: group.id,
                group_name: group.name,
                option_id: option.id,
                name: option.name,
                price: Number(option.price) || 0,
            });
        } else {
            const ids = Array.isArray(selected) ? selected : [];
            ids.forEach((optionId) => {
                const option = group.options.find((o) => o.id === optionId);
                if (!option) return;
                resolved.push({
                    group_id: group.id,
                    group_name: group.name,
                    option_id: option.id,
                    name: option.name,
                    price: Number(option.price) || 0,
                });
            });
        }
    });

    return resolved;
}

export function toApiOptionPayload(selectedOptions) {
    return selectedOptions.map((o) => ({
        group_id: o.group_id,
        option_id: o.option_id,
    }));
}

export function unitPriceFromOptions(basePrice, options) {
    const extras = options.reduce((sum, o) => sum + (Number(o.price) || 0), 0);
    return Number(basePrice) + extras;
}

export function displayNameWithOptions(name, options) {
    if (!options.length) return name;
    return `${name} (${options.map((o) => o.name).join(', ')})`;
}

export function hasProductOptions(product) {
    if (!Array.isArray(product?.option_groups)) {
        return false;
    }

    return product.option_groups.some(
        (group) => Array.isArray(group.options) && group.options.length > 0,
    );
}

/** Katalog listesinde seçenekli işaretli; gruplar henüz API'den yüklenmedi. */
export function productOptionsPendingLoad(product) {
    return product?.has_options === true && !hasProductOptions(product);
}

export function needsOptionPicker(product) {
    return hasProductOptions(product) || productOptionsPendingLoad(product);
}
