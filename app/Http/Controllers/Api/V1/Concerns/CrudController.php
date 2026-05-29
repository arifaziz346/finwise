<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Http\Requests\FinanceRequest;
use App\Http\Resources\ApiResource;
use App\Repositories\Eloquent\EloquentFinanceRepository;
use Illuminate\Http\Request;

trait CrudController
{
    protected function repository(): EloquentFinanceRepository
    {
        return new EloquentFinanceRepository($this->modelClass);
    }

    public function index(Request $request)
    {
        return ApiResource::collection(
            $this->repository()->paginate($request->user()->id, $request->query(), $this->with ?? [])
        );
    }

    public function store(FinanceRequest $request)
    {
        return ApiResource::make($this->repository()->create($request->user()->id, $request->validated())->load($this->with ?? []));
    }

    public function show(Request $request, int|string $id)
    {
        return ApiResource::make($this->repository()->findForUser($request->user()->id, $id, $this->with ?? []));
    }

    public function update(FinanceRequest $request, int|string $id)
    {
        return ApiResource::make($this->repository()->update($request->user()->id, $id, $request->validated())->load($this->with ?? []));
    }

    public function destroy(Request $request, int|string $id)
    {
        $this->repository()->delete($request->user()->id, $id);
        return response()->noContent();
    }
}
