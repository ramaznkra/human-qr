<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#121110">
    @include('partials.site-icons')
    <title>@yield('title', 'Bar Ekranı') — {{ $settings['venue_name'] }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/pages/bar-screen.js'])
</head>
<body class="min-h-dvh bg-[#121110] font-sans text-gray-100 antialiased">
    @yield('content')
</body>
</html>
