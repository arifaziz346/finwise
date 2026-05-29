<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Interfaces\FinanceRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class EloquentFinanceRepository implements FinanceRepositoryInterface
{
    public function __construct(private readonly string $modelClass) {}

    public function paginate(int $userId, array $filters = [], array $with = []): LengthAwarePaginator
    {
        $query = $this->modelClass::query()->where('user_id', $userId)->with($with)->latest();

        foreach (['type', 'status', 'period', 'category_id', 'account_id', 'source_id', 'is_blocked', 'is_paid'] as $field) {
            if (array_key_exists($field, $filters) && $filters[$field] !== '' && $filters[$field] !== null) {
                $query->where($field, $filters[$field]);
            }
        }

        $query
            ->when($filters['search'] ?? null, function ($q, $value) {
                $q->where(function ($inner) use ($value) {
                    $inner->whereRaw('1 = 0');
                    foreach (['name', 'description', 'notes'] as $column) {
                        if (Schema::hasColumn((new $this->modelClass)->getTable(), $column)) {
                            $inner->orWhere($column, 'like', "%{$value}%");
                        }
                    }
                });
            })
            ->when($filters['date_from'] ?? null, fn ($q, $value) => $this->dateFilter($q, '>=', $value))
            ->when($filters['date_to'] ?? null, fn ($q, $value) => $this->dateFilter($q, '<=', $value));

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    private function dateFilter($query, string $operator, string $value)
    {
        $dateColumn = match ($this->modelClass) {
            \App\Models\IncomeRecord::class => 'received_date',
            \App\Models\ExpenseRecord::class => 'expense_date',
            \App\Models\Budget::class => 'start_date',
            \App\Models\Reminder::class => 'due_date',
            default => null,
        };

        return $dateColumn ? $query->whereDate($dateColumn, $operator, $value) : $query;
    }

    public function create(int $userId, array $data): Model
    {
        $data['user_id'] = $userId;
        return $this->modelClass::query()->create($data);
    }

    public function findForUser(int $userId, int|string $id, array $with = []): Model
    {
        return $this->modelClass::query()->where('user_id', $userId)->with($with)->findOrFail($id);
    }

    public function update(int $userId, int|string $id, array $data): Model
    {
        $model = $this->findForUser($userId, $id);
        $model->update($data);
        return $model->refresh();
    }

    public function delete(int $userId, int|string $id): void
    {
        $this->findForUser($userId, $id)->delete();
    }
}
