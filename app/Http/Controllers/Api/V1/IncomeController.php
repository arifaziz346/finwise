<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\CrudController;
use App\Http\Controllers\Controller;
use App\Http\Requests\FinanceRequest;
use App\Http\Resources\ApiResource;
use App\Models\Account;
use App\Models\IncomeRecord;
use App\Models\IncomeSource;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class IncomeController extends Controller
{
    use CrudController;
    protected string $modelClass = IncomeRecord::class;
    protected array $with = ['source', 'account'];

    public function store(FinanceRequest $request)
    {
        $data = $this->preparePayload($request);
        return ApiResource::make($this->repository()->create($request->user()->id, $data)->load($this->with));
    }

    public function update(FinanceRequest $request, int|string $id)
    {
        $data = $request->validated();
        if (isset($data['source_id'])) {
            $this->ensureSourceIsAvailable($request->user()->id, $data['source_id']);
        }

        return ApiResource::make($this->repository()->update($request->user()->id, $id, $data)->load($this->with));
    }

    public function summary(Request $request)
    {
        $query = IncomeRecord::query()
            ->where('user_id', $request->user()->id)
            ->where('is_blocked', false)
            ->with('source')
            ->when($request->query('source_id'), fn ($q, $value) => $q->where('source_id', $value))
            ->when($request->query('date_from'), fn ($q, $value) => $q->whereDate('received_date', '>=', $value))
            ->when($request->query('date_to'), fn ($q, $value) => $q->whereDate('received_date', '<=', $value));

        $rows = $query->get();

        return [
            'total' => round((float) $rows->sum('amount'), 2),
            'by_category' => $rows
                ->groupBy('source_id')
                ->map(fn ($group) => [
                    'category' => $group->first()->source?->name ?? 'Uncategorized',
                    'total' => round((float) $group->sum('amount'), 2),
                ])
                ->values(),
        ];
    }

    private function preparePayload(FinanceRequest $request): array
    {
        $data = $request->validated();
        $this->ensureSourceIsAvailable($request->user()->id, $data['source_id']);
        $data['account_id'] = $this->defaultAccountId($request->user()->id);
        $data['currency'] = 'PKR';
        return $data;
    }

    private function ensureSourceIsAvailable(int $userId, int $sourceId): void
    {
        $exists = IncomeSource::query()
            ->where('user_id', $userId)
            ->where('id', $sourceId)
            ->where('is_blocked', false)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages(['source_id' => 'Selected income category is blocked or unavailable.']);
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
