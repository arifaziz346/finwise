<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>FinWise report</title>
    <style>
        @page { margin: 28px 30px; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #0F172A;
            margin: 0;
        }
        .muted { color: #64748B; }
        .right { text-align: right; }
        .center { text-align: center; }

        .header { border-bottom: 2px solid #4F46E5; padding-bottom: 10px; margin-bottom: 14px; }
        .header h1 { margin: 0 0 4px; font-size: 18px; color: #312E81; }
        .header .period { font-size: 11px; font-weight: bold; }
        .brand { float: right; font-size: 16px; font-weight: bold; color: #4F46E5; }

        .kpis { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin-bottom: 14px; }
        .kpis td { width: 25%; border: 1px solid #E2E8F0; border-radius: 6px; padding: 8px 10px; vertical-align: top; }
        .kpis .label { font-size: 8px; text-transform: uppercase; letter-spacing: .5px; color: #64748B; }
        .kpis .value { font-size: 14px; font-weight: bold; margin-top: 3px; }
        .kpis .sub { font-size: 8px; color: #64748B; margin-top: 2px; }

        h2 { font-size: 12px; margin: 16px 0 6px; color: #312E81; border-left: 3px solid #4F46E5; padding-left: 6px; }

        table.data { width: 100%; border-collapse: collapse; }
        table.data th {
            background: #F1F5F9; font-size: 8px; text-transform: uppercase; letter-spacing: .4px;
            color: #475569; padding: 5px 6px; border-bottom: 1px solid #CBD5E1; text-align: left;
        }
        table.data td { padding: 4px 6px; border-bottom: 1px solid #EEF2F7; }
        table.data tr.total td { font-weight: bold; background: #F8FAFC; border-top: 1px solid #CBD5E1; }
        .pos { color: #047857; }
        .neg { color: #B91C1C; }

        .bar { background: #EEF2F7; height: 6px; border-radius: 3px; }
        .bar span { display: block; height: 6px; border-radius: 3px; background: #4F46E5; }

        .insight { border: 1px solid #E2E8F0; border-left-width: 3px; border-radius: 5px; padding: 6px 8px; margin-bottom: 5px; }
        .insight.ok { border-left-color: #059669; }
        .insight.info { border-left-color: #2563EB; }
        .insight.warn { border-left-color: #D97706; }
        .insight.danger { border-left-color: #DC2626; }
        .insight .t { font-weight: bold; font-size: 10px; }
        .insight .m { color: #475569; margin-top: 2px; }

        .cols { width: 100%; border-collapse: collapse; }
        .cols > tbody > tr > td { vertical-align: top; padding: 0 8px 0 0; width: 50%; }
        .chip { display: inline-block; width: 7px; height: 7px; border-radius: 2px; margin-right: 4px; }

        .footer { margin-top: 16px; border-top: 1px solid #E2E8F0; padding-top: 6px; font-size: 8px; color: #94A3B8; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
@php
    $money = fn ($value) => number_format((float) $value, 2);
    $summary = $report['summary'];
    $period = $report['period'];
    $previous = $report['previous'] ?? null;
    $delta = function (float $previousValue, float $current) {
        if ($previousValue <= 0.0) {
            return null;
        }
        return round((($current - $previousValue) / $previousValue) * 100, 1);
    };
    $expenseDelta = $previous ? $delta((float) $previous['expenses'], (float) $summary['expenses']) : null;
    $incomeDelta = $previous ? $delta((float) $previous['income'], (float) $summary['income']) : null;
@endphp

<div class="header">
    <span class="brand">FinWise</span>
    <h1>Financial report</h1>
    <div class="period">{{ $period['from'] }} &nbsp;→&nbsp; {{ $period['to'] }}</div>
    <div class="muted">
        Grouped {{ $period['granularity'] }} &middot; {{ $period['days'] }} day(s) &middot;
        Prepared for {{ $user->name }} &middot; Generated {{ $generatedAt }}
    </div>
</div>

<table class="kpis">
    <tr>
        <td>
            <div class="label">Income</div>
            <div class="value pos">{{ $money($summary['income']) }}</div>
            <div class="sub">
                {{ $summary['income_count'] }} entries
                @if ($incomeDelta !== null) &middot; {{ $incomeDelta > 0 ? '+' : '' }}{{ $incomeDelta }}% vs previous @endif
            </div>
        </td>
        <td>
            <div class="label">Expenses</div>
            <div class="value neg">{{ $money($summary['expenses']) }}</div>
            <div class="sub">
                {{ $summary['expense_count'] }} entries
                @if ($expenseDelta !== null) &middot; {{ $expenseDelta > 0 ? '+' : '' }}{{ $expenseDelta }}% vs previous @endif
            </div>
        </td>
        <td>
            <div class="label">Net saved</div>
            <div class="value {{ $summary['net'] >= 0 ? 'pos' : 'neg' }}">{{ $money($summary['net']) }}</div>
            <div class="sub">
                Savings rate {{ $summary['savings_rate'] === null ? '—' : $summary['savings_rate'] . '%' }}
            </div>
        </td>
        <td>
            <div class="label">Daily average spend</div>
            <div class="value">{{ $money($summary['avg_daily_expense']) }}</div>
            <div class="sub">Budget use {{ $summary['budget_used_percent'] === null ? 'no budgets set' : $summary['budget_used_percent'] . '%' }}</div>
        </td>
    </tr>
</table>

@if (! empty($report['insights']))
    <h2>What the numbers say</h2>
    @foreach ($report['insights'] as $insight)
        <div class="insight {{ $insight['severity'] }}">
            <div class="t">{{ $insight['title'] }}</div>
            <div class="m">{{ $insight['message'] }}</div>
        </div>
    @endforeach
@endif

<h2>{{ $period['bucket_label'] }}-by-{{ $period['bucket_label'] }} breakdown</h2>
<table class="data">
    <thead>
        <tr>
            <th>{{ $period['bucket_label'] }}</th>
            <th class="right">Income</th>
            <th class="right">Expenses</th>
            <th class="right">Net</th>
            <th class="right">Running net</th>
            <th class="right">Saved %</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($report['series'] as $row)
            <tr>
                <td>{{ $row['full_label'] }}</td>
                <td class="right pos">{{ $money($row['income']) }}</td>
                <td class="right neg">{{ $money($row['expenses']) }}</td>
                <td class="right {{ $row['net'] >= 0 ? 'pos' : 'neg' }}">{{ $money($row['net']) }}</td>
                <td class="right">{{ $money($row['running_net']) }}</td>
                <td class="right">{{ $row['savings_rate'] === null ? '—' : $row['savings_rate'] . '%' }}</td>
            </tr>
        @endforeach
        <tr class="total">
            <td>Total</td>
            <td class="right">{{ $money($summary['income']) }}</td>
            <td class="right">{{ $money($summary['expenses']) }}</td>
            <td class="right">{{ $money($summary['net']) }}</td>
            <td class="right">{{ $money($summary['net']) }}</td>
            <td class="right">{{ $summary['savings_rate'] === null ? '—' : $summary['savings_rate'] . '%' }}</td>
        </tr>
    </tbody>
</table>

<table class="cols">
    <tr>
        <td>
            <h2>Where the money went</h2>
            @if (empty($report['category_breakdown']))
                <p class="muted">No expenses recorded in this period.</p>
            @else
                <table class="data">
                    <thead>
                        <tr><th>Category</th><th class="right">Entries</th><th class="right">Total</th><th class="right">Share</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($report['category_breakdown'] as $row)
                            <tr>
                                <td>{{ $row['category'] }}</td>
                                <td class="right">{{ $row['entries'] }}</td>
                                <td class="right">{{ $money($row['total']) }}</td>
                                <td class="right">{{ $row['share'] }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </td>
        <td>
            <h2>Where the money came from</h2>
            @if (empty($report['source_breakdown']))
                <p class="muted">No income recorded in this period.</p>
            @else
                <table class="data">
                    <thead>
                        <tr><th>Source</th><th class="right">Entries</th><th class="right">Total</th><th class="right">Share</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($report['source_breakdown'] as $row)
                            <tr>
                                <td>{{ $row['category'] }}</td>
                                <td class="right">{{ $row['entries'] }}</td>
                                <td class="right">{{ $money($row['total']) }}</td>
                                <td class="right">{{ $row['share'] }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </td>
    </tr>
</table>

@if (! empty($report['budgets']))
    <h2>Budget progress</h2>
    <table class="data">
        <thead>
            <tr><th>Category</th><th class="right">Limit for period</th><th class="right">Spent</th><th class="right">Remaining</th><th class="right">Used</th><th style="width:28%">Progress</th></tr>
        </thead>
        <tbody>
            @foreach ($report['budgets'] as $budget)
                <tr>
                    <td>{{ $budget['category'] }}</td>
                    <td class="right">{{ $money($budget['amount']) }}</td>
                    <td class="right">{{ $money($budget['spent']) }}</td>
                    <td class="right {{ $budget['remaining'] < 0 ? 'neg' : '' }}">{{ $money($budget['remaining']) }}</td>
                    <td class="right">{{ $budget['used_percent'] === null ? '—' : $budget['used_percent'] . '%' }}</td>
                    <td>
                        <div class="bar">
                            <span style="width: {{ min((float) ($budget['used_percent'] ?? 0), 100) }}%; background: {{ ($budget['used_percent'] ?? 0) >= 100 ? '#DC2626' : ((($budget['used_percent'] ?? 0) >= ($budget['alert_at_percent'] ?? 80)) ? '#D97706' : '#059669') }}"></span>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if (! empty($report['top_expenses']))
    <h2>Largest expenses</h2>
    <table class="data">
        <thead>
            <tr><th>Date</th><th>Description</th><th>Category</th><th>Account</th><th class="right">Amount</th></tr>
        </thead>
        <tbody>
            @foreach ($report['top_expenses'] as $row)
                <tr>
                    <td>{{ $row['date'] }}</td>
                    <td>{{ $row['description'] ?: '—' }}</td>
                    <td>{{ $row['category'] }}</td>
                    <td>{{ $row['account'] ?: '—' }}</td>
                    <td class="right">{{ $money($row['amount']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if (! empty($report['top_income']))
    <h2>Largest income entries</h2>
    <table class="data">
        <thead>
            <tr><th>Date</th><th>Description</th><th>Source</th><th class="right">Amount</th></tr>
        </thead>
        <tbody>
            @foreach ($report['top_income'] as $row)
                <tr>
                    <td>{{ $row['date'] }}</td>
                    <td>{{ $row['description'] ?: '—' }}</td>
                    <td>{{ $row['category'] }}</td>
                    <td class="right">{{ $money($row['amount']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if (! empty($report['accounts']['list']))
    <h2>Account balances (as of today)</h2>
    <table class="data">
        <thead>
            <tr><th>Account</th><th>Type</th><th class="right">Balance</th></tr>
        </thead>
        <tbody>
            @foreach ($report['accounts']['list'] as $account)
                <tr>
                    <td>{{ $account['name'] }}</td>
                    <td>{{ ucfirst($account['type']) }}</td>
                    <td class="right">{{ $money($account['balance']) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="2">Net worth</td>
                <td class="right">{{ $money($report['accounts']['total']) }}</td>
            </tr>
        </tbody>
    </table>
@endif

<div class="footer">
    FinWise &middot; amounts in {{ $report['currency'] ?? 'PKR' }} &middot; generated {{ $generatedAt }}
    &middot; blocked entries and inactive categories are excluded unless included by a filter.
</div>
</body>
</html>
