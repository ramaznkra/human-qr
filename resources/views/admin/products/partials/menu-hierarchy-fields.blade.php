@php
    $selectedSectionId = old('menu_section_id', $product->menu_section_id);
    $selectedCategoryId = old('category_id', $product->category_id);
    $product->loadMissing('menuSection.menuTab', 'menuTab');
    $initialTabId = old('menu_tab_id', $product->menu_section_id
        ? $product->menuSection?->menu_tab_id
        : $product->menu_tab_id);
@endphp

<div
    class="menu-hierarchy-fields"
    data-menu-hierarchy-fields
    data-hierarchy-catalog='@json($menuHierarchyCatalog ?? [])'
    data-tabs-url="/admin/api/menu-hierarchy/categories"
    data-sections-url="/admin/api/menu-hierarchy/tabs"
    data-store-tab-url="/admin/api/menu-hierarchy/tabs"
    data-store-section-url="/admin/api/menu-hierarchy/sections"
    data-destroy-tab-url="/admin/api/menu-hierarchy/tabs/delete"
    data-destroy-section-url="/admin/api/menu-hierarchy/sections/delete"
    data-initial-category="{{ $selectedCategoryId }}"
    data-initial-tab="{{ $initialTabId }}"
    data-initial-section="{{ $selectedSectionId }}"
>
    <p class="product-form-section__eyebrow mb-3">Menü Konumu</p>
    <p class="mb-3 text-xs text-zinc-400">Kategori → Sekme seçin. Gruplu sekmelerde akordeon başlığı; düz listede ürünler doğrudan sekme altında görünür.</p>

    <p class="mb-3 hidden rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-xs text-amber-200/90" data-hierarchy-warning></p>

    <input type="hidden" name="menu_tab_id" value="{{ old('menu_tab_id', $product->menu_tab_id) }}" data-hierarchy-tab-id>

    <div class="grid gap-3 sm:grid-cols-2">
        <div>
            <label class="form-label" for="menu-hierarchy-tab">Sekme *</label>
            <div class="flex gap-2">
                <select id="menu-hierarchy-tab" class="form-input max-w-none min-w-0 flex-1" data-hierarchy-tab disabled>
                    <option value="">Önce kategori seçin</option>
                </select>
                <button
                    type="button"
                    class="btn btn-secondary btn-sm shrink-0 px-3"
                    data-delete-tab-btn
                    title="Sekmeyi sil (içindeki ürünlerle birlikte)"
                >
                    Sil
                </button>
            </div>
        </div>
        <div data-section-row>
            <label class="form-label" for="menu-hierarchy-section">Grup Başlığı *</label>
            <div class="flex gap-2">
                <select
                    id="menu-hierarchy-section"
                    name="menu_section_id"
                    class="form-input max-w-none min-w-0 flex-1"
                    data-hierarchy-section
                    disabled
                    required
                >
                    <option value="">Önce sekme seçin</option>
                </select>
                <button
                    type="button"
                    class="btn btn-secondary btn-sm shrink-0 px-3"
                    data-delete-section-btn
                    title="Grubu sil (içindeki ürünlerle birlikte)"
                >
                    Sil
                </button>
            </div>
        </div>
    </div>

    <p class="mt-2 hidden text-xs text-[#C6A046]/90" data-flat-tab-hint>
        Düz liste sekmesi — grup seçimi gerekmez; ürünler doğrudan bu sekmede listelenir.
    </p>

    <div class="mt-3 hidden rounded-xl border border-dashed border-[#C6A046]/30 bg-[#0A0A0A]/60 p-3" data-new-tab-wrap>
        <label class="form-label" for="menu-hierarchy-new-tab">Yeni sekme</label>
        <div class="flex flex-wrap gap-2">
            <input
                type="text"
                id="menu-hierarchy-new-tab"
                class="form-input max-w-none min-w-[10rem] flex-1"
                placeholder="Örn: Meşrubat"
                data-new-tab-input
            >
            <select class="form-input w-auto shrink-0" data-new-tab-layout>
                <option value="flat">Düz liste</option>
                <option value="grouped">Gruplu (akordeon)</option>
            </select>
            <button type="button" class="btn btn-secondary btn-sm shrink-0" data-add-tab-btn>+ Sekme Ekle</button>
        </div>
        <p class="mt-1 text-xs text-zinc-500">Meşrubat gibi az ürünlü kategoriler için «Düz liste» seçin.</p>
    </div>

    <div class="mt-3 hidden rounded-xl border border-dashed border-[#C6A046]/30 bg-[#0A0A0A]/60 p-3" data-new-section-wrap>
        <label class="form-label" for="menu-hierarchy-new-section">Yeni grup başlığı</label>
        <div class="flex flex-wrap gap-2">
            <input
                type="text"
                id="menu-hierarchy-new-section"
                class="form-input max-w-none min-w-[12rem] flex-1"
                placeholder="Örn: Espresso Bazlılar"
                data-new-section-input
            >
            <button type="button" class="btn btn-secondary btn-sm shrink-0" data-add-section-btn>+ Grup Ekle</button>
        </div>
        <p class="mt-1 text-xs text-zinc-500">Ekledikten sonra listeden seçili gelir.</p>
    </div>

    @error('menu_section_id')<p class="form-field-error">{{ $message }}</p>@enderror
    @error('menu_tab_id')<p class="form-field-error">{{ $message }}</p>@enderror
</div>
