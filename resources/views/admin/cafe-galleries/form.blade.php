@extends('layouts.admin')
@section('title', $gallery->exists ? 'Social Spotted Düzenle' : 'Yeni Social Spotted')
@section('page_heading', $gallery->exists ? 'Kart Düzenle' : 'Yeni Kart')

@section('content')
<div class="mb-6"><h2 class="admin-page-title">{{ $gallery->exists ? 'Social Spotted Düzenle' : 'Yeni Social Spotted Kartı' }}</h2></div>
<div class="admin-card max-w-xl">
    <form method="POST" action="{{ $gallery->exists ? route('admin.cafe-galleries.update', $gallery) : route('admin.cafe-galleries.store') }}" enctype="multipart/form-data" class="space-y-4">
        @csrf
        @if($gallery->exists) @method('PUT') @endif
        <div>
            <label class="form-label">Görsel * (yatay / kare, yüksek kalite)</label>
            <input type="file" name="image" accept="image/*" class="form-input" {{ $gallery->exists ? '' : 'required' }}>
            @if($gallery->image_path)<img src="{{ $gallery->image_url }}" alt="" class="mt-2 max-h-48 rounded-2xl border border-zinc-800 object-cover">@endif
        </div>
        <div><label class="form-label">Başlık (isteğe bağlı)</label><input type="text" name="title" value="{{ old('title', $gallery->title) }}" class="form-input" placeholder="Misafir adı veya kısa başlık"></div>
        <div>
            <label class="form-label">Alt yazı / açıklama</label>
            <textarea name="description" rows="3" class="form-input" placeholder="Sevgili X, imza kahvemizi deneyimlerken...">{{ old('description', $gallery->description) }}</textarea>
        </div>
        <div>
            <label class="form-label">Rozet metni</label>
            <input type="text" name="badge_text" value="{{ old('badge_text', $gallery->badge_text ?? 'Spotted at HSP ✨') }}" class="form-input" placeholder="Spotted at HSP ✨">
        </div>
        <div><label class="form-label">Sıra</label><input type="number" name="sort_order" value="{{ old('sort_order', $gallery->sort_order ?? 0) }}" class="form-input"></div>
        <label class="admin-form-check">
            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $gallery->is_active ?? true) ? 'checked' : '' }} class="rounded border-zinc-700 bg-[#141414] text-[#C6A046] focus:ring-[#C6A046]/30"> Aktif
        </label>
        <div class="flex gap-3 pt-2">
            <button type="submit" class="btn btn-primary">Kaydet</button>
            <a href="{{ route('admin.cafe-galleries.index') }}" class="btn btn-secondary">İptal</a>
        </div>
    </form>
</div>
@endsection
