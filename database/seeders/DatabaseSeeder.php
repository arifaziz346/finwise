<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Business;
use App\Models\Debt;
use App\Models\ExpenseCategory;
use App\Models\ExpenseRecord;
use App\Models\IncomeRecord;
use App\Models\IncomeSource;
use App\Models\RecurringTransaction;
use App\Models\Reminder;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::firstOrCreate(['name' => 'user']);
        $user = User::factory()->create([
            'name' => 'Demo User',
            'email' => 'demo@finwise.com',
            'password' => 'demo1234',
            'currency' => 'PKR',
            'timezone' => 'Asia/Karachi',
        ]);
        $user->assignRole($role);
        UserProfile::create(['user_id' => $user->id, 'phone' => '+92 300 0000000', 'occupation' => 'Product Manager', 'monthly_income_target' => 350000, 'profile_complete' => true]);

        $accounts = collect([
            ['name' => 'Cash', 'type' => 'cash', 'balance' => 45000, 'icon' => 'banknote', 'color' => '#10B981'],
            ['name' => 'HBL Bank', 'type' => 'bank', 'balance' => 420000, 'icon' => 'landmark', 'color' => '#6366F1'],
            ['name' => 'JazzCash', 'type' => 'digital', 'balance' => 26000, 'icon' => 'smartphone', 'color' => '#F59E0B'],
            ['name' => 'Easypaisa', 'type' => 'digital', 'balance' => 18500, 'icon' => 'wallet', 'color' => '#10B981'],
            ['name' => 'Credit Card', 'type' => 'credit', 'balance' => 0, 'credit_limit' => 250000, 'icon' => 'credit-card', 'color' => '#EF4444'],
        ])->map(fn ($data) => Account::create($data + ['user_id' => $user->id, 'currency' => 'PKR']));

        $sources = collect(['Salary', 'Business', 'Freelance', 'Consulting', 'Investments', 'Dividends', 'Rental', 'Gift', 'Refunds', 'Other'])
            ->map(fn ($name, $i) => IncomeSource::create(['user_id' => $user->id, 'name' => $name, 'type' => $i < 4 ? 'active' : 'passive', 'is_default' => $i < 3]));

        $leafCategories = collect();
        $tree = [
            'Food & Dining' => ['Restaurants' => ['Fast Food', 'Coffee'], 'Groceries' => ['Vegetables', 'Meat']],
            'Transport' => ['Fuel' => ['Petrol'], 'Ride Hailing' => ['Careem', 'Uber']],
            'Bills' => ['Utilities' => ['Electricity', 'Gas'], 'Subscriptions' => ['Internet', 'Software']],
            'Health' => ['Medical' => ['Pharmacy'], 'Fitness' => ['Gym']],
            'Shopping' => ['Clothing' => ['Casual'], 'Electronics' => ['Mobile']],
        ];
        $sort = 0;

        foreach ($tree as $parent => $children) {
            $parentModel = ExpenseCategory::create(['user_id' => $user->id, 'name' => $parent, 'icon' => 'folder', 'color' => '#6366F1', 'sort_order' => $sort++]);
            foreach ($children as $child => $leaves) {
                $childModel = ExpenseCategory::create(['user_id' => $user->id, 'name' => $child, 'parent_id' => $parentModel->id, 'icon' => 'tag', 'color' => '#10B981', 'sort_order' => $sort++]);
                foreach ($leaves as $leaf) {
                    $leafCategories->push(ExpenseCategory::create(['user_id' => $user->id, 'name' => $leaf, 'parent_id' => $childModel->id, 'icon' => 'circle', 'color' => '#F59E0B', 'sort_order' => $sort++]));
                }
            }
        }

        foreach (range(0, 5) as $monthOffset) {
            foreach (range(1, 5) as $i) {
                IncomeRecord::create([
                    'user_id' => $user->id,
                    'source_id' => $sources->random()->id,
                    'account_id' => $accounts->random()->id,
                    'amount' => fake()->numberBetween(25000, 180000),
                    'currency' => 'PKR',
                    'received_date' => now()->subMonths($monthOffset)->subDays(fake()->numberBetween(0, 25))->toDateString(),
                    'description' => fake()->sentence(4),
                ]);

                ExpenseRecord::create([
                    'user_id' => $user->id,
                    'category_id' => $leafCategories->random()->id,
                    'account_id' => $accounts->random()->id,
                    'amount' => fake()->numberBetween(800, 45000),
                    'currency' => 'PKR',
                    'expense_date' => now()->subMonths($monthOffset)->subDays(fake()->numberBetween(0, 25))->toDateString(),
                    'description' => fake()->sentence(4),
                    'payment_method' => fake()->randomElement(['cash', 'card', 'online']),
                    'tags' => fake()->randomElements(['home', 'work', 'family', 'urgent'], 2),
                ]);
            }
        }

        $leafCategories->take(3)->values()->each(fn ($category, $i) => Budget::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => [25000, 60000, 120000][$i],
            'period' => 'monthly',
            'start_date' => now()->startOfMonth()->toDateString(),
            'alert_at_percent' => [70, 80, 90][$i],
        ]));

        collect(['FinWise Studio', 'Aziz Consulting'])->each(function ($name) use ($user) {
            $business = Business::create(['user_id' => $user->id, 'name' => $name, 'type' => 'side-hustle', 'currency' => 'PKR', 'started_at' => now()->subYear()]);
            foreach (range(1, 8) as $i) {
                $business->transactions()->create([
                    'user_id' => $user->id,
                    'type' => fake()->randomElement(['income', 'expense']),
                    'amount' => fake()->numberBetween(5000, 90000),
                    'category' => fake()->randomElement(['Sales', 'Tools', 'Marketing']),
                    'client_name' => fake()->company(),
                    'invoice_no' => 'INV-'.fake()->numberBetween(1000, 9999),
                    'transaction_date' => now()->subDays(fake()->numberBetween(1, 160)),
                    'status' => fake()->randomElement(['pending', 'completed', 'completed']),
                ]);
            }
        });

        foreach (range(1, 5) as $i) {
            Debt::create(['user_id' => $user->id, 'type' => fake()->randomElement(['given', 'taken']), 'person_name' => fake()->name(), 'original_amount' => 50000, 'remaining_amount' => fake()->numberBetween(5000, 50000), 'due_date' => now()->addDays($i * 7), 'status' => 'active']);
        }

        foreach (range(1, 10) as $i) {
            RecurringTransaction::create(['user_id' => $user->id, 'title' => fake()->words(3, true), 'amount' => fake()->numberBetween(1000, 40000), 'type' => fake()->randomElement(['income', 'expense']), 'category_id' => $leafCategories->random()->id, 'account_id' => $accounts->random()->id, 'frequency' => 'monthly', 'next_due_date' => now()->addDays($i * 3)]);
        }

        foreach (range(1, 5) as $i) {
            Reminder::create(['user_id' => $user->id, 'title' => fake()->words(3, true), 'amount' => fake()->numberBetween(1000, 30000), 'due_date' => now()->addDays($i * 4), 'frequency' => 'once', 'notify_before_days' => 2]);
        }
    }
}
