/**
 * Social Spotted — otomatik slider (kaydırma yok; dokununca duraklar)
 */
const AUTO_MS = 10000;

export function initSpottedCarousel(root) {
    if (!root) return;

    const track = root.querySelector('[data-spotted-track]');
    const dots = root.querySelectorAll('[data-spotted-dot]');
    const slides = root.querySelectorAll('[data-spotted-card]');

    if (!track || slides.length === 0) return;

    let activeIndex = 0;
    let autoTimer = null;
    let isHolding = false;

    function setActive(index) {
        activeIndex = Math.max(0, Math.min(index, slides.length - 1));
        dots.forEach((dot, i) => {
            dot.classList.toggle('w-5', i === activeIndex);
            dot.classList.toggle('bg-[#E67E22]', i === activeIndex);
            dot.classList.toggle('w-1.5', i !== activeIndex);
            dot.classList.toggle('bg-white/35', i !== activeIndex);
        });
    }

    function applyTransform(animate = true) {
        const offset = activeIndex * root.clientWidth;
        track.style.transition = animate ? 'transform 0.55s ease-out' : 'none';
        track.style.transform = `translate3d(-${offset}px, 0, 0)`;
        setActive(activeIndex);
    }

    function goTo(index, animate = true) {
        if (slides.length === 0) return;
        activeIndex = ((index % slides.length) + slides.length) % slides.length;
        applyTransform(animate);
    }

    function next() {
        goTo(activeIndex + 1);
    }

    function stopAuto() {
        if (autoTimer) {
            clearInterval(autoTimer);
            autoTimer = null;
        }
    }

    function startAuto() {
        stopAuto();
        if (slides.length <= 1 || isHolding) return;
        autoTimer = setInterval(() => {
            if (!isHolding) next();
        }, AUTO_MS);
    }

    function onHoldStart() {
        isHolding = true;
        stopAuto();
    }

    function onHoldEnd() {
        if (!isHolding) return;
        isHolding = false;
        startAuto();
    }

    dots.forEach((dot) => {
        dot.addEventListener('click', () => {
            goTo(parseInt(dot.dataset.index, 10));
            startAuto();
        });
    });

    root.addEventListener('pointerdown', onHoldStart);
    root.addEventListener('pointerup', onHoldEnd);
    root.addEventListener('pointercancel', onHoldEnd);
    root.addEventListener('pointerleave', (e) => {
        if (e.pointerType === 'mouse' && isHolding) onHoldEnd();
    });

    window.addEventListener('resize', () => applyTransform(false));

    goTo(0, false);
    if (slides.length > 1) startAuto();
}

document.addEventListener('DOMContentLoaded', () => {
    initSpottedCarousel(document.getElementById('spottedCarousel'));
});
