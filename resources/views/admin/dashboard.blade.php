@extends('layouts.admin')



@section('title', 'Panel')

@section('page_heading', 'Kontrol Paneli')



@section('content')

<div class="dash">

    <header class="dash__hero">

        <div class="dash__hero-text">

            <p class="dash__eyebrow">{{ now()->translatedFormat('d F Y') }}</p>

            <h1 class="dash__title">Hoş geldiniz{{ session('admin_name') ? ', ' . session('admin_name') : '' }}</h1>

            <p class="dash__subtitle">Bugünkü operasyon özeti</p>

        </div>

        <a href="{{ route('admin.live-orders.index') }}" class="dash__live-btn">

            <span class="dash__live-dot" aria-hidden="true"></span>

            Canlı Siparişler

        </a>

    </header>



    @if($lowStockProducts->isNotEmpty())

    <section class="dash-stock-alert" role="alert">

        <div class="dash-stock-alert__head">

            <span class="dash-stock-alert__icon" aria-hidden="true">⚠</span>

            <div>

                <h2 class="dash-stock-alert__title">Stok Uyarıları</h2>

                <p class="dash-stock-alert__meta">{{ $lowStockProducts->count() }} ürün tükendi veya stok dışı</p>

            </div>

            <a href="{{ route('admin.products.index') }}" class="dash-panel__link">Ürünlere git →</a>

        </div>

        <ul class="dash-stock-alert__list">

            @foreach($lowStockProducts as $product)

            <li class="dash-stock-alert__item">

                <a href="{{ route('admin.products.edit', $product) }}" class="dash-stock-alert__name">{{ $product->name }}</a>

                <span class="dash-stock-alert__badge">Tükendi</span>

            </li>

            @endforeach

        </ul>

    </section>

    @endif



    <div class="dash__kpi-grid">

        <article class="dash-kpi dash-kpi--featured">

            <p class="dash-kpi__label">Bugün Ciro</p>

            <p class="dash-kpi__value">{{ $finance['daily_revenue_formatted'] }}</p>

            <p class="dash-kpi__hint">Bugün teslim edilen</p>

        </article>

        <article class="dash-kpi">

            <p class="dash-kpi__label">Aktif Masa</p>

            <p class="dash-kpi__value">{{ $finance['active_tables'] }}</p>

            <p class="dash-kpi__hint">Canlı sipariş / çağrı</p>

        </article>

        <article class="dash-kpi">

            <p class="dash-kpi__label">Tamamlanan</p>

            <p class="dash-kpi__value">{{ $finance['completed_orders'] }}</p>

            <p class="dash-kpi__hint">Kapanan adisyon</p>

        </article>

        <article class="dash-kpi">

            <p class="dash-kpi__label">Bekleyen</p>

            <p class="dash-kpi__value">{{ $stats['pending_orders'] }}</p>

            <p class="dash-kpi__hint">Onay / mutfak kuyruğu</p>

        </article>

    </div>



    <section class="dash-panel dash-panel--chart dash-panel--busy">

        <div class="dash-panel__head">

            <h2 class="dash-panel__title">Bugün Ne Kadar Yoğunduk?</h2>

            <span class="dash-panel__meta">Saatlik sipariş yoğunluğu</span>

        </div>

        <div class="dash-busy-stats">

            <div class="dash-busy-stat">

                <p class="dash-busy-stat__label">En yoğun saat</p>

                <p class="dash-busy-stat__value">{{ $busyPeakHour }}</p>

                <p class="dash-busy-stat__hint">{{ $busyPeakCount }} sipariş</p>

            </div>

            <div class="dash-busy-stat">

                <p class="dash-busy-stat__label">Toplam sipariş</p>

                <p class="dash-busy-stat__value">{{ $busyTotalOrders }}</p>

                <p class="dash-busy-stat__hint">08:00 – 23:00 arası</p>

            </div>

        </div>

        <div class="dash-line-chart" id="dashBusyChart" data-trend='@json($busyTrend)' aria-label="Saatlik yoğunluk grafiği">

            <svg class="dash-line-chart__svg" viewBox="0 0 100 40" preserveAspectRatio="none" role="img" aria-hidden="true">

                <polyline class="dash-line-chart__line" points=""></polyline>

                <polyline class="dash-line-chart__area" points=""></polyline>

            </svg>

            <div class="dash-line-chart__labels"></div>

        </div>

    </section>



    <div class="dash__grid dash__grid--wide">

        <section class="dash-panel dash-panel--chart">

            <div class="dash-panel__head">

                <h2 class="dash-panel__title">Günlük Satış Trendi</h2>

                <span class="dash-panel__meta">Bugün · saatlik ciro</span>

            </div>

            <div class="dash-chart" id="dashSalesChart" data-trend='@json($salesTrend)' aria-label="Saatlik satış grafiği">

                <div class="dash-chart__bars"></div>

                <div class="dash-chart__labels"></div>

            </div>

        </section>



        <section class="dash-panel">

            <div class="dash-panel__head">

                <h2 class="dash-panel__title">Best Selling Items</h2>

                <span class="dash-panel__meta">Bugün · ilk 5</span>

            </div>

            @if($topProducts->isEmpty())

            <p class="dash-empty">Bugün henüz tamamlanan sipariş yok.</p>

            @else

            <div class="overflow-x-auto">

                <table class="dash-table w-full">

                    <thead>

                        <tr>

                            <th>#</th>

                            <th>Ürün</th>

                            <th class="text-center">Adet</th>

                            <th class="text-right">Ciro</th>

                        </tr>

                    </thead>

                    <tbody>

                        @foreach($topProducts as $i => $row)

                        <tr>

                            <td class="dash-table__rank">{{ $i + 1 }}</td>

                            <td class="font-medium text-zinc-200">{{ $row->product?->name ?? 'Ürün #'.$row->product_id }}</td>

                            <td class="text-center font-mono text-zinc-300">{{ (int) $row->total_qty }}</td>

                            <td class="text-right font-bold text-[#C6A046]">{{ number_format((float) $row->total_revenue, 0, ',', '.') }} ₺</td>

                        </tr>

                        @endforeach

                    </tbody>

                </table>

            </div>

            @endif

        </section>

    </div>



    <section class="dash-panel">

        <div class="dash-panel__head">

            <h2 class="dash-panel__title">Canlı Satış Akışı</h2>

            <span class="dash-panel__meta">Bugünkü kalemler · varyasyon detaylı</span>

        </div>

        @if($recentSalesLines->isEmpty())

        <p class="dash-empty">Bugün henüz satış kalemi yok.</p>

        @else

        <div class="overflow-x-auto">

            <table class="dash-table w-full">

                <thead>

                    <tr>

                        <th>Zaman</th>

                        <th>Ürün &amp; Detaylar</th>

                        <th class="text-center">Adet</th>

                        <th class="text-right">Tutar</th>

                    </tr>

                </thead>

                <tbody>

                    @foreach($recentSalesLines as $line)

                    <tr>

                        <td class="whitespace-nowrap font-mono text-zinc-200">{{ ($line->order?->created_at ?? $line->created_at)->format('H:i') }}</td>

                        <td>

                            <span class="font-bold text-zinc-200">{{ $line->product_name }}</span>

                            @if($line->optionLabelLines() !== [])

                            <span class="mt-0.5 block text-[10px] font-medium italic text-zinc-400">{{ implode(' | ', $line->optionLabelLines()) }}</span>

                            @endif

                        </td>

                        <td class="text-center text-zinc-300">{{ $line->quantity }}</td>

                        <td class="text-right font-semibold text-[#C6A046]">{{ number_format((float) $line->subtotal, 0, ',', '.') }} ₺</td>

                    </tr>

                    @endforeach

                </tbody>

            </table>

        </div>

        @endif

    </section>



    <div class="dash__grid">

        <section class="dash-panel">

            <div class="dash-panel__head">

                <h2 class="dash-panel__title">Son Siparişler</h2>

                <a href="{{ route('admin.orders.index') }}" class="dash-panel__link">Tümü →</a>

            </div>

            @if($recentOrders->isEmpty())

            <p class="dash-empty">Henüz sipariş kaydı yok.</p>

            @else

            <ul class="dash-order-list">

                @foreach($recentOrders->take(8) as $order)

                <li class="dash-order-list__item">

                    <a href="{{ route('admin.orders.show', $order) }}" class="dash-order-list__num">#{{ $order->order_number }}</a>

                    <span class="dash-order-list__table">{{ $order->table ? 'Masa '.$order->table->number : '—' }}</span>

                    <span class="dash-order-list__status dash-order-list__status--{{ $order->status }}">{{ $order->status_label }}</span>

                    <span class="dash-order-list__total">{{ number_format($order->total, 0) }} ₺</span>

                    <time class="dash-order-list__time">{{ $order->created_at->format('H:i') }}</time>

                </li>

                @endforeach

            </ul>

            @endif

        </section>



        <div class="dash__side">

            <section class="dash-panel">

                <div class="dash-panel__head">

                    <h2 class="dash-panel__title">Envanter</h2>

                </div>

                <div class="dash-mini-grid">

                    <div class="dash-mini-stat">

                        <span class="dash-mini-stat__value">{{ $stats['categories'] }}</span>

                        <span class="dash-mini-stat__label">Kategori</span>

                    </div>

                    <div class="dash-mini-stat">

                        <span class="dash-mini-stat__value">{{ $stats['products'] }}</span>

                        <span class="dash-mini-stat__label">Ürün</span>

                    </div>

                    <div class="dash-mini-stat">

                        <span class="dash-mini-stat__value">{{ $stats['tables'] }}</span>

                        <span class="dash-mini-stat__label">Masa</span>

                    </div>

                    <div class="dash-mini-stat dash-mini-stat--alert">

                        <span class="dash-mini-stat__value">{{ $lowStockProducts->count() }}</span>

                        <span class="dash-mini-stat__label">Stok Uyarı</span>

                    </div>

                </div>

            </section>

        </div>

    </div>

</div>

@endsection



@push('scripts')

@vite('resources/js/pages/admin-dashboard.js')

@endpush

