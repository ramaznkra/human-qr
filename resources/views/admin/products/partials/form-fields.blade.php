@php
    $names = old('name', $product->exists ? $product->getTranslations('name') : []);
    $descriptions = old('description', $product->exists ? $product->getTranslations('description') : []);
    $inDrawer = $inDrawer ?? false;

    $optionGroupsPreview = old('option_groups');
    if ($optionGroupsPreview === null && $product->exists) {
        $product->loadMissing(['optionGroups.options']);
        $optionGroupsPreview = $product->optionGroups->map(fn ($group) => [
            'options' => $group->options->all(),
        ])->all();
    }
    $optionGroupsPreview = is_array($optionGroupsPreview) ? array_values($optionGroupsPreview) : [];
    $variationGroupCount = count($optionGroupsPreview);
    $variationOptionCount = collect($optionGroupsPreview)->sum(fn ($g) => count($g['options'] ?? []));

    $departmentOptions = [
        ['value' => 'kitchen', 'label' => 'Mutfak / Yemek'],
        ['value' => 'bar', 'label' => 'Bar / İçecek'],
        ['value' => 'nargile', 'label' => 'Nargile İstasyonu'],
        ['value' => 'retail', 'label' => 'Kasa / Stand (Retail)'],
    ];
    $stationOptions = [
        ['value' => 'kitchen', 'label' => 'Mutfak'],
        ['value' => 'bar', 'label' => 'Bar'],
        ['value' => 'hookah', 'label' => 'Nargile'],
        ['value' => 'service', 'label' => 'Servis'],
    ];
    $categoryOptions = $categories->map(fn ($c) => [
        'value' => (string) $c->id,
        'label' => $c->getTranslation('name', 'tr'),
        'type' => $c->type,
        'slug' => $c->slug,
    ])->all();
@endphp

<div class="product-form-grid {{ $inDrawer ? 'product-form-grid--drawer' : '' }}">
    {{-- Sol sütun: Ana bilgiler --}}
    <div class="product-form-col product-form-col--main space-y-5">
        <div class="product-form-section">
            <p class="product-form-section__eyebrow">Ana Bilgiler</p>
            @include('admin.partials.locale-tabs', compact('names', 'descriptions'))
        </div>

        <div class="product-form-section">
            <label class="form-label" for="product-price">Fiyat (₺) *</label>
            <input
                type="number"
                id="product-price"
                step="0.01"
                name="price"
                value="{{ old('price', $product->price) }}"
                required
                class="form-input product-form-input--price max-w-none"
                placeholder="0.00"
            >
            @error('price')<p class="form-field-error">{{ $message }}</p>@enderror
        </div>

        <div class="product-form-section">
            <label class="form-label">Ürün Görseli</label>
            <label class="product-image-dropzone" data-image-dropzone>
                <input
                    type="file"
                    name="image"
                    accept="image/*"
                    class="sr-only"
                    data-image-input
                >
                <div class="product-image-dropzone__inner">
                    <div class="product-image-dropzone__preview" data-image-preview>
                        @if($product->image)
                        <img src="{{ $product->image_url }}" alt="" class="product-image-dropzone__img">
                        @else
                        <span class="product-image-dropzone__placeholder" aria-hidden="true">📷</span>
                        @endif
                    </div>
                    <div class="product-image-dropzone__copy">
                        <p class="product-image-dropzone__title">Görseli sürükleyin veya tıklayın</p>
                        <p class="product-image-dropzone__hint">PNG, JPG · Maks. {{ (int) ceil(config('upload.product_image_max_kb', 10240) / 1024) }} MB</p>
                    </div>
                </div>
            </label>
        </div>
    </div>

    {{-- Sağ sütun: Detaylar --}}
    <div class="product-form-col product-form-col--details space-y-5">
        <div class="product-form-section product-form-section--accent">
            <p class="product-form-section__eyebrow">Detaylar</p>

            @include('admin.partials.searchable-select', [
                'name' => 'type',
                'label' => 'Departman *',
                'value' => old('type', $product->type ?? 'kitchen'),
                'required' => true,
                'placeholder' => 'Departman seçin…',
                'searchPlaceholder' => 'Departman ara…',
                'options' => $departmentOptions,
            ])
            @error('type')<p class="form-field-error">{{ $message }}</p>@enderror

            @include('admin.partials.searchable-select', [
                'name' => 'station',
                'label' => 'Hazırlama İstasyonu',
                'value' => old('station', $product->station),
                'required' => false,
                'placeholder' => 'Kategoriye g?re otomatik',
                'searchPlaceholder' => '?stasyon ara?',
                'options' => $stationOptions,
            ])
            @error('station')<p class="form-field-error">{{ $message }}</p>@enderror

            @include('admin.partials.searchable-select', [
                'name' => 'category_id',
                'label' => 'Kategori *',
                'value' => old('category_id', $product->category_id),
                'required' => true,
                'placeholder' => 'Kategori seçin…',
                'searchPlaceholder' => 'Kategori ara…',
                'options' => $categoryOptions,
            ])
            @error('category_id')<p class="form-field-error">{{ $message }}</p>@enderror

            @include('admin.products.partials.menu-hierarchy-fields', compact('product', 'menuHierarchyCatalog'))

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label" for="product-sort">Sıra</label>
                    <input type="number" id="product-sort" name="sort_order" value="{{ old('sort_order', $product->sort_order ?? 0) }}" class="form-input max-w-none">
                </div>
                <div class="flex items-end pb-1">
                    <label class="admin-form-check w-full rounded-xl border border-[#1B4D36]/40 bg-[#0A0A0A] px-3 py-2.5">
                        <input type="hidden" name="in_stock" value="0">
                        <input type="checkbox" name="in_stock" value="1" {{ old('in_stock', $product->in_stock ?? true) ? 'checked' : '' }} class="rounded border-zinc-700 bg-[#141414] text-[#C6A046]">
                        Stokta
                    </label>
                </div>
            </div>
        </div>

        <div class="product-form-section">
            <label class="form-label">Rozet (Popüler, Yeni…)</label>
            @php $currentBadge = old('badge', $product->badge); @endphp
            <input type="text" id="badgeInput" name="badge" value="{{ $currentBadge }}" class="form-input max-w-none" placeholder="Rozet seç veya yaz" autocomplete="off" data-badge-input>
            @if(!empty($badgeSuggestions))
            <div class="mt-2 flex flex-wrap gap-2" data-badge-chips>
                @foreach($badgeSuggestions as $badge)
                <button
                    type="button"
                    class="product-badge-chip {{ $currentBadge === $badge ? 'is-active' : '' }}"
                    data-badge-chip
                    data-badge-value="{{ $badge }}"
                >{{ $badge }}</button>
                @endforeach
            </div>
            @endif
        </div>

        <div class="variation-compact-card" data-variation-compact-card>
            <div class="variation-compact-card__head">
                <div>
                    <p class="variation-compact-card__eyebrow">Dinamik Seçenekler</p>
                    <p class="variation-compact-card__summary">
                        <span data-variation-group-count>{{ $variationGroupCount }}</span> grup ·
                        <span data-variation-option-count>{{ $variationOptionCount }}</span> seçenek
                    </p>
                </div>
                @if($inDrawer)
                <span class="text-xs font-medium text-zinc-500">Aşağıda düzenleyin</span>
                @endif
            </div>

            <div class="drinks-variation-panel hidden" data-drinks-variation-panel>
                <p class="drinks-variation-panel__title">İçecek varyasyon şablonları</p>
                <p class="drinks-variation-panel__hint">Hazır içecek varyasyon şablonları. Şablon uygulandıktan sonra fiyatları özelleştirebilirsiniz.</p>
                <div class="drinks-variation-panel__actions">
                    <button type="button" class="drinks-variation-chip" data-apply-drink-template="temperature">Sıcak / Buzlu</button>
                    <button type="button" class="drinks-variation-chip" data-apply-drink-template="milk">Süt Değişimi</button>
                    <button type="button" class="drinks-variation-chip" data-apply-drink-template="shot">Shot (1–4)</button>
                    <button type="button" class="drinks-variation-chip drinks-variation-chip--gold" data-apply-drink-template="full">Tam Paket</button>
                </div>
            </div>

            <div class="retail-variation-hint hidden" data-retail-variation-hint>
                <p class="retail-variation-hint__title">Biblo / Figür boy varyasyonu</p>
                <p class="retail-variation-hint__text">Küçük ve Büyük Boy seçeneklerini ek fiyat farklarıyla tanımlayın (+150 ₺, +300 ₺ gibi).</p>
                <button type="button" class="btn btn-secondary btn-sm relative z-50 mt-2" data-apply-retail-boy-template>Boy Şablonu Uygula</button>
            </div>
            @if($inDrawer)
            <p class="variation-compact-card__hint" data-variation-default-hint>Boy, sos, ekstra gibi varyasyonları aşağıdan düzenleyin.</p>
            @else
            <p class="variation-compact-card__hint" data-variation-default-hint>Varyasyon grupları sayfanın altında geniş panelde düzenlenir.</p>
            @endif
            <p class="variation-compact-card__hint hidden" data-variation-drinks-hint>İçecekler için yukarıdaki şablonları kullanın; grupları alttaki panelden özelleştirin.</p>
        </div>

        @if($inDrawer)
        <div class="product-form-section mt-4">
            @include('admin.partials.product-option-groups', ['product' => $product])
        </div>
        @endif
    </div>
</div>

@if(! $inDrawer)
<section class="product-form-variations">
    @include('admin.partials.product-option-groups', [
        'product' => $product,
        'formId' => 'adminProductForm',
    ])
</section>
@endif
