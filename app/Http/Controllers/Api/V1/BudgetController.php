<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\CrudController;
use App\Http\Controllers\Controller;
use App\Http\Requests\FinanceRequest;
use App\Http\Resources\ApiResource;
use App\Models\Budget;
use App\Models\ExpenseCategory;
use App\Models\ExpenseRecord;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BudgetController extends Controller
{
    use CrudController;
    protected string $modelClass = Budget::class;
    protected array $with = ['category'];

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

    public function status(Request $request)
    {
        $budgets = Budget::query()
            ->where('user_id', $request->user()->id)
            ->with('category')
            ->when($request->query('category_id'), fn ($q, $value) => $q->where('category_id', $value))
            ->when($request->query('month'), function ($q, $value) {
                $q->whereDate('start_date', '<=', "{$value}-31")
                    ->where(fn ($inner) => $inner->whereNull('end_date')->orWhereDate('end_date', '>=', "{$value}-01"));
            })
            ->get()
            ->map(function ($budget) use ($request) {
            $month = $request->query('month');
            $from = $month ? "{$month}-01" : $budget->start_date;
            $to = $month ? date('Y-m-t', strtotime($from)) : $budget->end_date;
            $spent = ExpenseRecord::query()
                ->where('user_id', $request->user()->id)
                ->where('is_blocked', false)
                ->where('category_id', $budget->category_id)
                ->whereDate('expense_date', '>=', $from)
                ->when($to, fn ($q) => $q->whereDate('expense_date', '<=', $to))
                ->sum('amount');

            $budget->spent = round((float) $spent, 2);
            $budget->remaining = round((float) $budget->amount - (float) $spent, 2);
            $budget->used_percent = (float) $budget->amount > 0 ? round(((float) $spent / (float) $budget->amount) * 100) : 0;
            return $budget;
        });

        return ApiResource::collection($budgets);
    }

    private function preparePayload(FinanceRequest $request): array
    {
        $data = $request->validated();
        $this->ensureCategoryIsAvailable($request->user()->id, $data['category_id']);
        $data['period'] = 'monthly';
        $data['alert_at_percent'] = $data['alert_at_percent'] ?? 80;
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
            throw ValidationException::withMessages(['category_id' => 'Selected category is blocked or unavailable.']);
        }
    }
}
