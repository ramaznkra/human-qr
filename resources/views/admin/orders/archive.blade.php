@extends('layouts.admin')
@section('title', 'Geçmiş Adisyonlar')
@section('section_label', 'Siparişler')
@section('page_heading', 'Geçmiş Adisyonlar')

@section('content')
@php
    $exportQuery = request()->only(['q', 'status', 'table_id', 'date_from', 'date_to']);
@endphp
<div class="orders-archive">
    <div class="orders-archive__top mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <p class="orders-archive__intro">
            Ödenmiş veya iptal edilmiş adisyonlar. Filtreler sadece "Filtrele" butonuna basıldığında uygulanır.
        </p>
        <div class="orders-archive__top-actions flex shrink-0 flex-wrap gap-2">
            <a href="{{ route('admin.orders.index') }}" class="btn btn-secondary">Siparişler</a>
            <a href="{{ route('admin.live-orders.index') }}" class="btn btn-primary inline-flex items-center gap-2">
                Canlı Siparişler
                @include('admin.partials.icons.live-orders', ['class' => 'h-4 w-4 shrink-0'])
            </a>
        </div>
    </div>

    <div class="admin-card orders-archive__card">
        <form
            method="GET"
            action="{{ route('admin.orders.archive') }}"
            class="orders-archive__filters"
            data-archive-filter-form
        >
            <div class="orders-archive__filter-grid">
                <div class="orders-archive__filter-field">
                    <label class="form-label" for="archive-q">Ara</label>
                    <input
                        type="search"
                        id="archive-q"
                        name="q"
                        value="{{ request('q') }}"
                        placeholder="Adisyon no…"
                        class="form-input w-full max-w-none"
                    >
                </div>
                <div class="orders-archive__filter-field">
                    <label class="form-label" for="archive-table">Masa</label>
                    <select id="archive-table" name="table_id" class="form-input w-full max-w-none">
                        <option value="">Tüm masalar</option>
                        @foreach($tables as $table)
                            <option value="{{ $table->id }}" {{ (string) request('table_id') === (string) $table->id ? 'selected' : '' }}>
                                Masa {{ $table->number }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="orders-archive__filter-field">
                    <label class="form-label" for="archive-status">Durum</label>
                    <select id="archive-status" name="status" class="form-input w-full max-w-none">
                        <option value="">Tamamlandı + İptal</option>
                        <option value="delivered" {{ request('status') === 'delivered' ? 'selected' : '' }}>Tamamlandı</option>
                        <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>İptal</option>
                    </select>
                </div>
                <div class="orders-archive__filter-field">
                    <label class="form-label" for="archive-date-from">Başlangıç</label>
                    <input
                        type="date"
                        id="archive-date-from"
                        name="date_from"
                        value="{{ request('date_from') }}"
                        class="form-input w-full max-w-none"
                    >
                </div>
                <div class="orders-archive__filter-field">
                    <label class="form-label" for="archive-date-to">Bitiş</label>
                    <input
                        type="date"
                        id="archive-date-to"
                        name="date_to"
                        value="{{ request('date_to') }}"
                        class="form-input w-full max-w-none"
                    >
                </div>
            </div>

            <div class="orders-archive__filter-actions">
                <p class="orders-archive__filter-hint">
                    @if(request()->hasAny(['q', 'status', 'table_id', 'date_from', 'date_to']))
                        <span class="orders-archive__filter-count">{{ $filteredTotal }} kayıt</span>
                        <span class="orders-archive__summary-sep">·</span>
                        Filtre aktif
                    @else
                        Tüm arşiv kayıtları listeleniyor
                    @endif
                </p>
                <div class="orders-archive__filter-buttons flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">Filtrele</button>
                    <a href="{{ route('admin.orders.archive') }}" class="btn btn-secondary">Filtreleri Sıfırla</a>
                </div>
            </div>
        </form>

        <div class="orders-archive__summary">
            @if($staffIsAdmin ?? true)
            <div class="orders-archive__summary-item">
                <p class="orders-archive__summary-label">Net ciro (ödenen)</p>
                <p class="orders-archive__summary-value orders-archive__summary-value--accent">{{ $summary['net_revenue_formatted'] }}</p>
            </div>
            <div class="orders-archive__summary-item">
                <p class="orders-archive__summary-label">Nakit · Kart</p>
                <p class="orders-archive__summary-value text-base">
                    {{ $summary['cash_revenue_formatted'] }}
                    <span class="orders-archive__summary-sep">·</span>
                    {{ $summary['card_revenue_formatted'] }}
                </p>
            </div>
            @endif
            <div class="orders-archive__summary-item">
                <p class="orders-archive__summary-label">Ödenen / İptal</p>
                <p class="orders-archive__summary-value text-base">
                    {{ $summary['paid_orders'] }}
                    <span class="orders-archive__summary-sep">/</span>
                    {{ $summary['cancelled_orders'] }}
                </p>
            </div>
        </div>

        @if($staffIsAdmin ?? true)
        <div class="orders-archive__export-row mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="orders-archive__export-note">
                PDF indirmeleri mevcut filtreyi kullanır. Günlük dosya
                <strong>{{ $exportDayLabel }}</strong> gününü içerir.
            </p>
            <div class="orders-archive__downloads">
                <a
                    href="{{ route('admin.orders.archive.export', array_merge(['mode' => 'daily'], $exportQuery)) }}"
                    class="btn btn-secondary"
                >Günlük PDF</a>
                <a
                    href="{{ route('admin.orders.archive.export', array_merge(['mode' => 'report'], $exportQuery)) }}"
                    class="btn btn-primary"
                >Özet &amp; Liste PDF</a>
            </div>
        </div>
        @endif

        @if($staffIsAdmin ?? true)
        @if($filteredTotal > 0)
        <div class="orders-archive__purge">
            <div class="orders-archive__purge-text">
                <p>Arşivi temizle</p>
                <p>
                    Mevcut filtreye uyan <strong class="text-zinc-200">{{ $filteredTotal }}</strong> adisyon kalıcı olarak silinir. Geri alınamaz.
                </p>
            </div>
            <form
                method="POST"
                action="{{ route('admin.orders.archive.purge') }}"
                class="orders-archive__purge-form shrink-0"
                @include('admin.partials.confirm-form', [
                    'title' => 'Arşivi temizle',
                    'message' => "Mevcut filtreye uyan {$filteredTotal} adisyon kalıcı olarak silinecek.",
                    'hint' => 'Bu işlem geri alınamaz.',
                    'type' => 'danger',
                    'confirmLabel' => 'Temizle',
                ])
            >
                @csrf
                @foreach(request()->only(['q', 'status', 'table_id', 'date_from', 'date_to']) as $key => $value)
                    @if($value !== null && $value !== '')
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach
                <button type="submit" class="btn btn-danger whitespace-nowrap">
                    Temizle ({{ $filteredTotal }})
                </button>
            </form>
        </div>
        @endif
        @endif

        <div class="orders-archive__table-wrap overflow-x-auto">
            <table class="admin-table orders-archive__table w-full min-w-[720px]">
                <thead>
                    <tr>
                        <th>Adisyon</th>
                        <th>Kaynak</th>
                        <th>Masa</th>
                        @if($staffIsAdmin ?? true)
                        <th>Tutar</th>
                        @endif
                        <th>Ödeme</th>
                        <th>Durum</th>
                        <th>Tarih</th>
                        <th class="orders-archive__th-actions">İşlem</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($orders as $order)
                    <tr class="{{ $order->isWaiterOrder() ? 'order-row--waiter' : '' }}">
                        <td class="font-semibold text-zinc-100">#{{ $order->order_number }}</td>
                        <td>@include('admin.partials.waiter-order-badge', ['order' => $order])</td>
                        <td class="text-zinc-200">{{ $order->table?->number ?? '—' }}</td>
                        @if($staffIsAdmin ?? true)
                        <td class="font-semibold text-[#C6A046]">{{ number_format($order->total, 0, ',', '.') }} ₺</td>
                        @endif
                        <td>
                            @if($order->payment_method_label)
                                <span class="orders-archive__payment-badge">{{ $order->payment_method_label }}</span>
                            @else
                                <span class="text-xs text-zinc-400">—</span>
                            @endif
                        </td>
                        <td><span class="badge-status badge-{{ $order->status }}">{{ $order->status_label }}</span></td>
                        <td class="orders-archive__date">{{ $order->created_at->format('d.m.Y H:i') }}</td>
                        <td class="orders-archive__td-actions">
                            <div class="orders-archive__row-actions">
                                <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-sm btn-secondary">Detay</a>
                                @if($staffIsAdmin ?? true)
                                <form
                                    action="{{ route('admin.orders.destroy', $order) }}"
                                    method="POST"
                                    class="orders-archive__delete-form"
                                    @include('admin.partials.confirm-form', [
                                        'title' => 'Adisyonu sil',
                                        'message' => "#{$order->order_number} kalıcı olarak silinecek.",
                                        'hint' => 'Bu işlem geri alınamaz.',
                                        'type' => 'danger',
                                        'confirmLabel' => 'Sil',
                                    ])
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger">Sil</button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ ($staffIsAdmin ?? true) ? 8 : 7 }}" class="orders-archive__empty">
                            Arşivde adisyon bulunamadı.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($orders->hasPages())
        <div class="orders-archive__pagination">
            {{ $orders->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
