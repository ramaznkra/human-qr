@extends('layouts.admin')
@section('title', $product->exists ? 'Ürün Düzenle' : 'Yeni Ürün')
@section('page_heading', $product->exists ? 'Ürün Düzenle' : 'Yeni Ürün')
@section('section_label', 'Menü')

@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <h2 class="admin-page-title">{{ $product->exists ? 'Ürün Düzenle' : 'Yeni Ürün Ekle' }}</h2>
    <a href="{{ route('admin.products.index') }}" class="btn btn-secondary">← Ürünlere Dön</a>
</div>

<div class="admin-card product-form-card max-w-7xl">
    <form
        id="adminProductForm"
        method="POST"
        action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}"
        enctype="multipart/form-data"
        class="product-form relative z-[1]"
        data-product-form
        novalidate
    >
        @csrf
        @if($product->exists) @method('PUT') @endif

        @include('admin.partials.form-errors')

        @include('admin.products.partials.form-fields', compact('product', 'categories', 'badgeSuggestions', 'menuHierarchyCatalog'))

        @if($product->exists)
        <p class="mt-6 text-xs font-medium text-zinc-400">Menüde görünürlük için <a href="{{ route('admin.products.index') }}" class="admin-link-gold">Ürünler</a> listesindeki anahtarı kullanın.</p>
        @endif

        <div class="product-form__actions">
            <a href="{{ route('admin.products.index') }}" class="btn btn-secondary relative z-50 hidden sm:inline-flex">İptal</a>
            <button type="submit" class="btn btn-primary product-form__submit relative z-50">
                {{ $product->exists ? 'Değişiklikleri Kaydet' : 'Ürünü Kaydet' }}
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
@php
    $productImageMaxBytes = (int) config('upload.product_image_max_kb', 10240) * 1024;
    $productImageMaxMb = (int) ceil(config('upload.product_image_max_kb', 10240) / 1024);
@endphp
<script>
window.HSP_MAX_IMAGE_BYTES = {{ $productImageMaxBytes }};
</script>
@vite(['resources/js/pages/admin-product-form.js', 'resources/js/pages/admin-product-options.js'])
<script>
(function () {
    const MAX = window.HSP_MAX_IMAGE_BYTES || (10 * 1024 * 1024);
    const tooLargeMsg = `Görsel ${Math.round(MAX / 1024 / 1024)} MB sınırını aşıyor. Form gönderilemez; lütfen daha küçük bir görsel seçin.`;

    function showPreview(zone, input, preview, file) {
        zone.parentElement?.querySelector('[data-image-size-warning]')?.remove();
        preview.innerHTML = `<img src="${URL.createObjectURL(file)}" alt="" class="product-image-dropzone__img">`;
        zone.classList.add('is-filled');
    }

    function patchImageInputs() {
        document.querySelectorAll('[data-image-dropzone]').forEach((zone) => {
            const input = zone.querySelector('[data-image-input]');
            const preview = zone.querySelector('[data-image-preview]');
            if (!input || !preview || input.dataset.uploadLimitPatched === '1') return;
            input.dataset.uploadLimitPatched = '1';

            input.addEventListener('change', function (event) {
                const file = this.files?.[0];
                if (!file || !file.type.startsWith('image/')) return;

                if (file.size > MAX) {
                    return;
                }

                event.stopImmediatePropagation();
                showPreview(zone, input, preview, file);
            }, true);
        });
    }

    function patchSubmitGuards() {
        document.querySelectorAll('[data-product-form]').forEach((form) => {
            if (form.dataset.uploadLimitPatched === '1') return;
            form.dataset.uploadLimitPatched = '1';

            form.addEventListener('submit', (event) => {
                const file = form.querySelector('input[type="file"][name="image"]')?.files?.[0];
                if (!file) return;

                if (file.size <= MAX) {
                    form.querySelector('[data-form-client-alert]')?.remove();
                    form.querySelector('[data-image-size-warning]')?.remove();
                    event.stopPropagation();
                    return;
                }

                event.preventDefault();
                event.stopPropagation();
                alert(tooLargeMsg);
            }, true);
        });
    }

    function runPatch() {
        patchImageInputs();
        patchSubmitGuards();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', runPatch);
    } else {
        runPatch();
    }
})();
</script>
@endpush
