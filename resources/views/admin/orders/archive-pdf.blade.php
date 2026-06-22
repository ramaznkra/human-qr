<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #1f2937;
            margin: 0;
            padding: 18px 22px;
        }
        .header {
            border-bottom: 2px solid #E67E22;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }
        .header h1 {
            font-size: 16px;
            margin: 0 0 4px;
            color: #262220;
        }
        .header p {
            margin: 0;
            color: #6b7280;
            font-size: 9px;
        }
        .summary {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        .summary td {
            width: 25%;
            padding: 8px 10px;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            vertical-align: top;
        }
        .summary .label {
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #9ca3af;
            margin-bottom: 3px;
        }
        .summary .value {
            font-size: 12px;
            font-weight: bold;
            color: #E67E22;
        }
        .summary .value--dark {
            color: #111827;
            font-size: 11px;
        }
        .section-title {
            font-size: 11px;
            font-weight: bold;
            margin: 0 0 8px;
            color: #374151;
        }
        table.detail {
            width: 100%;
            border-collapse: collapse;
        }
        table.detail th {
            background: #262220;
            color: #f9fafb;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 6px 5px;
            text-align: left;
        }
        table.detail td {
            border-bottom: 1px solid #e5e7eb;
            padding: 5px;
            font-size: 9px;
        }
        table.detail tr:nth-child(even) td {
            background: #f9fafb;
        }
        .amount {
            color: #E67E22;
            font-weight: bold;
        }
        .footer {
            margin-top: 12px;
            font-size: 8px;
            color: #9ca3af;
            text-align: right;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 8px;
            font-size: 8px;
            background: #f3f4f6;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $venueName }} — {{ $title }}</h1>
        <p>Dönem: {{ $periodLabel }} · Oluşturulma: {{ $generatedAt }} · {{ $orders->count() }} kayıt</p>
    </div>

    <table class="summary">
        <tr>
            <td>
                <div class="label">Net ciro (ödenen)</div>
                <div class="value">{{ $summary['net_revenue_formatted'] }}</div>
            </td>
            <td>
                <div class="label">Nakit ciro</div>
                <div class="value value--dark">{{ $summary['cash_revenue_formatted'] }}</div>
            </td>
            <td>
                <div class="label">Kart ciro</div>
                <div class="value value--dark">{{ $summary['card_revenue_formatted'] }}</div>
            </td>
            <td>
                <div class="label">Ödenen / İptal / Toplam</div>
                <div class="value value--dark">
                    {{ $summary['paid_orders'] }} / {{ $summary['cancelled_orders'] }} / {{ $summary['total_records'] }}
                </div>
            </td>
        </tr>
    </table>

    <p class="section-title">Adisyon listesi</p>
    <table class="detail">
        <thead>
            <tr>
                <th>Adisyon</th>
                <th>Kaynak</th>
                <th>Masa</th>
                <th>Tutar</th>
                <th>Ödeme</th>
                <th>Durum</th>
                <th>Tarih</th>
            </tr>
        </thead>
        <tbody>
        @forelse($orders as $order)
            <tr>
                <td><strong>#{{ $order->order_number }}</strong></td>
                <td>{{ $order->source_label }}</td>
                <td>{{ $order->table?->number ?? '—' }}</td>
                <td class="amount">{{ number_format($order->total, 0, ',', '.') }} ₺</td>
                <td>{{ $order->payment_method_label ?? '—' }}</td>
                <td><span class="badge">{{ $order->status_label }}</span></td>
                <td>{{ $order->created_at->format('d.m.Y H:i') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="7" style="text-align:center;padding:16px;color:#9ca3af;">Kayıt bulunamadı.</td>
            </tr>
        @endforelse
        </tbody>
    </table>

    <p class="footer">Human QR Menü · Arşiv raporu</p>
</body>
</html>
