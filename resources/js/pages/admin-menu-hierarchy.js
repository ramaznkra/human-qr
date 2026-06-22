/**
 * Admin ürün formu: Kategori → Sekme → (isteğe bağlı) Grup.
 * Sekme/grup listesi sayfa yüklenirken sunucudan gelir; API ile arka planda senkronize edilir.
 */
import { confirmAdminAction } from '../admin-confirm.js';
import { showAdminToast } from '../admin-toast.js';

const LAYOUT_FLAT = 'flat';
const LAYOUT_GROUPED = 'grouped';

function notifyAdmin({ title, message = '', type = 'info', hint = '' }) {
    showAdminToast({ title, message, hint, type, durationMs: type === 'error' ? 5000 : 3500 });
}

export function initMenuHierarchyFields() {
    const root = document.querySelector('[data-menu-hierarchy-fields]');
    if (!root || root.dataset.bound === '1') return;
    root.dataset.bound = '1';

    const tabSelect = root.querySelector('[data-hierarchy-tab]');
    const sectionSelect = root.querySelector('[data-hierarchy-section]');
    const sectionRow = root.querySelector('[data-section-row]');
    const tabIdInput = root.querySelector('[data-hierarchy-tab-id]');
    const flatTabHint = root.querySelector('[data-flat-tab-hint]');
    const newTabWrap = root.querySelector('[data-new-tab-wrap]');
    const newTabInput = root.querySelector('[data-new-tab-input]');
    const newTabLayout = root.querySelector('[data-new-tab-layout]');
    const addTabBtn = root.querySelector('[data-add-tab-btn]');
    const deleteTabBtn = root.querySelector('[data-delete-tab-btn]');
    const newSectionWrap = root.querySelector('[data-new-section-wrap]');
    const newSectionInput = root.querySelector('[data-new-section-input]');
    const addSectionBtn = root.querySelector('[data-add-section-btn]');
    const deleteSectionBtn = root.querySelector('[data-delete-section-btn]');
    const warningEl = root.querySelector('[data-hierarchy-warning]');

    const tabsBase = root.dataset.tabsUrl;
    const sectionsBase = root.dataset.sectionsUrl;
    const storeTabUrl = root.dataset.storeTabUrl;
    const storeSectionUrl = root.dataset.storeSectionUrl;
    const destroyTabBase = root.dataset.destroyTabUrl;
    const destroySectionBase = root.dataset.destroySectionUrl;

    /** @type {Record<string, { tabs: Array<object> }>} */
    let catalog = parseCatalog(root.dataset.hierarchyCatalog);

    let currentCategoryId = root.dataset.initialCategory || '';
    let currentTabId = root.dataset.initialTab || '';
    /** @type {Array<object>} */
    let currentTabs = [];
    /** @type {Array<object>} */
    let currentSections = [];

    const fetchOpts = {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    };

    function parseCatalog(raw) {
        try {
            return JSON.parse(raw || '{}');
        } catch {
            return {};
        }
    }

    function persistCatalog() {
        root.dataset.hierarchyCatalog = JSON.stringify(catalog);
    }

    function catalogTabsFor(categoryId) {
        return catalog[String(categoryId)]?.tabs ?? [];
    }

    function setCatalogTabs(categoryId, tabs) {
        const key = String(categoryId);
        catalog[key] = { tabs: tabs.map(cloneTab) };
        persistCatalog();
    }

    function cloneTab(tab) {
        return {
            ...tab,
            sections: Array.isArray(tab.sections) ? tab.sections.map((s) => ({ ...s })) : [],
        };
    }

    function upsertCatalogTab(categoryId, tab) {
        if (!tab?.id) return;
        const key = String(categoryId);
        if (!catalog[key]) catalog[key] = { tabs: [] };
        const tabs = catalog[key].tabs;
        const idx = tabs.findIndex((t) => String(t.id) === String(tab.id));
        const merged = idx >= 0
            ? { ...tabs[idx], ...tab, sections: tab.sections ?? tabs[idx].sections ?? [] }
            : cloneTab(tab);
        if (idx >= 0) {
            tabs[idx] = merged;
        } else {
            tabs.push(merged);
        }
        tabs.sort((a, b) => (Number(a.sort_order) || 0) - (Number(b.sort_order) || 0));
        persistCatalog();
    }

    function upsertCatalogSection(categoryId, tabId, section) {
        if (!section?.id) return;
        const key = String(categoryId);
        if (!catalog[key]) catalog[key] = { tabs: [] };
        const tab = catalog[key].tabs.find((t) => String(t.id) === String(tabId));
        if (!tab) return;
        if (!Array.isArray(tab.sections)) tab.sections = [];
        const idx = tab.sections.findIndex((s) => String(s.id) === String(section.id));
        if (idx >= 0) {
            tab.sections[idx] = { ...tab.sections[idx], ...section };
        } else {
            tab.sections.push({ ...section });
        }
        persistCatalog();
    }

    function removeCatalogTab(categoryId, tabId) {
        const key = String(categoryId);
        if (!catalog[key]) return;
        catalog[key].tabs = catalog[key].tabs.filter((t) => String(t.id) !== String(tabId));
        persistCatalog();
    }

    function removeCatalogSection(categoryId, tabId, sectionId) {
        const key = String(categoryId);
        const tab = catalog[key]?.tabs?.find((t) => String(t.id) === String(tabId));
        if (!tab?.sections) return;
        tab.sections = tab.sections.filter((s) => String(s.id) !== String(sectionId));
        persistCatalog();
    }

    function mergeTab(tab) {
        if (!tab?.id) return;
        const idx = currentTabs.findIndex((t) => String(t.id) === String(tab.id));
        if (idx >= 0) {
            const existingSections = currentTabs[idx].sections ?? [];
            currentTabs[idx] = {
                ...currentTabs[idx],
                ...tab,
                sections: tab.sections ?? existingSections,
            };
        } else {
            currentTabs.push(cloneTab(tab));
        }
        currentTabs.sort((a, b) => (Number(a.sort_order) || 0) - (Number(b.sort_order) || 0));
    }

    function mergeSection(section) {
        if (!section?.id) return;
        const idx = currentSections.findIndex((s) => String(s.id) === String(section.id));
        if (idx >= 0) {
            currentSections[idx] = { ...currentSections[idx], ...section };
        } else {
            currentSections.push({ ...section });
        }

        const tab = currentTabs.find((t) => String(t.id) === String(currentTabId));
        if (tab) {
            if (!Array.isArray(tab.sections)) tab.sections = [];
            const sIdx = tab.sections.findIndex((s) => String(s.id) === String(section.id));
            if (sIdx >= 0) {
                tab.sections[sIdx] = { ...tab.sections[sIdx], ...section };
            } else {
                tab.sections.push({ ...section });
            }
        }
    }

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    function categoryHiddenInput() {
        return document.querySelector('[data-searchable-value][name="category_id"]');
    }

    function resolveCategoryId() {
        const fromState = currentCategoryId || root.dataset.initialCategory || '';
        const fromInput = categoryHiddenInput()?.value || '';
        return String(fromState || fromInput).trim();
    }

    async function readApiError(res) {
        try {
            const data = await res.json();
            if (data?.message) return data.message;
            const first = data?.errors ? Object.values(data.errors).flat()[0] : null;
            if (first) return String(first);
        } catch {
            // ignore
        }
        return null;
    }

    function ensureApiConfigured() {
        if (!storeTabUrl || !tabsBase || !sectionsBase) {
            notifyAdmin({
                title: 'Menü hiyerarşisi yüklenemedi',
                message: 'Sayfayı yenileyin veya npm run build çalıştırın.',
                type: 'error',
            });
            return false;
        }
        return true;
    }

    function fillSelect(select, items, placeholder, selectedId, labelFn = (item) => item.name) {
        if (!select) return;
        select.innerHTML = '';
        const empty = document.createElement('option');
        empty.value = '';
        empty.textContent = placeholder;
        select.appendChild(empty);

        items.forEach((item) => {
            const opt = document.createElement('option');
            opt.value = String(item.id);
            opt.textContent = labelFn(item);
            if (String(item.id) === String(selectedId)) opt.selected = true;
            select.appendChild(opt);
        });
    }

    function showWarning(message) {
        if (!warningEl) return;
        if (!message) {
            warningEl.classList.add('hidden');
            warningEl.textContent = '';
            return;
        }
        warningEl.textContent = message;
        warningEl.classList.remove('hidden');
    }

    function tabLabel(tab) {
        const count = Number(tab.products_count ?? 0);
        const layoutLabel = tab.layout === LAYOUT_FLAT ? 'düz liste' : 'gruplu';
        const countLabel = count === 0 ? 'boş' : `${count} ürün`;
        const hiddenLabel = tab.visible_on_menu === false ? ' · menüde gizli' : '';

        return `${tab.name} (#${tab.id} · ${layoutLabel} · ${countLabel}${hiddenLabel})`;
    }

    function sectionLabel(section) {
        const count = Number(section.products_count ?? 0);
        if (count === 0) {
            return `${section.name} (boş)`;
        }

        return `${section.name} (${count} ürün)`;
    }

    function selectedTab() {
        const id = tabSelect?.value;
        if (!id) return null;
        return currentTabs.find((t) => String(t.id) === String(id)) ?? null;
    }

    function selectedSection() {
        const id = sectionSelect?.value;
        if (!id) return null;
        return currentSections.find((s) => String(s.id) === String(id)) ?? null;
    }

    function syncHiddenTabId(tab) {
        if (!tabIdInput) return;
        if (tab?.layout === LAYOUT_FLAT) {
            tabIdInput.value = String(tab.id);
        } else {
            tabIdInput.value = '';
        }
    }

    function applyTabLayout(tab) {
        const isFlat = tab?.layout === LAYOUT_FLAT;

        sectionRow?.classList.toggle('hidden', isFlat);
        flatTabHint?.classList.toggle('hidden', !isFlat);
        newSectionWrap?.classList.toggle('hidden', isFlat || !tab);

        if (sectionSelect) {
            sectionSelect.required = !isFlat;
            if (isFlat) {
                sectionSelect.value = '';
                sectionSelect.disabled = true;
            }
        }

        syncHiddenTabId(tab);
        updateDeleteSectionUi();
        updateDeleteTabUi();
    }

    function setDeleteButtonState(button, _canDelete) {
        if (!button) return;
        button.disabled = false;
        button.classList.remove('opacity-40', 'cursor-not-allowed');
        button.setAttribute('aria-disabled', 'false');
    }

    function updateDeleteTabUi() {
        setDeleteButtonState(deleteTabBtn, true);
    }

    function updateDeleteSectionUi() {
        const tab = selectedTab();
        if (tab?.layout === LAYOUT_FLAT) {
            deleteSectionBtn?.classList.add('hidden');
            return;
        }

        deleteSectionBtn?.classList.remove('hidden');
        setDeleteButtonState(deleteSectionBtn, true);
    }

    function renderTabs(preselectTab = '', preselectSection = '') {
        const placeholder = currentTabs.length > 0
            ? 'Sekme seçin…'
            : 'Henüz sekme yok — aşağıdan ekleyin';
        const selected = preselectTab || tabSelect?.value || currentTabs[0]?.id || '';
        fillSelect(tabSelect, currentTabs, placeholder, selected, tabLabel);
        tabSelect.disabled = false;

        const tabId = tabSelect?.value || selected;
        if (tabId) {
            renderSections(tabId, preselectSection);
        } else {
            applyTabLayout(null);
        }
    }

    function sectionsForTab(tabId) {
        const tab = currentTabs.find((t) => String(t.id) === String(tabId));
        return Array.isArray(tab?.sections) ? tab.sections : [];
    }

    function renderSections(tabId, preselectSection = '') {
        currentTabId = tabId || '';
        const tab = currentTabs.find((t) => String(t.id) === String(tabId)) ?? null;
        applyTabLayout(tab);

        if (!tabId || tab?.layout === LAYOUT_FLAT) {
            updateDeleteTabUi();
            return;
        }

        currentSections = sectionsForTab(tabId).map((s) => ({ ...s }));
        const placeholder = currentSections.length > 0
            ? 'Grup seçin…'
            : 'Henüz grup yok — aşağıdan ekleyin';
        const selected = preselectSection || sectionSelect?.value || currentSections[0]?.id || '';
        fillSelect(sectionSelect, currentSections, placeholder, selected, sectionLabel);
        sectionSelect.disabled = false;
        newSectionWrap?.classList.remove('hidden');
        updateDeleteSectionUi();
        updateDeleteTabUi();
    }

    async function loadTabs(categoryId, preselectTab = '', preselectSection = '') {
        const resolvedId = String(categoryId || resolveCategoryId()).trim();
        currentCategoryId = resolvedId;
        newTabWrap?.classList.toggle('hidden', !resolvedId);

        if (!resolvedId) {
            currentTabs = [];
            currentSections = [];
            fillSelect(tabSelect, [], 'Önce kategori seçin', '');
            tabSelect.disabled = true;
            fillSelect(sectionSelect, [], 'Önce sekme seçin', '');
            sectionSelect.disabled = true;
            newSectionWrap?.classList.add('hidden');
            applyTabLayout(null);
            showWarning('');
            return;
        }

        currentTabs = catalogTabsFor(resolvedId).map(cloneTab);
        renderTabs(preselectTab, preselectSection);
        newTabWrap?.classList.remove('hidden');

        if (!ensureApiConfigured()) return;

        try {
            const res = await fetch(`${tabsBase}/${resolvedId}/tabs`, {
                ...fetchOpts,
                cache: 'no-store',
            });

            let data = {};
            try {
                data = await res.json();
            } catch {
                data = {};
            }

            if (!res.ok) {
                const msg = data?.message || data?.warning || `Sekmeler yüklenemedi (${res.status})`;
                throw new Error(msg);
            }

            (data.tabs || []).forEach((tab) => mergeTab(tab));
            setCatalogTabs(resolvedId, currentTabs);
            renderTabs(preselectTab || tabSelect?.value, preselectSection);

            if (data.warning || data.debug) {
                showWarning(data.warning || data.debug);
            } else {
                showWarning('');
            }
        } catch (error) {
            if (currentTabs.length === 0) {
                showWarning(
                    error?.message
                    || 'Sekme listesi sunucudan alınamadı. Yine de aşağıdan yeni sekme ekleyebilirsiniz.',
                );
            } else {
                showWarning('Canlı senkron başarısız — listedeki sekmeler sayfa yüklemesinden geliyor.');
            }
        }
    }

    async function syncSectionsFromApi(tabId, preselectSection = '') {
        if (!tabId || !sectionsBase) return;

        const tab = currentTabs.find((t) => String(t.id) === String(tabId));
        if (tab?.layout === LAYOUT_FLAT) return;

        try {
            const res = await fetch(`${sectionsBase}/${tabId}/sections`, {
                ...fetchOpts,
                cache: 'no-store',
            });
            if (!res.ok) {
                const msg = await readApiError(res);
                throw new Error(msg || 'sections');
            }
            const data = await res.json();
            const apiSections = Array.isArray(data.sections)
                ? data.sections
                : Object.values(data.sections || {});
            apiSections.forEach((section) => {
                mergeSection(section);
                upsertCatalogSection(currentCategoryId, tabId, section);
            });
            renderSections(tabId, preselectSection || sectionSelect?.value);
        } catch {
            if (currentSections.length === 0) {
                fillSelect(sectionSelect, [], 'Grup listesi alınamadı — aşağıdan ekleyin', '');
            }
            newSectionWrap?.classList.remove('hidden');
        }
    }

    async function loadSections(tabId, preselectSection = '') {
        renderSections(tabId, preselectSection);
        await syncSectionsFromApi(tabId, preselectSection);
    }

    tabSelect?.addEventListener('change', () => {
        loadSections(tabSelect.value);
        updateDeleteTabUi();
    });

    sectionSelect?.addEventListener('change', () => {
        updateDeleteSectionUi();
    });

    document.addEventListener('searchable-select:change', (event) => {
        if (event.detail?.name !== 'category_id') return;
        loadTabs(event.detail.value);
    });

    categoryHiddenInput()?.addEventListener('change', () => {
        loadTabs(categoryHiddenInput()?.value);
    });

    addTabBtn?.addEventListener('click', async () => {
        if (!ensureApiConfigured()) return;

        const name = newTabInput?.value?.trim();
        const layout = newTabLayout?.value || LAYOUT_FLAT;
        const categoryId = resolveCategoryId();

        if (!categoryId) {
            notifyAdmin({
                title: 'Kategori gerekli',
                message: 'Sekme eklemek için önce kategori seçin.',
                type: 'warning',
            });
            categoryHiddenInput()?.closest('[data-searchable-select]')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        if (!name) {
            notifyAdmin({ title: 'Sekme adı', message: 'Yeni sekme için bir ad yazın.', type: 'warning' });
            newTabInput?.focus();
            return;
        }

        addTabBtn.disabled = true;
        try {
            const res = await fetch(storeTabUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    category_id: Number(categoryId),
                    name,
                    layout,
                }),
            });
            if (!res.ok) {
                throw new Error(await readApiError(res) || 'Sekme eklenemedi.');
            }
            const data = await res.json();
            currentCategoryId = categoryId;
            if (data.tab) {
                upsertCatalogTab(categoryId, data.tab);
                mergeTab(data.tab);
                renderTabs(data.tab.id);
            }
            if (newTabInput) newTabInput.value = '';
            showWarning('');
            notifyAdmin({ title: 'Sekme eklendi', message: data.tab?.name || name, type: 'success' });
        } catch (error) {
            notifyAdmin({
                title: 'Sekme eklenemedi',
                message: error?.message || 'Tekrar deneyin.',
                type: 'error',
            });
        } finally {
            addTabBtn.disabled = false;
        }
    });

    newTabInput?.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') return;
        event.preventDefault();
        addTabBtn?.click();
    });

    newSectionInput?.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') return;
        event.preventDefault();
        addSectionBtn?.click();
    });

    deleteTabBtn?.addEventListener('click', async () => {
        const tab = selectedTab();
        if (!tab) {
            notifyAdmin({
                title: 'Sekme seçin',
                message: 'Silmek için önce listeden bir sekme seçin.',
                type: 'warning',
            });
            return;
        }

        const count = Number(tab.products_count ?? 0);
        const force = count > 0;
        const blockers = Array.isArray(tab.blocking_products) ? tab.blocking_products : [];
        const blockerHint = blockers.length
            ? blockers.map((p) => `${p.name} (#${p.id})`).join(', ')
            : '';

        const ok = await confirmAdminAction({
            title: force ? 'Sekme ve ürünleri sil' : 'Sekmeyi sil',
            message: force
                ? `"${tab.name}" sekmesi ve içindeki ${count} ürün kalıcı olarak silinecek.`
                : `"${tab.name}" sekmesi kalıcı olarak silinecek.`,
            hint: blockerHint,
            type: 'danger',
            confirmLabel: force ? 'Hepsini sil' : 'Sil',
            cancelLabel: 'Vazgeç',
        });
        if (!ok) return;

        deleteTabBtn.disabled = true;
        try {
            const res = await fetch(destroyTabBase, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ tab_id: Number(tab.id), force }),
            });

            if (!res.ok) {
                notifyAdmin({
                    title: 'Sekme silinemedi',
                    message: (await readApiError(res)) || `İşlem başarısız (${res.status}).`,
                    type: 'error',
                });
                await loadTabs(currentCategoryId, tabSelect?.value);
                return;
            }

            const data = await res.json();
            currentTabs = currentTabs.filter((t) => String(t.id) !== String(tab.id));
            removeCatalogTab(currentCategoryId, tab.id);
            renderTabs();
            notifyAdmin({
                title: 'Sekme silindi',
                message: data.deleted_products > 0
                    ? `${data.deleted_products} ürün de kaldırıldı.`
                    : tab.name,
                type: 'success',
            });
        } catch {
            notifyAdmin({
                title: 'Bağlantı hatası',
                message: 'Sekme silinemedi. İnterneti kontrol edip tekrar deneyin.',
                type: 'error',
            });
        } finally {
            deleteTabBtn.disabled = false;
            updateDeleteTabUi();
        }
    });

    addSectionBtn?.addEventListener('click', async () => {
        const name = newSectionInput?.value?.trim();
        if (!name || !currentTabId) return;

        addSectionBtn.disabled = true;
        try {
            const res = await fetch(storeSectionUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ menu_tab_id: Number(currentTabId), name }),
            });
            if (!res.ok) {
                throw new Error(await readApiError(res) || 'Grup eklenemedi.');
            }
            const data = await res.json();
            if (data.section) {
                upsertCatalogSection(currentCategoryId, currentTabId, data.section);
                mergeSection(data.section);
                renderSections(currentTabId, data.section.id);
            }
            if (newSectionInput) newSectionInput.value = '';
            notifyAdmin({ title: 'Grup eklendi', message: data.section?.name || name, type: 'success' });
        } catch (error) {
            notifyAdmin({
                title: 'Grup eklenemedi',
                message: error?.message || 'Tekrar deneyin.',
                type: 'error',
            });
        } finally {
            addSectionBtn.disabled = false;
        }
    });

    deleteSectionBtn?.addEventListener('click', async () => {
        const section = selectedSection();
        if (!section) {
            notifyAdmin({
                title: 'Grup seçin',
                message: 'Silmek için önce listeden bir grup seçin.',
                type: 'warning',
            });
            return;
        }

        const count = Number(section.products_count ?? 0);
        const force = count > 0;

        const ok = await confirmAdminAction({
            title: force ? 'Grup ve ürünleri sil' : 'Grubu sil',
            message: force
                ? `"${section.name}" grubu ve içindeki ${count} ürün kalıcı olarak silinecek.`
                : `"${section.name}" grubu kalıcı olarak silinecek.`,
            type: 'danger',
            confirmLabel: force ? 'Hepsini sil' : 'Sil',
            cancelLabel: 'Vazgeç',
        });
        if (!ok) return;

        deleteSectionBtn.disabled = true;
        try {
            const res = await fetch(destroySectionBase, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ section_id: Number(section.id), force }),
            });

            if (!res.ok) {
                notifyAdmin({
                    title: 'Grup silinemedi',
                    message: (await readApiError(res)) || `İşlem başarısız (${res.status}).`,
                    type: 'error',
                });
                await loadSections(currentTabId);
                return;
            }

            const data = await res.json();
            currentSections = currentSections.filter((s) => String(s.id) !== String(section.id));
            removeCatalogSection(currentCategoryId, currentTabId, section.id);
            const tab = currentTabs.find((t) => String(t.id) === String(currentTabId));
            if (tab?.sections) {
                tab.sections = tab.sections.filter((s) => String(s.id) !== String(section.id));
            }
            renderSections(currentTabId);
            notifyAdmin({
                title: 'Grup silindi',
                message: data.deleted_products > 0
                    ? `${data.deleted_products} ürün de kaldırıldı.`
                    : section.name,
                type: 'success',
            });
        } catch {
            notifyAdmin({
                title: 'Bağlantı hatası',
                message: 'Grup silinemedi. İnterneti kontrol edip tekrar deneyin.',
                type: 'error',
            });
        } finally {
            deleteSectionBtn.disabled = false;
            updateDeleteSectionUi();
        }
    });

    const initialCategory = resolveCategoryId();
    const initialTab = root.dataset.initialTab;
    const initialSection = root.dataset.initialSection;

    if (initialCategory) {
        loadTabs(initialCategory, initialTab, initialSection);
    }
}
