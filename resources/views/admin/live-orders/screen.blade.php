<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.site-icons')
    <title>Canlı Siparişler — {{ $settings['venue_name'] }}</title>
    @vite(['resources/css/app.css', 'resources/js/pages/live-orders.js'])
</head>
<body class="lo-body-fullscreen">
    @include('admin.live-orders._shell', [
        'fullscreen' => true,
        'showSidebar' => false,
        'tables' => $tables,
        'busyTableIds' => $busyTableIds,
        'defaultStation' => $defaultStation ?? 'all',
        'showPendingApproval' => true,
    ])
</body>
</html>
