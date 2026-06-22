/**
 * Admin bağlamsal yan panel — ürün / personel düzenleme.
 */
const scriptLoaders = {
    'product-form': async () => (await import('./admin-product-form.js')).initProductForm(),
    'product-options': async () => (await import('./admin-product-options.js')).initProductOptionsEditor(),
    'locale-tabs': async () => (await import('./admin-locale-tabs.js')).initLocaleTabs(),
    'menu-hierarchy': async () => (await import('./admin-menu-hierarchy.js')).initMenuHierarchyFields(),
};

function getCsrf() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function showDrawerError(body, message) {
    body.innerHTML = `<p class="py-8 text-center text-sm font-medium text-red-400">${message}</p>`;
}

async function loadDrawerScripts(root) {
    const list = (root.dataset.drawerScripts || '').split(',').map((s) => s.trim()).filter(Boolean);
    await Promise.all(list.map((key) => scriptLoaders[key]?.() ?? Promise.resolve()));
}

function bindDrawerForm(form, onSuccess) {
    if (form.dataset.drawerBound === '1') return;
    form.dataset.drawerBound = '1';

    form.addEventListener('submit', async (event) => {
        if (event.defaultPrevented) return;

        event.preventDefault();
        const submitBtn = form.querySelector('[type="submit"]');
        const original = submitBtn?.textContent ?? 'Kaydet';

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Kaydediliyor…';
        }

        try {
            const res = await fetch(form.action, {
                method: form.method.toUpperCase() === 'GET' ? 'GET' : 'POST',
                headers: {
                    'X-CSRF-TOKEN': getCsrf(),
                    'X-Admin-Drawer': '1',
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                body: new FormData(form),
            });

            const data = await res.json().catch(() => ({}));

            if (!res.ok) {
                const firstError = data?.errors
                    ? Object.values(data.errors).flat()[0]
                    : data?.message;
                const message = firstError || 'Kaydedilemedi.';
                const alert = form.querySelector('[data-form-client-alert]')
                    ?? (() => {
                        const el = document.createElement('div');
                        el.dataset.formClientAlert = '1';
                        el.className = 'admin-form-errors';
                        el.setAttribute('role', 'alert');
                        form.insertBefore(el, form.firstChild);
                        return el;
                    })();
                alert.innerHTML = `<p class="admin-form-errors__title">${message}</p>`;
                alert.hidden = false;
                alert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                return;
            }

            onSuccess(data?.message || 'Kaydedildi.');
        } catch {
            alert('Bağlantı hatası.');
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = original;
            }
        }
    });
}

export function initAdminDrawer() {
    const root = document.getElementById('adminDrawer');
    if (!root) return;

    const panel = root.querySelector('.admin-drawer__panel');
    const body = root.querySelector('[data-admin-drawer-body]');
    let open = false;

    const close = () => {
        open = false;
        root.classList.remove('is-open');
        root.setAttribute('aria-hidden', 'true');
        root.setAttribute('inert', '');
        document.body.classList.remove('admin-drawer-open');
        body.innerHTML = '';
    };

    const openDrawer = async (url) => {
        open = true;
        root.classList.add('is-open');
        root.removeAttribute('inert');
        root.setAttribute('aria-hidden', 'false');
        document.body.classList.add('admin-drawer-open');
        body.innerHTML = '<p class="py-8 text-center text-sm font-medium text-zinc-400">Yükleniyor…</p>';

        try {
            const res = await fetch(url, {
                headers: {
                    'X-Admin-Drawer': '1',
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'text/html',
                },
            });

            if (!res.ok) throw new Error('Panel yüklenemedi');

            const html = await res.text();
            body.innerHTML = html;

            const formRoot = body.querySelector('[data-drawer-scripts]') || body;
            await loadDrawerScripts(formRoot);

            body.querySelectorAll('[data-admin-drawer-form]').forEach((form) => {
                bindDrawerForm(form, (message) => {
                    close();
                    window.location.reload();
                });
            });
        } catch {
            showDrawerError(body, 'Panel yüklenemedi.');
        }
    };

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-admin-drawer-open]');
        if (trigger) {
            event.preventDefault();
            const url = trigger.dataset.adminDrawerOpen || trigger.getAttribute('href');
            if (url) openDrawer(url);
            return;
        }

        if (event.target.closest('[data-admin-drawer-close]')) {
            event.preventDefault();
            close();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && open) close();
    });
}

initAdminDrawer();
