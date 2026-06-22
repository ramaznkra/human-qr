@extends('layouts.admin')
@section('title', 'Sipariş #' . $order->order_number)
@section('page_heading', 'Sipariş #' . $order->order_number)
@section('section_label', 'Adisyon')
@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div class="flex flex-wrap items-center gap-3">
        @include('admin.partials.waiter-order-badge', ['order' => $order])
    </div>
    <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('admin.orders.archive') }}" class="btn btn-secondary">← Geri</a>
</div>
<div class="admin-card max-w-2xl">
    <div class="mb-6 grid gap-2 text-sm text-zinc-400">
        <p><strong class="text-zinc-200">Masa:</strong> {{ $order->table?->number ?? 'Belirtilmedi' }}</p>
        <p><strong class="text-zinc-200">Tarih:</strong> {{ $order->created_at->format('d.m.Y H:i') }}</p>
        <p><strong class="text-zinc-200">Not:</strong> {{ $order->notes ?? '—' }}</p>
        <p><span class="badge-status badge-{{ $order->status }}">{{ $order->status_label }}</span></p>
        @if($order->payment_method_label)
        <p><strong class="text-zinc-200">Ödeme:</strong> {{ $order->payment_method_label }}</p>
        @endif
    </div>
    <table class="admin-table w-full">
        <thead>
            <tr>
                <th>Ürün</th>
                <th class="admin-table-col-center">Adet</th>
                @if($staffIsAdmin ?? true)
                <th class="text-right">Fiyat</th>
                <th class="text-right">Toplam</th>
                @endif
            </tr>
        </thead>
        <tbody>
        @foreach($order->items as $item)
        <tr>
            <td>@include('admin.partials.order-item-cell', ['item' => $item])</td>
            <td class="admin-table-col-center text-zinc-200">{{ $item->quantity }}</td>
            @if($staffIsAdmin ?? true)
            <td class="text-right text-zinc-300">{{ number_format($item->unit_price, 0) }} ₺</td>
            <td class="text-right font-semibold text-[#C6A046]">{{ number_format($item->subtotal, 0) }} ₺</td>
            @endif
        </tr>
        @endforeach
        </tbody>
    </table>
    @if($staffIsAdmin ?? true)
    <p class="mt-4 text-xl font-bold text-zinc-200">Toplam: <span class="text-[#C6A046]">{{ number_format($order->total, 0) }} ₺</span></p>
    @endif

    <form method="POST" action="{{ route('admin.orders.status', $order) }}" class="mt-6 flex flex-wrap items-center gap-3 border-t border-zinc-900 pt-6">
        @csrf @method('PATCH')
        <label class="text-sm font-medium text-zinc-300">Durum:</label>
        <select name="status" class="form-input max-w-[200px]">
            @foreach(['pending','preparing','ready','delivered','cancelled'] as $s)
            <option value="{{ $s }}" {{ $order->status==$s?'selected':'' }}>
                @switch($s)
                    @case('pending') Bekliyor @break
                    @case('preparing') Hazırlanıyor @break
                    @case('ready') Hazır @break
                    @case('delivered') Teslim Edildi @break
                    @case('cancelled') İptal @break
                @endswitch
            </option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-primary">Güncelle</button>
    </form>

    @if(($staffIsAdmin ?? true) && in_array($order->status, \App\Models\Order::archivedStatuses(), true))
    <form
        method="POST"
        action="{{ route('admin.orders.destroy', $order) }}"
        class="mt-4 border-t border-zinc-900 pt-6"
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
        <button type="submit" class="btn btn-danger">Adisyonu Sil</button>
    </form>
    @endif
</div>
@endsection
