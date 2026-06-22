@extends('layouts.admin')
@section('title', $table->exists ? 'Masa Düzenle' : 'Yeni Masa')
@section('page_heading', $table->exists ? 'Masa Düzenle' : 'Yeni Masa')
@section('content')
<div class="mb-6"><h2 class="admin-page-title">{{ $table->exists ? 'Masa Düzenle' : 'Yeni Masa Ekle' }}</h2></div>
<div class="grid gap-6 lg:grid-cols-2">
    <div class="admin-card max-w-xl">
        <form method="POST" action="{{ $table->exists ? route('admin.tables.update', $table) : route('admin.tables.store') }}" class="space-y-4">
            @csrf
            @if($table->exists) @method('PUT') @endif
            <div>
                <label class="form-label">Masa No *</label>
                <input type="text" name="number" value="{{ old('number', $table->number) }}" required class="form-input" placeholder="15">
                <p class="mt-1 text-xs text-zinc-500">QR linki masaya özel güvenli kimlik (UUID) ile oluşturulur:<br><code class="break-all text-zinc-400">{{ url('/menu') }}/<strong class="text-zinc-200">{{ $table->exists ? $table->uuid : 'otomatik-uuid' }}</strong></code></p>
            </div>
            @if($table->exists)
            <p class="text-xs text-zinc-500">Masanın aktif/pasif durumunu <a href="{{ route('admin.tables.index') }}" class="admin-link-gold">Masalar</a> listesindeki anahtardan değiştirebilirsiniz.</p>
            @endif
            <div class="flex gap-3 pt-2">
                <button type="submit" class="btn btn-primary">{{ $table->exists ? 'Kaydet' : 'Masa Ekle & QR Oluştur' }}</button>
                <a href="{{ route('admin.tables.index') }}" class="btn btn-secondary">İptal</a>
            </div>
        </form>
    </div>
    @if($table->exists)
    <div class="admin-card">
        <h3 class="mb-4 font-semibold text-zinc-200">QR Kod — Masa {{ $table->number }}</h3>
        @if($table->qr_image_url)
        <div class="flex flex-col items-center rounded-xl border border-zinc-800 bg-[#141414] p-6">
            <img src="{{ $table->qr_image_url }}" alt="QR" class="h-48 w-48 object-contain">
            <p class="mt-3 break-all text-center text-xs text-zinc-500">{{ $table->menu_url }}</p>
            <div class="mt-4 flex gap-2">
                <a href="{{ route('admin.tables.qr.png', $table) }}" class="btn btn-sm btn-primary" download>PNG İndir</a>
                <a href="{{ route('admin.tables.qr.svg', $table) }}" class="btn btn-sm btn-secondary" download>SVG İndir</a>
            </div>
        </div>
        @else
        <p class="text-sm text-zinc-500">QR henüz yok. <form action="{{ route('admin.tables.regenerate', $table) }}" method="POST" class="inline">@csrf<button type="submit" class="admin-link-gold">Oluştur</button></form></p>
        @endif
    </div>
    @endif
</div>
@endsection
