<?php

namespace Tests\Feature;

use App\Models\ExpenseCategory;
use App\Models\ExpenseRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Covers the quick-entry form: the suggestion payload the sheet renders and the
 * fields the sheet is allowed to save.
 */
class ExpenseEntryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ExpenseCategory $fuel;

    private ExpenseCategory $food;

    private ExpenseCategory $rent;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-06-30 12:00:00'));

        $this->user = User::factory()->create(['currency' => 'PKR']);
        $this->fuel = $this->category('Fuel');
        $this->food = $this->category('Food');
        $this->rent = $this->category('Rent');

        // Fuel is used most often, Food most recently.
        foreach (['2026-06-01', '2026-06-05', '2026-06-09'] as $date) {
            $this->expense($date, 500, $this->fuel);
        }
        $this->expense('2026-06-28', 1200, $this->food, ['payment_method' => 'card', 'location' => 'Peshawar']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_form_options_rank_categories_by_usage_and_recency(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/expenses/form-options')
            ->assertOk();

        $names = array_column($response->json('suggestions'), 'name');

        // Fuel has the most entries, Food was used most recently.
        $this->assertSame(['Fuel', 'Food'], array_slice($names, 0, 2));

        $fuel = $response->json('suggestions.0');
        $this->assertSame(3, $fuel['times_used']);
        $this->assertSame('2026-06-09', $fuel['last_used_at']);
        $this->assertNotEmpty($fuel['used_label']);
        $this->assertNotEmpty($fuel['color']);

        // Rent has never been used, so it is offered in the full list only.
        $this->assertContains('Rent', array_column($response->json('categories'), 'name'));
        $this->assertNotContains('Rent', $names);
        $this->assertSame(3, $response->json('total_categories'));
    }

    public function test_form_options_supply_smart_defaults(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/expenses/form-options')
            ->assertOk();

        $defaults = $response->json('defaults');

        // Most used category is pre-selected, and the last payment method and
        // place are remembered from the previous entry.
        $this->assertSame($this->fuel->id, $defaults['category_id']);
        $this->assertSame('card', $defaults['payment_method']);
        $this->assertSame('Peshawar', $defaults['location']);
        $this->assertSame('2026-06-30', $defaults['expense_date']);
        $this->assertContains('cheque', $response->json('payment_methods'));
    }

    public function test_form_options_fall_back_gracefully_for_a_new_user(): void
    {
        $fresh = User::factory()->create();
        $first = ExpenseCategory::create(['user_id' => $fresh->id, 'name' => 'Alpha']);

        $response = $this->actingAs($fresh)
            ->getJson('/api/v1/expenses/form-options')
            ->assertOk();

        $this->assertSame([], $response->json('suggestions'));
        $this->assertSame($first->id, $response->json('defaults.category_id'));
        $this->assertSame('cash', $response->json('defaults.payment_method'));
        $this->assertNull($response->json('defaults.location'));
    }

    public function test_blocked_categories_never_appear_in_suggestions(): void
    {
        $blocked = $this->category('Old Habit', blocked: true);
        $this->expense('2026-06-20', 900, $blocked);
        $blocked->update(['is_blocked' => true]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/expenses/form-options')
            ->assertOk();

        $this->assertNotContains('Old Habit', array_column($response->json('suggestions'), 'name'));
        $this->assertNotContains('Old Habit', array_column($response->json('categories'), 'name'));
    }

    public function test_quick_entry_saves_payment_method_location_and_note(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/expenses', [
                'amount' => 1850.75,
                'category_id' => $this->fuel->id,
                'expense_date' => '2026-06-29',
                'description' => 'Filled the tank',
                'payment_method' => 'card',
                'location' => 'Ring Road',
            ])
            ->assertCreated();

        $record = ExpenseRecord::findOrFail($response->json('data.id'));

        // Regression: payment_method used to be overwritten with "cash".
        $this->assertSame('card', $record->payment_method);
        $this->assertSame('Ring Road', $record->location);
        $this->assertSame('Filled the tank', $record->description);
        $this->assertSame('2026-06-29', $record->expense_date->toDateString());
        $this->assertEquals(1850.75, (float) $record->amount);
        $this->assertSame('PKR', $record->currency);
        $this->assertNotNull($record->account_id);
        $this->assertFalse((bool) $record->is_blocked);
    }

    public function test_quick_entry_defaults_to_cash_when_no_method_is_sent(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/expenses', [
                'amount' => 300,
                'category_id' => $this->food->id,
                'expense_date' => '2026-06-29',
            ])
            ->assertCreated();

        $this->assertSame('cash', ExpenseRecord::findOrFail($response->json('data.id'))->payment_method);
    }

    public function test_editing_updates_only_the_fields_sent(): void
    {
        $record = $this->expense('2026-06-10', 700, $this->food, ['payment_method' => 'cash', 'location' => 'Home']);

        $this->actingAs($this->user)
            ->putJson("/api/v1/expenses/{$record->id}", ['amount' => 750, 'payment_method' => 'online'])
            ->assertOk();

        $record->refresh();

        $this->assertEquals(750.0, (float) $record->amount);
        $this->assertSame('online', $record->payment_method);
        // Untouched fields survive a partial update.
        $this->assertSame('Home', $record->location);
        $this->assertSame($this->food->id, $record->category_id);
    }

    public function test_invalid_method_and_unknown_category_are_rejected(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/v1/expenses', [
                'amount' => 10,
                'category_id' => $this->food->id,
                'expense_date' => '2026-06-29',
                'payment_method' => 'bitcoin',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('payment_method');

        $this->actingAs($this->user)
            ->postJson('/api/v1/expenses', [
                'amount' => 10,
                'category_id' => 999999,
                'expense_date' => '2026-06-29',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('category_id');
    }

    public function test_another_users_category_cannot_be_used(): void
    {
        $stranger = User::factory()->create();
        $theirs = ExpenseCategory::create(['user_id' => $stranger->id, 'name' => 'Not yours']);

        $this->actingAs($this->user)
            ->postJson('/api/v1/expenses', [
                'amount' => 10,
                'category_id' => $theirs->id,
                'expense_date' => '2026-06-29',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('category_id');
    }

    public function test_form_options_requires_authentication(): void
    {
        $this->getJson('/api/v1/expenses/form-options')->assertUnauthorized();
    }

    // ------------------------------------------------------------------ helpers

    private function category(string $name, bool $blocked = false): ExpenseCategory
    {
        return ExpenseCategory::create([
            'user_id' => $this->user->id,
            'name' => $name,
            'color' => '#6366F1',
            'is_blocked' => $blocked,
        ]);
    }

    private function expense(string $date, float $amount, ExpenseCategory $category, array $extra = []): ExpenseRecord
    {
        return ExpenseRecord::create(array_merge([
            'user_id' => $this->user->id,
            'category_id' => $category->id,
            'account_id' => $this->defaultAccount(),
            'amount' => $amount,
            'currency' => 'PKR',
            'expense_date' => $date,
        ], $extra));
    }

    private function defaultAccount(): int
    {
        return \App\Models\Account::firstOrCreate(
            ['user_id' => $this->user->id, 'name' => 'Default'],
            ['type' => 'cash', 'balance' => 0, 'currency' => 'PKR']
        )->id;
    }
}
