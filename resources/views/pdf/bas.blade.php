<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>BAS Report {{ $selectedMonth }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #222;
            margin: 24px;
        }

        h1 {
            font-size: 22px;
            margin: 0 0 6px;
        }

        .muted {
            color: #666;
            margin-bottom: 18px;
        }

        .summary {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }

        .summary th,
        .summary td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        .summary th {
            background: #f5f5f5;
        }

        .section-title {
            font-size: 14px;
            font-weight: bold;
            margin: 16px 0 8px;
        }

        table.list {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        table.list th,
        table.list td {
            border: 1px solid #ddd;
            padding: 6px;
            font-size: 11px;
            text-align: left;
        }

        table.list th {
            background: #f7f7f7;
        }

        .right {
            text-align: right;
        }
    </style>
</head>

<body>
    <h1>BAS Report</h1>
    <div class="muted">Period: {{ $periodStart->format('M j, Y') }} - {{ $periodEnd->format('M j, Y') }}</div>

    <table class="summary">
        <thead>
            <tr>
                <th>Metric</th>
                <th class="right">Amount (AUD)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Sales incl GST</td>
                <td class="right">{{ money((float) $summary['payments_inc']) }}</td>
            </tr>
            <tr>
                <td>Sales Ex GST</td>
                <td class="right">{{ money((float) $summary['payments_ex']) }}</td>
            </tr>
            <tr>
                <td>GST on Sales</td>
                <td class="right">{{ money((float) $summary['payments_gst']) }}</td>
            </tr>
            <tr>
                <td>Expenses incl GST</td>
                <td class="right">{{ money((float) $summary['expenses_inc']) }}</td>
            </tr>
            <tr>
                <td>Expenses Ex GST</td>
                <td class="right">{{ money((float) $summary['expenses_ex']) }}</td>
            </tr>
            <tr>
                <td>GST on Expenses</td>
                <td class="right">{{ money((float) $summary['expenses_gst']) }}</td>
            </tr>
            <tr>
                <td><strong>Net GST</strong></td>
                <td class="right"><strong>{{ money((float) $summary['net_gst']) }}</strong></td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">Processed Payments</div>
    <table class="list">
        <thead>
            <tr>
                <th>Date</th>
                <th>Customer</th>
                <th>Summary</th>
                <th class="right">Amount Ex GST</th>
                <th class="right">GST</th>
                <th class="right">Total incl GST</th>
            </tr>
        </thead>
        <tbody>
            @forelse($customerPayments as $payment)
                <tr>
                    <td>{{ $payment->received_on?->format('Y-m-d H:i') ?? '-' }}</td>
                    <td>{{ $payment->user?->getName() ?? '-' }}</td>
                    <td>{{ $payment->bas_summary ?? '-' }}</td>
                    <td class="right">{{ money((float) ($payment->bas_ex_amount ?? (($payment->bas_total_amount ?? $payment->total_amount) - ($payment->bas_gst_amount ?? $payment->gst_amount)))) }}</td>
                    <td class="right">{{ money((float) ($payment->bas_gst_amount ?? $payment->gst_amount)) }}</td>
                    <td class="right">{{ money((float) ($payment->bas_total_amount ?? $payment->total_amount)) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">No processed payments in this period.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">Expenses</div>
    <table class="list">
        <thead>
            <tr>
                <th>Date</th>
                <th>Supplier</th>
                <th>Invoice ID</th>
                <th>Description</th>
                <th class="right">Amount Ex GST</th>
                <th class="right">GST</th>
                <th class="right">Total incl GST</th>
            </tr>
        </thead>
        <tbody>
            @forelse($expenses as $expense)
                @php
                    $expenseTotal = round((float) $expense->total_amount, 2);
                    $expenseGst = round((float) $expense->gst_amount, 2);
                    $expenseEx = round($expenseTotal - $expenseGst, 2);
                @endphp
                <tr>
                    <td>{{ $expense->paid_on?->format('Y-m-d') ?? '-' }}</td>
                    <td>{{ $expense->supplier ?: '-' }}</td>
                    <td>{{ $expense->invoice_id ?: '-' }}</td>
                    <td>{{ $expense->description ?: '-' }}</td>
                    <td class="right">{{ money($expenseEx) }}</td>
                    <td class="right">{{ money($expenseGst) }}</td>
                    <td class="right">{{ money($expenseTotal) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">No expenses in this period.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>

</html>
