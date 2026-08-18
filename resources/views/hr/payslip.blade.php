<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payslip {{ $run?->reference ?? $item->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 20px; margin-bottom: 4px; }
        .meta { margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f5f5f5; }
        .totals { margin-top: 16px; width: 45%; margin-left: auto; }
        .totals td { border: none; padding: 4px 0; }
        .totals tr.total td { font-weight: bold; border-top: 1px solid #111; padding-top: 8px; }
        .muted { color: #555; }
    </style>
</head>
<body>
    <h1>Payslip</h1>
    <div class="meta">
        <div>Reference: {{ $run?->reference ?? '—' }}</div>
        <div>Period: {{ optional($run?->period_start)->toDateString() }} to {{ optional($run?->period_end)->toDateString() }}</div>
        <div>Employee: {{ trim(($employee?->user?->first_name ?? '').' '.($employee?->user?->last_name ?? '')) ?: '—' }}</div>
        <div>Employee number: {{ $employee?->employee_number ?? '—' }}</div>
        <div>TIN: {{ $employee?->tax_id ?? '—' }}</div>
        <div class="muted">Bank: {{ $item->bank_name ?? '—' }} {{ $item->account_number ?? '' }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Type</th>
                <th>Code</th>
                <th>Description</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $line)
                <tr>
                    <td>{{ $line->type instanceof \App\Enums\Tenant\HR\PayrollLineType ? $line->type->value : $line->type }}</td>
                    <td>{{ $line->code }}</td>
                    <td>{{ $line->label }}</td>
                    <td>{{ $run?->currency ?? '' }} {{ number_format((float) $line->amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Scheduled days</td><td>{{ $item->scheduled_days }}</td></tr>
        <tr><td>Worked days</td><td>{{ $item->working_days }}</td></tr>
        <tr><td>Gross</td><td>{{ $run?->currency ?? '' }} {{ number_format((float) $item->gross_pay, 2) }}</td></tr>
        <tr><td>Deductions</td><td>{{ $run?->currency ?? '' }} {{ number_format((float) $item->deduction_total, 2) }}</td></tr>
        <tr class="total"><td>Net pay</td><td>{{ $run?->currency ?? '' }} {{ number_format((float) $item->net_pay, 2) }}</td></tr>
    </table>
</body>
</html>
