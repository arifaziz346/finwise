<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BudgetController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ExpenseCategoryController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\IncomeController;
use App\Http\Controllers\Api\V1\IncomeSourceController;
use App\Http\Controllers\Api\V1\ReminderController;
use App\Http\Controllers\Api\V1\SettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
        Route::get('/dashboard/cashflow', [DashboardController::class, 'cashflow']);
        Route::get('/dashboard/expense-by-category', [DashboardController::class, 'expenseByCategory']);
        Route::get('/dashboard/recent-transactions', [DashboardController::class, 'recentTransactions']);

        Route::get('/income/summary', [IncomeController::class, 'summary']);
        Route::apiResource('income', IncomeController::class);
        Route::apiResource('income-sources', IncomeSourceController::class)->except(['show']);
        Route::apiResource('expenses', ExpenseController::class);
        Route::get('/expenses-report', [ExpenseController::class, 'report']);
        Route::get('/expenses-report/csv', [ExpenseController::class, 'reportCsv']);
        Route::get('/expenses-report/pdf', [ExpenseController::class, 'reportPdf']);
        Route::get('/expense-categories/tree', [ExpenseCategoryController::class, 'tree']);
        Route::apiResource('expense-categories', ExpenseCategoryController::class)->except(['show']);
        Route::get('/budgets/status', [BudgetController::class, 'status']);
        Route::apiResource('budgets', BudgetController::class);
        Route::apiResource('reminders', ReminderController::class)->except(['show']);
        Route::post('/reminders/{id}/mark-paid', [ReminderController::class, 'markPaid']);
        Route::post('/reminders/{id}/mark-open', [ReminderController::class, 'markOpen']);
        Route::match(['get', 'put'], '/settings/profile', [SettingsController::class, 'profile']);
        Route::put('/settings/password', [SettingsController::class, 'password']);
    });
});
