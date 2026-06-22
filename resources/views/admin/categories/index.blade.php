@extends('layouts.admin')
@section('title', 'Kategoriler')
@section('section_label', 'Menü')
@section('page_heading', 'Kategoriler')

@section('content')
<div class="admin-categories-page space-y-6">
    <div class="admin-products-toolbar flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <p class="admin-text-muted max-w-xl">Kategorileri kart görünümünde yönetin. Sıra numaralarını güncelleyip kaydedin.</p>
        <div class="flex shrink-0 flex-wrap gap-2">
            <button type="button" id="saveCategorySort" class="admin-products-toolbar__btn admin-products-toolbar__btn--ghost hidden" data-save-category-sort>Sıralamayı Kaydet</button>
            <a href="{{ route('admin.categories.create') }}" class="admin-products-toolbar__btn admin-products-toolbar__btn--gold">+ Yeni Kategori</a>
        </div>
    </div>

    @if($categories->isEmpty())
    <div class="admin-card py-16 text-center text-zinc-500">
        Henüz kategori yok. <a href="{{ route('admin.categories.create') }}" class="admin-link-gold">İlk kategoriyi ekleyin</a>.
    </div>
    @else
    <div class="category-kpi-grid">
        @foreach($categories as $cat)
        @php
            $revenue = (float) ($revenueByCategory[$cat->id] ?? 0);
            $share = $totalRevenueToday > 0 ? round(($revenue / $totalRevenueToday) * 100, 1) : 0;
        @endphp
        <article
            class="category-kpi-card {{ $cat->is_active ? '' : 'category-kpi-card--inactive' }}"
            data-category-item
            data-category-id="{{ $cat->id }}"
        >
            <div class="category-kpi-card__media">
                @if($cat->image_url)
                <img src="{{ $cat->image_url }}" alt="{{ $cat->name }}" class="category-kpi-card__img">
                @else
                <div class="category-kpi-card__placeholder" aria-hidden="true">{{ $cat->icon ?: '📁' }}</div>
                @endif
                <span class="table-status-dot {{ $cat->is_active ? 'table-status-dot--on' : 'table-status-dot--off' }} category-kpi-card__status" data-category-dot aria-hidden="true"></span>
            </div>

            <div class="category-kpi-card__body">
                <div class="category-kpi-card__head">
                    <h3 class="category-kpi-card__title">{{ $cat->getTranslation('name', 'tr') }}</h3>
                    @include('admin.partials.category-active-toggle', ['category' => $cat])
                </div>

                <p class="category-kpi-card__meta">{{ $cat->slug }} · {{ $cat->typeLabel() }}</p>

                <div class="category-kpi-card__stats">
                    <div class="category-kpi-card__stat">
                        <span class="category-kpi-card__stat-value">{{ $cat->products_count }}</span>
                        <span class="category-kpi-card__stat-label">Ürün</span>
                    </div>
                    <div class="category-kpi-card__stat">
                        <span class="category-kpi-card__stat-value">{{ number_format($revenue, 0, ',', '.') }} ₺</span>
                        <span class="category-kpi-card__stat-label">Bugünkü ciro</span>
                    </div>
                    <div class="category-kpi-card__stat">
                        <span class="category-kpi-card__stat-value category-kpi-card__stat-value--gold">%{{ number_format($share, 1, ',', '.') }}</span>
                        <span class="category-kpi-card__stat-label">Ciro payı</span>
                    </div>
                </div>

                <div class="category-kpi-card__sort">
                    <label class="text-[10px] font-bold uppercase tracking-wider text-zinc-500">Sıra</label>
                    <input
                        type="number"
                        min="0"
                        class="form-input mt-1 w-20 py-1.5 text-center text-sm"
                        data-category-sort-input
                        value="{{ $cat->sort_order }}"
                        aria-label="Sıra {{ $cat->getTranslation('name', 'tr') }}"
                    >
                </div>

                <div class="category-kpi-card__actions">
                    <a href="{{ route('admin.categories.edit', $cat) }}" class="btn btn-sm btn-secondary flex-1">Düzenle</a>
                    @include('admin.partials.category-delete-form', ['category' => $cat])
                </div>
            </div>
        </article>
        @endforeach
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>window.HSP_ADMIN_CATEGORIES = { sortUrl: @json(route('admin.categories.sort-order')) };</script>
@vite(['resources/js/pages/admin-categories.js'])
@endpush
