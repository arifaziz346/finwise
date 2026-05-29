<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class IncomeRecord extends Model
{
    use BelongsToUser;
    protected $guarded = [];
    protected $casts = ['amount' => 'decimal:2', 'received_date' => 'date', 'is_recurring' => 'boolean', 'recurrence_rule' => 'array', 'is_blocked' => 'boolean'];
    public function source() { return $this->belongsTo(IncomeSource::class, 'source_id'); }
    public function account() { return $this->belongsTo(Account::class); }
}
