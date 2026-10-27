<!DOCTYPE html>
@inject('businessTime', 'App\Services\BusinessTime')
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; color: #2d2a26; font-size: 12px; }
        .header { border-bottom: 2px solid #b3541e; padding-bottom: 12px; margin-bottom: 20px; }
        .brand { font-size: 22px; font-weight: 700; }
        .brand span { color: #b3541e; }
        .muted { color: #8a8178; font-size: 11px; }
        h2 { font-size: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th { text-align: left; border-bottom: 1px solid #efe6dc; padding: 8px 4px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #8a8178; }
        td { padding: 8px 4px; border-bottom: 1px solid #f3eee8; }
        .text-right { text-align: right; }
        .notes { margin-top: 14px; }
        .total-row td { font-weight: 700; font-size: 14px; border-bottom: none; padding-top: 14px; }
        .meta { margin-top: 16px; }
        .meta div { margin-top: 4px; }
        .footer { margin-top: 36px; text-align: center; color: #a69c92; font-size: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <p class="brand">Le <span>Miting</span></p>
        <p class="muted">46 Jalan Ampang, 50450 Kuala Lumpur · +60 3-2141 9000</p>
    </div>

    <h2>Receipt</h2>

    <div class="meta">
        <div><strong>Receipt number:</strong> {{ $order->order_number }}</div>
        <div><strong>Date:</strong> {{ $businessTime->local($order->created_at)->format('d M Y, g:i A') }} MYT</div>
        <div><strong>Order type:</strong> {{ ucfirst($order->order_type) }}</div>
        <div><strong>Customer:</strong> {{ $order->customer_name }} ({{ $order->customer_email }})</div>
<div><strong>Payment:</strong> Verified — reference {{ $order->payment->provider_reference }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Item</th>
<th class="text-right">Qty</th>
<th class="text-right">Unit price</th>
<th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->orderItems as $item)
                <tr>
                    <td>{{ $item->item_name }}</td>
<td class="text-right">{{ $item->quantity }}</td>
<td class="text-right">RM {{ number_format($item->unit_price, 2) }}</td>
<td class="text-right">RM {{ number_format($item->unit_price * $item->quantity, 2) }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
<td colspan="3" class="text-right">Total paid</td>
<td class="text-right">RM {{ number_format($order->total, 2) }}</td>
            </tr>
        </tbody>
    </table>

    @if ($order->notes)
<p class="muted notes"><strong>Notes:</strong> {{ $order->notes }}</p>
    @endif

    <div class="footer">Thank you for dining with Le Miting.</div>
</body>
</html>
