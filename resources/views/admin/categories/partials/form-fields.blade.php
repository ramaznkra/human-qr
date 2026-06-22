@php
    $names = old('name', $category->exists ? $category->getTranslations('name') : []);
    $descriptions = old('description', $category->exists ? $category->getTranslations('description') : []);
    $currentType = old('type', $category->type ?? 'kitchen');
    $isActive = filter_var(old('is_active', $category->is_active ?? true), FILTER_VALIDATE_BOOLEAN);
    $previewUrl = $category->image_url ?? null;
@endphp

<div class="category-form-grid">
    {{-- Sol sütun --}}
    <div class="category-form-col category-form-col--main space-y-6">
        <div class="category-form-block">
            <p class="category-form-block__eyebrow">Kategori Kimliği</p>
            @include('admin.partials.locale-tabs', compact('names', 'descriptions'))
            <div class="mt-4">
                <label class="form-label" for="category-slug">Slug</label>
                <input
                    type="text"
                    id="category-slug"
                    name="slug"
                    value="{{ old('slug', $category->slug) }}"
                    placeholder="otomatik oluşturulur"
                    class="form-input max-w-none"
                >
            </div>
        </div>

        <div class="category-form-block">
            <p class="category-form-block__eyebrow category-form-block__eyebrow--muted">Banner Görseli</p>
            <label class="category-image-dropzone" data-category-image-dropzone>
                <input type="file" name="image" accept="image/*" class="sr-only" data-category-image-input>
                <div class="category-image-dropzone__frame aspect-video rounded-2xl border-2 border-dashed border-zinc-800" data-category-image-preview>
                    @if($previewUrl)
                    <img src="{{ $previewUrl }}" alt="" class="category-image-dropzone__img">
                    @else
                    <div class="category-image-dropzone__empty">
                        <span class="category-image-dropzone__icon" aria-hidden="true">📸</span>
                        <p class="category-image-dropzone__title">Banner görselini sürükleyin veya tıklayın</p>
                        <p class="category-image-dropzone__hint">JPG, PNG, WEBP · Maks. 3MB</p>
                    </div>
                    @endif
                </div>
            </label>
            <label class="admin-form-check mt-3">
                <input type="checkbox" name="remove_image" value="1" class="rounded border-zinc-700 bg-[#141414] text-[#C6A046]" data-category-remove-image>
                Görseli kaldır
            </label>
        </div>
    </div>

    {{-- Sağ sütun --}}
    <div class="category-form-col category-form-col--meta space-y-6">
        <div class="category-form-block category-form-block--accent">
            <p class="category-form-block__eyebrow">İşlem &amp; Yayın</p>

            <div class="space-y-3">
                <label class="form-label">İşlem İstasyonu *</label>
                <div class="station-radio-grid" data-station-radio-grid>
                    <label class="station-radio-card {{ $currentType === 'kitchen' ? 'is-selected' : '' }}" data-station-radio-card>
                        <input type="radio" name="type" value="kitchen" class="sr-only" {{ $currentType === 'kitchen' ? 'checked' : '' }} required>
                        <span class="station-radio-card__icon" aria-hidden="true">👨‍🍳</span>
                        <span class="station-radio-card__label">Mutfak</span>
                        <span class="station-radio-card__hint">Yemekler</span>
                    </label>
                    <label class="station-radio-card {{ $currentType === 'bar' ? 'is-selected' : '' }}" data-station-radio-card>
                        <input type="radio" name="type" value="bar" class="sr-only" {{ $currentType === 'bar' ? 'checked' : '' }}>
                        <span class="station-radio-card__icon" aria-hidden="true">🍹</span>
                        <span class="station-radio-card__label">Bar</span>
                        <span class="station-radio-card__hint">İçecekler</span>
                    </label>
                    <label class="station-radio-card {{ $currentType === 'nargile' ? 'is-selected' : '' }}" data-station-radio-card>
                        <input type="radio" name="type" value="nargile" class="sr-only" {{ $currentType === 'nargile' ? 'checked' : '' }}>
                        <span class="station-radio-card__icon" aria-hidden="true">💨</span>
                        <span class="station-radio-card__label">Nargile</span>
                        <span class="station-radio-card__hint">Nargile İstasyonu</span>
                    </label>
                    <label class="station-radio-card {{ $currentType === 'retail' ? 'is-selected' : '' }}" data-station-radio-card>
                        <input type="radio" name="type" value="retail" class="sr-only" {{ $currentType === 'retail' ? 'checked' : '' }}>
                        <span class="station-radio-card__icon" aria-hidden="true">🏛️</span>
                        <span class="station-radio-card__label">Kasadan Teslim</span>
                        <span class="station-radio-card__hint">Biblo / Figür / Retail</span>
                    </label>
                </div>
                <p class="text-xs font-medium text-zinc-300">Mutfak, bar ve nargile ekranlarına; biblo/figür satışları doğrudan kasa paneline yönlendirilir.</p>
            </div>

            <div class="category-form-divider"></div>

            <div>
                <label class="form-label" for="category-sort">Menü Sıralaması</label>
                <input
                    type="number"
                    id="category-sort"
                    name="sort_order"
                    min="0"
                    value="{{ old('sort_order', $category->sort_order ?? 0) }}"
                    class="form-input max-w-none"
                >
            </div>

            <div class="category-form-divider"></div>

            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-bold text-zinc-100">QR Menüde Yayınla</p>
                    <p class="text-xs font-medium text-zinc-300">Kapalıyken kategori menüde gizlenir</p>
                </div>
                <label class="relative inline-flex shrink-0 cursor-pointer items-center">
                    <input type="hidden" name="is_active" value="0">
                    <input
                        type="checkbox"
                        class="peer sr-only"
                        name="is_active"
                        value="1"
                        {{ $isActive ? 'checked' : '' }}
                    >
                    <span class="admin-toggle__track admin-toggle__track--gold"></span>
                </label>
            </div>
        </div>
    </div>
</div>
