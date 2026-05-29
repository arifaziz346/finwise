<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    use BelongsToUser;
    protected $guarded = [];
    protected $casts = ['amount' => 'decimal:2', 'start_date' => 'date', 'end_date' => 'date', 'is_blocked' => 'boolean'];
    public function category() { return $this->belongsTo(ExpenseCategory::class, 'category_id'); }
}
