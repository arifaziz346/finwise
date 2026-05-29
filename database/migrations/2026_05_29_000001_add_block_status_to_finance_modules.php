<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['expense_categories', 'income_sources', 'expense_records', 'income_records', 'budgets'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (! Schema::hasColumn($table, 'is_blocked')) {
                    $blueprint->boolean('is_blocked')->default(false)->index();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['expense_categories', 'income_sources', 'expense_records', 'income_records', 'budgets'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (Schema::hasColumn($table, 'is_blocked')) {
                    $blueprint->dropColumn('is_blocked');
                }
            });
        }
    }
};
