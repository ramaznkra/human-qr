/**
 * Müşteri sepeti ↔ localStorage köprüsü.
 * (Livewire mount/hydrate yerine vanilla JS init'te kullanılır.)
 */
export const RESTAURANT_CART_KEY = 'restaurant_cart';

export const CART_STORAGE_VERSION = 2;

/**
 * @param {{ restaurantId?: string|number|null, tableToken?: string|null, locale?: string|null }} context
 */
export function createRestaurantCartStorage(context) {
    const ctx = {
        restaurantId: String(context.restaurantId ?? '0'),
        tableToken: String(context.tableToken ?? 'guest'),
        locale: String(context.locale ?? 'tr'),
    };

    function legacyKey() {
        return `hsp_cart_v1_${ctx.restaurantId}_${ctx.tableToken}_${ctx.locale}`;
    }

    function contextMatches(payload) {
        if (!payload) {
            return false;
        }

        return (
            String(payload.restaurantId ?? '0') === ctx.restaurantId &&
            String(payload.tableToken ?? 'guest') === ctx.tableToken
        );
    }

    function save(items, orderNotes = '') {
        const payload = {
            v: CART_STORAGE_VERSION,
            restaurantId: ctx.restaurantId,
            tableToken: ctx.tableToken,
            locale: ctx.locale,
            items,
            orderNotes,
            savedAt: Date.now(),
        };

        try {
            localStorage.setItem(RESTAURANT_CART_KEY, JSON.stringify(payload));
        } catch {
            /* depolama dolu / gizli mod */
        }

        try {
            localStorage.removeItem(legacyKey());
        } catch {
            /* sessiz */
        }
    }

    function load() {
        const fromPrimary = parsePayload(localStorage.getItem(RESTAURANT_CART_KEY));
        if (fromPrimary && contextMatches(fromPrimary)) {
            return normalizePayload(fromPrimary);
        }

        const fromLegacy = parsePayload(localStorage.getItem(legacyKey()));
        if (fromLegacy && contextMatches(fromLegacy)) {
            save(fromLegacy.items ?? {}, fromLegacy.orderNotes ?? '');
            return normalizePayload(fromLegacy);
        }

        return null;
    }

    function clear() {
        try {
            localStorage.removeItem(RESTAURANT_CART_KEY);
            localStorage.removeItem(legacyKey());
        } catch {
            /* sessiz */
        }
    }

    return { save, load, clear, key: RESTAURANT_CART_KEY };
}

function parsePayload(raw) {
    if (!raw) {
        return null;
    }

    try {
        const data = JSON.parse(raw);
        if (!data || typeof data !== 'object') {
            return null;
        }

        const version = data.v ?? 1;
        if (version !== 1 && version !== CART_STORAGE_VERSION) {
            return null;
        }

        return data;
    } catch {
        return null;
    }
}

function normalizePayload(data) {
    const items = data.items && typeof data.items === 'object' ? data.items : {};

    return {
        items,
        orderNotes: typeof data.orderNotes === 'string' ? data.orderNotes : '',
    };
}
