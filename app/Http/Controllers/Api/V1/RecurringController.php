<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\CrudController;
use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResource;
use App\Models\RecurringTransaction;
use Illuminate\Http\Request;

class RecurringController extends Controller
{
    use CrudController;
    protected string $modelClass = RecurringTransaction::class;
    protected array $with = ['category', 'account'];

    public function toggle(Request $request, int $id)
    {
        $item = RecurringTransaction::where('user_id', $request->user()->id)->findOrFail($id);
        $item->update(['is_active' => ! $item->is_active]);
        return ApiResource::make($item->refresh());
    }
}
