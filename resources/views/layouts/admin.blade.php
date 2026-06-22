@php

    $kasaFullWidth = request()->routeIs('admin.live-orders.*') && session('admin_role') === 'cashier';

    $adminRoleLabel = match (session('admin_role', 'admin')) {

        'cashier' => 'Kasa',

        'waiter' => 'Garson',

        default => 'Admin',

    };

    $hideAdminChrome = $kasaFullWidth || request()->routeIs('admin.live-orders.*');

@endphp

<!DOCTYPE html>

<html lang="tr" data-staff-theme="{{ \App\Support\StaffUi::theme() }}">

@php app()->setLocale('tr'); @endphp

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <meta name="theme-color" content="#0A0A0A">

    @include('partials.site-icons', ['includePwaManifest' => true, 'pwaAppTitle' => $settings['venue_name']])

    <title>@yield('title', 'Admin') — {{ $settings['venue_name'] }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <script>

        window.HSP_MANUAL_ORDER = {

            bootstrapUrl: @json(route('admin.manual-order.bootstrap', [], false)),

            productsUrl: @json(route('admin.manual-order.products', [], false)),

            storeUrl: @json(route('admin.manual-order.store', [], false)),

        };

        window.HSP_KASA = {

            ...window.HSP_MANUAL_ORDER,

            tableStateUrl: @json(route('admin.kasa.table-state', [], false)),

            selectTableUrl: @json(route('admin.kasa.select-table', [], false)),

            addItemUrl: @json(route('admin.kasa.add-item', [], false)),

            updateItemUrl: @json(route('admin.kasa.update-item', [], false)),

            itemStatusUrl: @json(route('admin.kasa.item-status', [], false)),

            notifyWaiterUrl: @json(route('admin.kasa.notify-waiter', [], false)),

            resumeOrderUrl: @json(route('admin.kasa.resume-order', [], false)),

            approveOrderUrl: @json(route('admin.kasa.approve-order', [], false)),

            payCashUrl: @json(route('admin.kasa.pay-cash', [], false)),

            payCardUrl: @json(route('admin.kasa.pay-card', [], false)),

            payPosUrl: @json(route('admin.kasa.pay-pos', [], false)),

        };

    </script>

    @vite(['resources/css/app.css', 'resources/js/pages/admin-shell.js', 'resources/js/pages/admin-manual-order.js'])

</head>

<body class="admin-body min-h-screen bg-[#0A0A0A] font-sans text-zinc-100 antialiased {{ $kasaFullWidth ? 'admin-body--kasa' : '' }}">

<div class="admin-shell flex h-screen w-full overflow-hidden">

    @unless($kasaFullWidth)

    @include('admin.partials.sidebar')

    @endunless



    <div class="admin-frame flex min-w-0 flex-1 flex-col overflow-hidden">

        @unless($hideAdminChrome)

        <header class="admin-chrome-header flex h-16 shrink-0 items-center justify-between border-b border-zinc-950 bg-[#111111] px-6">

            <div>

                <h2 class="text-base font-bold text-zinc-200">@yield('page_heading', 'Yönetim Paneli')</h2>

                <p class="text-[10px] font-medium uppercase tracking-wider text-zinc-400">@yield('section_label', 'Yönetim')</p>

            </div>

            <div class="flex items-center gap-3">

                <span class="rounded-full border border-[#C6A046]/20 bg-[#C6A046]/10 px-3 py-1.5 text-xs font-bold uppercase tracking-wider text-[#C6A046]">{{ $adminRoleLabel }}</span>

                <time class="font-mono text-sm text-zinc-400" datetime="{{ now()->toDateString() }}">{{ now()->format('d.m.Y') }}</time>

            </div>

        </header>

        @endunless



        <main class="admin-main flex-1 overflow-x-hidden overflow-y-auto p-6 md:p-8 {{ $kasaFullWidth ? '!p-0' : 'space-y-6' }}">

            @if(session('success'))

                <span

                    data-admin-flash

                    data-admin-flash-type="success"

                    data-admin-flash-title="Tamam"

                    data-admin-flash-message="{{ session('success') }}"

                    hidden

                ></span>

            @endif

            @if(session('error'))

                <span

                    data-admin-flash

                    data-admin-flash-type="error"

                    data-admin-flash-title="Hata"

                    data-admin-flash-message="{{ session('error') }}"

                    hidden

                ></span>

            @endif



            @yield('content')

        </main>

    </div>

</div>

@include('admin.partials.manual-order-modal')

@include('admin.partials.admin-drawer')

@include('admin.partials.admin-toast-host')

@stack('scripts')

<script>

if ('serviceWorker' in navigator) {

    window.addEventListener('load', function () {

        navigator.serviceWorker.register('{{ asset('staff-sw.js') }}?v=7', { scope: '/admin/' })

            .catch(function () {});

    });

}

</script>

</body>

</html>


