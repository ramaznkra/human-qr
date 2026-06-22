@extends('layouts.admin')
@section('title', $slide->exists ? 'Slayt Düzenle' : 'Yeni Slayt')
@section('content')
<div class="mb-6"><h2 class="admin-page-title">{{ $slide->exists ? 'Slayt Düzenle' : 'Yeni Slayt' }}</h2></div>
<div class="admin-card max-w-xl">
    <p class="mb-4 text-sm text-zinc-400">TV Brand Showcase (<strong class="text-zinc-200">/ekran</strong>) — ünlü ve mekan fotoğrafları. 1920×1080 önerilir.</p>
    <form method="POST" action="{{ $slide->exists ? route('admin.slides.update', $slide) : route('admin.slides.store') }}" enctype="multipart/form-data" class="space-y-4">
        @csrf
        @if($slide->exists) @method('PUT') @endif
        <div>
            <label class="form-label">Rozet</label>
            <input type="text" name="badge" value="{{ old('badge', $slide->badge) }}" placeholder="VIP Guest veya Ambience" class="form-input">
            <p class="mt-1 text-xs text-zinc-500">“VIP” içeren rozetler altın, diğerleri koyu rozet olarak gösterilir.</p>
        </div>
        <div><label class="form-label">Başlık</label><input type="text" name="title" value="{{ old('title', $slide->title) }}" placeholder="Sefo / Premium Nargile Alanı" class="form-input"></div>
        <div><label class="form-label">Alt satır</label><input type="text" name="subtitle" value="{{ old('subtitle', $slide->subtitle) }}" placeholder="@ Human Social Lounge" class="form-input"></div>
        <div>
            <label class="form-label">Görsel * (1920×1080 önerilir)</label>
            <input type="file" name="image" accept="image/*" {{ $slide->exists ? '' : 'required' }} class="form-input">
            @if($slide->image)<img src="{{ $slide->image_url }}" class="mt-2 max-w-xs rounded-lg border border-zinc-800">@endif
        </div>
        <div>
            <label class="form-label">Geçiş Süresi (sn)</label>
            <input type="number" name="duration" value="{{ old('duration', $slide->duration ?? 7) }}" min="3" max="60" class="form-input">
            <p class="mt-1 text-xs text-zinc-500">Showcase ekranında varsayılan gösterim süresi 7 saniyedir.</p>
        </div>
        <div><label class="form-label">Sıra</label><input type="number" name="sort_order" value="{{ old('sort_order', $slide->sort_order ?? 0) }}" class="form-input"></div>
        <label class="admin-form-check">
            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $slide->is_active ?? true) ? 'checked' : '' }} class="rounded border-zinc-700 bg-[#141414] text-[#C6A046] focus:ring-[#C6A046]/30"> Aktif
        </label>
        <div class="flex gap-3 pt-2">
            <button type="submit" class="btn btn-primary">Kaydet</button>
            <a href="{{ route('admin.slides.index') }}" class="btn btn-secondary">İptal</a>
        </div>
    </form>
</div>
@endsection
