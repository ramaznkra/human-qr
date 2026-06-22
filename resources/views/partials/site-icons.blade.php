@php
    use App\Support\SiteBranding;
    $pwaAppTitle = $pwaAppTitle ?? ($settings['venue_name'] ?? \App\Support\SiteBranding::defaultVenueName());
@endphp
<link rel="icon" href="{{ SiteBranding::faviconSvgUrl() }}" type="image/svg+xml">
<link rel="icon" href="{{ SiteBranding::faviconPngUrl() }}" sizes="32x32" type="image/png">
<link rel="icon" href="{{ SiteBranding::favicon16Url() }}" sizes="16x16" type="image/png">
<link rel="apple-touch-icon" href="{{ SiteBranding::appleTouchIconUrl() }}" sizes="180x180">
@if ($includePwaManifest ?? false)
    <link rel="manifest" href="{{ route('waiter.manifest') }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ $pwaAppTitle }}">
@endif
