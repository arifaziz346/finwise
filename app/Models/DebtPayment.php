<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DebtPayment extends Model
{
    protected $guarded = [];
    protected $casts = ['amount' => 'decimal:2', 'paid_at' => 'date'];
    public function debt() { return $this->belongsTo(Debt::class); }
}
