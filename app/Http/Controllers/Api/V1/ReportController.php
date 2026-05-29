<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\ExpenseRecord;
use App\Models\IncomeRecord;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function incomeExpense(Request $request) { return app(DashboardController::class)->cashflow($request); }

    public function expenseByCategory(Request $request)
    {
        return ExpenseRecord::where('user_id', $request->user()->id)
            ->selectRaw('category_id, sum(amount) as total')
            ->with('category')
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->get();
    }

    public function netWorth(Request $request)
    {
        return [['label' => now()->format('M Y'), 'value' => (float) Account::where('user_id', $request->user()->id)->sum('balance')]];
    }

    public function cashflow(Request $request) { return $this->incomeExpense($request); }

    public function exportPdf(Request $request)
    {
        $summary = app(DashboardController::class)->summary($request);
        return Pdf::loadHTML(view('reports.summary', compact('summary'))->render())->download('finwise-report.pdf');
    }

    public function exportExcel(Request $request)
    {
        return Excel::download(new class($request->user()->id) implements \Maatwebsite\Excel\Concerns\FromArray {
            public function __construct(private int $userId) {}
            public function array(): array
            {
                return IncomeRecord::where('user_id', $this->userId)->get(['received_date', 'description', 'amount'])->toArray();
            }
        }, 'finwise-report.xlsx');
    }
}
