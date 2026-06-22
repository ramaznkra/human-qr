@props(['category', 'block' => false])
<form
    action="{{ route('admin.categories.destroy', $category) }}"
    method="POST"
    class="{{ $block ? 'w-full' : 'inline' }}"
    @include('admin.partials.confirm-form', [
        'title' => 'Kategoriyi sil',
        'message' => $category->name.' kalıcı olarak silinecek.',
        'type' => 'danger',
        'confirmLabel' => 'Sil',
    ])
>
    @csrf
    @method('DELETE')
    <button type="submit" class="btn btn-sm btn-danger {{ $block ? 'w-full' : '' }}">Sil</button>
</form>
