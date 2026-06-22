@extends('layouts.admin')
@section('title', 'Social Spotted')
@section('page_heading', 'Social Spotted Galeri')

@section('content')
<div class="spotted-page">
    <div class="spotted-page__head">
        <div>
            <h2 class="admin-page-title">Social Spotted</h2>
            <p class="mt-1 text-sm font-medium text-zinc-400">Menüde logonun altında görünen HSP Moments galeri kartları.</p>
        </div>
        <a href="{{ route('admin.cafe-galleries.create') }}" class="btn btn-primary">+ Yeni Kart</a>
    </div>

    @if($galleries->isEmpty())
    <div class="spotted-page__empty">
        <p class="font-medium text-zinc-300">Henüz kart yok.</p>
        <p class="mt-1 text-sm font-medium text-zinc-400"><code class="text-zinc-300">php artisan migrate</code> ve seed çalıştırın veya ilk kartı ekleyin.</p>
        <a href="{{ route('admin.cafe-galleries.create') }}" class="btn btn-primary mt-4">+ İlk Kartı Ekle</a>
    </div>
    @else
    <div class="spotted-grid">
        @foreach($galleries as $g)
        <article class="spotted-card {{ $g->is_active ? '' : 'spotted-card--inactive' }}">
            <div class="spotted-card__media">
                <img src="{{ $g->image_url }}" alt="{{ $g->title ?? 'Social Spotted' }}" class="spotted-card__img">
                <span class="spotted-card__badge">{{ $g->badge_text }}</span>
            </div>
            <div class="spotted-card__body">
                <div class="spotted-card__head">
                    <h3 class="spotted-card__title">{{ $g->title ?: 'İsimsiz Kart' }}</h3>
                    <span class="spotted-card__status {{ $g->is_active ? 'spotted-card__status--on' : 'spotted-card__status--off' }}">
                        {{ $g->is_active ? 'Aktif' : 'Pasif' }}
                    </span>
                </div>
                <p class="spotted-card__desc">{{ $g->description ?: 'Açıklama eklenmemiş.' }}</p>
                <div class="spotted-card__meta">
                    <span class="font-medium text-zinc-300">Sıra {{ $g->sort_order }}</span>
                </div>
                <div class="spotted-card__actions">
                    <a href="{{ route('admin.cafe-galleries.edit', $g) }}" class="btn btn-sm btn-secondary flex-1">Düzenle</a>
                    <form
                        action="{{ route('admin.cafe-galleries.destroy', $g) }}"
                        method="POST"
                        class="shrink-0"
                        @include('admin.partials.confirm-form', [
                            'title' => 'Kaydı sil',
                            'message' => 'Social Spotted kaydı kalıcı olarak silinecek.',
                            'type' => 'danger',
                            'confirmLabel' => 'Sil',
                        ])
                    >
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-danger">Sil</button>
                    </form>
                </div>
            </div>
        </article>
        @endforeach
    </div>
    @endif
</div>
@endsection
