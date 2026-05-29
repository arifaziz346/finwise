<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class IncomeSource extends Model
{
    use BelongsToUser;
    protected $guarded = [];
    protected $casts = ['is_default' => 'boolean', 'is_blocked' => 'boolean'];
    public function records() { return $this->hasMany(IncomeRecord::class, 'source_id'); }
}
