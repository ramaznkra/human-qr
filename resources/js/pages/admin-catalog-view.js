/**
 * Admin katalog görünümü: Sahibinden tarzı tepsi (kare grid) ↔ liste tablosu.
 */
export function initCatalogView(scope) {
    const root = document.querySelector(`[data-catalog-root="${scope}"]`);
    if (!root) return;

    const listPanel = root.querySelector('[data-view-panel="list"]');
    const trayPanel = root.querySelector('[data-view-panel="tray"]');
    const buttons = document.querySelectorAll(`[data-view-scope="${scope}"][data-view-mode]`);
    const storageKey = `admin-catalog-view-${scope}`;

    const applyMode = (mode) => {
        const isTray = mode === 'tray';
        listPanel?.classList.toggle('hidden', isTray);
        trayPanel?.classList.toggle('hidden', !isTray);
        buttons.forEach((btn) => {
            const active = btn.dataset.viewMode === mode;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        try {
            localStorage.setItem(storageKey, mode);
        } catch {
            /* ignore */
        }
    };

    buttons.forEach((btn) => {
        btn.addEventListener('click', () => applyMode(btn.dataset.viewMode));
    });

    let saved = scope === 'products' ? 'list' : 'tray';
    try {
        saved = localStorage.getItem(storageKey) || saved;
    } catch {
        saved = 'tray';
    }
    if (saved !== 'list' && saved !== 'tray') {
        saved = scope === 'products' ? 'list' : 'tray';
    }
    applyMode(saved);
}

function boot() {
    document.querySelectorAll('[data-catalog-root]').forEach((root) => {
        const scope = root.dataset.catalogRoot;
        if (scope) initCatalogView(scope);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
