<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use BelongsToUser;
    protected $guarded = [];
    protected $casts = ['balance' => 'decimal:2', 'credit_limit' => 'decimal:2', 'is_active' => 'boolean'];
    public function incomeRecords() { return $this->hasMany(IncomeRecord::class); }
    public function expenseRecords() { return $this->hasMany(ExpenseRecord::class); }
}
