<div class="admin-drawer-form" data-drawer-scripts="product-form,product-options,locale-tabs,menu-hierarchy">
    <h2 class="mb-1 text-lg font-bold text-zinc-100">Ürün Düzenle</h2>
    <p class="mb-4 text-sm font-medium text-zinc-400">{{ $product->getTranslation('name', 'tr') }}</p>

    <form
        method="POST"
        action="{{ route('admin.products.update', $product) }}"
        enctype="multipart/form-data"
        class="product-form"
        data-product-form
        data-admin-drawer-form
    >
        @csrf
        @method('PUT')
        @include('admin.products.partials.form-fields', [
            'product' => $product,
            'categories' => $categories,
            'badgeSuggestions' => $badgeSuggestions,
            'inDrawer' => true,
        ])
        <div class="admin-drawer-form__actions sticky bottom-0 flex gap-2 border-t border-zinc-900 bg-[#111111] pt-4">
            <button type="submit" class="btn btn-primary flex-1">Kaydet</button>
            <button type="button" class="btn btn-secondary" data-admin-drawer-close>İptal</button>
        </div>
    </form>
</div>
