<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Expense Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; }
        table { width: 100%; border-collapse: collapse; margin-top: 18px; }
        th, td { border-bottom: 1px solid #cbd5e1; padding: 10px; text-align: left; }
        th { background: #f1f5f9; }
        .total { font-weight: 700; }
    </style>
</head>
<body>
    <h1>Expense Report</h1>
    <table>
        <thead><tr><th>Category</th><th>Total</th></tr></thead>
        <tbody>
            @foreach ($report['categories'] as $row)
                <tr><td>{{ $row['category'] }}</td><td>{{ number_format($row['total'], 2) }}</td></tr>
            @endforeach
            <tr class="total"><td>Grand Total</td><td>{{ number_format($report['grand_total'], 2) }}</td></tr>
        </tbody>
    </table>
</body>
</html>
