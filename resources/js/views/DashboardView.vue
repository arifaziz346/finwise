<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { useToast } from 'vue-toastification';
import {
    ArrowRight, ArrowDownLeft, ArrowUpRight, CalendarClock, Download, Loader2,
    PiggyBank, Plus, RefreshCw, Target, TrendingDown, TrendingUp, Wallet,
} from 'lucide-vue-next';
import AppLayout from '../components/layout/AppLayout.vue';
import AppCard from '../components/ui/AppCard.vue';
import AppButton from '../components/ui/AppButton.vue';
import AppSkeleton from '../components/ui/AppSkeleton.vue';
import AppStatCard from '../components/ui/AppStatCard.vue';
import PeriodFilter from '../components/ui/PeriodFilter.vue';
import InsightList from '../components/ui/InsightList.vue';
import BreakdownBars from '../components/ui/BreakdownBars.vue';
import ChartCanvas from '../components/charts/ChartCanvas.vue';
import { useReportsStore } from '../stores/reports';
import { useUiStore } from '../stores/ui';
import { compactMoney, money, percent } from '../utils/currency';
import { relativeDays, shortDate } from '../utils/date';
import { donutOptions, palette, readableColor, runningOptions, trendOptions } from '../utils/charts';

const reports = useReportsStore();
const ui = useUiStore();
const darkMode = computed(() => ui.dark);
const toast = useToast();
const busy = ref('');

const summary = computed(() => reports.summary ?? {});
const previous = computed(() => reports.previous ?? null);
const currency = computed(() => reports.currency || 'PKR');
const hasBudgets = computed(() => (reports.budgets ?? []).length > 0);

const onPreset = async (preset) => {
    busy.value = `preset-${preset}`;
    try {
        await reports.applyPreset(preset);
    } finally {
        busy.value = '';
    }
};

const onGranularity = async (granularity) => {
    await reports.applyGranularity(granularity);
};

const onCustom = async ({ from, to }) => {
    await reports.applyCustomRange(from, to);
};

const onExport = async (format) => {
    busy.value = `export-${format}`;
    try {
        await reports.export(format);
        toast.success('Report downloaded');
    } catch {
        toast.error('Could not generate the export');
    } finally {
        busy.value = '';
    }
};

// ---------------------------------------------------------------- chart data

const labels = computed(() => reports.series.map((row) => row.label));

const trendData = computed(() => ({
    labels: labels.value,
    datasets: [
        {
            type: 'bar',
            label: 'Income',
            data: reports.series.map((row) => row.income),
            backgroundColor: palette.income,
            borderRadius: 6,
            maxBarThickness: 34,
            order: 2,
        },
        {
            type: 'bar',
            label: 'Expenses',
            data: reports.series.map((row) => row.expenses),
            backgroundColor: palette.expense,
            borderRadius: 6,
            maxBarThickness: 34,
            order: 2,
        },
        {
            type: 'line',
            label: 'Net',
            data: reports.series.map((row) => row.net),
            borderColor: palette.net,
            backgroundColor: palette.net,
            borderWidth: 2,
            tension: 0.35,
            pointRadius: 2,
            pointHoverRadius: 5,
            fill: false,
            order: 1,
        },
    ],
}));

const runningData = computed(() => ({
    labels: labels.value,
    datasets: [
        {
            type: 'line',
            label: 'Running net',
            data: reports.series.map((row) => row.running_net),
            borderColor: palette.running,
            backgroundColor: palette.netSoft,
            borderWidth: 2,
            tension: 0.3,
            pointRadius: 0,
            pointHoverRadius: 4,
            fill: true,
        },
    ],
}));

const donutData = computed(() => {
    const rows = reports.categoryChartRows;

    return {
        labels: rows.map((row) => row.category),
        datasets: [
            {
                data: rows.map((row) => row.total),
                backgroundColor: rows.map((row, index) => readableColor(row.color, index, { dark: darkMode.value })),
                borderWidth: 0,
                hoverOffset: 6,
            },
        ],
    };
});

const donutTotal = computed(() => reports.categoryChartRows.reduce((sum, row) => sum + Number(row.total || 0), 0));

const trend = computed(() => trendOptions({ granularity: reports.period.granularity, currency: currency.value }));
const running = computed(() => runningOptions({ currency: currency.value }));
const donut = computed(() => donutOptions({ currency: currency.value, total: donutTotal.value }));

const isEmpty = computed(() => !reports.loading && !reports.hasData);
const topCategories = computed(() => reports.categoryBreakdown.slice(0, 6));
const budgetTone = computed(() => {
    const used = summary.value.budget_used_percent;
    if (used === null || used === undefined) return 0;
    return used;
});

onMounted(async () => {
    await reports.loadFilters();
    await reports.load();
});
</script>

<template>
    <AppLayout>
        <!-- Header + filters -->
        <div class="mb-5 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Dashboard</h1>
                <p class="mt-0.5 text-sm text-slate-500">
                    <template v-if="reports.period.from">
                        {{ reports.period.label }} · {{ reports.period.days }} days · grouped by {{ reports.period.bucket_label?.toLowerCase() }}
                    </template>
                    <template v-else>Your money at a glance.</template>
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <AppButton variant="secondary" :loading="busy === 'refresh'" @click="reports.load()">
                    <span class="flex items-center gap-2"><RefreshCw class="h-4 w-4" /> Refresh</span>
                </AppButton>
                <AppButton variant="secondary" :loading="busy === 'export-pdf'" @click="onExport('pdf')">
                    <span class="flex items-center gap-2"><Download class="h-4 w-4" /> Report</span>
                </AppButton>
                <RouterLink to="/expenses" class="fw-btn-primary flex items-center gap-2">
                    <Plus class="h-4 w-4" /> Add expense
                </RouterLink>
            </div>
        </div>

        <div class="mb-6 flex flex-wrap items-center gap-3">
            <PeriodFilter
                :period="reports.period"
                :presets="reports.filters.presets"
                :granularity="reports.granularity"
                :loading="reports.loading"
                @preset="onPreset"
                @granularity="onGranularity"
                @custom="onCustom"
            />
        </div>

        <div v-if="reports.error" class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300">
            {{ reports.error }}
        </div>

        <!-- Empty period hint -->
        <div v-if="isEmpty" class="mb-6 flex flex-wrap items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
            <CalendarClock class="h-5 w-5 shrink-0" />
            <span class="flex-1">No income or expenses are recorded between {{ reports.period.from }} and {{ reports.period.to }}. Pick another range to see your history.</span>
            <AppButton variant="secondary" @click="onPreset('all')">Show all time</AppButton>
        </div>

        <!-- KPI row -->
        <div v-if="reports.loading && !reports.period.from" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <AppSkeleton v-for="i in 4" :key="i" class="h-32" />
        </div>
        <div v-else class="grid gap-4 sm:grid-cols-2" :class="hasBudgets ? 'xl:grid-cols-5' : 'xl:grid-cols-4'">
            <AppStatCard
                label="Income"
                :value="money(summary.income, currency)"
                :raw="Number(summary.income || 0)"
                :previous="previous ? Number(previous.income) : null"
                :rise-is-good="true"
                tone="emerald"
                :hint="`${summary.income_count || 0} entries · ${money(summary.avg_daily_income, currency)}/day`"
            >
                <template #icon><TrendingUp class="h-4 w-4" /></template>
            </AppStatCard>

            <AppStatCard
                label="Expenses"
                :value="money(summary.expenses, currency)"
                :raw="Number(summary.expenses || 0)"
                :previous="previous ? Number(previous.expenses) : null"
                :rise-is-good="false"
                tone="red"
                :hint="`${summary.expense_count || 0} entries · ${money(summary.avg_daily_expense, currency)}/day`"
            >
                <template #icon><TrendingDown class="h-4 w-4" /></template>
            </AppStatCard>

            <AppStatCard
                label="Net saved"
                :value="money(summary.net, currency)"
                :raw="Number(summary.net || 0)"
                :previous="previous ? Number(previous.net) : null"
                :rise-is-good="true"
                :tone="Number(summary.net || 0) >= 0 ? 'indigo' : 'red'"
                :hint="summary.savings_rate === null ? 'No income recorded in this period' : `Savings rate ${percent(summary.savings_rate)} of income`"
            >
                <template #icon><PiggyBank class="h-4 w-4" /></template>
            </AppStatCard>

            <AppStatCard v-if="hasBudgets" label="Budget used" :value="money(summary.budget_spent, currency)" tone="amber" :progress="budgetTone" :progress-label="`of ${money(summary.budget_limit, currency)}`" :hint="`${summary.budget_rows} budget${summary.budget_rows === 1 ? '' : 's'} · ${money(summary.budget_remaining, currency)} left`">
                <template #icon><Target class="h-4 w-4" /></template>
            </AppStatCard>

            <AppStatCard
                v-else
                label="Net worth"
                :value="money(reports.accounts.total, currency)"
                tone="sky"
                :hint="`Across ${(reports.accounts.list || []).length} account(s)`"
                :footnote="`Avg daily spend ${money(summary.avg_daily_expense, currency)}`"
            >
                <template #icon><Wallet class="h-4 w-4" /></template>
            </AppStatCard>
        </div>

        <!-- Charts -->
        <div class="mt-6 grid gap-5 xl:grid-cols-[1.6fr_1fr]">
            <AppCard class="!p-5">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-bold">Income vs expenses</h2>
                        <p class="text-xs text-slate-500">Bars show money in and out, the line shows your net result.</p>
                    </div>
                    <RouterLink to="/reports" class="flex items-center gap-1 text-xs font-semibold text-indigo-600 hover:underline dark:text-indigo-400">
                        Full report <ArrowRight class="h-3.5 w-3.5" />
                    </RouterLink>
                </div>
                <ChartCanvas type="bar" :data="trendData" :options="trend" height="300px" :empty="!reports.series.length" />
            </AppCard>

            <AppCard class="!p-5">
                <div class="mb-4">
                    <h2 class="font-bold">Where the money went</h2>
                    <p class="text-xs text-slate-500">
                        <template v-if="donutTotal > 0">Top categories, {{ money(donutTotal, currency) }} total</template>
                        <template v-else>No expenses recorded in this period</template>
                    </p>
                </div>
                <ChartCanvas type="doughnut" :data="donutData" :options="donut" height="300px" :empty="!donutTotal" empty-text="No expenses to break down yet." />
            </AppCard>
        </div>

        <!-- Insights + cumulative savings -->
        <div class="mt-5 grid gap-5 xl:grid-cols-[1fr_1fr]">
            <AppCard class="!p-5">
                <InsightList :insights="reports.insights" :limit="4" />
            </AppCard>

            <AppCard class="!p-5">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="font-bold">Savings building up</h2>
                        <p class="text-xs text-slate-500">Cumulative net across {{ reports.period.label || 'the selected period' }}</p>
                    </div>
                    <span class="rounded-lg px-2.5 py-1 font-mono text-xs font-semibold" :class="Number(summary.net || 0) >= 0 ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-red-50 text-red-500 dark:bg-red-500/10'">
                        {{ compactMoney(summary.net, currency) }}
                    </span>
                </div>
                <ChartCanvas type="line" :data="runningData" :options="running" height="220px" :empty="!reports.series.length" />
            </AppCard>
        </div>

        <!-- Breakdown + recent activity -->
        <div class="mt-5 grid gap-5 xl:grid-cols-[1fr_1.2fr]">
            <AppCard class="!p-5">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="font-bold">Top spending categories</h2>
                    <RouterLink to="/reports" class="text-xs font-semibold text-indigo-600 hover:underline dark:text-indigo-400">See all</RouterLink>
                </div>
                <BreakdownBars :rows="topCategories" metric="total" :currency="currency" empty-text="No expenses recorded in this period." />
            </AppCard>

            <AppCard class="!p-5">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="font-bold">Recent activity</h2>
                    <span class="text-xs text-slate-500">{{ reports.recent.length }} latest in range</span>
                </div>

                <p v-if="!reports.recent.length" class="py-8 text-center text-sm text-slate-500">Nothing recorded in this period.</p>

                <ul v-else class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    <li v-for="row in reports.recent" :key="`${row.type}-${row.id}`" class="flex items-center gap-3 py-2.5">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl" :class="row.type === 'income' ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-red-50 text-red-500 dark:bg-red-500/10 dark:text-red-400'">
                            <ArrowDownLeft v-if="row.type === 'income'" class="h-4 w-4" />
                            <ArrowUpRight v-else class="h-4 w-4" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ row.title }}</p>
                            <p class="text-xs text-slate-500">{{ row.subtitle }} · {{ shortDate(row.date) }} ({{ relativeDays(row.date) }})</p>
                        </div>
                        <p class="shrink-0 font-mono text-sm font-semibold" :class="row.type === 'income' ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500'">
                            {{ row.type === 'income' ? '+' : '−' }}{{ money(row.amount, currency) }}
                        </p>
                    </li>
                </ul>
            </AppCard>
        </div>

        <!-- Budgets -->
        <AppCard v-if="hasBudgets" class="mt-5 !p-5">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div>
                    <h2 class="font-bold">Budget progress</h2>
                    <p class="text-xs text-slate-500">Limits prorated to {{ reports.period.label }}</p>
                </div>
                <RouterLink to="/budgets" class="text-xs font-semibold text-indigo-600 hover:underline dark:text-indigo-400">Manage budgets</RouterLink>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <div v-for="budget in reports.budgets" :key="budget.id">
                    <div class="mb-1.5 flex items-baseline justify-between gap-3 text-sm">
                        <span class="font-medium">{{ budget.category }}</span>
                        <span class="font-mono text-xs">{{ money(budget.spent, currency) }} / {{ money(budget.amount, currency) }}</span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-700">
                        <div class="h-full rounded-full transition-all duration-500" :class="(budget.used_percent ?? 0) >= 100 ? 'bg-red-500' : (budget.used_percent ?? 0) >= (budget.alert_at_percent ?? 80) ? 'bg-amber-500' : 'bg-emerald-500'" :style="{ width: `${Math.min(budget.used_percent ?? 0, 100)}%` }" />
                    </div>
                    <p class="mt-1 text-xs" :class="budget.remaining < 0 ? 'text-red-500' : 'text-slate-500'">
                        {{ budget.remaining < 0 ? `${money(Math.abs(budget.remaining), currency)} over budget` : `${money(budget.remaining, currency)} remaining` }}
                    </p>
                </div>
            </div>
        </AppCard>

        <p v-if="reports.loading" class="mt-4 flex items-center gap-2 text-xs text-slate-500">
            <Loader2 class="h-3.5 w-3.5 animate-spin" /> Updating figures…
        </p>
    </AppLayout>
</template>
