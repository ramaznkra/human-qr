@props(['category'])
<label class="relative inline-flex shrink-0 cursor-pointer items-center" title="Kategoriyi aç / kapat">
    <input
        type="checkbox"
        class="peer sr-only"
        data-category-toggle
        data-toggle-url="{{ route('admin.categories.toggle-active', $category) }}"
        {{ $category->is_active ? 'checked' : '' }}
        aria-label="{{ $category->name }} aktif"
    >
    <span class="admin-toggle__track admin-toggle__track--emerald"></span>
</label>
