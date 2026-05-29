<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\CrudController;
use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResource;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;

class ExpenseCategoryController extends Controller
{
    use CrudController;
    protected string $modelClass = ExpenseCategory::class;

    public function tree(Request $request)
    {
        $categories = ExpenseCategory::query()
            ->where(fn ($q) => $q->where('user_id', $request->user()->id)->orWhere('is_system', true))
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('sort_order')
            ->get();

        return ApiResource::collection($categories);
    }
}
