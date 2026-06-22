@extends('layouts.admin')
@section('title', $category->exists ? 'Kategori Düzenle' : 'Yeni Kategori')
@section('page_heading', $category->exists ? 'Kategori Düzenle' : 'Yeni Kategori')
@section('section_label', 'Menü')

@section('content')
<div class="category-form-page">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <h2 class="admin-page-title">{{ $category->exists ? '📂 Kategori Düzenle' : '📂 Yeni Kategori' }}</h2>
        <a href="{{ route('admin.categories.index') }}" class="btn btn-secondary">← Kategorilere Dön</a>
    </div>

    <div class="admin-card category-form-card max-w-6xl">
        <form
            method="POST"
            enctype="multipart/form-data"
            action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}"
            class="category-form"
            data-category-form
        >
            @csrf
            @if($category->exists) @method('PUT') @endif

            @include('admin.categories.partials.form-fields', compact('category'))

            <div class="category-form__actions">
                <a href="{{ route('admin.categories.index') }}" class="btn btn-secondary hidden sm:inline-flex">İptal</a>
                <button type="submit" class="btn btn-primary category-form__submit">
                    {{ $category->exists ? 'Değişiklikleri Kaydet' : 'Kategoriyi Kaydet' }}
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
@vite(['resources/js/pages/admin-category-form.js'])
@endpush
