<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    protected $guarded = [];
    protected $casts = ['date_of_birth' => 'date', 'profile_complete' => 'boolean', 'monthly_income_target' => 'decimal:2'];
    public function user() { return $this->belongsTo(User::class); }
}
