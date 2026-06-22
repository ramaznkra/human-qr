<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0A0A0A">
    @include('partials.site-icons')
    <title>@yield('title', ($settings['venue_name']) . ' — QR Menü')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="min-h-dvh bg-gradient-to-b from-[#0F0F0F] via-[#0A0A0A] to-[#050505] font-sans text-zinc-200 antialiased selection:bg-[#C6A046]/30">
    <div class="menu-ambient pointer-events-none fixed inset-0 z-0 overflow-hidden" aria-hidden="true">
        <div class="absolute -top-24 -right-16 h-72 w-72 rounded-full bg-[#C6A046]/8 blur-[130px]"></div>
        <div class="absolute top-1/3 -left-20 h-80 w-80 rounded-full bg-[#C6A046]/6 blur-[130px]"></div>
        <div class="absolute -bottom-32 right-1/4 h-96 w-96 rounded-full bg-[#1f1f1f]/60 blur-[120px]"></div>
        <div class="absolute bottom-1/4 left-1/3 h-64 w-64 rounded-full bg-[#161616]/70 blur-[120px]"></div>
    </div>

    <div class="menu-device relative z-[1]">
        @yield('content')
    </div>

    @stack('menu-overlays')
    @stack('scripts')
</body>
</html>
