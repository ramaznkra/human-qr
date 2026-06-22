import { initOrderStatusPoll } from '../order-status-poll.js';

const root = document.getElementById('order-status-root');
if (root) {
    let stepLabels = ['', '', ''];
    try {
        stepLabels = JSON.parse(root.dataset.stepLabels || '[]');
    } catch {
        /* fallback */
    }

    initOrderStatusPoll({
        orderId: Number(root.dataset.orderId),
        initialStatus: root.dataset.initialStatus,
        initialStep: Number(root.dataset.initialStep) || 1,
        pollUrl: root.dataset.pollUrl,
        menuRedirectUrl: root.dataset.menuUrl || '',
        redirectDelayMs: Number(root.dataset.redirectDelay) || 5000,
        intervalMs: 10000,
        stepLabels,
        i18n: {
            afiyetTitle: root.dataset.i18nAfiyetTitle || '',
            afiyetHint: root.dataset.i18nAfiyetHint || '',
            paymentClosed: root.dataset.i18nPaymentClosed || '',
            pollHint: root.dataset.i18nPollHint || '',
            pollEnjoy: root.dataset.i18nPollEnjoy || '',
            pollClosed: root.dataset.i18nPollClosed || '',
            pollComing: root.dataset.i18nPollComing || '',
            redirectMenu: root.dataset.i18nRedirect || '',
        },
    });
}
