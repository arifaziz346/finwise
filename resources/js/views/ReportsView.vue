<script setup>
import { onMounted, ref } from 'vue';
import AppLayout from '../components/layout/AppLayout.vue';
import AppCard from '../components/ui/AppCard.vue';
import BarChart from '../components/charts/BarChart.vue';
import DonutChart from '../components/charts/DonutChart.vue';
import api from '../services/api';

const cashflow = ref([]);
const categories = ref([]);
onMounted(async () => {
    cashflow.value = (await api.get('/reports/income-expense')).data;
    categories.value = (await api.get('/reports/expense-by-category')).data;
});
</script>
<template>
    <AppLayout>
        <div class="mb-6 flex items-center justify-between"><h1 class="text-2xl font-bold tracking-tight">Reports</h1><div class="flex gap-3"><a class="fw-btn-primary" href="/api/v1/reports/export-pdf" target="_blank">PDF</a><a class="fw-btn-primary" href="/api/v1/reports/export-excel" target="_blank">Excel</a></div></div>
        <div class="grid gap-6 xl:grid-cols-2">
            <AppCard><h2 class="mb-4 font-bold">Income vs Expense</h2><div class="h-80"><BarChart :data="{ labels: cashflow.map(i => i.label), datasets: [{ label: 'Income', data: cashflow.map(i => i.income), backgroundColor: '#10B981' }, { label: 'Expense', data: cashflow.map(i => i.expenses), backgroundColor: '#EF4444' }] }" :options="{ responsive: true, maintainAspectRatio: false }" /></div></AppCard>
            <AppCard><h2 class="mb-4 font-bold">Expense by Category</h2><div class="h-80"><DonutChart :data="{ labels: categories.map(i => i.category?.name || 'Other'), datasets: [{ data: categories.map(i => i.total), backgroundColor: ['#6366F1','#10B981','#F59E0B','#EF4444'] }] }" :options="{ responsive: true, maintainAspectRatio: false }" /></div></AppCard>
        </div>
    </AppLayout>
</template>
