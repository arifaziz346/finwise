<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'currency', 'timezone', 'avatar'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function profile() { return $this->hasOne(UserProfile::class); }
    public function accounts() { return $this->hasMany(Account::class); }
    public function incomeSources() { return $this->hasMany(IncomeSource::class); }
    public function incomeRecords() { return $this->hasMany(IncomeRecord::class); }
    public function expenseCategories() { return $this->hasMany(ExpenseCategory::class); }
    public function expenseRecords() { return $this->hasMany(ExpenseRecord::class); }
    public function businesses() { return $this->hasMany(Business::class); }
    public function budgets() { return $this->hasMany(Budget::class); }
    public function transfers() { return $this->hasMany(Transfer::class); }
    public function debts() { return $this->hasMany(Debt::class); }
    public function recurringTransactions() { return $this->hasMany(RecurringTransaction::class); }
    public function reminders() { return $this->hasMany(Reminder::class); }
    public function documents() { return $this->hasMany(Document::class); }
}
