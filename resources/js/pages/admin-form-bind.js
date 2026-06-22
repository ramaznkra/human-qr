/** Ortak admin form bağlama — Blade POST formları (Livewire değil). */
const DEFAULT_MAX_IMAGE_BYTES = 10 * 1024 * 1024;

export function resolveMaxImageBytes() {
    const fromWindow = window.HSP_MAX_IMAGE_BYTES;
    if (typeof fromWindow === 'number' && fromWindow > 0) {
        return fromWindow;
    }

    return DEFAULT_MAX_IMAGE_BYTES;
}

export function maxImageSizeLabel() {
    return `${Math.round(resolveMaxImageBytes() / 1024 / 1024)} MB`;
}

export function imageTooLargeMessage() {
    return `Görsel ${maxImageSizeLabel()} sınırını aşıyor. Form gönderilemez; lütfen daha küçük bir görsel seçin.`;
}

function ensureFormAlert(form) {
    let alert = form.querySelector('[data-form-client-alert]');
    if (!alert) {
        alert = document.createElement('div');
        alert.dataset.formClientAlert = '1';
        alert.className = 'admin-form-errors';
        alert.setAttribute('role', 'alert');
        alert.hidden = true;
        form.insertBefore(alert, form.firstChild);
    }
    return alert;
}

export function showFormClientError(form, message) {
    const alert = ensureFormAlert(form);
    alert.innerHTML = `<p class="admin-form-errors__title">${message}</p>`;
    alert.hidden = false;
}

export function clearFormClientError(form) {
    form.querySelector('[data-form-client-alert]')?.remove();
}

/**
 * Gönderim öncesi: TR adı ve görsel boyutu kontrolü.
 * POST verisi PHP tarafında silinmeden kullanıcıya net uyarı verir.
 */
export function bindAdminFormSubmit(form) {
    if (!form || form.dataset.submitGuardBound === '1') return;
    form.dataset.submitGuardBound = '1';

    form.addEventListener('submit', (event) => {
        clearFormClientError(form);

        const nameTr = form.querySelector('[name="name[tr]"]');
        const trValue = nameTr?.value?.trim() ?? '';

        const imageInput = form.querySelector('input[type="file"][name="image"]');
        const file = imageInput?.files?.[0];
        const maxBytes = resolveMaxImageBytes();
        if (file && file.size > maxBytes) {
            event.preventDefault();
            event.stopImmediatePropagation();
            showFormClientError(form, imageTooLargeMessage());
            imageInput.closest('[data-category-image-dropzone], [data-image-dropzone]')
                ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        if (!trValue) {
            event.preventDefault();
            event.stopImmediatePropagation();
            showFormClientError(form, 'Türkçe ad alanı zorunludur. Lütfen Türkçe sekmesinde adı girin.');
            form.querySelector('[data-locale-tabs] [data-locale-tab="tr"]')?.click();
            nameTr?.focus();
        }
    });
}
