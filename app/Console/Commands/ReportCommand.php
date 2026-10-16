<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Reports\Insights;
use App\Support\Reports\ReportQuery;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

/**
 * Print the same report the dashboard shows, straight from the terminal.
 *
 *   php artisan finwise:report --user=1 --preset=this_year --granularity=monthly
 */
class ReportCommand extends Command
{
    protected $signature = 'finwise:report
        {--user= : User id or email (defaults to the first user with data)}
        {--preset=all : today|yesterday|this_week|last_week|this_month|last_month|last_3_months|last_6_months|last_12_months|this_quarter|this_year|last_year|all|custom}
        {--granularity=auto : auto|daily|weekly|monthly|quarterly|yearly}
        {--from= : Start date for --preset=custom}
        {--to= : End date for --preset=custom}
        {--category= : Expense category id}
        {--source= : Income source id}';

    protected $description = 'Show a FinWise income/expense report for any period';

    public function handle(): int
    {
        $user = $this->resolveUser();

        if (! $user) {
            $this->error('No matching user found.');

            return self::FAILURE;
        }

        $request = Request::create('/api/v1/reports/overview', 'GET', array_filter([
            'preset' => $this->option('preset'),
            'granularity' => $this->option('granularity'),
            'from' => $this->option('from'),
            'to' => $this->option('to'),
            'category_id' => $this->option('category'),
            'source_id' => $this->option('source'),
        ], fn ($value) => $value !== null && $value !== ''));

        $query = new ReportQuery($user, $request);
        $overview = $query->overview();
        $summary = $overview['summary'];
        $period = $overview['period'];

        $this->newLine();
        $this->line("  <fg=white;options=bold>{$user->name}</>  ({$user->email})");
        $this->line("  Period: <fg=cyan>{$period['from']} to {$period['to']}</>  grouped {$period['granularity']}  ({$period['days']} days)");
        $this->newLine();

        $this->table(['Income', 'Expenses', 'Net saved', 'Savings rate', 'Avg/day spend'], [[
            number_format($summary['income'], 2),
            number_format($summary['expenses'], 2),
            number_format($summary['net'], 2),
            $summary['savings_rate'] === null ? '—' : $summary['savings_rate'] . '%',
            number_format($summary['avg_daily_expense'], 2),
        ]]);

        $this->table(
            [$period['bucket_label'], 'Income', 'Expenses', 'Net', 'Running net'],
            collect($overview['series'])->map(fn ($row) => [
                $row['full_label'],
                number_format($row['income'], 2),
                number_format($row['expenses'], 2),
                number_format($row['net'], 2),
                number_format($row['running_net'], 2),
            ])->all()
        );

        if ($overview['category_breakdown'] !== []) {
            $this->table(
                ['Expense category', 'Total', 'Share %'],
                collect($overview['category_breakdown'])->map(fn ($row) => [
                    $row['category'], number_format($row['total'], 2), $row['share'],
                ])->all()
            );
        }

        if ($overview['source_breakdown'] !== []) {
            $this->table(
                ['Income source', 'Total', 'Share %'],
                collect($overview['source_breakdown'])->map(fn ($row) => [
                    $row['category'], number_format($row['total'], 2), $row['share'],
                ])->all()
            );
        }

        if ($overview['budgets'] !== []) {
            $this->table(
                ['Budget', 'Limit', 'Spent', 'Remaining', 'Used %'],
                collect($overview['budgets'])->map(fn ($row) => [
                    $row['category'],
                    number_format($row['amount'], 2),
                    number_format($row['spent'], 2),
                    number_format($row['remaining'], 2),
                    $row['used_percent'] ?? '—',
                ])->all()
            );
        }

        $insights = Insights::fromOverview($overview);

        if ($insights !== []) {
            $this->newLine();
            $this->line('  <fg=white;options=bold>What the numbers say</>');

            foreach ($insights as $insight) {
                $this->line('  • <fg=yellow>' . $insight['title'] . '</> — ' . $insight['message']);
            }
        }

        $this->newLine();

        return self::SUCCESS;
    }

    private function resolveUser(): ?User
    {
        $needle = $this->option('user');

        if ($needle) {
            return User::query()
                ->where('id', $needle)
                ->orWhere('email', $needle)
                ->first();
        }

        return User::query()
            ->whereHas('incomeRecords')
            ->orWhereHas('expenseRecords')
            ->orderBy('id')
            ->first()
            ?? User::query()->orderBy('id')->first();
    }
}
