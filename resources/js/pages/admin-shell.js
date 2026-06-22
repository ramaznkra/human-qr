import { initAdminConfirm, initAdminFlashToasts } from '../admin-confirm.js';
import './admin-drawer.js';

initAdminConfirm();
initAdminFlashToasts();

document.addEventListener('click', (event) => {
    const btn = event.target.closest('[data-copy-target]');
    if (!btn) return;

    const input = btn.previousElementSibling;
    const value = input instanceof HTMLInputElement ? input.value : '';
    if (!value) return;

    navigator.clipboard?.writeText(value).then(() => {
        const original = btn.innerHTML;
        btn.innerHTML = '✓';
        btn.classList.add('text-emerald-400');
        setTimeout(() => {
            btn.innerHTML = original;
            btn.classList.remove('text-emerald-400');
        }, 1200);
    });
});
