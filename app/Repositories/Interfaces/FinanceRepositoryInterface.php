<?php

namespace App\Repositories\Interfaces;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

interface FinanceRepositoryInterface
{
    public function paginate(int $userId, array $filters = [], array $with = []): LengthAwarePaginator;
    public function create(int $userId, array $data): Model;
    public function findForUser(int $userId, int|string $id, array $with = []): Model;
    public function update(int $userId, int|string $id, array $data): Model;
    public function delete(int $userId, int|string $id): void;
}
