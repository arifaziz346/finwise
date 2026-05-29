<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\CrudController;
use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResource;
use App\Models\Debt;
use Illuminate\Http\Request;

class DebtController extends Controller
{
    use CrudController;
    protected string $modelClass = Debt::class;
    protected array $with = ['payments'];

    public function payment(Request $request, int $id)
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0.01'], 'paid_at' => ['required', 'date'], 'notes' => ['nullable', 'string']]);
        $debt = Debt::query()->where('user_id', $request->user()->id)->findOrFail($id);
        $payment = $debt->payments()->create($data);
        $debt->decrement('remaining_amount', $data['amount']);
        $debt->refresh();
        if ((float) $debt->remaining_amount <= 0) {
            $debt->update(['status' => 'settled', 'remaining_amount' => 0]);
        }
        return ApiResource::make($payment);
    }
}
