/**
 * İçecekler detay: sticky sekmeler, filtreleme, scroll-spy.
 */
export function initMenuDrinks() {
    const panels = document.querySelectorAll('[data-drinks-panel]');
    if (!panels.length) return;

    panels.forEach((panel) => {
        const tabsNav = panel.querySelector('.drinks-tabs-nav');
        const tabs = panel.querySelectorAll('.drinks-tab');
        const sections = panel.querySelectorAll('.drinks-section');
        const rows = panel.querySelectorAll('.drinks-list-row');

        if (!tabs.length) return;

        let activeTab = tabs[0]?.dataset.drinkTab || 'all';

        function applyTabFilter(tabId) {
            activeTab = tabId;

            tabs.forEach((tab) => {
                const isActive = tab.dataset.drinkTab === tabId;
                tab.classList.toggle('is-active', isActive);
                tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });

            sections.forEach((section) => {
                const sectionTab = section.dataset.drinkTab;
                const show = tabId === 'all' || sectionTab === tabId;
                section.classList.toggle('is-hidden', !show);
            });

            rows.forEach((row) => {
                const rowTab = row.dataset.drinkTab;
                const show = tabId === 'all' || rowTab === tabId;
                row.classList.toggle('is-hidden', !show);
            });
        }

        tabs.forEach((tab) => {
            tab.addEventListener('click', () => {
                const tabId = tab.dataset.drinkTab;
                if (!tabId || tabId === activeTab) return;

                applyTabFilter(tabId);

                const firstVisible = panel.querySelector(
                    `.drinks-section:not(.is-hidden)`,
                );
                firstVisible?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });

        if (tabsNav && sections.length) {
            const observer = new IntersectionObserver(
                (entries) => {
                    const visible = entries
                        .filter((e) => e.isIntersecting)
                        .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];

                    if (!visible || activeTab !== 'all') return;

                    const sectionTab = visible.target.dataset.drinkTab;
                    if (!sectionTab) return;

                    tabs.forEach((tab) => {
                        const highlight =
                            tab.dataset.drinkTab === sectionTab ||
                            tab.dataset.drinkTab === 'all';
                        tab.classList.toggle('is-near', highlight && tab.dataset.drinkTab === sectionTab);
                    });
                },
                {
                    root: null,
                    rootMargin: '-120px 0px -55% 0px',
                    threshold: [0, 0.25, 0.5],
                },
            );

            sections.forEach((section) => observer.observe(section));
        }

        applyTabFilter('all');
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMenuDrinks);
} else {
    initMenuDrinks();
}
