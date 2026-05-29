<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class BusinessTransaction extends Model
{
    use BelongsToUser;
    protected $guarded = [];
    protected $casts = ['amount' => 'decimal:2', 'transaction_date' => 'date'];
    public function business() { return $this->belongsTo(Business::class); }
}
