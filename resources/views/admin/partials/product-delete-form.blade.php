@props(['product', 'block' => false, 'icon' => false])
<form
    action="{{ route('admin.products.destroy', $product) }}"
    method="POST"
    class="{{ $block ? 'w-full' : 'inline' }}"
    @include('admin.partials.confirm-form', [
        'title' => 'Ürünü sil',
        'message' => $product->name.' kalıcı olarak silinecek.',
        'type' => 'danger',
        'confirmLabel' => 'Sil',
    ])
>
    @csrf
    @method('DELETE')
    @if($icon)
    <button type="submit" class="admin-products-actions__icon admin-products-actions__icon--danger" title="Sil" aria-label="Sil">🗑️</button>
    @else
    <button type="submit" class="btn btn-sm btn-danger {{ $block ? 'w-full' : '' }}">Sil</button>
    @endif
</form>
