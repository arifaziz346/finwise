<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
    use BelongsToUser;
    protected $guarded = [];
    protected $casts = ['is_system' => 'boolean', 'is_blocked' => 'boolean'];
    public function parent() { return $this->belongsTo(self::class, 'parent_id'); }
    public function children() { return $this->hasMany(self::class, 'parent_id')->with('children'); }
    public function records() { return $this->hasMany(ExpenseRecord::class, 'category_id'); }
}
