<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class Transfer extends Model
{
    use BelongsToUser;
    protected $guarded = [];
    protected $casts = ['amount' => 'decimal:2', 'fee' => 'decimal:2', 'transferred_at' => 'datetime'];
    public function fromAccount() { return $this->belongsTo(Account::class, 'from_account_id'); }
    public function toAccount() { return $this->belongsTo(Account::class, 'to_account_id'); }
}
