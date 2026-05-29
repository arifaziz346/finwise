<script setup>
import { computed, onMounted } from 'vue';
import AppLayout from '../components/layout/AppLayout.vue';
import AppCard from '../components/ui/AppCard.vue';
import AppSkeleton from '../components/ui/AppSkeleton.vue';
import BarChart from '../components/charts/BarChart.vue';
import DonutChart from '../components/charts/DonutChart.vue';
import { useDashboardStore } from '../stores/dashboard';
import { money } from '../utils/currency';
import { date } from '../utils/date';

const dashboard = useDashboardStore();
onMounted(() => dashboard.fetch());

const cards = computed(() => [
    ['Total Income', dashboard.summary.monthly_income],
    ['Total Expense', dashboard.summary.monthly_expenses],
    ['Remaining Balance', dashboard.summary.remaining_balance],
    ['Budget Status', `${money(dashboard.summary.budget_used)} / ${money(dashboard.summary.budget_limit)}`],
]);
const cashflowData = computed(() => ({
    labels: dashboard.cashflow.map((row) => row.label),
    datasets: [
        { label: 'Income', data: dashboard.cashflow.map((row) => row.income), backgroundColor: '#10B981' },
        { label: 'Expense', data: dashboard.cashflow.map((row) => row.expenses), backgroundColor: '#EF4444' },
    ],
}));
const donutData = computed(() => ({
    labels: dashboard.expenseByCategory.map((row) => row.category),
    datasets: [{ data: dashboard.expenseByCategory.map((row) => row.total), backgroundColor: ['#2563EB', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#14B8A6'] }],
}));
</script>
<template>
    <AppLayout>
        <div class="mb-6 flex items-center justify-between">
            <div><h1 class="text-2xl font-bold tracking-tight">Dashboard</h1><p class="text-sm text-slate-500">Your financial pulse for this month.</p></div>
        </div>
        <div v-if="dashboard.loading" class="grid gap-6 md:grid-cols-2 xl:grid-cols-4"><AppSkeleton v-for="i in 4" :key="i" class="h-28" /></div>
        <div v-else class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
            <AppCard v-for="[label, value] in cards" :key="label">
                <div class="flex items-start justify-between">
                    <div><p class="text-sm text-slate-500">{{ label }}</p><p class="mt-2 font-mono text-2xl font-semibold">{{ typeof value === 'number' ? money(value) : value }}</p></div>
                </div>
                <div v-if="label === 'Budget Status'" class="mt-4 h-2 rounded-full bg-slate-100 dark:bg-slate-700">
                    <div class="h-2 rounded-full bg-emerald-500" :style="{ width: `${Math.min(dashboard.summary.budget_percent || 0, 100)}%` }" />
                </div>
            </AppCard>
        </div>
        <div class="mt-6 grid gap-6 xl:grid-cols-[2fr_1fr]">
            <AppCard><h2 class="mb-4 font-bold">Cash Flow</h2><div class="h-80"><BarChart :data="cashflowData" :options="{ responsive: true, maintainAspectRatio: false }" /></div></AppCard>
            <AppCard><h2 class="mb-4 font-bold">Expense Breakdown</h2><div class="h-80"><DonutChart :data="donutData" :options="{ responsive: true, maintainAspectRatio: false }" /></div></AppCard>
        </div>
        <AppCard class="mt-6"><h2 class="mb-4 font-bold">Recent Transactions</h2><div class="divide-y divide-slate-100 dark:divide-slate-700"><div v-for="row in dashboard.recent" :key="`${row.type}-${row.id}`" class="flex items-center justify-between py-3"><div><p class="font-medium">{{ row.description || row.source?.name || row.category?.name || row.type }}</p><p class="text-xs text-slate-500">{{ date(row.received_date || row.expense_date) }}</p></div><p :class="row.type === 'income' ? 'text-emerald-600' : 'text-red-500'" class="font-mono font-semibold">{{ row.type === 'income' ? '+' : '-' }}{{ money(row.amount) }}</p></div></div></AppCard>
    </AppLayout>
</template>
