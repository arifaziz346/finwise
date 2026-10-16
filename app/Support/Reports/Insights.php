<?php

namespace App\Support\Reports;

use Carbon\CarbonImmutable;

/**
 * Turns the raw report numbers into short, plain-language observations.
 *
 * Each insight carries a severity so the UI can colour it, and a metric so the
 * same payload can be reused by the PDF export without re-deriving anything.
 */
class Insights
{
    public const OK = 'ok';

    public const INFO = 'info';

    public const WARN = 'warn';

    public const DANGER = 'danger';

    /**
     * @param  array<string, mixed>  $overview  The payload from ReportQuery::overview().
     * @return array<int, array{key: string, severity: string, title: string, message: string}>
     */
    public static function fromOverview(array $overview): array
    {
        $summary = $overview['summary'] ?? [];
        $previous = $overview['previous'] ?? null;
        $categories = $overview['category_breakdown'] ?? [];
        $sources = $overview['source_breakdown'] ?? [];
        $budgets = $overview['budgets'] ?? [];
        $series = $overview['series'] ?? [];
        $period = $overview['period'] ?? [];

        $insights = [];

        if (($summary['has_data'] ?? false) === false) {
            return [[
                'key' => 'empty',
                'severity' => self::INFO,
                'title' => 'No activity in this period',
                'message' => 'Nothing was recorded between ' . ($period['from'] ?? '') . ' and ' . ($period['to'] ?? '') .
                    '. Widen the range or pick “All time” to see your history.',
            ]];
        }

        $income = (float) ($summary['income'] ?? 0);
        $expenses = (float) ($summary['expenses'] ?? 0);
        $net = (float) ($summary['net'] ?? 0);
        $days = max((int) ($summary['days'] ?? 1), 1);
        $savingsRate = $summary['savings_rate'] ?? null;

        // 1. Savings health -------------------------------------------------
        $expenseRatio = $summary['expense_ratio'];

        if ($income > 0 && $expenseRatio !== null && $expenseRatio < 1.0) {
            $insights[] = self::line('savings', self::INFO, 'Almost nothing recorded as spent',
                sprintf('Only %s of expenses were recorded against %s of income in this period, so the savings figures below are not meaningful yet. Log your regular spending to see a real picture.',
                    self::pkr($expenses), self::pkr($income)));
        } elseif ($income > 0) {
            $rate = (float) $savingsRate;
            $insights[] = match (true) {
                $rate >= 30 => self::line('savings', self::OK, 'Strong savings rate',
                    sprintf('You kept %.0f%% of your income (%s) inside this period. Consider moving the surplus into a dedicated savings or investment account.', $rate, self::pkr($net))),
                $rate >= 10 => self::line('savings', self::INFO, 'Positive but thin margin',
                    sprintf('You saved %.0f%% of your income (%s). Pushing this above 20%% gives you a real buffer for surprise costs.', $rate, self::pkr($net))),
                $rate >= 0 => self::line('savings', self::WARN, 'Barely breaking even',
                    sprintf('Only %.0f%% of income is left after spending (%s). A single unexpected bill would tip this period negative.', $rate, self::pkr($net))),
                default => self::line('savings', self::DANGER, 'Spending more than you earn',
                    sprintf('Expenses exceeded income by %s (%.0f%% of income used). If this keeps up you are drawing down savings, so this is the number to fix first.', self::pkr(abs($net)), (float) ($summary['expense_ratio'] ?? 0))),
            };
        } else {
            $insights[] = self::line('savings', self::WARN, 'No income recorded',
                sprintf('%s of expenses were recorded with no matching income in this period. Add the income entries so the savings figures are meaningful.', self::pkr($expenses)));
        }

        // 2. Biggest category ------------------------------------------------
        if ($categories !== []) {
            $top = $categories[0];
            $share = (float) $top['share'];
            $severity = $share >= 50 ? self::WARN : ($share >= 30 ? self::INFO : self::OK);
            $topTotal = (float) $top['total'];
            $base = sprintf('“%s” took %s — %.0f%% of everything you spent (across %d entries).',
                $top['category'], self::pkr($topTotal), $share, (int) $top['entries']);

            if ((float) ($summary['expenses'] ?? 0) <= 0 || $share >= 98) {
                // A single dominant row tells us nothing about distribution.
                $insights[] = self::line('top_category', self::INFO, 'Biggest spending category', $base);
            } else {
                $insights[] = self::line('top_category', $severity, 'Biggest spending category', $base . ' ' . ($share >= 40
                    ? 'Focus here: a 10% cut in this one category saves PKR ' . self::pkr(round($topTotal * 0.1, 2)) . '.'
                    : 'Spending is spread reasonably across categories.'));
            }
        }

        // 3. Income concentration --------------------------------------------
        if (count($sources) > 1) {
            $topSource = $sources[0];
            if ((float) $topSource['share'] >= 80) {
                $insights[] = self::line('income_source', self::WARN, 'Income depends on one source',
                    sprintf('%.0f%% of your income came from “%s” (%s). A second stream would make your budget far more resilient.',
                        (float) $topSource['share'], $topSource['category'], self::pkr((float) $topSource['total'])));
            } elseif (count($sources) >= 2) {
                $insights[] = self::line('income_source', self::OK, 'Income is diversified',
                    sprintf('Your income came from %d sources, led by “%s” at %.0f%%.', count($sources), $topSource['category'], (float) $topSource['share']));
            }
        }

        // 4. Daily burn rate -------------------------------------------------
        if ($expenses > 0) {
            $avgDaily = (float) ($summary['avg_daily_expense'] ?? 0);
            $months = round($days / 30.44, 1);
            $insights[] = self::line('burn_rate', self::INFO, 'Average daily spending',
                sprintf('You spent %s per day on average over %d days (~%s per month).',
                    self::pkr($avgDaily), $days, self::pkr(round($avgDaily * 30.44, 2))));
        }

        // 5. Peak spending bucket --------------------------------------------
        // Only worth saying when there is enough spending to compare against.
        $expenseCount = (int) ($summary['expense_count'] ?? 0);

        if ($series !== [] && $expenseCount >= 3) {
            $avgDaily = (float) ($summary['avg_daily_expense'] ?? 0);
            $peak = collect($series)->sortByDesc('expenses')->first();
            $peakShare = $expenses > 0 ? ((float) $peak['expenses'] / $expenses) * 100 : 0;

            if ($avgDaily > 0 && (float) $peak['expenses'] > $avgDaily * 2 && $peakShare < 90) {
                $insights[] = self::line('peak', self::INFO, 'Heaviest ' . strtolower((string) ($period['bucket_label'] ?? 'period')),
                    sprintf('%s was your heaviest at %s (%.0f%% of the period total) — about %.1fx your daily average.',
                        $peak['full_label'] ?? $peak['label'], self::pkr((float) $peak['expenses']),
                        $peakShare, (float) $peak['expenses'] / $avgDaily));
            }
        }

        // 6. Trend vs previous period ----------------------------------------
        if ($previous) {
            $expenseDelta = self::percentChange((float) $previous['expenses'], $expenses);
            $incomeDelta = self::percentChange((float) $previous['income'], $income);

            if ($expenseDelta !== null && abs($expenseDelta) >= 5) {
                $insights[] = self::line('trend_expense', $expenseDelta > 0 ? self::WARN : self::OK,
                    $expenseDelta > 0 ? 'Spending is up' : 'Spending is down',
                    sprintf('Expenses are %s %.0f%% versus the previous period (%s → %s).',
                        $expenseDelta > 0 ? 'up' : 'down', abs($expenseDelta),
                        self::pkr((float) $previous['expenses']), self::pkr($expenses)));
            }

            if ($incomeDelta !== null && abs($incomeDelta) >= 5) {
                $insights[] = self::line('trend_income', $incomeDelta > 0 ? self::OK : self::WARN,
                    $incomeDelta > 0 ? 'Income is up' : 'Income is down',
                    sprintf('Income changed %.0f%% versus the previous period (%s → %s).',
                        $incomeDelta, self::pkr((float) $previous['income']), self::pkr($income)));
            }
        }

        // 7. Budget pressure -------------------------------------------------
        foreach (array_slice($budgets, 0, 2) as $budget) {
            $used = $budget['used_percent'];
            if ($used === null) {
                continue;
            }

            $insights[] = match (true) {
                $used >= 100 => self::line('budget_' . $budget['id'], self::DANGER, 'Budget exceeded: ' . $budget['category'],
                    sprintf('%s spent against a limit of %s (%.0f%%). You are %s over.',
                        self::pkr((float) $budget['spent']), self::pkr((float) $budget['amount']), $used, self::money(abs((float) $budget['remaining'])))),
                $used >= (float) $budget['alert_at_percent'] => self::line('budget_' . $budget['id'], self::WARN, 'Approaching budget limit: ' . $budget['category'],
                    sprintf('%.0f%% of the %s limit is used with %s left.',
                        $used, self::pkr((float) $budget['amount']), self::pkr((float) $budget['remaining']))),
                default => self::line('budget_' . $budget['id'], self::OK, 'On track: ' . $budget['category'],
                    sprintf('%.0f%% of the %s limit used, %s still available.',
                        $used, self::pkr((float) $budget['amount']), self::pkr((float) $budget['remaining']))),
            };
        }

        // 8. Upcoming reminders ----------------------------------------------
        if ((float) ($summary['pending_total'] ?? 0) > 0) {
            $insights[] = self::line('pending', self::WARN, 'Unpaid bills in this period',
                sprintf('%d reminder(s) worth %s are still marked unpaid inside this range.',
                    (int) $summary['pending_count'], self::pkr((float) $summary['pending_total'])));
        }

        return array_slice($insights, 0, 8);
    }

    /**
     * Flag unusually large swings between consecutive buckets of a time series.
     *
     * @param  array<int, array<string, mixed>>  $series
     * @return array<int, array{key: string, severity: string, title: string, message: string}>
     */
    public static function fromSeries(array $series): array
    {
        $insights = [];

        foreach ($series as $index => $row) {
            if ($index === 0) {
                continue;
            }

            $previous = $series[$index - 1];
            $delta = self::percentChange((float) $previous['expenses'], (float) $row['expenses']);

            if ($delta !== null && abs($delta) >= 25 && (float) $row['expenses'] > 0) {
                $insights[] = self::line('series_' . $row['key'], $delta > 0 ? self::WARN : self::OK,
                    $row['label'] . ': spending ' . ($delta > 0 ? 'spiked' : 'dropped'),
                    sprintf('%s %.0f%% compared with %s (%s → %s).',
                        $delta > 0 ? 'Up' : 'Down', abs($delta), $previous['full_label'] ?? $previous['label'],
                        self::pkr((float) $previous['expenses']), self::pkr((float) $row['expenses'])));
            }
        }

        return array_slice($insights, 0, 5);
    }

    public static function percentChange(float $previous, float $current): ?float
    {
        if ($previous <= 0.0) {
            return $current > 0 ? 100.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    private static function line(string $key, string $severity, string $title, string $message): array
    {
        return compact('key', 'severity', 'title', 'message');
    }

    /** Plain number used inside generated sentences. */
    private static function money(float|int $value): string
    {
        return number_format(round($value, 2), 2);
    }

    /** Amount including the currency prefix, matching the UI formatting. */
    private static function pkr(float|int $value): string
    {
        return 'PKR ' . self::money($value);
    }

    /** Human label used in PDF headings. */
    public static function severityLabel(string $severity): string
    {
        return match ($severity) {
            self::DANGER => 'Action needed',
            self::WARN => 'Watch',
            self::OK => 'Good',
            default => 'Info',
        };
    }

    public static function generatedAt(): string
    {
        return CarbonImmutable::now()->format('M j, Y H:i');
    }
}

