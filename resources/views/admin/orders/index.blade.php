@extends('layouts.admin')

@section('title', 'Siparişler')

@section('page_heading', 'Siparişler')

@section('content')

<div class="mb-6 flex flex-wrap items-center justify-between gap-4">

    <a href="{{ route('admin.live-orders.index') }}" class="btn btn-primary">⚡ Canlı Siparişler</a>

</div>

<div id="ordersCard" class="admin-card overflow-x-auto">

    <form id="ordersFilterForm" method="GET" action="{{ route('admin.orders.index') }}" class="mb-4 flex flex-wrap gap-3">

        <select name="status" class="form-input orders-filter-select max-w-[200px]">

            <option value="">Tüm Durumlar</option>

            @foreach(['pending' => 'Beklemede', 'preparing' => 'Hazırlanıyor', 'ready' => 'Masada', 'delivered' => 'Tamamlandı', 'cancelled' => 'İptal'] as $s => $label)

            <option value="{{ $s }}" {{ request('status')==$s?'selected':'' }}>{{ $label }}</option>

            @endforeach

        </select>

        <input type="date" name="date" value="{{ request('date') }}" class="form-input max-w-[180px]">

    </form>

    <div id="ordersTableWrap">

    <table class="admin-table w-full">

        <thead><tr><th>No</th><th>Kaynak</th><th>Masa</th>@if($staffIsAdmin ?? true)<th>Tutar</th>@endif<th>Durum</th><th>Tarih</th><th></th></tr></thead>

        <tbody>

        @foreach($orders as $order)

        <tr class="{{ $order->isWaiterOrder() ? 'order-row--waiter' : '' }}">

            <td class="font-medium text-zinc-200">#{{ $order->order_number }}</td>

            <td>@include('admin.partials.waiter-order-badge', ['order' => $order])</td>

            <td class="text-zinc-300">{{ $order->table?->number ?? '—' }}</td>

            @if($staffIsAdmin ?? true)

            <td class="font-semibold text-[#C6A046]">{{ number_format($order->total, 0) }} ₺</td>

            @endif

            <td><span class="badge-status badge-{{ $order->status }}">{{ $order->status_label }}</span></td>

            <td class="text-zinc-200">{{ $order->created_at->format('d.m.Y H:i') }}</td>

            <td><a href="{{ route('admin.orders.show', $order) }}" class="btn btn-sm btn-secondary">Detay</a></td>

        </tr>

        @endforeach

        </tbody>

    </table>

    <div class="mt-4">{{ $orders->links() }}</div>

    </div>

</div>

@endsection



@push('scripts')

<script>

(() => {

    const form = document.getElementById('ordersFilterForm');

    const tableWrap = document.getElementById('ordersTableWrap');

    const card = document.getElementById('ordersCard');

    if (!form || !tableWrap || !card) return;



    let activeController = null;



    const setLoading = (loading) => {

        card.classList.toggle('opacity-70', loading);

        card.classList.toggle('pointer-events-none', loading);

    };



    const renderFromHtml = (html, nextUrl) => {

        const doc = new DOMParser().parseFromString(html, 'text/html');

        const nextWrap = doc.getElementById('ordersTableWrap');

        if (!nextWrap) return;

        tableWrap.innerHTML = nextWrap.innerHTML;

        if (nextUrl) {

            window.history.replaceState({}, '', nextUrl);

        }

    };



    const fetchAndRender = async (url) => {

        if (activeController) activeController.abort();

        activeController = new AbortController();

        setLoading(true);

        try {

            const res = await fetch(url, {

                headers: {

                    'X-Requested-With': 'XMLHttpRequest',

                },

                signal: activeController.signal,

            });

            if (!res.ok) throw new Error('Sipariş listesi alınamadı');

            const html = await res.text();

            renderFromHtml(html, url);

        } catch (err) {

            if (err.name !== 'AbortError') {

                window.location.href = url;

            }

        } finally {

            setLoading(false);

        }

    };



    form.addEventListener('change', () => {

        const params = new URLSearchParams(new FormData(form));

        const url = `${form.action}?${params.toString()}`;

        fetchAndRender(url);

    });



    tableWrap.addEventListener('click', (event) => {

        const link = event.target.closest('.pagination a');

        if (!link) return;

        event.preventDefault();

        fetchAndRender(link.href);

    });

})();

</script>

@endpush

