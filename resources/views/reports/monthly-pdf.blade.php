<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Monthly Report - {{ $ledgerName }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 13px;
            color: #1a1a1a;
            line-height: 1.5;
            padding: 40px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 20px;
        }
        .header h1 {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .header h2 {
            font-size: 16px;
            font-weight: 400;
            color: #6b7280;
        }
        .header p {
            font-size: 11px;
            color: #9ca3af;
            margin-top: 4px;
        }
        .summary-box {
            display: table;
            width: 100%;
            margin-bottom: 30px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
        }
        .summary-item {
            display: table-cell;
            width: 33.33%;
            text-align: center;
            padding: 16px 12px;
            border-right: 1px solid #e5e7eb;
        }
        .summary-item:last-child {
            border-right: none;
        }
        .summary-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6b7280;
            margin-bottom: 4px;
        }
        .summary-value {
            font-size: 20px;
            font-weight: 700;
        }
        .summary-value.income {
            color: #16a34a;
        }
        .summary-value.expense {
            color: #dc2626;
        }
        .summary-value.net-positive {
            color: #16a34a;
        }
        .summary-value.net-negative {
            color: #dc2626;
        }
        .meta-line {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 24px;
            text-align: center;
        }
        .section-title {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 12px;
            padding-bottom: 6px;
            border-bottom: 1px solid #e5e7eb;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th {
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6b7280;
            padding: 8px 12px;
            border-bottom: 2px solid #e5e7eb;
        }
        th:last-child, th:nth-child(2) {
            text-align: right;
        }
        td {
            padding: 8px 12px;
            border-bottom: 1px solid #f3f4f6;
        }
        td:last-child, td:nth-child(2) {
            text-align: right;
        }
        tr:last-child td {
            border-bottom: none;
        }
        .footer {
            margin-top: 40px;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 10px;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $ledgerName }}</h1>
        <h2>Monthly Report &mdash; {{ $monthLabel }}</h2>
    </div>

    <div class="summary-box">
        <div class="summary-item">
            <div class="summary-label">Income</div>
            <div class="summary-value income">{{ number_format($incomeTotal, 2) }}</div>
        </div>
        <div class="summary-item">
            <div class="summary-label">Expenses</div>
            <div class="summary-value expense">{{ number_format($expenseTotal, 2) }}</div>
        </div>
        <div class="summary-item">
            <div class="summary-label">Net</div>
            <div class="summary-value {{ $netTotal >= 0 ? 'net-positive' : 'net-negative' }}">
                {{ $netTotal >= 0 ? '+' : '' }}{{ number_format($netTotal, 2) }}
            </div>
        </div>
    </div>

    <div class="meta-line">
        {{ $transactionCount }} transaction{{ $transactionCount !== 1 ? 's' : '' }} in this period
    </div>

    @if(count($categoryBreakdown) > 0)
        <h3 class="section-title">Category Breakdown</h3>
        <table>
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Amount</th>
                    <th>% of Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($categoryBreakdown as $category)
                    <tr>
                        <td>{{ $category['name'] }}</td>
                        <td>{{ number_format($category['total'], 2) }}</td>
                        <td>{{ $category['percentage'] }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p style="color: #6b7280; text-align: center; margin: 20px 0;">
            No categorised expenses in this period.
        </p>
    @endif

    <div class="footer">
        Generated on {{ $generatedAt }}
    </div>
</body>
</html>
