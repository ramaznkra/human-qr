@extends('layouts.menu')

@section('title', __('menu.order_progress_title'))

@section('content')
@php
    use App\Support\MenuLocale;

    $progressStep = $order->customerStatusStep();
    $progressPercent = $progressStep > 0 ? ($progressStep / 3) * 100 : 0;
    $menuBackUrl = MenuLocale::menuUrl($order->table, $locale ?? app()->getLocale());
    $progressSteps = [
        __('menu.step_received'),
        __('menu.step_preparing'),
        __('menu.step_enjoy'),
    ];
@endphp

<div class="menu-page">
<div
    id="orderProgressSticky"
    class="order-progress-sticky border-b border-zinc-900 bg-[#0A0A0A]/95 backdrop-blur-md"
    data-initial-step="{{ $progressStep }}"
>
    <div class="menu-shell px-4 py-3">
        <div class="flex items-center justify-between gap-3">
            <p class="text-xs font-bold uppercase tracking-wider text-zinc-500">{{ __('menu.order_progress_title') }}</p>
            <p class="text-sm font-black text-[#C6A046]" id="progressOrderNo">#{{ $order->order_number }}</p>
        </div>
        <p class="mt-1 text-base font-bold text-zinc-100" id="progressStepLabel">{{ $order->customer_status_label }}</p>
        <div class="order-progress-track mt-3 bg-zinc-900">
            <div class="order-progress-fill bg-[#C6A046]" id="progressFill" style="width: {{ $progressPercent }}%"></div>
        </div>
        <div class="mt-2 grid grid-cols-3 gap-1">
            @foreach($progressSteps as $i => $stepLabel)
            <span
                class="order-progress-step text-center {{ $progressStep > $i + 1 ? 'is-done' : ($progressStep === $i + 1 ? 'is-active' : '') }}"
                data-step="{{ $i + 1 }}"
            >{{ $stepLabel }}</span>
            @endforeach
        </div>
    </div>
</div>

<div
    id="order-status-root"
    class="menu-shell px-4 pb-8 pt-36 text-center"
    data-order-id="{{ $order->id }}"
    data-initial-status="{{ $order->status }}"
    data-initial-step="{{ $progressStep }}"
    data-initial-paid="{{ $order->payment_method ? '1' : '0' }}"
    data-poll-url="{{ route('order.status.api', $order->public_token) }}?lang={{ $locale ?? app()->getLocale() }}"
    data-poll-interval="10000"
    data-menu-url="{{ $menuBackUrl }}"
    data-redirect-delay="5000"
    data-step-labels="{{ json_encode($progressSteps) }}"
    data-i18n-afiyet-title="{{ __('menu.afiyet_title') }}"
    data-i18n-afiyet-hint="{{ __('menu.afiyet_hint') }}"
    data-i18n-payment-closed="{{ __('menu.payment_closed', ['method' => ':method']) }}"
    data-i18n-poll-hint="{{ __('menu.poll_hint') }}"
    data-i18n-poll-enjoy="{{ __('menu.poll_hint_enjoy') }}"
    data-i18n-poll-closed="{{ __('menu.poll_hint_closed') }}"
    data-i18n-poll-coming="{{ __('menu.poll_hint_coming') }}"
    data-i18n-redirect="{{ __('menu.redirect_menu', ['seconds' => ':seconds']) }}"
>
    <div class="mb-4 flex justify-center">
        @include('menu.partials.lang-switcher', ['table' => $order->table, 'locale' => $locale ?? app()->getLocale()])
    </div>

    <div id="pollBanner" class="mx-auto mb-4 hidden max-w-md rounded-xl border px-4 py-2 text-xs transition-all duration-500 ease-in-out" role="status" aria-live="polite"></div>

    <div id="statusIcon" class="status-icon mb-4 text-5xl transition-all duration-500 ease-in-out">⏳</div>

    <span
        class="status-label status-{{ $order->status }} status-label-pulse inline-block rounded-full border border-[#C6A046]/30 bg-[#C6A046]/10 px-5 py-2 text-sm font-bold tracking-wide text-[#C6A046] transition-all duration-500 ease-in-out"
        id="statusLabel"
    >{{ $order->customer_status_label }}</span>

    <p id="statusMessage" class="mx-auto mt-4 max-w-sm text-sm leading-relaxed text-zinc-400">{{ $order->customer_status_message }}</p>

    <div
        id="statusAfiyetBlock"
        class="mx-auto mt-6 max-w-sm rounded-2xl border border-[#C6A046]/30 bg-[#C6A046]/10 px-5 py-4 {{ $order->status === 'delivered' && !$order->payment_method ? '' : 'hidden' }}"
    >
        <p class="text-lg font-bold text-[#C6A046]">{{ __('menu.afiyet_title') }}</p>
        <p class="mt-1 text-xs text-zinc-400">{{ __('menu.afiyet_hint') }}</p>
    </div>

    <p id="statusPaymentNote" class="mx-auto mt-3 max-w-sm text-xs text-zinc-500 {{ $order->payment_method ? '' : 'hidden' }}">
        @if($order->payment_method)
        {{ __('menu.payment_closed', ['method' => $order->payment_method_label]) }}
        @endif
    </p>

    <div class="mt-8 rounded-3xl border border-zinc-900 bg-[#111111] p-5 text-left">
        @foreach($order->items as $item)
        <div class="flex justify-between border-b border-zinc-800 py-3 text-sm last:border-0">
            <span class="font-medium text-zinc-200">{{ $item->product_name }} ×{{ $item->quantity }}</span>
            <span class="font-bold text-[#C6A046]">{{ number_format($item->subtotal, 2, ',', '.') }} {{ $settings['currency'] ?? '₺' }}</span>
        </div>
        @endforeach
        <div class="mt-2 flex justify-between border-t border-zinc-800 pt-4 font-black text-zinc-100">
            <span>{{ __('menu.total') }}</span>
            <span class="text-[#C6A046]">{{ number_format($order->total, 2, ',', '.') }} {{ $settings['currency'] ?? '₺' }}</span>
        </div>
    </div>

    <p id="pollHint" class="mt-6 text-sm text-zinc-500">{{ __('menu.poll_hint') }}</p>
    <a href="{{ $menuBackUrl }}" class="mt-4 inline-block text-sm font-bold text-[#C6A046] transition hover:underline">{{ __('menu.back_menu') }}</a>
</div>
</div>
@endsection

@push('scripts')
@vite('resources/js/pages/order-status.js')
@endpush
