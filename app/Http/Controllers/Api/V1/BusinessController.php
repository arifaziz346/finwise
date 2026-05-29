<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\CrudController;
use App\Http\Controllers\Controller;
use App\Http\Requests\FinanceRequest;
use App\Http\Resources\ApiResource;
use App\Models\Business;
use App\Models\BusinessTransaction;
use Illuminate\Http\Request;

class BusinessController extends Controller
{
    use CrudController;
    protected string $modelClass = Business::class;
    protected array $with = ['transactions'];

    public function transactions(Request $request, FinanceRequest $financeRequest, int $id)
    {
        Business::query()->where('user_id', $request->user()->id)->findOrFail($id);

        if ($request->isMethod('post')) {
            $data = $financeRequest->validated();
            $data['business_id'] = $id;
            $data['user_id'] = $request->user()->id;
            return ApiResource::make(BusinessTransaction::create($data));
        }

        return ApiResource::collection(
            BusinessTransaction::query()->where('user_id', $request->user()->id)->where('business_id', $id)->latest()->paginate(15)
        );
    }
}
