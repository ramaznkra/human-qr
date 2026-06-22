@extends('layouts.admin')
@section('title', 'Ekran Slaytları')
@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <h2 class="admin-page-title">Brand Showcase Slaytları</h2>
    <div class="flex gap-2">
        <a href="{{ route('display.index') }}" target="_blank" class="btn btn-secondary">Ekranı Önizle</a>
        <a href="{{ route('admin.slides.create') }}" class="btn btn-primary">+ Yeni Slayt</a>
    </div>
</div>
<div class="admin-card overflow-x-auto">
    <p class="mb-4 text-sm font-medium text-zinc-400">Kasa arkası dev ekran — ünlü & mekan fotoğrafları. TV'de <strong class="font-semibold text-zinc-200">/ekran</strong> adresini tam ekran açın.</p>
    <table class="admin-table w-full">
        <thead><tr><th>Önizleme</th><th>Rozet / Başlık</th><th>Süre</th><th>Sıra</th><th>Durum</th><th></th></tr></thead>
        <tbody>
        @foreach($slides as $s)
        <tr>
            <td><img src="{{ $s->image_url }}" class="h-14 w-24 rounded-lg border border-zinc-800 object-cover bg-zinc-900" onerror="this.classList.add('bg-zinc-800')"></td>
            <td>
                @if($s->badge)<span class="text-[10px] font-bold uppercase tracking-wider text-[#C6A046]">{{ $s->badge }}</span><br>@endif
                <span class="font-medium text-zinc-100">{{ $s->title }}</span>
                @if($s->subtitle)<br><span class="text-xs font-medium text-zinc-400">{{ $s->subtitle }}</span>@endif
            </td>
            <td class="text-zinc-200">{{ $s->duration }} sn</td>
            <td class="text-zinc-200">{{ $s->sort_order }}</td>
            <td class="font-medium {{ $s->is_active ? 'text-emerald-400' : 'text-zinc-400' }}">{{ $s->is_active ? 'Aktif' : 'Pasif' }}</td>
            <td class="space-x-1 whitespace-nowrap">
                <a href="{{ route('admin.slides.edit', $s) }}" class="btn btn-sm btn-secondary">Düzenle</a>
                <form
                    action="{{ route('admin.slides.destroy', $s) }}"
                    method="POST"
                    class="inline"
                    @include('admin.partials.confirm-form', [
                        'title' => 'Slaytı sil',
                        'message' => 'Bu slayt kalıcı olarak silinecek.',
                        'type' => 'danger',
                        'confirmLabel' => 'Sil',
                    ])
                >
                    @csrf @method('DELETE')<button class="btn btn-sm btn-danger">Sil</button>
                </form>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection
