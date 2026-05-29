<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class ExpenseRecord extends Model
{
    use BelongsToUser;
    protected $guarded = [];
    protected $casts = ['amount' => 'decimal:2', 'expense_date' => 'date', 'tags' => 'array', 'is_recurring' => 'boolean', 'recurrence_rule' => 'array', 'is_blocked' => 'boolean'];
    public function category() { return $this->belongsTo(ExpenseCategory::class, 'category_id'); }
    public function account() { return $this->belongsTo(Account::class); }
}
