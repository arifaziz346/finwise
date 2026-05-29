<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class RecurringTransaction extends Model
{
    use BelongsToUser;
    protected $guarded = [];
    protected $casts = ['amount' => 'decimal:2', 'next_due_date' => 'date', 'is_active' => 'boolean'];
    public function category() { return $this->belongsTo(ExpenseCategory::class, 'category_id'); }
    public function account() { return $this->belongsTo(Account::class); }
}
