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
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ExpenseController extends Controller
{
    use CrudController;
    protected string $modelClass = ExpenseRecord::class;
    protected array $with = ['category.parent', 'account'];

    public const PAYMENT_METHODS = ['cash', 'card', 'online', 'cheque'];

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

    /**
     * Everything the quick-entry form needs in one small payload: category
     * suggestions ranked by how the user actually spends, plus smart defaults
     * (most recent category, payment method and location).
     */
    public function formOptions(Request $request)
    {
        $userId = $request->user()->id;

        $usage = ExpenseRecord::query()
            ->where('user_id', $userId)
            ->where('is_blocked', false)
            ->selectRaw('category_id, count(*) as times_used, max(expense_date) as last_used_at')
            ->groupBy('category_id')
            ->orderByDesc('times_used')
            ->orderByDesc('last_used_at')
            ->get();

        $active = $this->activeCategories($userId);
        $suggestions = $usage
            ->map(function ($row) use ($active) {
                $category = $active->get($row->category_id);

                if (! $category) {
                    return null;
                }

                return $this->formatCategory($category) + [
                    'times_used' => (int) $row->times_used,
                    'last_used_at' => $row->last_used_at
                        ? Carbon::parse($row->last_used_at)->toDateString()
                        : null,
                    'used_label' => $row->last_used_at
                        ? Carbon::parse($row->last_used_at)->diffForHumans(['short' => true])
                        : null,
                ];
            })
            ->filter()
            ->take(12)
            ->values();

        $defaults = [
            'category_id' => $suggestions->first()['id'] ?? $active->first()?->id,
            'payment_method' => $this->lastUsedValue($userId, 'payment_method') ?? 'cash',
            'location' => $this->lastUsedValue($userId, 'location'),
            'expense_date' => now()->toDateString(),
        ];

        return [
            'suggestions' => $suggestions->all(),
            'categories' => $active->map(fn ($category) => $this->formatCategory($category))->values()->all(),
            'defaults' => $defaults,
            'accounts' => $request->user()->accounts()
                ->where('is_active', true)
                ->get(['id', 'name', 'type', 'balance'])
                ->all(),
            'payment_methods' => self::PAYMENT_METHODS,
            'total_categories' => $active->count(),
        ];
    }

    /** The user's own categories plus the shared system ones, all unblocked. */
    private function activeCategories(int $userId): Collection
    {
        return ExpenseCategory::query()
            ->where('is_blocked', false)
            ->where(fn ($q) => $q->where('user_id', $userId)->orWhere('is_system', true))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'color', 'icon'])
            ->keyBy('id');
    }

    /** @return array{id: int, name: string, color: string, icon: string} */
    private function formatCategory(ExpenseCategory $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'color' => $category->color ?: '#6366F1',
            'icon' => $category->icon ?: 'tag',
        ];
    }

    private function lastUsedValue(int $userId, string $column): ?string
    {
        $value = ExpenseRecord::query()
            ->where('user_id', $userId)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->value($column);

        return $value !== null && $value !== '' ? (string) $value : null;
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
        $data['account_id'] = $data['account_id'] ?? $this->defaultAccountId($request->user()->id);
        $data['currency'] = 'PKR';
        // Only fall back to cash when the form did not send a method — the quick
        // entry sheet lets the user pick card / online / cheque.
        $method = (string) ($data['payment_method'] ?? '');
        $data['payment_method'] = in_array($method, self::PAYMENT_METHODS, true) ? $method : 'cash';
        $data['expense_date'] = $data['expense_date'] ?? now()->toDateString();

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
