@props(['product', 'compact' => false, 'pill' => false])
<div class="admin-toggle-wrap flex items-center gap-3 {{ $compact ? 'justify-center' : '' }}">
    @unless($pill)
    <label class="relative inline-flex shrink-0 cursor-pointer items-center">
        <input
            type="checkbox"
            class="peer sr-only"
            data-product-stock-toggle
            data-toggle-url="{{ route('admin.products.toggle-in-stock', $product) }}"
            {{ $product->in_stock ? 'checked' : '' }}
            aria-label="{{ $product->name }} stok durumu"
        >
        <span class="admin-toggle__track admin-toggle__track--emerald"></span>
    </label>
    @endunless
    @unless($compact)
    <span
        data-stock-label
        class="product-stock-pill {{ $product->in_stock ? 'product-stock-pill--in' : 'product-stock-pill--out' }}"
    >{{ $product->in_stock ? 'STOKTA' : 'TÜKENDİ' }}</span>
    @if($pill)
    <label class="relative ml-1 inline-flex shrink-0 cursor-pointer items-center" title="Stok durumunu değiştir">
        <input
            type="checkbox"
            class="peer sr-only"
            data-product-stock-toggle
            data-toggle-url="{{ route('admin.products.toggle-in-stock', $product) }}"
            {{ $product->in_stock ? 'checked' : '' }}
            aria-label="{{ $product->name }} stok durumu"
        >
        <span class="admin-toggle__track admin-toggle__track--emerald admin-toggle__track--sm"></span>
    </label>
    @endif
    @endunless
</div>
