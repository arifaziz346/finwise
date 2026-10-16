<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Reports\Insights;
use App\Support\Reports\ReportQuery;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    /**
     * One call powers the whole report screen: summary, time series, breakdowns,
     * budgets, top transactions and generated insights.
     */
    public function overview(Request $request): array
    {
        $query = new ReportQuery($request->user(), $request);

        return $this->payload($query);
    }

    /**
     * Compact payload for the dashboard: same engine, fewer rows.
     */
    public function dashboard(Request $request): array
    {
        $query = new ReportQuery($request->user(), $request);
        $payload = $this->payload($query);

        $payload['recent'] = $query->recentTransactions(8);
        $payload['series'] = array_slice($payload['series'], -24);

        return $payload;
    }

    /** Just the time series – handy for embedding a chart elsewhere. */
    public function series(Request $request): array
    {
        $query = new ReportQuery($request->user(), $request);

        return [
            'period' => $query->periodPayload(),
            'series' => $query->series(),
        ];
    }

    /** Category / income-source breakdown only. */
    public function breakdown(Request $request): array
    {
        $query = new ReportQuery($request->user(), $request);

        return [
            'period' => $query->periodPayload(),
            'expenses' => $query->categoryBreakdown(),
            'income' => $query->sourceBreakdown(),
            'daily' => $query->dailyExpenses(),
        ];
    }

    /** Options for the filter bar, plus where the user's data actually lives. */
    public function filters(Request $request): array
    {
        $query = new ReportQuery($request->user(), $request);

        return array_merge($query->filtersPayload(), [
            'default_period' => $query->periodPayload(),
        ]);
    }

    public function exportCsv(Request $request): Response
    {
        $query = new ReportQuery($request->user(), $request);
        $overview = $this->payload($query);

        $lines = [];
        $lines[] = ['FinWise report'];
        $lines[] = ['Period', $overview['period']['from'] . ' to ' . $overview['period']['to']];
        $lines[] = ['Grouped by', $overview['period']['granularity']];
        $lines[] = [];
        $lines[] = ['Summary'];
        $lines[] = ['Income', $overview['summary']['income'], 'Expenses', $overview['summary']['expenses'], 'Net', $overview['summary']['net'], 'Savings rate %', $overview['summary']['savings_rate'] ?? ''];
        $lines[] = [];
        $lines[] = ['Period', 'Income', 'Expenses', 'Net', 'Running net', 'Savings rate %'];

        foreach ($overview['series'] as $row) {
            $lines[] = [$row['full_label'], $row['income'], $row['expenses'], $row['net'], $row['running_net'], $row['savings_rate'] ?? ''];
        }

        $lines[] = [];
        $lines[] = ['Expenses by category', 'Total', 'Entries', 'Share %'];

        foreach ($overview['category_breakdown'] as $row) {
            $lines[] = [$row['category'], $row['total'], $row['entries'], $row['share']];
        }

        $lines[] = [];
        $lines[] = ['Income by source', 'Total', 'Entries', 'Share %'];

        foreach ($overview['source_breakdown'] as $row) {
            $lines[] = [$row['category'], $row['total'], $row['entries'], $row['share']];
        }

        $lines[] = [];
        $lines[] = ['Largest expenses', 'Date', 'Category', 'Amount'];

        foreach ($overview['top_expenses'] as $row) {
            $lines[] = [$row['description'] ?: $row['category'], $row['date'], $row['category'], $row['amount']];
        }

        $handle = fopen('php://temp', 'r+');
        foreach ($lines as $line) {
            fputcsv($handle, $line);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="finwise-report-' . $overview['period']['from'] . '-to-' . $overview['period']['to'] . '.csv"',
        ]);
    }

    public function exportExcel(Request $request)
    {
        $query = new ReportQuery($request->user(), $request);
        $overview = $this->payload($query);

        $summarySheet = new class($overview) implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
        {
            public function __construct(private array $overview) {}

            public function title(): string
            {
                return 'Report';
            }

            public function headings(): array
            {
                return ['Section', 'Label', 'Detail', 'Amount', 'Second amount', 'Percent'];
            }

            public function array(): array
            {
                $rows = [];
                $period = $this->overview['period'];
                $summary = $this->overview['summary'];

                $rows[] = ['Period', $period['from'] . ' to ' . $period['to'], $period['granularity'], null, null, null];
                $rows[] = ['Summary', 'Income', '', $summary['income'], null, null];
                $rows[] = ['Summary', 'Expenses', '', $summary['expenses'], null, null];
                $rows[] = ['Summary', 'Net saved', '', $summary['net'], null, null];
                $rows[] = ['Summary', 'Savings rate', '', null, null, $summary['savings_rate']];
                $rows[] = ['Summary', 'Average daily expense', '', $summary['avg_daily_expense'], null, null];
                $rows[] = ['Summary', 'Budget limit', '', $summary['budget_limit'], null, $summary['budget_used_percent']];

                return $rows;
            }
        };

        $detail = new class($overview) implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
        {
            public function __construct(private array $overview) {}

            public function title(): string
            {
                return 'Details';
            }

            public function headings(): array
            {
                return ['Section', 'Label', 'Detail', 'Amount', 'Second amount', 'Percent'];
            }

            public function array(): array
            {
                $rows = [];

                foreach ($this->overview['series'] as $row) {
                    $rows[] = ['Period detail', $row['full_label'], '', $row['income'], $row['expenses'], $row['savings_rate']];
                }

                $rows[] = [];
                foreach ($this->overview['category_breakdown'] as $row) {
                    $rows[] = ['Expense category', $row['category'], $row['entries'] . ' entries', $row['total'], null, $row['share']];
                }

                $rows[] = [];
                foreach ($this->overview['source_breakdown'] as $row) {
                    $rows[] = ['Income source', $row['category'], $row['entries'] . ' entries', $row['total'], null, $row['share']];
                }

                $rows[] = [];
                foreach ($this->overview['top_expenses'] as $row) {
                    $rows[] = ['Largest expense', $row['category'], trim($row['date'] . ' ' . (string) $row['description']), $row['amount'], null, null];
                }

                $rows[] = [];
                foreach ($this->overview['insights'] as $row) {
                    $rows[] = ['Insight', $row['title'], $row['message'], null, null, null];
                }

                return $rows;
            }
        };

        return Excel::download([$summarySheet, $detail], 'finwise-report-' . $overview['period']['from'] . '-to-' . $overview['period']['to'] . '.xlsx');
    }

    public function exportPdf(Request $request)
    {
        $query = new ReportQuery($request->user(), $request);
        $overview = $this->payload($query);

        $pdf = Pdf::loadView('reports.summary', [
            'report' => $overview,
            'user' => $request->user(),
            'generatedAt' => Insights::generatedAt(),
        ])->setPaper('a4');

        return $pdf->download('finwise-report-' . $overview['period']['from'] . '-to-' . $overview['period']['to'] . '.pdf');
    }

    /** Attach generated insights so every export shows the same guidance. */
    private function payload(ReportQuery $query): array
    {
        $overview = $query->overview();
        $overview['insights'] = Insights::fromOverview($overview);
        $overview['series_insights'] = Insights::fromSeries($overview['series']);
        $overview['filters'] = $query->filtersPayload();
        $overview['currency'] = $query->user()->currency ?? 'PKR';

        return $overview;
    }
}
