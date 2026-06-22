/**
 * Smart order status polling with exponential backoff + progress UI.
 */
export function initOrderStatusPoll(config) {
    const {
        orderId,
        initialStatus,
        initialStep = 1,
        pollUrl,
        menuRedirectUrl = '',
        redirectDelayMs = 5000,
        stepLabels: configStepLabels = null,
        i18n: i18nConfig = {},
        intervalMs = 5000,
        maxIntervalMs = 60000,
        finalStatuses = ['cancelled', 'completed'],
    } = config;

    const icons = {
        pending: '⏳',
        preparing: '👨‍🍳',
        ready: '🚶',
        delivered: '🎉',
        cancelled: '❌',
        completed: '🎉',
    };

    const statusClasses = {
        pending: 'status-pending',
        preparing: 'status-preparing',
        ready: 'status-ready',
        delivered: 'status-delivered',
        cancelled: 'status-cancelled',
        completed: 'status-delivered',
    };

    const stepLabels =
        configStepLabels && configStepLabels.length >= 3
            ? configStepLabels
            : ['Sipariş Alındı', 'Hazırlanıyor', 'Afiyet Olsun'];
    const progressSteps = 3;
    const i18n = i18nConfig;

    let currentStatus = initialStatus;
    let currentStep = initialStep;
    let lastUpdatedAt = null;
    let pollTimer = null;
    let failCount = 0;
    let currentInterval = intervalMs;
    let redirectTimer = null;
    let redirectCountdownTimer = null;
    let redirectSecondsLeft = 0;

    const elLabel = document.getElementById('statusLabel');
    const elIcon = document.getElementById('statusIcon');
    const elBanner = document.getElementById('pollBanner');
    const elHint = document.getElementById('pollHint');
    const elProgressFill = document.getElementById('progressFill');
    const elProgressStepLabel = document.getElementById('progressStepLabel');
    const elProgressSteps = document.querySelectorAll('.order-progress-step');
    const elStatusMessage = document.getElementById('statusMessage');
    const elAfiyetBlock = document.getElementById('statusAfiyetBlock');
    const elPaymentNote = document.getElementById('statusPaymentNote');

    function setBanner(type, message) {
        if (!elBanner) return;
        elBanner.classList.remove('hidden', 'border-amber-500/30', 'bg-amber-500/10', 'border-red-500/30', 'bg-red-500/10', 'text-amber-200', 'text-red-200');
        if (type === 'loading') {
            elBanner.classList.add('border-amber-500/30', 'bg-amber-500/10', 'text-amber-200');
            elBanner.textContent = message;
            elBanner.classList.remove('hidden');
        } else if (type === 'error') {
            elBanner.classList.add('border-red-500/30', 'bg-red-500/10', 'text-red-200');
            elBanner.textContent = message;
            elBanner.classList.remove('hidden');
        } else {
            elBanner.classList.add('hidden');
            elBanner.textContent = '';
        }
    }

    function cancelMenuRedirect() {
        if (redirectTimer) {
            clearTimeout(redirectTimer);
            redirectTimer = null;
        }
        if (redirectCountdownTimer) {
            clearInterval(redirectCountdownTimer);
            redirectCountdownTimer = null;
        }
        redirectSecondsLeft = 0;
    }

    function updateRedirectHint() {
        if (!elHint || redirectSecondsLeft <= 0) return;
        const tpl = i18n.redirectMenu || ':seconds sn içinde menüye yönlendiriliyorsunuz…';
        elHint.textContent = tpl.replace(':seconds', String(redirectSecondsLeft));
    }

    function scheduleMenuRedirect() {
        if (!menuRedirectUrl) return;

        cancelMenuRedirect();
        redirectSecondsLeft = Math.max(1, Math.round(redirectDelayMs / 1000));
        updateRedirectHint();

        redirectCountdownTimer = setInterval(() => {
            redirectSecondsLeft -= 1;
            if (redirectSecondsLeft <= 0) {
                clearInterval(redirectCountdownTimer);
                redirectCountdownTimer = null;
                return;
            }
            updateRedirectHint();
        }, 1000);

        redirectTimer = setTimeout(() => {
            window.location.href = menuRedirectUrl;
        }, redirectDelayMs);
    }

    function maybeRedirectAfterAfiyet(data) {
        const unpaidDelivered = data.status === 'delivered' && !data.payment_method;
        if (unpaidDelivered) {
            scheduleMenuRedirect();
        } else {
            cancelMenuRedirect();
        }
    }

    function updateProgressUI(step, label) {
        const safeStep = Math.max(0, Math.min(progressSteps, step));
        const percent = safeStep > 0 ? (safeStep / progressSteps) * 100 : 0;

        if (elProgressFill) {
            elProgressFill.style.width = `${percent}%`;
        }

        if (elProgressStepLabel && label) {
            elProgressStepLabel.textContent = label;
        }

        elProgressSteps.forEach((el) => {
            const stepNum = parseInt(el.dataset.step, 10);
            el.classList.remove('is-active', 'is-done');
            if (safeStep > stepNum) {
                el.classList.add('is-done');
            } else if (safeStep === stepNum) {
                el.classList.add('is-active');
            }
        });
    }

    function applyStatus(data) {
        if (data.updated_at !== undefined) {
            lastUpdatedAt = data.updated_at;
        }

        const statusChanged = data.status !== currentStatus;
        const stepChanged = data.status_step !== undefined && data.status_step !== currentStep;

        if (!statusChanged && !stepChanged) {
            return false;
        }

        currentStatus = data.status;
        if (data.status_step !== undefined) {
            currentStep = data.status_step;
        }
        failCount = 0;
        currentInterval = intervalMs;
        setBanner(null);

        const customerLabel = data.customer_status_label || data.status_label;

        updateProgressUI(
            currentStep,
            customerLabel || stepLabels[Math.max(0, currentStep - 1)],
        );

        if (elLabel) {
            elLabel.textContent = customerLabel;
            elLabel.className = [
                'status-label inline-block rounded-full px-5 py-2 text-sm font-semibold tracking-wide',
                'transition-all duration-500 ease-in-out status-label-pulse',
                statusClasses[data.status] || 'status-pending',
            ].join(' ');
        }

        if (elIcon) {
            elIcon.textContent = icons[data.status] || '⏳';
            elIcon.classList.add('scale-110');
            setTimeout(() => elIcon?.classList.remove('scale-110'), 500);
        }

        const message = data.customer_status_message || '';
        if (elStatusMessage) {
            elStatusMessage.textContent = message;
        }

        const isPaid = data.status === 'delivered' && data.payment_method;

        if (elAfiyetBlock) {
            elAfiyetBlock.classList.toggle(
                'hidden',
                data.status !== 'delivered' || isPaid,
            );
        }

        if (elAfiyetBlock && data.status === 'delivered' && !isPaid && i18n.afiyetTitle) {
            const titleEl = elAfiyetBlock.querySelector('.text-lg');
            const hintEl = elAfiyetBlock.querySelector('.text-xs');
            if (titleEl) titleEl.textContent = i18n.afiyetTitle;
            if (hintEl) hintEl.textContent = i18n.afiyetHint || '';
        }

        if (elPaymentNote) {
            if (isPaid && data.payment_method_label) {
                const tpl = i18n.paymentClosed || 'Ödeme: :method';
                elPaymentNote.textContent = tpl.replace(':method', data.payment_method_label);
                elPaymentNote.classList.remove('hidden');
            } else {
                elPaymentNote.textContent = '';
                elPaymentNote.classList.add('hidden');
            }
        }

        if (elHint) {
            if (isPaid) {
                cancelMenuRedirect();
                elHint.textContent = i18n.pollClosed || i18n.pollHint || '';
            } else if (data.status === 'delivered') {
                maybeRedirectAfterAfiyet(data);
            } else if (data.status === 'ready') {
                cancelMenuRedirect();
                elHint.textContent = i18n.pollComing || i18n.pollHint || '';
            } else {
                cancelMenuRedirect();
                elHint.textContent = i18n.pollHint || '';
            }
        } else {
            maybeRedirectAfterAfiyet(data);
        }

        return true;
    }

    function stopPolling() {
        if (pollTimer) {
            clearTimeout(pollTimer);
            pollTimer = null;
        }
    }

    function scheduleNext() {
        stopPolling();
        pollTimer = setTimeout(tick, currentInterval);
    }

    async function tick() {
        if (finalStatuses.includes(currentStatus)) {
            stopPolling();
            return;
        }

        try {
            const res = await fetch(pollUrl, {
                headers: { Accept: 'application/json' },
            });

            if (!res.ok) throw new Error(`HTTP ${res.status}`);

            const data = await res.json();
            applyStatus(data);
            setBanner(null);

            if (data.is_final || finalStatuses.includes(data.status)) {
                stopPolling();
                return;
            }

            failCount = 0;
            currentInterval = intervalMs;
        } catch {
            failCount += 1;
            const backoff = Math.min(intervalMs * Math.pow(2, failCount), maxIntervalMs);
            currentInterval = backoff;

            if (failCount >= 3) {
                setBanner('error', 'Bağlantı zayıf — yeniden deneniyor…');
            } else {
                setBanner('loading', 'Durum güncelleniyor…');
            }
        }

        scheduleNext();
    }

    updateProgressUI(
        currentStep,
        elProgressStepLabel?.textContent || stepLabels[Math.max(0, currentStep - 1)],
    );

    if (elAfiyetBlock) {
        const root = document.getElementById('order-status-root');
        const paidOnLoad = root?.dataset.initialPaid === '1';
        elAfiyetBlock.classList.toggle(
            'hidden',
            currentStatus !== 'delivered' || paidOnLoad,
        );
    }

    const rootEl = document.getElementById('order-status-root');
    const paidOnLoad = rootEl?.dataset.initialPaid === '1';
    if (currentStatus === 'delivered' && !paidOnLoad) {
        maybeRedirectAfterAfiyet({ status: 'delivered', payment_method: null });
    }

    scheduleNext();

    return { stop: stopPolling };
}
