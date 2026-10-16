<?php

namespace App\Support\Reports;

use App\Models\Budget;
use App\Models\ExpenseCategory;
use App\Models\ExpenseRecord;
use App\Models\IncomeRecord;
use App\Models\IncomeSource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Single source of truth for every date/period/category calculation used by the
 * dashboard and the reports screens.
 *
 * The frontend only ever sends: preset | from | to | granularity | filters.
 * Every resolved value (bounds, bucket list, labels) is echoed back in the
 * "period" block so the UI never has to re-derive anything.
 */
class ReportQuery
{
    public const PRESETS = [
        'today', 'yesterday', 'this_week', 'last_week', 'this_month', 'last_month',
        'last_3_months', 'last_6_months', 'last_12_months', 'this_quarter',
        'this_year', 'last_year', 'all', 'custom',
    ];

    public const GRANULARITIES = ['auto', 'daily', 'weekly', 'monthly', 'quarterly', 'yearly'];

    /** Hard ceiling so a huge range can never build an unbounded bucket list. */
    private const MAX_BUCKETS = 800;

    public readonly CarbonImmutable $from;

    public readonly CarbonImmutable $to;

    public readonly string $granularity;

    public readonly string $preset;

    public readonly bool $autoRange;

    /** @var array<string, mixed> */
    public readonly array $filters;

    public function __construct(private readonly User $user, Request $request)
    {
        $this->autoRange = $request->filled('preset') === false
            && $request->filled('from') === false
            && $request->filled('to') === false;

        $this->preset = $this->autoRange ? 'auto' : (string) $request->query('preset', 'this_month');

        // Filters must exist before the bounds are resolved: the smart-default and
        // "all time" branches already use the filtered income/expense queries.
        $this->filters = [
            'category_id' => $request->filled('category_id') ? (int) $request->query('category_id') : null,
            'source_id' => $request->filled('source_id') ? (int) $request->query('source_id') : null,
            'search' => $request->filled('search') ? trim((string) $request->query('search')) : null,
            'group_parent' => filter_var($request->query('group_parent', false), FILTER_VALIDATE_BOOL),
            'include_blocked' => filter_var($request->query('include_blocked', false), FILTER_VALIDATE_BOOL),
        ];

        [$from, $to] = $this->resolveBounds($request);

        $this->from = $from->startOfDay();
        $this->to = $to->endOfDay();

        $this->granularity = $this->resolveGranularity((string) $request->query('granularity', 'auto'));
    }

    /**
     * Resolve the requested window. When the caller sends no dates at all we pick
     * the most recent window that actually contains data, so the dashboard is
     * never a wall of zeroes.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function resolveBounds(Request $request): array
    {
        if ($this->autoRange) {
            return $this->smartDefaultBounds();
        }

        $preset = $this->preset;
        $today = CarbonImmutable::now()->startOfDay();

        $bounds = match ($preset) {
            'today' => [$today, $today],
            'yesterday' => [$today->subDay(), $today->subDay()],
            'this_week' => [$today->startOfWeek(), $today->endOfWeek()],
            'last_week' => [$today->subWeek()->startOfWeek(), $today->subWeek()->endOfWeek()],
            'this_month' => [$today->startOfMonth(), $today->endOfMonth()],
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            'last_3_months' => [$today->subMonthsNoOverflow(2)->startOfMonth(), $today->endOfMonth()],
            'last_6_months' => [$today->subMonthsNoOverflow(5)->startOfMonth(), $today->endOfMonth()],
            'last_12_months' => [$today->subMonthsNoOverflow(11)->startOfMonth(), $today->endOfMonth()],
            'this_quarter' => [$today->startOfQuarter(), $today->endOfQuarter()],
            'this_year' => [$today->startOfYear(), $today->endOfYear()],
            'last_year' => [$today->subYear()->startOfYear(), $today->subYear()->endOfYear()],
            'all' => [$this->earliestActivity() ?? $today->subMonthsNoOverflow(11), $today],
            'custom' => $this->customBounds($request),
            default => throw ValidationException::withMessages([
                'preset' => 'Unknown period preset. Use one of: ' . implode(', ', self::PRESETS) . '.',
            ]),
        };

        return $this->clamp([$bounds[0], $bounds[1]]);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function customBounds(Request $request): array
    {
        $dateFrom = $request->query('from');
        $dateTo = $request->query('to') ?: $dateFrom;

        if (! $dateFrom) {
            throw ValidationException::withMessages(['from' => 'A start date is required for a custom range.']);
        }

        try {
            $from = CarbonImmutable::parse($dateFrom)->startOfDay();
            $to = CarbonImmutable::parse($dateTo)->endOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['from' => 'The date range could not be read.']);
        }

        if ($to->lessThan($from)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }

        return [$from, $to];
    }

    /**
     * Default window: the last six months when they hold data, otherwise the
     * window ending on the newest record, so real history is always visible.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function smartDefaultBounds(): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $recent = [$today->subMonthsNoOverflow(5)->startOfMonth(), $today->endOfMonth()];

        if ($this->hasActivity($recent[0], $recent[1])) {
            return $recent;
        }

        $latest = $this->latestActivity();

        if ($latest === null) {
            return $recent;
        }

        $fallback = [$latest->subMonthsNoOverflow(5)->startOfMonth(), $latest->endOfMonth()];

        return $this->hasActivity($fallback[0], $fallback[1]) ? $fallback : [$this->earliestActivity() ?? $fallback[0], $latest];
    }

    /**
     * Guard against absurd input (e.g. year 1000 – year 3000) without changing
     * the user's stated intent.
     *
     * @param  array{0: CarbonImmutable, 1: CarbonImmutable}  $bounds
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function clamp(array $bounds): array
    {
        [$from, $to] = $bounds;

        if ($to->lessThan($from)) {
            return [$to->startOfDay(), $from->endOfDay()];
        }

        return [$from, $to];
    }

    private function resolveGranularity(string $requested): string
    {
        if (! in_array($requested, self::GRANULARITIES, true)) {
            throw ValidationException::withMessages([
                'granularity' => 'Unknown granularity. Use one of: ' . implode(', ', self::GRANULARITIES) . '.',
            ]);
        }

        if ($requested !== 'auto') {
            return $requested;
        }

        $days = (int) $this->from->diffInDays($this->to);

        return match (true) {
            $days <= 62 => 'daily',
            $days <= 181 => 'weekly',
            $days <= 1096 => 'monthly',
            default => 'yearly',
        };
    }

    private function hasActivity(CarbonImmutable $from, CarbonImmutable $to): bool
    {
        return $this->incomeQuery($from, $to)->exists() || $this->expenseQuery($from, $to)->exists();
    }

    private function latestActivity(): ?CarbonImmutable
    {
        $income = IncomeRecord::query()->where('user_id', $this->user->id)->max('received_date');
        $expense = ExpenseRecord::query()->where('user_id', $this->user->id)->max('expense_date');
        $latest = collect([$income, $expense])->filter()->max();

        return $latest ? CarbonImmutable::parse($latest) : null;
    }

    private function earliestActivity(): ?CarbonImmutable
    {
        $income = IncomeRecord::query()->where('user_id', $this->user->id)->min('received_date');
        $expense = ExpenseRecord::query()->where('user_id', $this->user->id)->min('expense_date');
        $earliest = collect([$income, $expense])->filter()->min();

        return $earliest ? CarbonImmutable::parse($earliest) : null;
    }

    // ---------------------------------------------------------------- payload

    /** Everything the dashboard and report header need in one response. */
    public function overview(): array
    {
        return [
            'period' => $this->periodPayload(),
            'summary' => $this->summary(),
            'previous' => $this->previousSummary(),
            'series' => $this->series(),
            'category_breakdown' => $this->categoryBreakdown(),
            'source_breakdown' => $this->sourceBreakdown(),
            'top_expenses' => $this->topExpenses(),
            'top_income' => $this->topIncome(),
            'budgets' => $this->budgetProgress(),
            'accounts' => $this->accounts(),
            'recent' => $this->recentTransactions(),
        ];
    }

    public function periodPayload(): array
    {
        return [
            'preset' => $this->preset,
            'auto' => $this->autoRange,
            'granularity' => $this->granularity,
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
            'label' => $this->rangeLabel(),
            'days' => (int) $this->from->diffInDays($this->to) + 1,
            'bucket_label' => match ($this->granularity) {
                'daily' => 'Day',
                'weekly' => 'Week',
                'quarterly' => 'Quarter',
                'yearly' => 'Year',
                default => 'Month',
            },
            'is_empty' => $this->isEmpty(),
            'filters' => $this->filters,
            'available' => [
                'presets' => self::PRESETS,
                'granularities' => self::GRANULARITIES,
            ],
        ];
    }

    private function isEmpty(): bool
    {
        return (float) $this->incomeQuery()->sum('amount') === 0.0
            && (float) $this->expenseQuery()->sum('amount') === 0.0;
    }

    public function rangeLabel(): string
    {
        if ($this->from->isSameDay($this->to)) {
            return $this->from->format('M j, Y');
        }

        if ($this->from->year === $this->to->year) {
            return $this->from->format('M j') . ' – ' . $this->to->format('M j, Y');
        }

        return $this->from->format('M j, Y') . ' – ' . $this->to->format('M j, Y');
    }

    // ---------------------------------------------------------------- queries

    private function incomeQuery(?CarbonImmutable $from = null, ?CarbonImmutable $to = null)
    {
        $from ??= $this->from;
        $to ??= $this->to;

        return IncomeRecord::query()
            ->where('user_id', $this->user->id)
            ->when(! $this->filters['include_blocked'], fn ($q) => $q->where('is_blocked', false))
            ->whereDate('received_date', '>=', $from->toDateString())
            ->whereDate('received_date', '<=', $to->toDateString())
            ->when($this->filters['source_id'], fn ($q, $id) => $q->where('source_id', $id))
            ->when($this->filters['search'], fn ($q, $term) => $q->where('description', 'like', "%{$term}%"));
    }

    private function expenseQuery(?CarbonImmutable $from = null, ?CarbonImmutable $to = null)
    {
        $from ??= $this->from;
        $to ??= $this->to;

        return ExpenseRecord::query()
            ->where('user_id', $this->user->id)
            ->when(! $this->filters['include_blocked'], fn ($q) => $q->where('is_blocked', false))
            ->whereDate('expense_date', '>=', $from->toDateString())
            ->whereDate('expense_date', '<=', $to->toDateString())
            ->when($this->filters['category_id'], fn ($q, $id) => $q->whereIn('category_id', $this->categoryIdsWithChildren($id)))
            ->when($this->filters['search'], fn ($q, $term) => $q->where('description', 'like', "%{$term}%"));
    }

    /** Selecting a parent category also matches its children. */
    public function categoryIdsWithChildren(int $categoryId): array
    {
        $ids = collect([$categoryId]);
        $frontier = collect([$categoryId]);

        for ($depth = 0; $depth < 4 && $frontier->isNotEmpty(); $depth++) {
            $frontier = ExpenseCategory::query()
                ->whereIn('parent_id', $frontier->all())
                ->pluck('id');
            $ids = $ids->merge($frontier);
        }

        return $ids->unique()->values()->all();
    }

    // ---------------------------------------------------------------- summary

    public function summary(?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array
    {
        $from ??= $this->from;
        $to ??= $this->to;

        $income = (float) $this->incomeQuery($from, $to)->sum('amount');
        $expenses = (float) $this->expenseQuery($from, $to)->sum('amount');

        [$budgetLimit, $budgetRows] = $this->budgetTotals($from, $to);
        [$pendingTotal, $pendingCount] = $this->pendingTotals($from, $to);

        $days = (int) $from->diffInDays($to) + 1;
        $net = $income - $expenses;
        $today = CarbonImmutable::now();

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'days' => $days,
            'income' => round($income, 2),
            'expenses' => round($expenses, 2),
            'net' => round($net, 2),
            'savings_rate' => $income > 0 ? round(($net / $income) * 100, 1) : null,
            'expense_ratio' => $income > 0 ? round(($expenses / $income) * 100, 1) : null,
            'avg_daily_income' => round($income / $days, 2),
            'avg_daily_expense' => round($expenses / $days, 2),
            'avg_per_bucket' => round($income / max($days, 1), 2),
            'income_count' => $this->incomeQuery($from, $to)->count(),
            'expense_count' => $this->expenseQuery($from, $to)->count(),
            'budget_limit' => round($budgetLimit, 2),
            'budget_spent' => round($expenses, 2),
            'budget_used_percent' => $budgetLimit > 0 ? round(($expenses / $budgetLimit) * 100, 1) : null,
            'budget_remaining' => round($budgetLimit - $expenses, 2),
            'budget_rows' => $budgetRows->count(),
            'pending_total' => round($pendingTotal, 2),
            'pending_count' => $pendingCount,
            'is_future_period' => $from->greaterThan($today->endOfDay()),
            'has_data' => $income > 0 || $expenses > 0,
            'saved' => round(max($net, 0), 2),
            // Kept for backwards compatibility with the first dashboard build.
            'monthly_income' => round($income, 2),
            'monthly_expenses' => round($expenses, 2),
            'remaining_balance' => round($net, 2),
        ];
    }

    public function previousSummary(): ?array
    {
        $days = (int) $this->from->diffInDays($this->to) + 1;
        $previousTo = $this->from->subDay()->endOfDay();
        $previousFrom = $previousTo->subDays($days - 1)->startOfDay();

        if (! $this->hasActivity($previousFrom, $previousTo)) {
            return null;
        }

        $income = (float) $this->incomeQuery($previousFrom, $previousTo)->sum('amount');
        $expenses = (float) $this->expenseQuery($previousFrom, $previousTo)->sum('amount');
        $net = $income - $expenses;

        return [
            'from' => $previousFrom->toDateString(),
            'to' => $previousTo->toDateString(),
            'income' => round($income, 2),
            'expenses' => round($expenses, 2),
            'net' => round($net, 2),
            'savings_rate' => $income > 0 ? round(($net / $income) * 100, 1) : null,
            'label' => $previousFrom->format('M j') . ' – ' . $previousTo->format('M j, Y'),
        ];
    }

    // ----------------------------------------------------------------- series

    /** @return array<int, array<string, mixed>> */
    public function series(): array
    {
        $bucketKeys = $this->buckets()->keys();
        $filled = [];

        foreach ($bucketKeys as $key) {
            $filled[$key] = ['income' => 0.0, 'expenses' => 0.0];
        }

        $this->incomeQuery()
            ->selectRaw('received_date as bucket_date, amount')
            ->get()
            ->each(function ($row) use (&$filled) {
                $key = $this->bucketKey(CarbonImmutable::parse($row->bucket_date));
                if (isset($filled[$key])) {
                    $filled[$key]['income'] += (float) $row->amount;
                }
            });

        $this->expenseQuery()
            ->selectRaw('expense_date as bucket_date, amount')
            ->get()
            ->each(function ($row) use (&$filled) {
                $key = $this->bucketKey(CarbonImmutable::parse($row->bucket_date));
                if (isset($filled[$key])) {
                    $filled[$key]['expenses'] += (float) $row->amount;
                }
            });

        $running = 0.0;
        $series = [];

        foreach ($bucketKeys as $key) {
            $key = (string) $key;
            $income = round($filled[$key]['income'], 2);
            $expenses = round($filled[$key]['expenses'], 2);
            $running += $income - $expenses;

            $series[] = [
                'key' => $key,
                'label' => $this->bucketLabel($key),
                'full_label' => $this->bucketFullLabel($key),
                'income' => $income,
                'expenses' => $expenses,
                'net' => round($income - $expenses, 2),
                'running_net' => round($running, 2),
                'savings_rate' => $income > 0 ? round((($income - $expenses) / $income) * 100, 1) : null,
            ];
        }

        return $series;
    }

    /** Ordered list of every bucket in the range, gaps included. */
    public function buckets(): Collection
    {
        $keys = collect();
        $cursor = $this->bucketStart($this->from);
        $guard = 0;

        while ($cursor->lessThanOrEqualTo($this->to) && $guard < self::MAX_BUCKETS) {
            $keys->push($this->bucketKey($cursor));
            $cursor = $this->advance($cursor);
            $guard++;
        }

        if ($keys->isEmpty()) {
            $keys->push($this->bucketKey($this->from));
        }

        return $keys->mapWithKeys(fn ($key) => [$key => $this->bucketLabel($key)]);
    }

    private function advance(CarbonImmutable $cursor): CarbonImmutable
    {
        return match ($this->granularity) {
            'daily' => $cursor->addDay(),
            'weekly' => $cursor->addWeek(),
            'quarterly' => $cursor->addQuarter(),
            'yearly' => $cursor->addYear(),
            default => $cursor->addMonthNoOverflow(),
        };
    }

    private function bucketStart(CarbonImmutable $date): CarbonImmutable
    {
        return match ($this->granularity) {
            'daily' => $date->startOfDay(),
            'weekly' => $date->startOfWeek(),
            'quarterly' => $date->startOfQuarter(),
            'yearly' => $date->startOfYear(),
            default => $date->startOfMonth(),
        };
    }

    public function bucketKey(CarbonImmutable $date): string
    {
        return match ($this->granularity) {
            'daily' => $date->format('Y-m-d'),
            'weekly' => $date->startOfWeek()->format('Y-m-d'),
            'quarterly' => $date->format('Y') . '-Q' . $date->quarter,
            'yearly' => $date->format('Y'),
            default => $date->format('Y-m'),
        };
    }

    public function bucketLabel(string $key): string
    {
        return match ($this->granularity) {
            'daily' => CarbonImmutable::parse($key)->format('M j'),
            'weekly' => 'Wk ' . CarbonImmutable::parse($key)->format('M j'),
            'quarterly' => $key,
            'yearly' => $key,
            default => CarbonImmutable::parse($key . '-01')->format('M Y'),
        };
    }

    public function bucketFullLabel(string $key): string
    {
        return match ($this->granularity) {
            'daily' => CarbonImmutable::parse($key)->format('D, M j, Y'),
            'weekly' => 'Week of ' . CarbonImmutable::parse($key)->format('M j, Y'),
            'quarterly' => $this->quarterFullLabel($key),
            'yearly' => 'Year ' . $key,
            default => CarbonImmutable::parse($key . '-01')->format('F Y'),
        };
    }

    private function quarterFullLabel(string $key): string
    {
        [$year, $quarter] = explode('-Q', $key);
        $start = CarbonImmutable::parse($year . '-01-01')->addQuartersNoOverflow((int) $quarter - 1);

        return $start->format('M') . ' – ' . $start->addMonthsNoOverflow(2)->format('M Y');
    }

    // -------------------------------------------------------------- breakdowns

    /** @return array<int, array<string, mixed>> */
    public function categoryBreakdown(): array
    {
        $rows = $this->expenseQuery()
            ->selectRaw('category_id, sum(amount) as total, count(*) as entries')
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->get();

        $categories = ExpenseCategory::query()
            ->whereIn('id', $rows->pluck('category_id')->filter()->unique()->all())
            ->get(['id', 'name', 'color', 'parent_id'])
            ->keyBy('id');

        $total = (float) $rows->sum('total');

        return $rows->map(function ($row) use ($categories, $total) {
            $category = $categories->get($row->category_id);
            $amount = round((float) $row->total, 2);

            return [
                'category_id' => $row->category_id,
                'category' => $category?->name ?? 'Uncategorized',
                'color' => $category?->color ?? '#94A3B8',
                'total' => $amount,
                'entries' => (int) $row->entries,
                'share' => $total > 0 ? round(($amount / $total) * 100, 1) : 0.0,
            ];
        })->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function sourceBreakdown(): array
    {
        $rows = $this->incomeQuery()
            ->selectRaw('source_id, sum(amount) as total, count(*) as entries')
            ->groupBy('source_id')
            ->orderByDesc('total')
            ->get();

        $sources = IncomeSource::query()
            ->whereIn('id', $rows->pluck('source_id')->filter()->unique()->all())
            ->get(['id', 'name'])
            ->keyBy('id');

        $total = (float) $rows->sum('total');

        return $rows->map(function ($row) use ($sources, $total) {
            $amount = round((float) $row->total, 2);

            return [
                'source_id' => $row->source_id,
                'category' => $sources->get($row->source_id)?->name ?? 'Uncategorized',
                'total' => $amount,
                'entries' => (int) $row->entries,
                'share' => $total > 0 ? round(($amount / $total) * 100, 1) : 0.0,
            ];
        })->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function topExpenses(int $limit = 10): array
    {
        return $this->expenseQuery()
            ->with(['category:id,name,color', 'account:id,name'])
            ->orderByDesc('amount')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'type' => 'expense',
                'date' => optional($row->expense_date)->toDateString(),
                'description' => $row->description,
                'category' => $row->category?->name ?? 'Uncategorized',
                'category_color' => $row->category?->color ?? '#94A3B8',
                'account' => $row->account?->name,
                'amount' => round((float) $row->amount, 2),
            ])->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function topIncome(int $limit = 10): array
    {
        return $this->incomeQuery()
            ->with(['source:id,name', 'account:id,name'])
            ->orderByDesc('amount')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'type' => 'income',
                'date' => optional($row->received_date)->toDateString(),
                'description' => $row->description,
                'category' => $row->source?->name ?? 'Uncategorized',
                'category_color' => '#10B981',
                'account' => $row->account?->name,
                'amount' => round((float) $row->amount, 2),
            ])->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function recentTransactions(int $limit = 8): array
    {
        // Wrapped in collect() so merge() uses the base collection (these rows are
        // arrays, not models, and Eloquent's merge() assumes models).
        $income = collect($this->incomeQuery()
            ->with('source:id,name')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'type' => 'income',
                'date' => optional($row->received_date)->toDateString(),
                'sort' => optional($row->received_date)?->timestamp ?? 0,
                'title' => $row->description ?: ($row->source?->name ?? 'Income'),
                'subtitle' => $row->source?->name ?? 'Income',
                'amount' => round((float) $row->amount, 2),
            ])->all());

        $expenses = collect($this->expenseQuery()
            ->with('category:id,name')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'type' => 'expense',
                'date' => optional($row->expense_date)->toDateString(),
                'sort' => optional($row->expense_date)?->timestamp ?? 0,
                'title' => $row->description ?: ($row->category?->name ?? 'Expense'),
                'subtitle' => $row->category?->name ?? 'Expense',
                'amount' => round((float) $row->amount, 2),
            ])->all());

        return $income->merge($expenses)
            ->sortByDesc('sort')
            ->take($limit)
            ->values()
            ->map(fn ($row) => collect($row)->except('sort')->all())
            ->all();
    }

    // ----------------------------------------------------------------- budgets

    /** @return array<int, array<string, mixed>> */
    public function budgetProgress(): array
    {
        $budgets = Budget::query()
            ->where('user_id', $this->user->id)
            ->with('category:id,name,color')
            ->when(! $this->filters['include_blocked'], fn ($q) => $q->where('is_blocked', false))
            ->get();

        if ($budgets->isEmpty()) {
            return [];
        }

        $spendByCategory = $this->expenseQuery()
            ->selectRaw('category_id, sum(amount) as total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        $rangeDays = (int) $this->from->diffInDays($this->to) + 1;

        return $budgets->map(function (Budget $budget) use ($spendByCategory, $rangeDays) {
            $monthly = (float) $budget->amount;
            $monthDays = (int) $budget->start_date->daysInMonth;
            $dailyLimit = $monthDays > 0 ? $monthly / $monthDays : $monthly;

            $overlapStart = $budget->start_date->greaterThan($this->from) ? $budget->start_date->startOfDay() : $this->from;
            $overlapEnd = $budget->end_date && $budget->end_date->lessThan($this->to) ? $budget->end_date->endOfDay() : $this->to;
            $overlapDays = $overlapEnd->greaterThanOrEqualTo($overlapStart) ? (int) $overlapStart->diffInDays($overlapEnd) + 1 : 0;

            $limit = round($dailyLimit * $overlapDays, 2);
            $spent = round((float) ($spendByCategory[$budget->category_id] ?? 0), 2);

            return [
                'id' => $budget->id,
                'category' => $budget->category?->name ?? 'Uncategorized',
                'color' => $budget->category?->color ?? '#6366F1',
                'period' => $budget->period,
                'monthly_amount' => round($monthly, 2),
                'amount' => $limit,
                'spent' => $spent,
                'remaining' => round($limit - $spent, 2),
                'used_percent' => $limit > 0 ? round(($spent / $limit) * 100, 1) : null,
                'alert_at_percent' => (int) $budget->alert_at_percent,
                'covers_full_range' => $overlapDays >= $rangeDays,
                'days' => $overlapDays,
            ];
        })->sortByDesc('used_percent')->values()->all();
    }

    /** @return array{0: float, 1: Collection} */
    private function budgetTotals(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rangeDays = (int) $from->diffInDays($to) + 1;

        $rows = Budget::query()
            ->where('user_id', $this->user->id)
            ->when(! $this->filters['include_blocked'], fn ($q) => $q->where('is_blocked', false))
            ->get()
            ->map(function (Budget $budget) use ($from, $to, $rangeDays) {
                $monthly = (float) $budget->amount;
                $monthDays = (int) $budget->start_date->daysInMonth;
                $dailyLimit = $monthDays > 0 ? $monthly / $monthDays : $monthly;

                $overlapStart = $budget->start_date->greaterThan($from) ? $budget->start_date->startOfDay() : $from;
                $overlapEnd = $budget->end_date && $budget->end_date->lessThan($to) ? $budget->end_date->endOfDay() : $to;
                $overlapDays = $overlapEnd->greaterThanOrEqualTo($overlapStart)
                    ? (int) $overlapStart->diffInDays($overlapEnd) + 1
                    : 0;

                return [
                    'id' => $budget->id,
                    'amount' => round($dailyLimit * $overlapDays, 2),
                    'covers_full_range' => $overlapDays >= $rangeDays,
                ];
            });

        $covered = $rows->where('covers_full_range', true)->sum('amount');

        // When at least one budget spans the whole selected range we treat that as
        // the period limit; otherwise fall back to the prorated sum so the KPI
        // still means something for partial coverage.
        $limit = $covered > 0 ? (float) $covered : (float) $rows->sum('amount');

        return [$limit, $rows];
    }

    /** @return array{0: float, 1: int} */
    private function pendingTotals(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $table = 'reminders';

        try {
            $query = DB::table($table)
                ->where('user_id', $this->user->id)
                ->where('is_paid', false)
                ->whereDate('due_date', '>=', $from->toDateString())
                ->whereDate('due_date', '<=', $to->toDateString());

            return [round((float) $query->sum('amount'), 2), (int) $query->count()];
        } catch (\Throwable) {
            return [0.0, 0];
        }
    }

    // ------------------------------------------------------------------ extras

    public function accounts(): array
    {
        $rows = $this->user->accounts()
            ->when(! $this->filters['include_blocked'], fn ($q) => $q->where('is_active', true))
            ->get(['id', 'name', 'type', 'balance', 'currency', 'color']);

        return [
            'total' => round((float) $rows->sum('balance'), 2),
            'currency' => $rows->first()->currency ?? 'PKR',
            'by_type' => $rows->groupBy('type')->map(fn ($group) => [
                'type' => $group->first()->type,
                'total' => round((float) $group->sum('balance'), 2),
                'accounts' => $group->count(),
            ])->values()->all(),
            'list' => $rows->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'type' => $row->type,
                'balance' => round((float) $row->balance, 2),
                'color' => $row->color,
            ])->all(),
        ];
    }

    /** Day-by-day expense totals – used for the heat strip and "biggest day" stats. */
    public function dailyExpenses(): array
    {
        $expenses = $this->expenseQuery()
            ->selectRaw('expense_date as day, amount')
            ->get()
            ->groupBy(fn ($row) => CarbonImmutable::parse($row->day)->toDateString())
            ->map(fn ($group, $day) => ['date' => $day, 'total' => round((float) $group->sum('amount'), 2)])
            ->values();

        $income = $this->incomeQuery()
            ->selectRaw('received_date as day, amount')
            ->get()
            ->groupBy(fn ($row) => CarbonImmutable::parse($row->day)->toDateString())
            ->map(fn ($group, $day) => ['date' => $day, 'total' => round((float) $group->sum('amount'), 2)]);

        $days = (int) $this->from->diffInDays($this->to) + 1;

        return [
            'days' => $days,
            'expense_days' => $expenses->count(),
            'income_days' => $income->count(),
            'busiest_expense_day' => $expenses->sortByDesc('total')->first(),
            'busiest_income_day' => $income->sortByDesc('total')->first(),
            'expenses' => $expenses->sortBy('date')->values()->all(),
        ];
    }

    public function filtersPayload(): array
    {
        $categories = ExpenseCategory::query()
            ->where(fn ($q) => $q->where('user_id', $this->user->id)->orWhere('is_system', true))
            ->orderBy('name')
            ->get(['id', 'name', 'color', 'parent_id', 'is_blocked'])
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'color' => $row->color,
                'parent_id' => $row->parent_id,
                'is_blocked' => (bool) $row->is_blocked,
            ]);

        $sources = IncomeSource::query()
            ->where('user_id', $this->user->id)
            ->orderBy('name')
            ->get(['id', 'name', 'is_blocked'])
            ->map(fn ($row) => ['id' => $row->id, 'name' => $row->name, 'is_blocked' => (bool) $row->is_blocked]);

        return [
            'categories' => $categories->all(),
            'sources' => $sources->all(),
            'accounts' => $this->user->accounts()->get(['id', 'name', 'type'])->all(),
            'presets' => self::PRESETS,
            'granularities' => self::GRANULARITIES,
            'has_budgets' => Budget::query()->where('user_id', $this->user->id)->exists(),
        ];
    }

    public function user(): User
    {
        return $this->user;
    }
}
