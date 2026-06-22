@extends('layouts.admin')
@section('title', 'Masalar')
@section('page_heading', 'Masalar & QR')
@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div>
        <p class="admin-text-muted">QR kodlarını indirip masaya yapıştırın. Canlı masa durumu için <a href="{{ route('admin.live-orders.index') }}" class="admin-link-gold">Canlı Siparişler</a> ekranına bakın.</p>
    </div>
    <a href="{{ route('admin.tables.create') }}" class="btn btn-primary">+ Yeni Masa</a>
</div>

<div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
    @foreach($tables as $t)
    <article
        class="table-map-card flex flex-col rounded-2xl border p-4 shadow-2xl transition
            {{ $t->is_active ? 'table-map-card--on' : 'table-map-card--off' }}"
        data-table-item
        title="{{ $t->is_active ? 'Masa açık' : 'Masa kapalı' }}"
    >
        <div class="flex items-start justify-between gap-2">
            <div class="flex items-start gap-2">
                <span
                    class="table-status-dot mt-1 shrink-0 {{ $t->is_active ? 'table-status-dot--on' : 'table-status-dot--off' }}"
                    data-table-dot
                    aria-hidden="true"
                ></span>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wider text-zinc-500">Masa</p>
                    <p class="table-card__number text-2xl font-bold {{ $t->is_active ? 'text-zinc-100' : 'text-zinc-500' }}" data-table-number>{{ $t->number }}</p>
                </div>
            </div>
            <label class="relative inline-flex shrink-0 cursor-pointer items-center" title="Masayı aç / kapat">
                <input
                    type="checkbox"
                    class="peer sr-only"
                    data-table-toggle
                    data-toggle-url="{{ route('admin.tables.toggle-active', $t) }}"
                    {{ $t->is_active ? 'checked' : '' }}
                    aria-label="Masa {{ $t->number }} aktif"
                >
                <span class="admin-toggle__track admin-toggle__track--emerald"></span>
            </label>
        </div>
        <p class="mt-1 text-[11px] font-medium {{ $t->is_active ? 'text-emerald-400' : 'text-zinc-500' }}" data-table-status-label>
            {{ $t->is_active ? 'Masa açık' : 'Masa kapalı' }}
        </p>

        @if($t->qr_image_url)
        <div class="mx-auto my-3 flex h-24 w-24 items-center justify-center rounded-xl border border-zinc-800 bg-[#141414] p-2">
            <img src="{{ $t->qr_image_url }}" alt="QR Masa {{ $t->number }}" class="h-full w-full object-contain">
        </div>
        @else
        <div class="my-3 flex h-24 items-center justify-center rounded-xl border border-dashed border-zinc-800 bg-[#0A0A0A] text-xs text-zinc-500">QR yok</div>
        @endif

        <p class="mb-3 break-all text-center text-[10px] leading-tight text-zinc-500">
            <a href="{{ $t->menu_url }}" target="_blank" class="admin-link-gold">{{ $t->menu_url }}</a>
        </p>

        <div class="mt-auto flex flex-col gap-2">
            <div class="grid grid-cols-2 gap-2">
                <a href="{{ route('admin.tables.qr.png', $t) }}" class="btn btn-sm btn-secondary text-center" download>PNG</a>
                <a href="{{ route('admin.tables.qr.svg', $t) }}" class="btn btn-sm btn-secondary text-center" download>SVG</a>
            </div>
            <a href="{{ route('admin.tables.qr.png', $t) }}" class="btn btn-sm btn-primary w-full text-center" download>
                QR Kodu İndir
            </a>
            <div class="flex gap-2">
                <a href="{{ $t->menu_url }}" target="_blank" class="btn btn-sm btn-secondary flex-1 text-center">Menü</a>
                <a href="{{ route('admin.tables.edit', $t) }}" class="btn btn-sm btn-secondary flex-1 text-center">Düzenle</a>
            </div>
            <form action="{{ route('admin.tables.regenerate', $t) }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-sm btn-secondary w-full" title="QR yeniden üret">↻ QR Yenile</button>
            </form>
        </div>
    </article>
    @endforeach
</div>

@if($tables->isEmpty())
<div class="admin-card py-12 text-center text-zinc-500">
    Henüz masa yok. <a href="{{ route('admin.tables.create') }}" class="admin-link-gold">İlk masayı ekleyin</a>.
</div>
@endif
@endsection

@push('scripts')
@vite('resources/js/pages/admin-tables.js')
@endpush
