/**
 * Admin panel toast bildirimleri (Hazırlanıyor, sipariş alındı vb.)
 */
export function showAdminToast({ title, message, hint = '', type = 'success', durationMs = 2000 }) {
    let host = document.getElementById('adminToastHost');
    if (!host) {
        host = document.createElement('div');
        host.id = 'adminToastHost';
        host.className = 'admin-toast-host';
        host.setAttribute('aria-live', 'polite');
        document.body.appendChild(host);
    }

    const icons = {
        success: '👨‍🍳',
        info: '⚡',
        warning: '🔔',
        error: '⚠️',
    };

    const el = document.createElement('div');
    el.className = `admin-toast admin-toast--${type}`;
    el.innerHTML = `
        <div class="admin-toast__icon" aria-hidden="true">${icons[type] || '✓'}</div>
        <div class="admin-toast__body">
            <p class="admin-toast__title">${title}</p>
            ${message ? `<p class="admin-toast__message">${message}</p>` : ''}
            ${hint ? `<p class="admin-toast__hint">${hint}</p>` : ''}
        </div>
        <button type="button" class="admin-toast__close" aria-label="Kapat">✕</button>
    `;

    const dismiss = () => {
        el.classList.add('admin-toast--out');
        setTimeout(() => el.remove(), 280);
    };

    el.querySelector('.admin-toast__close')?.addEventListener('click', dismiss);
    host.appendChild(el);
    requestAnimationFrame(() => el.classList.add('admin-toast--in'));

    // Tüm bildirimler 2 saniyede elle kapatmaya gerek kalmadan kapanır.
    let remaining = 2000;
    let startedAt = Date.now();
    let timer = setTimeout(dismiss, remaining);

    // Fareyle üzerine gelince duraklat, ayrılınca kalan süreden devam et (asılı kalmasın).
    el.addEventListener('mouseenter', () => {
        clearTimeout(timer);
        remaining -= Date.now() - startedAt;
    });
    el.addEventListener('mouseleave', () => {
        startedAt = Date.now();
        timer = setTimeout(dismiss, Math.max(remaining, 600));
    });
}

/**
 * Kasa paneli: sağ altta 2 sn görünen minimal durum çubuğu (ödeme vb.).
 */
export function showKasaFlashBar(message, type = 'success', durationMs = 2000) {
    let host = document.getElementById('kasaFlashBarHost');
    if (!host) {
        host = document.createElement('div');
        host.id = 'kasaFlashBarHost';
        host.className = 'kasa-flash-bar-host';
        host.setAttribute('aria-live', 'polite');
        document.body.appendChild(host);
    }

    host.querySelectorAll('.kasa-flash-bar').forEach((el) => el.remove());

    const el = document.createElement('div');
    el.className = `kasa-flash-bar kasa-flash-bar--${type}`;
    el.textContent = message;
    host.appendChild(el);
    requestAnimationFrame(() => el.classList.add('kasa-flash-bar--in'));

    const dismiss = () => {
        el.classList.add('kasa-flash-bar--out');
        setTimeout(() => el.remove(), 280);
    };

    setTimeout(dismiss, durationMs);
}
