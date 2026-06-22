@props(['product', 'compact' => false])
<div class="admin-toggle-wrap flex items-center gap-3 {{ $compact ? 'justify-center' : '' }}">
    <label class="relative inline-flex shrink-0 cursor-pointer items-center">
        <input
            type="checkbox"
            class="peer sr-only"
            data-product-toggle
            data-toggle-url="{{ route('admin.products.toggle-availability', $product) }}"
            {{ $product->is_available ? 'checked' : '' }}
            aria-label="{{ $product->name }} menüde göster"
        >
        <span class="admin-toggle__track admin-toggle__track--gold"></span>
    </label>
    @unless($compact)
    <span
        data-availability-label
        class="shrink-0 text-xs font-medium {{ $product->is_available ? 'text-[#C6A046]' : 'text-zinc-500' }}"
    >{{ $product->is_available ? 'Menüde' : 'Gizli' }}</span>
    @endunless
</div>
