import { showAdminToast } from './admin-toast.js';

const ICONS = {
    warning: '⚠️',
    danger: '🗑️',
    info: '⚡',
};

let activeConfirm = null;

/**
 * Canlı sipariş bildirimi stilinde onay kartı (Promise).
 */
export function confirmAdminAction({
    title,
    message = '',
    hint = '',
    type = 'warning',
    confirmLabel = 'Evet',
    cancelLabel = 'Vazgeç',
}) {
    if (activeConfirm) {
        activeConfirm.close(false);
    }

    return new Promise((resolve) => {
        let host = document.getElementById('adminConfirmHost');
        if (!host) {
            host = document.createElement('div');
            host.id = 'adminConfirmHost';
            host.className = 'admin-confirm-host';
            host.setAttribute('aria-live', 'assertive');
            document.body.appendChild(host);
        }

        const backdrop = document.createElement('button');
        backdrop.type = 'button';
        backdrop.className = 'admin-confirm-backdrop';
        backdrop.setAttribute('aria-label', 'İptal');

        const card = document.createElement('div');
        card.className = `admin-confirm admin-confirm--${type}`;
        card.setAttribute('role', 'alertdialog');
        card.setAttribute('aria-modal', 'true');
        card.innerHTML = `
            <div class="admin-confirm__top">
                <div class="admin-confirm__icon" aria-hidden="true">${ICONS[type] || ICONS.warning}</div>
                <div class="admin-confirm__body">
                    <p class="admin-confirm__title"></p>
                    <p class="admin-confirm__message"></p>
                    <p class="admin-confirm__hint"></p>
                </div>
            </div>
            <div class="admin-confirm__actions">
                <button type="button" class="btn btn-secondary btn-sm" data-confirm-cancel></button>
                <button type="button" class="btn btn-sm" data-confirm-ok></button>
            </div>
        `;

        card.querySelector('.admin-confirm__title').textContent = title;
        const msgEl = card.querySelector('.admin-confirm__message');
        const hintEl = card.querySelector('.admin-confirm__hint');
        if (message) {
            msgEl.textContent = message;
        } else {
            msgEl.remove();
        }
        if (hint) {
            hintEl.textContent = hint;
        } else {
            hintEl.remove();
        }

        const cancelBtn = card.querySelector('[data-confirm-cancel]');
        const okBtn = card.querySelector('[data-confirm-ok]');
        cancelBtn.textContent = cancelLabel;
        okBtn.textContent = confirmLabel;
        okBtn.classList.add(type === 'danger' ? 'btn-danger' : 'btn-primary');

        const close = (result) => {
            if (!activeConfirm) return;
            card.classList.add('admin-confirm--out');
            backdrop.classList.add('admin-confirm-backdrop--out');
            setTimeout(() => {
                backdrop.remove();
                card.remove();
                if (!host.childElementCount) {
                    host.remove();
                }
            }, 220);
            activeConfirm = null;
            document.removeEventListener('keydown', onKey);
            resolve(result);
        };

        const onKey = (e) => {
            if (e.key === 'Escape') close(false);
        };

        cancelBtn.addEventListener('click', () => close(false));
        backdrop.addEventListener('click', () => close(false));
        okBtn.addEventListener('click', () => close(true));
        document.addEventListener('keydown', onKey);

        host.appendChild(backdrop);
        host.appendChild(card);
        activeConfirm = { close };

        requestAnimationFrame(() => {
            backdrop.classList.add('admin-confirm-backdrop--in');
            card.classList.add('admin-confirm--in');
            okBtn.focus();
        });
    });
}

function parseConfirmConfig(form) {
    const raw = form.dataset.confirmConfig;
    if (raw) {
        try {
            return JSON.parse(raw);
        } catch {
            /* fall through */
        }
    }

    return {
        title: form.dataset.confirmTitle || 'Emin misiniz?',
        message: form.dataset.confirmMessage || '',
        hint: form.dataset.confirmHint || '',
        type: form.dataset.confirmType || 'warning',
        confirmLabel: form.dataset.confirmLabel || 'Evet',
        cancelLabel: form.dataset.confirmCancel || 'Vazgeç',
    };
}

export function initAdminConfirm() {
    document.addEventListener('submit', async (e) => {
        const form = e.target.closest('form[data-admin-confirm]');
        if (!form || form.dataset.confirmBypass === '1') return;

        e.preventDefault();
        const ok = await confirmAdminAction(parseConfirmConfig(form));
        if (!ok) return;

        form.dataset.confirmBypass = '1';
        form.requestSubmit();
        delete form.dataset.confirmBypass;
    });
}

export function initAdminFlashToasts() {
    document.querySelectorAll('[data-admin-flash]').forEach((el) => {
        const type = el.dataset.adminFlashType || 'success';
        const title = el.dataset.adminFlashTitle || (type === 'error' ? 'Hata' : 'Tamam');
        const message = el.dataset.adminFlashMessage || '';
        if (!message) return;

        showAdminToast({
            title,
            message,
            type: type === 'error' ? 'error' : 'success',
            durationMs: 5000,
        });
        el.remove();
    });
}
