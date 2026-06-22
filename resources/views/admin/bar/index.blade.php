@extends('layouts.bar')

@section('title', 'Bar / Kahve Ekranı')

@section('content')
<header class="sticky top-0 z-50 flex items-center justify-between border-b border-white/10 bg-[#262220] px-4 py-4 md:px-8">
    <div>
        <h1 class="text-xl font-bold uppercase tracking-wider text-[#E67E22]">Human Bar</h1>
        <p class="text-sm text-[#D4C5B9]">Dokunmatik hazırlık ekranı</p>
    </div>
    <div class="text-right">
        <span id="barClock" class="text-2xl font-bold tabular-nums text-gray-100"></span>
        <p id="barStatus" class="text-xs text-[#D4C5B9]">Yükleniyor…</p>
    </div>
</header>

<main id="barOrders" class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 md:p-6">
    <p class="col-span-full py-20 text-center text-[#D4C5B9]">İçecek siparişi bekleniyor…</p>
</main>

<a href="{{ route('admin.dashboard') }}" class="fixed bottom-4 left-4 rounded-full border border-white/10 bg-[#262220]/90 px-4 py-2 text-xs text-[#D4C5B9] backdrop-blur hover:text-white">← Admin</a>
@endsection
