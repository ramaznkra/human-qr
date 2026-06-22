/**
 * Admin dashboard — saatlik ciro (bar) + yoğunluk (line) grafikleri.
 */
function initDashSalesChart() {
    const root = document.getElementById('dashSalesChart');
    if (!root) return;

    let trend = [];
    try {
        trend = JSON.parse(root.dataset.trend || '[]');
    } catch {
        return;
    }

    const barsEl = root.querySelector('.dash-chart__bars');
    const labelsEl = root.querySelector('.dash-chart__labels');
    if (!barsEl || !labelsEl) return;

    const max = Math.max(...trend.map((row) => Number(row.value) || 0), 1);

    barsEl.innerHTML = trend
        .map((row) => {
            const pct = Math.max(4, Math.round(((Number(row.value) || 0) / max) * 100));
            return `<div class="dash-chart__bar-wrap" title="${row.formatted} ₺">
                <div class="dash-chart__bar" style="height:${pct}%"></div>
            </div>`;
        })
        .join('');

    labelsEl.innerHTML = trend
        .map((row) => `<span class="dash-chart__label">${String(row.label).replace(':00', '')}</span>`)
        .join('');
}

function initDashBusyLineChart() {
    const root = document.getElementById('dashBusyChart');
    if (!root) return;

    let trend = [];
    try {
        trend = JSON.parse(root.dataset.trend || '[]');
    } catch {
        return;
    }

    const svg = root.querySelector('.dash-line-chart__svg');
    const line = root.querySelector('.dash-line-chart__line');
    const area = root.querySelector('.dash-line-chart__area');
    const labelsEl = root.querySelector('.dash-line-chart__labels');
    if (!svg || !line || !area || !labelsEl) return;

    const max = Math.max(...trend.map((row) => Number(row.value) || 0), 1);
    const width = 100;
    const height = 40;
    const pad = 2;

    const coords = trend.map((row, index) => {
        const x = trend.length <= 1 ? width / 2 : (index / (trend.length - 1)) * width;
        const value = Number(row.value) || 0;
        const y = height - pad - ((value / max) * (height - pad * 2));
        return { x, y, row };
    });

    const linePoints = coords.map(({ x, y }) => `${x.toFixed(2)},${y.toFixed(2)}`).join(' ');
    const areaPoints = `${pad},${height - pad} ${linePoints} ${(width - pad).toFixed(2)},${height - pad}`;

    line.setAttribute('points', linePoints);
    area.setAttribute('points', areaPoints);

    labelsEl.innerHTML = trend
        .map((row) => `<span class="dash-line-chart__label">${String(row.label).replace(':00', '')}</span>`)
        .join('');
}

initDashSalesChart();
initDashBusyLineChart();
