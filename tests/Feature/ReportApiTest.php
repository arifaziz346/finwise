<?php

namespace Tests\Feature;

use App\Models\ExpenseCategory;
use App\Models\ExpenseRecord;
use App\Models\IncomeRecord;
use App\Models\IncomeSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReportApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ExpenseCategory $food;

    private ExpenseCategory $fuel;

    private IncomeSource $salary;

    protected function setUp(): void
    {
        parent::setUp();

        // Frozen "today" so every relative preset resolves to a known window.
        Carbon::setTestNow(Carbon::parse('2026-06-30 12:00:00'));

        $this->user = User::factory()->create(['currency' => 'PKR']);

        $this->food = ExpenseCategory::create(['user_id' => $this->user->id, 'name' => 'Food']);
        $this->fuel = ExpenseCategory::create(['user_id' => $this->user->id, 'name' => 'Fuel']);
        $this->salary = IncomeSource::create(['user_id' => $this->user->id, 'name' => 'Salary']);

        // Last month (May 2026): income 100,000 / expenses 30,000
        $this->income('2026-05-10', 100000);
        $this->expense('2026-05-05', 20000, $this->food);
        $this->expense('2026-05-20', 10000, $this->fuel);

        // This month (June 2026): income 50,000 / expenses 60,000
        $this->income('2026-06-10', 30000);
        $this->income('2026-06-20', 20000);
        $this->expense('2026-06-02', 50000, $this->food);
        $this->expense('2026-06-15', 7000, $this->fuel);
        $this->expense('2026-06-25', 3000, $this->food);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_summary_and_deltas_are_scoped_to_the_selected_month(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/reports/overview?preset=this_month&granularity=daily')
            ->assertOk();

        $response->assertJsonPath('summary.income', 50000)
            ->assertJsonPath('summary.expenses', 60000)
            ->assertJsonPath('summary.net', -10000)
            ->assertJsonPath('summary.income_count', 2)
            ->assertJsonPath('summary.expense_count', 3)
            ->assertJsonPath('summary.savings_rate', -20)
            ->assertJsonPath('period.from', '2026-06-01')
            ->assertJsonPath('period.to', '2026-06-30')
            ->assertJsonPath('period.granularity', 'daily');

        // Previous equal-length window is May 1-31.
        $response->assertJsonPath('previous.income', 100000)
            ->assertJsonPath('previous.expenses', 30000);
    }

    public function test_monthly_series_fills_the_gaps_between_recorded_months(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/reports/overview?preset=last_3_months&granularity=monthly')
            ->assertOk();

        $series = $response->json('series');

        $this->assertCount(3, $series);
        $this->assertSame(['2026-04', '2026-05', '2026-06'], array_column($series, 'key'));

        // April is an empty gap and must still appear with zeroes.
        $this->assertSame(0.0, (float) $series[0]['income']);
        $this->assertSame('April 2026', $series[0]['full_label']);

        $this->assertSame(100000.0, (float) $series[1]['income']);
        $this->assertSame(30000.0, (float) $series[1]['expenses']);
        $this->assertSame(70000.0, (float) $series[1]['net']);

        // Running net accumulates across every bucket, including empty ones:
        // April 0, +70,000 in May, -10,000 in June.
        $this->assertSame(60000.0, (float) $series[2]['running_net']);
    }

    public function test_yearly_and_weekly_granularity_bucket_correctly(): void
    {
        $yearly = $this->actingAs($this->user)
            ->getJson('/api/v1/reports/overview?preset=this_year&granularity=yearly')
            ->assertOk();

        $this->assertCount(1, $yearly->json('series'));
        $this->assertSame('2026', $yearly->json('series.0.key'));
        $this->assertSame(150000.0, (float) $yearly->json('series.0.income'));
        $this->assertSame(90000.0, (float) $yearly->json('series.0.expenses'));

        $weekly = $this->actingAs($this->user)
            ->getJson('/api/v1/reports/overview?preset=last_12_months&granularity=weekly')
            ->assertOk();

        $this->assertNotEmpty($weekly->json('series'));

        // Every bucket key must be a week start (Monday).
        foreach ($weekly->json('series') as $row) {
            $this->assertSame('1', Carbon::parse($row['key'])->format('N'), "Bucket {$row['key']} is not a Monday");
        }
    }

    public function test_category_breakdown_supports_an_honest_share_and_currency_filter(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/reports/overview?preset=all&granularity=monthly')
            ->assertOk();

        $breakdown = collect($response->json('category_breakdown'));

        $food = $breakdown->firstWhere('category', 'Food');
        $fuel = $breakdown->firstWhere('category', 'Fuel');

        $this->assertSame(73000.0, (float) $food['total']);
        $this->assertSame(3, (int) $food['entries']);
        $this->assertSame(17000.0, (float) $fuel['total']);

        // Shares must add up to ~100% of the filtered total.
        $this->assertEqualsWithDelta(100.0, $breakdown->sum('share'), 0.5);

        $filtered = $this->actingAs($this->user)
            ->getJson('/api/v1/reports/overview?preset=all&granularity=monthly&category_id=' . $this->fuel->id)
            ->assertOk();

        // A category filter narrows expenses; income is reported in full because
        // expense categories say nothing about where income came from.
        $this->assertSame(17000.0, (float) $filtered->json('summary.expenses'));
        $this->assertSame(150000.0, (float) $filtered->json('summary.income'));
        $this->assertCount(1, $filtered->json('category_breakdown'));
        $this->assertSame('Fuel', $filtered->json('category_breakdown.0.category'));
    }

    public function test_income_source_filter_narrows_income_only(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/reports/overview?preset=all&granularity=monthly&source_id=' . $this->salary->id)
            ->assertOk();

        $this->assertSame(150000.0, (float) $response->json('summary.income'));
        // Expenses are independent of the income-source filter.
        $this->assertSame(90000.0, (float) $response->json('summary.expenses'));
    }

    public function test_blocked_entries_are_excluded_unless_requested(): void
    {
        $this->expense('2026-06-28', 99000, $this->food, blocked: true);

        $this->actingAs($this->user)
            ->getJson('/api/v1/reports/overview?preset=this_month')
            ->assertOk()
            ->assertJsonPath('summary.expenses', 60000);

        $this->actingAs($this->user)
            ->getJson('/api/v1/reports/overview?preset=this_month&include_blocked=1')
            ->assertOk()
            ->assertJsonPath('summary.expenses', 159000);
    }

    public function test_insights_explain_the_overspend_and_the_biggest_category(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/reports/overview?preset=this_month&granularity=daily')
            ->assertOk();

        $insights = collect($response->json('insights'));
        $keys = $insights->pluck('key')->all();

        $this->assertContains('savings', $keys);
        $this->assertContains('top_category', $keys);

        $savings = $insights->firstWhere('key', 'savings');
        $this->assertSame('danger', $savings['severity']);
        $this->assertStringContainsString('Spending more than you earn', $savings['title']);

        // Food (53,000) is 88% of June spending, so a 10% cut is quantified.
        $category = $insights->firstWhere('key', 'top_category');
        $this->assertStringContainsString('Food', $category['message']);
        $this->assertStringContainsString('5,300.00', $category['message']);
    }

    public function test_empty_period_returns_an_actionable_hint_instead_of_zeroes(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/reports/overview?preset=last_year')
            ->assertOk();

        $response->assertJsonPath('summary.has_data', false)
            ->assertJsonPath('period.is_empty', true);

        $this->assertSame('empty', $response->json('insights.0.key'));
        $this->assertStringContainsString('All time', $response->json('insights.0.message'));
    }

    public function test_automatic_period_falls_back_to_the_window_with_data(): void
    {
        // No preset and no dates: the engine reports that it chose the window and
        // must surface the data that actually exists rather than an empty month.
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/reports/overview')
            ->assertOk();

        $response->assertJsonPath('period.auto', true)
            ->assertJsonPath('period.preset', 'auto')
            ->assertJsonPath('summary.has_data', true);

        // Today is June 2026 with June data, so the recent six-month window wins.
        $response->assertJsonPath('period.from', '2026-01-01')
            ->assertJsonPath('period.to', '2026-06-30')
            ->assertJsonPath('summary.income', 150000);

        // With a user whose newest data is months old, the window follows the data.
        $stale = User::factory()->create();
        $source = IncomeSource::create(['user_id' => $stale->id, 'name' => 'Old']);
        IncomeRecord::create([
            'user_id' => $stale->id,
            'source_id' => $source->id,
            'account_id' => $this->defaultAccount($stale->id),
            'amount' => 5000,
            'currency' => 'PKR',
            'received_date' => '2024-02-05',
        ]);

        $this->actingAs($stale)
            ->getJson('/api/v1/reports/overview')
            ->assertOk()
            ->assertJsonPath('period.to', '2024-02-29')
            ->assertJsonPath('period.from', '2023-09-01')
            ->assertJsonPath('summary.income', 5000);
    }

    public function test_invalid_preset_and_granularity_are_rejected(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/v1/reports/overview?preset=nonsense')
            ->assertStatus(422)
            ->assertJsonValidationErrors('preset');

        $this->actingAs($this->user)
            ->getJson('/api/v1/reports/overview?preset=this_month&granularity=hourly')
            ->assertStatus(422)
            ->assertJsonValidationErrors('granularity');

        $this->actingAs($this->user)
            ->getJson('/api/v1/reports/overview?preset=custom')
            ->assertStatus(422)
            ->assertJsonValidationErrors('from');
    }

    public function test_exports_are_generated_for_the_filtered_period(): void
    {
        $csv = $this->actingAs($this->user)
            ->get('/api/v1/reports/export/csv?preset=this_month&granularity=monthly')
            ->assertOk();

        $this->assertStringContainsString('text/csv', $csv->headers->get('content-type'));
        $this->assertStringContainsString('June 2026', $csv->getContent());
        $this->assertStringContainsString('Summary', $csv->getContent());
        $this->assertStringContainsString('Expenses by category', $csv->getContent());

        $excel = $this->actingAs($this->user)
            ->get('/api/v1/reports/export/excel?preset=this_month')
            ->assertOk();

        $this->assertStringContainsString('spreadsheetml', $excel->headers->get('content-type'));

        $this->actingAs($this->user)
            ->get('/api/v1/reports/export/pdf?preset=this_month')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_filters_endpoint_lists_user_options(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/reports/filters')
            ->assertOk();

        $names = array_column($response->json('categories'), 'name');

        $this->assertContains('Food', $names);
        $this->assertContains('Fuel', $names);
        $this->assertSame('Salary', $response->json('sources.0.name'));
        $this->assertContains('last_6_months', $response->json('presets'));
    }

    public function test_reports_require_authentication(): void
    {
        $this->getJson('/api/v1/reports/overview')->assertUnauthorized();
        $this->getJson('/api/v1/reports/dashboard')->assertUnauthorized();
        $this->getJson('/api/v1/reports/export/pdf')->assertUnauthorized();
    }

    // ------------------------------------------------------------------ helpers

    private function income(string $date, float $amount): IncomeRecord
    {
        return IncomeRecord::create([
            'user_id' => $this->user->id,
            'source_id' => $this->salary->id,
            'account_id' => $this->defaultAccount($this->user->id),
            'amount' => $amount,
            'currency' => 'PKR',
            'received_date' => $date,
            'description' => 'Income on ' . $date,
        ]);
    }

    private function expense(string $date, float $amount, ExpenseCategory $category, bool $blocked = false): ExpenseRecord
    {
        return ExpenseRecord::create([
            'user_id' => $this->user->id,
            'category_id' => $category->id,
            'account_id' => $this->defaultAccount($this->user->id),
            'amount' => $amount,
            'currency' => 'PKR',
            'expense_date' => $date,
            'description' => 'Expense on ' . $date,
            'is_blocked' => $blocked,
        ]);
    }

    private function defaultAccount(int $userId): int
    {
        return \App\Models\Account::firstOrCreate(
            ['user_id' => $userId, 'name' => 'Default'],
            ['type' => 'cash', 'balance' => 0, 'currency' => 'PKR']
        )->id;
    }
}
