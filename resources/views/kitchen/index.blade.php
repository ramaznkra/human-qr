<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.site-icons')
    <title>Mutfak — {{ $settings['venue_name'] }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/pages/kitchen.js'])
</head>
<body class="min-h-screen bg-[#121110] font-sans text-gray-100 antialiased">
<header class="flex items-center justify-between border-b border-white/5 px-5 py-5">
    <div>
        <h1 class="text-xl font-bold uppercase tracking-wider text-gray-100">{{ $settings['venue_name'] }}</h1>
        <span class="text-sm text-[#E67E22]">Sipariş Takip</span>
    </div>
    <span id="clock" class="text-xl font-semibold tabular-nums text-[#D4C5B9]"></span>
</header>
<div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" id="ordersGrid">
    @forelse($orders as $order)
    <div class="rounded-2xl border border-white/5 bg-[#262220] p-5 border-l-4 {{ $order->status === 'preparing' ? 'border-l-emerald-500' : ($order->status === 'ready' ? 'border-l-[#E67E22]' : 'border-l-[#E67E22]/60') }}" data-id="{{ $order->id }}">
        <div class="mb-2 flex justify-between text-sm">
            <strong class="text-gray-100">#{{ $order->order_number }}</strong>
            <span class="text-[#D4C5B9]">{{ $order->created_at->format('H:i') }}</span>
        </div>
        @if($order->table)<div class="mb-2 text-sm font-semibold text-[#E67E22]">Masa {{ $order->table->number }}</div>@endif
        @foreach($order->items as $item)
        <div class="mb-1 text-sm text-gray-100">{{ $item->quantity }}× {{ $item->product_name }}</div>
        @endforeach
        @if($order->notes)<div class="mt-2 text-xs font-light text-[#D4C5B9]">📝 {{ $order->notes }}</div>@endif
        <div class="mt-3 flex flex-wrap gap-2">
            <button onclick="setStatus({{ $order->id }},'preparing')" class="rounded-lg border border-white/10 px-3 py-1.5 text-xs text-[#D4C5B9] transition hover:border-[#E67E22] hover:bg-[#E67E22]/15 hover:text-[#E67E22]">Hazırlanıyor</button>
            <button onclick="setStatus({{ $order->id }},'ready')" class="rounded-lg border border-white/10 px-3 py-1.5 text-xs text-[#D4C5B9] transition hover:border-[#E67E22] hover:bg-[#E67E22]/15 hover:text-[#E67E22]">Hazır</button>
            <button onclick="setStatus({{ $order->id }},'delivered')" class="rounded-lg bg-[#E67E22]/15 px-3 py-1.5 text-xs font-medium text-[#E67E22] transition hover:bg-[#E67E22] hover:text-white">Teslim</button>
        </div>
    </div>
    @empty
    <div class="col-span-full py-20 text-center text-[#D4C5B9]">Bekleyen sipariş yok ✨</div>
    @endforelse
</div>

<script>
function updateClock() {
    document.getElementById('clock').textContent = new Date().toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' });
}
updateClock();
setInterval(updateClock, 1000);
</script>
</body>
</html>
