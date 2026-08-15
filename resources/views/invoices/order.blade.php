<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 20px; margin-bottom: 4px; }
        .meta { margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f5f5f5; }
        .totals { margin-top: 16px; width: 40%; margin-left: auto; }
        .totals td { border: none; padding: 4px 0; }
        .totals tr.total td { font-weight: bold; border-top: 1px solid #111; padding-top: 8px; }
    </style>
</head>
<body>
    <h1>Invoice {{ $invoice->invoice_number }}</h1>
    <div class="meta">
        <div>Order: {{ $order?->order_number ?? '—' }}</div>
        <div>Issued: {{ optional($invoice->issued_at)->format('Y-m-d H:i') }}</div>
        <div>Customer: {{ $customer?->full_name ?? '—' }}</div>
        <div>Email: {{ $customer?->email ?? '—' }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th>Qty</th>
                <th>Unit Price</th>
                <th>Tax</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ $invoice->currency }} {{ number_format((float) $item->unit_price, 2) }}</td>
                    <td>{{ $invoice->currency }} {{ number_format((float) $item->tax_amount, 2) }}</td>
                    <td>{{ $invoice->currency }} {{ number_format((float) $item->total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td>{{ $invoice->currency }} {{ number_format((float) $invoice->subtotal, 2) }}</td></tr>
        <tr><td>Discount</td><td>{{ $invoice->currency }} {{ number_format((float) $invoice->discount_total, 2) }}</td></tr>
        <tr><td>Tax</td><td>{{ $invoice->currency }} {{ number_format((float) $invoice->tax_total, 2) }}</td></tr>
        <tr><td>Shipping</td><td>{{ $invoice->currency }} {{ number_format((float) $invoice->shipping_total, 2) }}</td></tr>
        <tr class="total"><td>Grand Total</td><td>{{ $invoice->currency }} {{ number_format((float) $invoice->grand_total, 2) }}</td></tr>
    </table>
</body>
</html>
