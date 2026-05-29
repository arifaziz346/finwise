import { defineStore } from 'pinia';
import api from '../services/api';

export const useDashboardStore = defineStore('dashboard', {
    state: () => ({ summary: {}, cashflow: [], recent: [], expenseByCategory: [], loading: false }),
    actions: {
        async fetch() {
            this.loading = true;
            const [summary, cashflow, recent, expenseByCategory] = await Promise.all([
                api.get('/dashboard/summary'),
                api.get('/dashboard/cashflow'),
                api.get('/dashboard/recent-transactions'),
                api.get('/dashboard/expense-by-category'),
            ]);
            this.summary = summary.data;
            this.cashflow = cashflow.data;
            this.recent = recent.data;
            this.expenseByCategory = expenseByCategory.data;
            this.loading = false;
        },
    },
});
