<div class="product-variations-drawer" data-product-variations-drawer aria-hidden="true" inert>
    <div class="product-variations-drawer__backdrop" data-close-variations-panel tabindex="-1" aria-hidden="true"></div>
    <div class="product-variations-drawer__panel" role="dialog" aria-modal="true" aria-labelledby="productVariationsTitle">
        <header class="product-variations-drawer__head">
            <div>
                <p id="productVariationsTitle" class="text-[10px] font-bold uppercase tracking-widest text-[#C6A046]">Varyasyon Paneli</p>
                <p class="text-sm font-medium text-zinc-300">Dinamik seçenek grupları</p>
            </div>
            <button type="button" class="product-variations-drawer__close" data-close-variations-panel aria-label="Kapat">✕</button>
        </header>
        <div class="product-variations-drawer__body">
            @include('admin.partials.product-option-groups', [
                'product' => $product,
                'formId' => 'adminProductForm',
            ])
        </div>
    </div>
</div>
