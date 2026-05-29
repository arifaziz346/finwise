<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\ExpenseRecord;
use App\Models\IncomeRecord;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function summary(Request $request)
    {
        $month = now()->startOfMonth();
        $income = IncomeRecord::where('user_id', $request->user()->id)->where('is_blocked', false)->whereDate('received_date', '>=', $month)->sum('amount');
        $expenses = ExpenseRecord::where('user_id', $request->user()->id)->where('is_blocked', false)->whereDate('expense_date', '>=', $month)->sum('amount');
        $budgetLimit = Budget::where('user_id', $request->user()->id)->where('is_blocked', false)->sum('amount');

        return [
            'monthly_income' => round((float) $income, 2),
            'monthly_expenses' => round((float) $expenses, 2),
            'remaining_balance' => round((float) $income - (float) $expenses, 2),
            'budget_limit' => round((float) $budgetLimit, 2),
            'budget_used' => round((float) $expenses, 2),
            'budget_percent' => (float) $budgetLimit > 0 ? round(((float) $expenses / (float) $budgetLimit) * 100) : 0,
        ];
    }

    public function cashflow(Request $request)
    {
        return collect(range(5, 0))->map(function ($offset) use ($request) {
            $date = now()->subMonths($offset);
            return [
                'label' => $date->format('M'),
                'income' => (float) IncomeRecord::where('user_id', $request->user()->id)->where('is_blocked', false)->whereYear('received_date', $date->year)->whereMonth('received_date', $date->month)->sum('amount'),
                'expenses' => (float) ExpenseRecord::where('user_id', $request->user()->id)->where('is_blocked', false)->whereYear('expense_date', $date->year)->whereMonth('expense_date', $date->month)->sum('amount'),
            ];
        });
    }

    public function expenseByCategory(Request $request)
    {
        return ExpenseRecord::query()
            ->where('user_id', $request->user()->id)
            ->where('is_blocked', false)
            ->with('category')
            ->whereDate('expense_date', '>=', now()->startOfMonth())
            ->get()
            ->groupBy('category_id')
            ->map(fn ($group) => [
                'category' => $group->first()->category?->name ?? 'Uncategorized',
                'total' => round((float) $group->sum('amount'), 2),
            ])
            ->values();
    }

    public function recentTransactions(Request $request)
    {
        $income = IncomeRecord::where('user_id', $request->user()->id)->where('is_blocked', false)->with('source')->latest()->limit(5)->get()->map(fn ($r) => ['type' => 'income'] + $r->toArray());
        $expenses = ExpenseRecord::where('user_id', $request->user()->id)->where('is_blocked', false)->with('category')->latest()->limit(5)->get()->map(fn ($r) => ['type' => 'expense'] + $r->toArray());
        return $income->merge($expenses)->sortByDesc('created_at')->take(10)->values();
    }
}
