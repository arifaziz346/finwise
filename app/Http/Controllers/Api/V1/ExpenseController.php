<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\CrudController;
use App\Http\Controllers\Controller;
use App\Http\Requests\FinanceRequest;
use App\Http\Resources\ApiResource;
use App\Models\Account;
use App\Models\ExpenseCategory;
use App\Models\ExpenseRecord;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ExpenseController extends Controller
{
    use CrudController;
    protected string $modelClass = ExpenseRecord::class;
    protected array $with = ['category.parent', 'account'];

    public function store(FinanceRequest $request)
    {
        $data = $this->preparePayload($request);
        return ApiResource::make($this->repository()->create($request->user()->id, $data)->load($this->with));
    }

    public function update(FinanceRequest $request, int|string $id)
    {
        $data = $request->validated();
        if (isset($data['category_id'])) {
            $this->ensureCategoryIsAvailable($request->user()->id, $data['category_id']);
        }

        return ApiResource::make($this->repository()->update($request->user()->id, $id, $data)->load($this->with));
    }

    public function report(Request $request)
    {
        $rows = $this->reportRows($request);

        return [
            'grand_total' => round((float) $rows->sum('amount'), 2),
            'categories' => $rows
                ->groupBy('category_id')
                ->map(fn ($group) => [
                    'category' => $group->first()->category?->name ?? 'Uncategorized',
                    'total' => round((float) $group->sum('amount'), 2),
                ])
                ->values(),
        ];
    }

    public function reportCsv(Request $request)
    {
        $report = $this->report($request);
        $csv = "Category,Total\r\n";
        foreach ($report['categories'] as $row) {
            $csv .= sprintf("\"%s\",%s\r\n", str_replace('"', '""', $row['category']), $row['total']);
        }
        $csv .= sprintf("\"Grand Total\",%s\r\n", $report['grand_total']);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="expense-report.csv"',
        ]);
    }

    public function reportPdf(Request $request)
    {
        $report = $this->report($request);
        return Pdf::loadView('reports.expense', ['report' => $report])->download('expense-report.pdf');
    }

    private function reportRows(Request $request)
    {
        return ExpenseRecord::query()
            ->where('user_id', $request->user()->id)
            ->where('is_blocked', false)
            ->with('category')
            ->when($request->query('category_id'), fn ($q, $value) => $q->where('category_id', $value))
            ->when($request->query('date_from'), fn ($q, $value) => $q->whereDate('expense_date', '>=', $value))
            ->when($request->query('date_to'), fn ($q, $value) => $q->whereDate('expense_date', '<=', $value))
            ->get();
    }

    private function preparePayload(FinanceRequest $request): array
    {
        $data = $request->validated();
        $this->ensureCategoryIsAvailable($request->user()->id, $data['category_id']);
        $data['account_id'] = $this->defaultAccountId($request->user()->id);
        $data['currency'] = 'PKR';
        $data['payment_method'] = 'cash';
        return $data;
    }

    private function ensureCategoryIsAvailable(int $userId, int $categoryId): void
    {
        $exists = ExpenseCategory::query()
            ->where('id', $categoryId)
            ->where('is_blocked', false)
            ->where(fn ($q) => $q->where('user_id', $userId)->orWhere('is_system', true))
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages(['category_id' => 'Selected expense category is blocked or unavailable.']);
        }
    }

    private function defaultAccountId(int $userId): int
    {
        return Account::query()->firstOrCreate(
            ['user_id' => $userId, 'name' => 'Default'],
            ['type' => 'cash', 'balance' => 0, 'currency' => 'PKR', 'color' => '#64748B', 'icon' => 'wallet']
        )->id;
    }
}
