<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class Debt extends Model
{
    use BelongsToUser;
    protected $guarded = [];
    protected $casts = ['original_amount' => 'decimal:2', 'remaining_amount' => 'decimal:2', 'due_date' => 'date'];
    public function payments() { return $this->hasMany(DebtPayment::class); }
}
