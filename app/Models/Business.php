<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class Business extends Model
{
    use BelongsToUser;
    protected $guarded = [];
    protected $casts = ['started_at' => 'date'];
    public function transactions() { return $this->hasMany(BusinessTransaction::class); }
}
