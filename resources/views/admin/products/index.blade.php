@extends('layouts.admin')
@section('title', 'Ürünler')
@section('section_label', 'Menü')
@section('page_heading', 'Ürünler')

@section('content')
<div class="admin-products-page space-y-6" data-admin-products>
    <div class="admin-products-toolbar flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.products.create') }}" class="admin-products-toolbar__btn admin-products-toolbar__btn--gold">+ Yeni Ürün Ekle</a>
            <a href="{{ route('admin.categories.index') }}" class="admin-products-toolbar__btn admin-products-toolbar__btn--ghost">Kategori Yönetimi</a>
        </div>
        <input
            type="search"
            class="admin-products-search"
            placeholder="Ürünlerde ara..."
            data-product-search
            autocomplete="off"
            aria-label="Ürünlerde ara"
        >
    </div>

    <div class="admin-category-tabs" data-category-tabs role="tablist" aria-label="Kategori filtresi">
        <button
            type="button"
            class="admin-category-tabs__tab is-active"
            data-category-tab=""
            role="tab"
            aria-selected="true"
        >Tümü <span class="admin-category-tabs__count">{{ $products->count() }}</span></button>
        @foreach($categories as $cat)
        @php $count = $products->where('category_id', $cat->id)->count(); @endphp
        <button
            type="button"
            class="admin-category-tabs__tab"
            data-category-tab="{{ $cat->id }}"
            role="tab"
            aria-selected="false"
        >{{ $cat->getTranslation('name', 'tr') }} <span class="admin-category-tabs__count">{{ $count }}</span></button>
        @endforeach
    </div>

    <div class="admin-catalog" data-catalog-root="products">
        <div class="admin-catalog__toolbar mb-4 flex justify-end">
            @include('admin.partials.catalog-view-toggle', ['scope' => 'products'])
        </div>

        <div data-view-panel="list">
            <div class="admin-products-table-wrap overflow-x-auto">
                <table class="admin-products-table w-full min-w-[720px] text-left">
                    <thead>
                        <tr>
                            <th>Ürün</th>
                            <th>Kategori</th>
                            <th>Fiyat</th>
                            <th class="admin-table-col-center">Menüde</th>
                            <th>Stok</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody data-products-tbody>
                    @forelse($products as $p)
                    <tr
                        data-product-item
                        data-product-id="{{ $p->id }}"
                        data-category-id="{{ $p->category_id }}"
                        data-product-name="{{ Str::lower($p->getTranslation('name', 'tr')) }}"
                        data-quick-update-url="{{ route('admin.products.quick-update', $p) }}"
                        class="admin-products-table__row {{ $p->is_available ? '' : 'admin-products-table__row--hidden' }} {{ $p->in_stock ? '' : 'admin-product-row--sold-out' }}"
                    >
                        <td class="admin-products-table__product">
                            <div class="flex items-center gap-3">
                                @if($p->image)
                                <img src="{{ $p->image_url }}" alt="" class="product-avatar {{ $p->in_stock ? '' : 'grayscale' }}">
                                @else
                                <div class="product-avatar product-avatar--placeholder" aria-hidden="true">{{ Str::upper(Str::substr($p->getTranslation('name', 'tr'), 0, 1)) }}</div>
                                @endif
                                <div class="min-w-0">
                                    <span class="admin-products-table__name" data-product-name-display>{{ $p->getTranslation('name', 'tr') }}</span>
                                    <input type="text" class="admin-products-inline-input hidden" data-product-name-input value="{{ $p->getTranslation('name', 'tr') }}" maxlength="120">
                                    @if($p->badge)<span class="product-badge ml-1">{{ $p->badge }}</span>@endif
                                    @if($preview = $p->variationPreviewText())
                                    <span class="mt-0.5 block text-[10px] font-normal italic text-zinc-500">{{ $preview }}</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="text-sm text-zinc-400">{{ $p->category->getTranslation('name', 'tr') }}</td>
                        <td>
                            <span class="admin-products-table__price" data-product-price-display>{{ number_format($p->price, 2, ',', '.') }} ₺</span>
                            <input type="number" step="0.01" min="0" class="admin-products-inline-input admin-products-inline-input--price hidden" data-product-price-input value="{{ $p->price }}">
                        </td>
                        <td class="admin-table-col-center">
                            @include('admin.partials.product-availability-toggle', ['product' => $p])
                        </td>
                        <td>
                            @include('admin.partials.product-in-stock-toggle', ['product' => $p, 'pill' => true])
                        </td>
                        <td>
                            <div class="admin-products-actions">
                                <button type="button" class="admin-products-actions__quick" data-quick-edit-toggle>Hızlı Güncelle</button>
                                <button type="button" class="admin-products-actions__save hidden" data-quick-edit-save>Kaydet</button>
                                <button type="button" class="admin-products-actions__cancel hidden" data-quick-edit-cancel>İptal</button>
                                <a href="{{ route('admin.products.edit', $p) }}" class="admin-products-actions__quick">Düzenle</a>
                                @include('admin.partials.product-delete-form', ['product' => $p, 'icon' => true])
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr data-products-empty>
                        <td colspan="6" class="py-16 text-center text-zinc-500">Ürün bulunamadı.</td>
                    </tr>
                    @endforelse
                    </tbody>
                </table>
                <p class="hidden py-12 text-center text-sm text-zinc-500" data-products-filter-empty>Filtreye uygun ürün bulunamadı.</p>
            </div>
        </div>

        <div data-view-panel="tray" class="hidden">
            @if($products->isEmpty())
            <div class="admin-card py-16 text-center text-zinc-500">Ürün bulunamadı.</div>
            @else
            <div class="admin-catalog-tray">
                @foreach($products as $p)
                <article
                    data-product-item
                    data-product-id="{{ $p->id }}"
                    data-category-id="{{ $p->category_id }}"
                    data-product-name="{{ Str::lower($p->getTranslation('name', 'tr')) }}"
                    class="admin-tray-card {{ $p->is_available ? '' : 'admin-tray-card--hidden' }} {{ $p->in_stock ? '' : 'admin-tray-card--sold-out' }}"
                >
                    <div class="admin-tray-card__media {{ $p->in_stock ? '' : 'admin-tray-card__media--sold-out' }}">
                        @if($p->image)
                        <img src="{{ $p->image_url }}" alt="" class="admin-tray-card__img" loading="lazy">
                        @else
                        <div class="admin-tray-card__placeholder" aria-hidden="true">🍽️</div>
                        @endif
                        @if($p->badge)
                        <span class="admin-tray-card__badge">{{ $p->badge }}</span>
                        @endif
                    </div>
                    <div class="admin-tray-card__body">
                        <h3 class="admin-tray-card__title" title="{{ $p->name }}">{{ $p->name }}</h3>
                        @if($preview = $p->variationPreviewText())
                        <p class="text-[10px] italic leading-snug text-zinc-500">{{ $preview }}</p>
                        @endif
                        <p class="admin-tray-card__meta">{{ $p->category->name }}</p>
                        <p class="admin-tray-card__price">{{ number_format($p->price, 2, ',', '.') }} ₺</p>
                        <div class="admin-tray-card__toggle">
                            @include('admin.partials.product-availability-toggle', ['product' => $p, 'compact' => true])
                        </div>
                        <div class="admin-tray-card__toggle mt-1">
                            @include('admin.partials.product-in-stock-toggle', ['product' => $p, 'compact' => true])
                        </div>
                        <div class="admin-tray-card__actions">
                            <a href="{{ route('admin.products.edit', $p) }}" class="btn btn-sm btn-secondary w-full">Düzenle</a>
                            @include('admin.partials.product-delete-form', ['product' => $p, 'block' => true])
                        </div>
                    </div>
                </article>
                @endforeach
            </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
@vite(['resources/js/pages/admin-catalog-view.js', 'resources/js/pages/admin-products.js'])
@endpush
