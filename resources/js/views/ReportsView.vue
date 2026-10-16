<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import { useToast } from 'vue-toastification';
import {
    BarChart3, CalendarClock, ChartLine, Download, FileSpreadsheet, FileText, Filter,
    Loader2, PiggyBank, RefreshCw, SlidersHorizontal, Target, TrendingDown, TrendingUp, Wallet, X,
} from 'lucide-vue-next';
import AppLayout from '../components/layout/AppLayout.vue';
import AppCard from '../components/ui/AppCard.vue';
import AppButton from '../components/ui/AppButton.vue';
import AppInput from '../components/ui/AppInput.vue';
import AppSelect from '../components/ui/AppSelect.vue';
import AppStatCard from '../components/ui/AppStatCard.vue';
import InsightList from '../components/ui/InsightList.vue';
import BreakdownBars from '../components/ui/BreakdownBars.vue';
import ChartCanvas from '../components/charts/ChartCanvas.vue';
import api from '../services/api';
import { useReportsStore } from '../stores/reports';
import { useUiStore } from '../stores/ui';
import { compactMoney, money, percent } from '../utils/currency';
import { date } from '../utils/date';
import { donutOptions, palette, rankingOptions, readableColor, runningOptions, trendOptions } from '../utils/charts';

const reports = useReportsStore();
const ui = useUiStore();
const darkMode = computed(() => ui.dark);
const toast = useToast();

const view = ref('trend');          // trend | breakdown | transactions
const chartType = ref('bar');       // bar | line
const showFilters = ref(false);
const busy = ref('');
const daily = ref({ expenses: [], days: 0 });

// Draft filter state, applied only when the user presses Apply.
const draft = ref({ categoryId: '', sourceId: '', search: '', includeBlocked: false, from: '', to: '' });

const summary = computed(() => reports.summary ?? {});
const previous = computed(() => reports.previous ?? null);
const currency = computed(() => reports.currency || 'PKR');
const granularity = computed(() => reports.period.granularity ?? 'auto');
const bucketLabel = computed(() => reports.period.bucket_label ?? 'Period');
const hasBudgets = computed(() => (reports.budgets ?? []).length > 0);
const periodOptions = ['auto', 'daily', 'weekly', 'monthly', 'quarterly', 'yearly'];

const syncDraft = () => {
    draft.value = {
        categoryId: reports.categoryId,
        sourceId: reports.sourceId,
        search: reports.search,
        includeBlocked: reports.includeBlocked,
        from: reports.period.from ?? '',
        to: reports.period.to ?? '',
    };
};

watch(() => reports.period.from, syncDraft);

// ---------------------------------------------------------------- chart data

const seriesLabels = computed(() => reports.series.map((row) => row.label));

const trendData = computed(() => {
    if (chartType.value === 'line') {
        return {
            labels: seriesLabels.value,
            datasets: [
                {
                    type: 'line', label: 'Income', data: reports.series.map((row) => row.income),
                    borderColor: palette.income, backgroundColor: palette.incomeSoft, borderWidth: 2.5,
                    tension: 0.35, pointRadius: 2, pointHoverRadius: 5, fill: true,
                },
                {
                    type: 'line', label: 'Expenses', data: reports.series.map((row) => row.expenses),
                    borderColor: palette.expense, backgroundColor: palette.expenseSoft, borderWidth: 2.5,
                    tension: 0.35, pointRadius: 2, pointHoverRadius: 5, fill: true,
                },
            ],
        };
    }

    return {
        labels: seriesLabels.value,
        datasets: [
            { type: 'bar', label: 'Income', data: reports.series.map((row) => row.income), backgroundColor: palette.income, borderRadius: 6, maxBarThickness: 38 },
            { type: 'bar', label: 'Expenses', data: reports.series.map((row) => row.expenses), backgroundColor: palette.expense, borderRadius: 6, maxBarThickness: 38 },
            { type: 'line', label: 'Net', data: reports.series.map((row) => row.net), borderColor: palette.net, borderWidth: 2, tension: 0.35, pointRadius: 2, pointHoverRadius: 5, fill: false },
        ],
    };
});

const runningData = computed(() => ({
    labels: seriesLabels.value,
    datasets: [{
        type: 'line', label: 'Running net', data: reports.series.map((row) => row.running_net),
        borderColor: palette.running, backgroundColor: palette.netSoft, borderWidth: 2.5,
        tension: 0.3, pointRadius: 0, pointHoverRadius: 4, fill: true,
    }],
}));

const categoryDonut = computed(() => {
    const rows = reports.categoryChartRows;

    return {
        labels: rows.map((row) => row.category),
        datasets: [{
            data: rows.map((row) => row.total),
            backgroundColor: rows.map((row, index) => readableColor(row.color, index, { dark: darkMode.value })),
            borderWidth: 0,
            hoverOffset: 6,
        }],
    };
});

const categoryTotal = computed(() => reports.categoryChartRows.reduce((sum, row) => sum + Number(row.total || 0), 0));

const sourceRanking = computed(() => {
    const rows = [...reports.sourceBreakdown].slice(0, 7);

    return {
        labels: rows.map((row) => row.category),
        datasets: [{ label: 'Income', data: rows.map((row) => row.total), backgroundColor: rows.map((_, index) => readableColor(null, index, { dark: darkMode.value })), borderRadius: 6, maxBarThickness: 22 }],
    };
});

const trendOpts = computed(() => trendOptions({ granularity: granularity.value, currency: currency.value }));
const runningOpts = computed(() => runningOptions({ currency: currency.value }));
const donutOpts = computed(() => donutOptions({ currency: currency.value, total: categoryTotal.value }));
const rankingOpts = computed(() => rankingOptions({ currency: currency.value }));

/** Day-by-day intensity strip; only meaningful when the range is shortish. */
const heat = computed(() => {
    const rows = daily.value.expenses ?? [];
    const peak = Math.max(1, ...rows.map((row) => Number(row.total || 0)));

    return rows.slice(-90).map((row) => ({
        ...row,
        intensity: Math.min(Number(row.total || 0) / peak, 1),
    }));
});

/** Table row deltas vs the previous bucket (computed client-side). */
const tableRows = computed(() => reports.series.map((row, index) => {
    const prior = index > 0 ? reports.series[index - 1] : null;
    const delta = prior && Number(prior.expenses) > 0
        ? Math.round(((Number(row.expenses) - Number(prior.expenses)) / Number(prior.expenses)) * 1000) / 10
        : null;

    return { ...row, expenseDelta: delta };
}));

const tableTotals = computed(() => ({
    income: reports.series.reduce((sum, row) => sum + Number(row.income || 0), 0),
    expenses: reports.series.reduce((sum, row) => sum + Number(row.expenses || 0), 0),
}));

// ------------------------------------------------------------------ actions

const loadDaily = async () => {
    const { data } = await api.get('/reports/breakdown', { params: reports.params() });
    daily.value = data.daily ?? { expenses: [], days: 0 };
};

const onPreset = async (preset) => {
    busy.value = 'preset';
    try {
        await reports.applyPreset(preset);
        await loadDaily();
    } finally {
        busy.value = '';
    }
};

const onGranularity = async (option) => {
    busy.value = 'granularity';
    try {
        await reports.applyGranularity(option);
    } finally {
        busy.value = '';
    }
};

const onCustom = async ({ from, to }) => {
    busy.value = 'custom';
    try {
        await reports.applyCustomRange(from, to);
        await loadDaily();
    } finally {
        busy.value = '';
    }
};

const applyFilters = async () => {
    busy.value = 'filters';
    try {
        // Only switch to a custom range when the dates actually differ from the
        // period that is currently showing; otherwise keep the active preset.
        const sameRange = draft.value.from === reports.period.from && draft.value.to === reports.period.to;

        await reports.applyFilters({
            categoryId: draft.value.categoryId,
            sourceId: draft.value.sourceId,
            search: draft.value.search,
            includeBlocked: draft.value.includeBlocked,
            preset: sameRange ? reports.preset : 'custom',
            customFrom: draft.value.from,
            customTo: draft.value.to || draft.value.from,
        });
        await loadDaily();
        showFilters.value = false;
        toast.success('Filters applied');
    } finally {
        busy.value = '';
    }
};

const resetFilters = async () => {
    busy.value = 'reset';
    try {
        await reports.resetFilters();
        await loadDaily();
        syncDraft();
        toast.success('Filters cleared');
    } finally {
        busy.value = '';
    }
};

const onExport = async (format) => {
    busy.value = `export-${format}`;
    try {
        await reports.export(format);
        toast.success(`${format.toUpperCase()} report downloaded`);
    } catch {
        toast.error('Could not generate the export');
    } finally {
        busy.value = '';
    }
};

const refresh = async () => {
    busy.value = 'refresh';
    try {
        await reports.loadOverview();
        await loadDaily();
    } finally {
        busy.value = '';
    }
};

onMounted(async () => {
    await reports.loadFilters();
    await reports.loadOverview();
    syncDraft();
    await loadDaily();
});
</script>

<template>
    <AppLayout>
        <!-- Header -->
        <div class="mb-5 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Reports</h1>
                <p class="mt-0.5 text-sm text-slate-500">
                    Daily, monthly and yearly analysis you can filter, compare and export.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <AppButton variant="secondary" :loading="busy === 'refresh'" @click="refresh">
                    <span class="flex items-center gap-2"><RefreshCw class="h-4 w-4" /> Refresh</span>
                </AppButton>
                <AppButton variant="secondary" :loading="busy === 'export-csv'" @click="onExport('csv')">
                    <span class="flex items-center gap-2"><FileText class="h-4 w-4" /> CSV</span>
                </AppButton>
                <AppButton variant="secondary" :loading="busy === 'export-excel'" @click="onExport('excel')">
                    <span class="flex items-center gap-2"><FileSpreadsheet class="h-4 w-4" /> Excel</span>
                </AppButton>
                <AppButton :loading="busy === 'export-pdf'" @click="onExport('pdf')">
                    <span class="flex items-center gap-2"><Download class="h-4 w-4" /> PDF report</span>
                </AppButton>
            </div>
        </div>

        <!-- Period + granularity + filters -->
        <AppCard class="mb-5 !p-4">
            <div class="flex flex-wrap items-center gap-2">
                <div class="flex items-center gap-1 rounded-xl border border-slate-200 bg-white p-1 dark:border-slate-700 dark:bg-slate-800">
                    <button
                        v-for="option in periodOptions"
                        :key="option"
                        type="button"
                        class="rounded-lg px-3 py-1.5 text-xs font-medium capitalize transition disabled:opacity-60"
                        :class="granularity === option ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700'"
                        :disabled="reports.loading"
                        @click="onGranularity(option)"
                    >
                        {{ option }}
                    </button>
                </div>

                <button
                    type="button"
                    class="flex items-center gap-2 rounded-xl border px-3 py-2 text-xs font-medium transition"
                    :class="reports.activeFilterCount ? 'border-indigo-300 bg-indigo-50 text-indigo-700 dark:border-indigo-500/40 dark:bg-indigo-500/10 dark:text-indigo-300' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300'"
                    @click="showFilters = !showFilters"
                >
                    <SlidersHorizontal class="h-4 w-4" />
                    Filters
                    <span v-if="reports.activeFilterCount" class="rounded-full bg-indigo-600 px-1.5 text-[10px] font-bold text-white">{{ reports.activeFilterCount }}</span>
                </button>

                <button
                    v-if="reports.activeFilterCount || reports.preset !== 'last_6_months'"
                    type="button"
                    class="flex items-center gap-1.5 rounded-xl px-2.5 py-2 text-xs font-medium text-slate-500 hover:text-slate-800 dark:hover:text-slate-200"
                    @click="resetFilters"
                >
                    <X class="h-3.5 w-3.5" /> Clear
                </button>

                <span class="ml-auto flex items-center gap-2 text-xs text-slate-500">
                    <CalendarClock class="h-3.5 w-3.5" />
                    {{ reports.period.label || '—' }} · {{ reports.period.days || 0 }} days
                </span>
            </div>

            <!-- Presets -->
            <div class="mt-3 flex flex-wrap items-center gap-1.5 border-t border-slate-100 pt-3 dark:border-slate-700/60">
                <span class="mr-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Period</span>
                <button
                    v-for="preset in reports.filters.presets.filter((item) => item !== 'auto')"
                    :key="preset"
                    type="button"
                    class="rounded-full border px-3 py-1 text-xs font-medium capitalize transition"
                    :class="reports.preset === preset ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300'"
                    :disabled="reports.loading"
                    @click="onPreset(preset)"
                >
                    {{ preset.replaceAll('_', ' ') }}
                </button>
            </div>

            <!-- Advanced filters -->
            <div v-if="showFilters" class="mt-4 grid gap-3 border-t border-slate-100 pt-4 dark:border-slate-700/60 md:grid-cols-2 xl:grid-cols-5">
                <AppInput v-model="draft.from" label="From date" type="date" />
                <AppInput v-model="draft.to" label="To date" type="date" />
                <AppSelect v-model="draft.categoryId" label="Expense category" :options="reports.categoryOptions" />
                <AppSelect v-model="draft.sourceId" label="Income source" :options="reports.sourceOptions" />
                <AppInput v-model="draft.search" label="Note contains" />

                <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <input v-model="draft.includeBlocked" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-indigo-600">
                    Include blocked entries
                </label>

                <p class="self-center text-xs text-slate-400 md:col-span-2 xl:col-span-5">
                    The category filter narrows expenses; the income source filter narrows income. Change the dates above to analyse a custom window.
                </p>

                <div class="flex items-end gap-2 md:col-span-2 xl:col-span-4">
                    <AppButton :loading="busy === 'filters'" @click="applyFilters">
                        <span class="flex items-center gap-2"><Filter class="h-4 w-4" /> Apply filters</span>
                    </AppButton>
                    <AppButton variant="secondary" :loading="busy === 'reset'" @click="resetFilters">Reset</AppButton>
                </div>
            </div>
        </AppCard>

        <!-- Empty state -->
        <div v-if="!reports.loading && !reports.hasData" class="mb-5 flex flex-wrap items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
            <CalendarClock class="h-5 w-5 shrink-0" />
            <span class="flex-1">No records between {{ reports.period.from }} and {{ reports.period.to }}{{ reports.activeFilterCount ? ' with the current filters' : '' }}.</span>
            <AppButton variant="secondary" @click="resetFilters">Reset to last 6 months</AppButton>
        </div>

        <!-- KPI row -->
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <AppStatCard
                label="Income"
                :value="money(summary.income, currency)"
                :raw="Number(summary.income || 0)"
                :previous="previous ? Number(previous.income) : null"
                tone="emerald"
                :hint="`${summary.income_count || 0} entries · avg ${money(summary.avg_daily_income, currency)}/day`"
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
                :hint="`${summary.expense_count || 0} entries · avg ${money(summary.avg_daily_expense, currency)}/day`"
            >
                <template #icon><TrendingDown class="h-4 w-4" /></template>
            </AppStatCard>

            <AppStatCard
                label="Net saved"
                :value="money(summary.net, currency)"
                :raw="Number(summary.net || 0)"
                :previous="previous ? Number(previous.net) : null"
                :tone="Number(summary.net || 0) >= 0 ? 'indigo' : 'red'"
                :hint="summary.savings_rate === null ? 'No income recorded in this period' : `Savings rate ${percent(summary.savings_rate)}`"
            >
                <template #icon><PiggyBank class="h-4 w-4" /></template>
            </AppStatCard>

            <AppStatCard
                v-if="hasBudgets"
                label="Budget used"
                :value="money(summary.budget_spent, currency)"
                tone="amber"
                :progress="summary.budget_used_percent ?? 0"
                :progress-label="`of ${money(summary.budget_limit, currency)}`"
                :hint="`${money(summary.budget_remaining, currency)} remaining`"
            >
                <template #icon><Target class="h-4 w-4" /></template>
            </AppStatCard>

            <AppStatCard
                v-else
                label="Net worth"
                :value="money(reports.accounts.total, currency)"
                tone="sky"
                :hint="`${(reports.accounts.list || []).length} account(s) · latest balances`"
                :footnote="`Avg daily spend ${money(summary.avg_daily_expense, currency)}`"
            >
                <template #icon><Wallet class="h-4 w-4" /></template>
            </AppStatCard>
        </div>

        <!-- Insights -->
        <AppCard class="mt-5 !p-5">
            <InsightList :insights="reports.insights" title="Analysis and suggestions" :limit="8" />
        </AppCard>

        <!-- Trend + running -->
        <div class="mt-5 grid gap-5 xl:grid-cols-[1.7fr_1fr]">
            <AppCard class="!p-5">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-bold">Income vs expenses by {{ bucketLabel.toLowerCase() }}</h2>
                        <p class="text-xs text-slate-500">{{ reports.period.label }} · {{ reports.series.length }} {{ bucketLabel.toLowerCase() }} buckets</p>
                    </div>
                    <div class="flex items-center gap-1 rounded-xl border border-slate-200 p-1 dark:border-slate-700">
                        <button type="button" class="flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium transition" :class="chartType === 'bar' ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900' : 'text-slate-500'" @click="chartType = 'bar'">
                            <BarChart3 class="h-3.5 w-3.5" /> Bars
                        </button>
                        <button type="button" class="flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium transition" :class="chartType === 'line' ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900' : 'text-slate-500'" @click="chartType = 'line'">
                            <ChartLine class="h-3.5 w-3.5" /> Lines
                        </button>
                    </div>
                </div>
                <ChartCanvas :type="chartType === 'line' ? 'line' : 'bar'" :data="trendData" :options="trendOpts" height="340px" :empty="!reports.series.length" />
            </AppCard>

            <AppCard class="!p-5">
                <div class="mb-4">
                    <h2 class="font-bold">Running savings</h2>
                    <p class="text-xs text-slate-500">Cumulative net position across the range</p>
                </div>
                <ChartCanvas type="line" :data="runningData" :options="runningOpts" height="340px" :empty="!reports.series.length" />
            </AppCard>
        </div>

        <!-- Breakdowns -->
        <div class="mt-5 grid gap-5 xl:grid-cols-3">
            <AppCard class="!p-5">
                <div class="mb-4">
                    <h2 class="font-bold">Spending by category</h2>
                    <p class="text-xs text-slate-500">{{ money(categoryTotal, currency) }} across {{ reports.categoryBreakdown.length }} categories</p>
                </div>
                <ChartCanvas type="doughnut" :data="categoryDonut" :options="donutOpts" height="260px" :empty="!categoryTotal" empty-text="No expenses in this period." />
            </AppCard>

            <AppCard class="!p-5">
                <div class="mb-4">
                    <h2 class="font-bold">Income sources</h2>
                    <p class="text-xs text-slate-500">{{ reports.sourceBreakdown.length }} source(s) · {{ money(summary.income, currency) }}</p>
                </div>
                <ChartCanvas v-if="reports.sourceBreakdown.length" type="bar" :data="sourceRanking" :options="rankingOpts" height="260px" />
                <p v-else class="grid h-[260px] place-items-center rounded-xl border border-dashed border-slate-200 text-center text-sm text-slate-500 dark:border-slate-700">
                    No income recorded in this period.
                </p>
            </AppCard>

            <AppCard class="!p-5">
                <div class="mb-4">
                    <h2 class="font-bold">Top expense categories</h2>
                    <p class="text-xs text-slate-500">Biggest levers on your spending</p>
                </div>
                <BreakdownBars :rows="reports.categoryBreakdown.slice(0, 7)" metric="total" :currency="currency" empty-text="No expenses recorded in this period." />
            </AppCard>
        </div>

        <!-- Daily intensity -->
        <AppCard v-if="heat.length" class="mt-5 !p-5">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-bold">Daily spending intensity</h2>
                    <p class="text-xs text-slate-500">Last {{ heat.length }} day(s) with expenses · darker means heavier spending</p>
                </div>
                <div class="flex items-center gap-2 text-[11px] text-slate-500">
                    Low
                    <span class="h-3 w-24 rounded-full" style="background: linear-gradient(90deg, #E0E7FF, #4F46E5)" />
                    High
                </div>
            </div>
            <div class="flex flex-wrap gap-1">
                <span
                    v-for="day in heat"
                    :key="day.date"
                    class="h-5 w-5 rounded"
                    :style="{ backgroundColor: `rgba(79, 70, 229, ${0.12 + day.intensity * 0.88})` }"
                    :title="`${date(day.date)}: ${money(day.total, currency)}`"
                />
            </div>
        </AppCard>

        <!-- Full table -->
        <AppCard class="mt-5 !p-5">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-bold">{{ bucketLabel }}-by-{{ bucketLabel.toLowerCase() }} breakdown</h2>
                    <p class="text-xs text-slate-500">{{ reports.period.label }} · scroll for the full list</p>
                </div>
                <div class="flex items-center gap-2">
                    <AppButton variant="secondary" :loading="busy === 'export-csv'" @click="onExport('csv')"><span class="flex items-center gap-2"><FileText class="h-4 w-4" /> CSV</span></AppButton>
                    <AppButton variant="secondary" :loading="busy === 'export-excel'" @click="onExport('excel')"><span class="flex items-center gap-2"><FileSpreadsheet class="h-4 w-4" /> Excel</span></AppButton>
                </div>
            </div>

            <div class="max-h-[460px] overflow-auto rounded-xl border border-slate-200 dark:border-slate-700">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                    <thead class="sticky top-0 z-10 bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-900">
                        <tr>
                            <th class="px-4 py-3">{{ bucketLabel }}</th>
                            <th class="px-4 py-3 text-right">Income</th>
                            <th class="px-4 py-3 text-right">Expenses</th>
                            <th class="px-4 py-3 text-right">Net</th>
                            <th class="px-4 py-3 text-right">Saved %</th>
                            <th class="px-4 py-3 text-right">Running net</th>
                            <th class="px-4 py-3 text-right">Expense change</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        <tr v-for="row in tableRows" :key="row.key" class="hover:bg-slate-50 dark:hover:bg-slate-700/40">
                            <td class="whitespace-nowrap px-4 py-2.5 font-medium">{{ row.full_label }}</td>
                            <td class="px-4 py-2.5 text-right font-mono text-emerald-600 dark:text-emerald-400">{{ row.income ? money(row.income, currency) : '—' }}</td>
                            <td class="px-4 py-2.5 text-right font-mono text-red-500">{{ row.expenses ? money(row.expenses, currency) : '—' }}</td>
                            <td class="px-4 py-2.5 text-right font-mono font-semibold" :class="row.net >= 0 ? 'text-slate-700 dark:text-slate-200' : 'text-red-500'">{{ money(row.net, currency) }}</td>
                            <td class="px-4 py-2.5 text-right text-slate-500">{{ row.savings_rate === null ? '—' : percent(row.savings_rate, 0) }}</td>
                            <td class="px-4 py-2.5 text-right font-mono text-slate-500">{{ money(row.running_net, currency) }}</td>
                            <td class="px-4 py-2.5 text-right text-xs font-medium" :class="row.expenseDelta === null ? 'text-slate-400' : row.expenseDelta > 0 ? 'text-red-500' : 'text-emerald-600 dark:text-emerald-400'">
                                {{ row.expenseDelta === null ? '—' : `${row.expenseDelta > 0 ? '+' : ''}${row.expenseDelta.toFixed(1)}%` }}
                            </td>
                        </tr>
                        <tr class="bg-slate-50 font-semibold dark:bg-slate-900/60">
                            <td class="px-4 py-3">Total</td>
                            <td class="px-4 py-3 text-right font-mono text-emerald-600 dark:text-emerald-400">{{ money(tableTotals.income, currency) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-red-500">{{ money(tableTotals.expenses, currency) }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ money(tableTotals.income - tableTotals.expenses, currency) }}</td>
                            <td class="px-4 py-3 text-right">{{ summary.savings_rate === null ? '—' : percent(summary.savings_rate, 0) }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ money(summary.net, currency) }}</td>
                            <td class="px-4 py-3" />
                        </tr>
                    </tbody>
                </table>
            </div>
        </AppCard>

        <!-- Biggest transactions -->
        <div class="mt-5 grid gap-5 xl:grid-cols-2">
            <AppCard class="!p-5">
                <h2 class="mb-4 font-bold">Largest expenses</h2>
                <p v-if="!reports.topExpenses.length" class="py-6 text-center text-sm text-slate-500">No expenses in this period.</p>
                <ul v-else class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    <li v-for="(row, index) in reports.topExpenses" :key="row.id" class="flex items-center gap-3 py-2.5">
                        <span class="h-8 w-1.5 shrink-0 rounded-full" :style="{ backgroundColor: readableColor(row.category_color, index, { dark: darkMode.value }) }" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ row.description || row.category }}</p>
                            <p class="text-xs text-slate-500">{{ row.category }} · {{ date(row.date) }}{{ row.account ? ` · ${row.account}` : '' }}</p>
                        </div>
                        <span class="shrink-0 font-mono text-sm font-semibold text-red-500">{{ money(row.amount, currency) }}</span>
                    </li>
                </ul>
            </AppCard>

            <AppCard class="!p-5">
                <h2 class="mb-4 font-bold">Largest income entries</h2>
                <p v-if="!reports.topIncome.length" class="py-6 text-center text-sm text-slate-500">No income in this period.</p>
                <ul v-else class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    <li v-for="row in reports.topIncome" :key="row.id" class="flex items-center gap-3 py-2.5">
                        <span class="h-8 w-1.5 shrink-0 rounded-full bg-emerald-500" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ row.description || row.category }}</p>
                            <p class="text-xs text-slate-500">{{ row.category }} · {{ date(row.date) }}</p>
                        </div>
                        <span class="shrink-0 font-mono text-sm font-semibold text-emerald-600 dark:text-emerald-400">{{ money(row.amount, currency) }}</span>
                    </li>
                </ul>
            </AppCard>
        </div>

        <!-- Budgets -->
        <AppCard v-if="hasBudgets" class="mt-5 !p-5">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-bold">Budget performance</h2>
                    <p class="text-xs text-slate-500">Monthly limits prorated across {{ reports.period.days }} day(s)</p>
                </div>
                <RouterLink to="/budgets" class="text-xs font-semibold text-indigo-600 hover:underline dark:text-indigo-400">Manage budgets</RouterLink>
            </div>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div v-for="budget in reports.budgets" :key="budget.id" class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                    <div class="mb-2 flex items-baseline justify-between gap-2">
                        <span class="font-medium">{{ budget.category }}</span>
                        <span class="text-xs font-semibold" :class="(budget.used_percent ?? 0) >= 100 ? 'text-red-500' : (budget.used_percent ?? 0) >= budget.alert_at_percent ? 'text-amber-600' : 'text-emerald-600'">
                            {{ budget.used_percent === null ? '—' : percent(budget.used_percent, 0) }}
                        </span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-700">
                        <div class="h-full rounded-full" :class="(budget.used_percent ?? 0) >= 100 ? 'bg-red-500' : (budget.used_percent ?? 0) >= budget.alert_at_percent ? 'bg-amber-500' : 'bg-emerald-500'" :style="{ width: `${Math.min(budget.used_percent ?? 0, 100)}%` }" />
                    </div>
                    <div class="mt-2 flex items-baseline justify-between text-xs text-slate-500">
                        <span>{{ money(budget.spent, currency) }} spent</span>
                        <span>{{ budget.remaining < 0 ? `${money(Math.abs(budget.remaining), currency)} over` : `${money(budget.remaining, currency)} left` }}</span>
                    </div>
                </div>
            </div>
        </AppCard>

        <p v-if="reports.loading || busy" class="mt-4 flex items-center gap-2 text-xs text-slate-500">
            <Loader2 class="h-3.5 w-3.5 animate-spin" /> {{ reports.loading ? 'Recalculating report…' : 'Working…' }}
        </p>
    </AppLayout>
</template>
