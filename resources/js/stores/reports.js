import { defineStore } from 'pinia';
import api from '../services/api';

/**
 * One store drives both the dashboard and the reports screen.
 *
 * All period maths happens on the server (App\Support\Reports\ReportQuery) and
 * comes back in the payload's `period` block, so the UI never re-derives dates.
 */
export const useReportsStore = defineStore('reports', {
    state: () => ({
        // ---- applied filters (what the server is currently showing) ----
        preset: 'last_6_months',
        granularity: 'auto',
        categoryId: '',
        sourceId: '',
        search: '',
        includeBlocked: false,
        customFrom: '',
        customTo: '',

        // ---- last successful payload ----
        period: {},
        summary: {},
        previous: null,
        series: [],
        categoryBreakdown: [],
        sourceBreakdown: [],
        topExpenses: [],
        topIncome: [],
        budgets: [],
        accounts: {},
        recent: [],
        insights: [],
        filters: { categories: [], sources: [], accounts: [], presets: [], has_budgets: false },
        currency: 'PKR',

        loading: false,
        filtersLoaded: false,
        error: null,
    }),

    getters: {
        granularityLabel: (state) => state.period.bucket_label ?? 'Period',
        hasData: (state) => Boolean(state.summary?.has_data),
        isEmptyPeriod: (state) => Boolean(state.period?.is_empty),
        categoryOptions: (state) => [
            { label: 'All categories', value: '' },
            ...state.filters.categories.filter((item) => !item.is_blocked).map((item) => ({ label: item.name, value: String(item.id) })),
        ],
        sourceOptions: (state) => [
            { label: 'All sources', value: '' },
            ...state.filters.sources.filter((item) => !item.is_blocked).map((item) => ({ label: item.name, value: String(item.id) })),
        ],
        /** Top categories for a doughnut, with the tail folded into "Other". */
        categoryChartRows: (state) => foldTail(state.categoryBreakdown, 8),
        sourceChartRows: (state) => foldTail(state.sourceBreakdown, 6),
        activeFilterCount: (state) => [state.categoryId, state.sourceId, state.search].filter((value) => value !== '' && value !== null).length + (state.includeBlocked ? 1 : 0),
        periodLabel: (state) => state.period.label ?? '',
        /** Human summary used in headers: "Jun 1 – Oct 31, 2026 · 153 days". */
        periodSummary: (state) => {
            if (!state.period.from) return '';
            const days = state.period.days ?? 0;
            return `${state.period.from} → ${state.period.to} · ${days} day${days === 1 ? '' : 's'}`;
        },
    },

    actions: {
        /** Query params built from the applied filters. */
        params(overrides = {}) {
            const query = {
                preset: this.preset,
                granularity: this.granularity,
                include_blocked: this.includeBlocked ? 1 : 0,
                ...overrides,
            };

            if (this.preset === 'custom') {
                query.from = this.customFrom;
                query.to = this.customTo || this.customFrom;
            }
            if (this.categoryId) query.category_id = this.categoryId;
            if (this.sourceId) query.source_id = this.sourceId;
            if (this.search) query.search = this.search;

            return query;
        },

        async loadFilters(force = false) {
            if (this.filtersLoaded && !force) return;
            const { data } = await api.get('/reports/filters');
            this.filters = data;
            this.filtersLoaded = true;

            // The server resolves which window actually holds data; adopt it on the
            // very first call so the dashboard never opens on an empty month.
            if (data.default_period?.auto && !this.period.from) {
                this.preset = 'last_6_months';
            }
        },

        async load(overrides = {}) {
            this.loading = true;
            this.error = null;

            try {
                const { data } = await api.get('/reports/dashboard', { params: this.params(overrides) });
                this.apply(data);
            } catch (error) {
                this.error = error.response?.data?.message ?? 'Unable to load the report right now.';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async loadOverview(overrides = {}) {
            this.loading = true;
            this.error = null;

            try {
                const { data } = await api.get('/reports/overview', { params: this.params(overrides) });
                this.apply(data);
            } catch (error) {
                this.error = error.response?.data?.message ?? 'Unable to load the report right now.';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        apply(data) {
            this.period = data.period ?? {};
            this.summary = data.summary ?? {};
            this.previous = data.previous ?? null;
            this.series = data.series ?? [];
            this.categoryBreakdown = data.category_breakdown ?? [];
            this.sourceBreakdown = data.source_breakdown ?? [];
            this.topExpenses = data.top_expenses ?? [];
            this.topIncome = data.top_income ?? [];
            this.budgets = data.budgets ?? [];
            this.accounts = data.accounts ?? {};
            this.recent = data.recent ?? [];
            this.insights = data.insights ?? [];
            if (data.filters) this.filters = { ...this.filters, ...data.filters };
            if (data.currency) this.currency = data.currency;
        },

        /** Adopt a preset and reload. */
        async applyPreset(preset) {
            this.preset = preset;
            if (preset !== 'custom') {
                this.customFrom = '';
                this.customTo = '';
            }
            await this.load();
        },

        async applyGranularity(granularity) {
            this.granularity = granularity;
            await this.load();
        },

        async applyCustomRange(from, to) {
            this.preset = 'custom';
            this.customFrom = from;
            this.customTo = to;
            await this.load();
        },

        async applyFilters(partial = {}) {
            Object.assign(this, partial);
            await this.load();
        },

        async resetFilters() {
            this.preset = 'last_6_months';
            this.granularity = 'auto';
            this.categoryId = '';
            this.sourceId = '';
            this.search = '';
            this.includeBlocked = false;
            this.customFrom = '';
            this.customTo = '';
            await this.load();
        },

        /** Trigger a file download for the current filters. */
        async export(format) {
            const { data } = await api.get(`/reports/export/${format}`, { params: this.params(), responseType: 'blob' });
            const url = URL.createObjectURL(data);
            const link = document.createElement('a');
            const stamp = `${this.period.from ?? 'report'}-to-${this.period.to ?? 'today'}`;
            link.href = url;
            link.download = `finwise-report-${stamp}.${format === 'excel' ? 'xlsx' : format}`;
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(url);
        },
    },
});

/** Keep charts readable by folding long tails into a single "Other" slice. */
function foldTail(rows = [], limit = 8) {
    if (rows.length <= limit) return rows;

    const head = rows.slice(0, limit);
    const tail = rows.slice(limit);
    const tailTotal = tail.reduce((sum, row) => sum + Number(row.total || 0), 0);

    return [
        ...head,
        {
            category: `Other (${tail.length})`,
            total: Math.round(tailTotal * 100) / 100,
            share: Math.round(tail.reduce((sum, row) => sum + Number(row.share || 0), 0) * 10) / 10,
            entries: tail.reduce((sum, row) => sum + Number(row.entries || 0), 0),
            color: '#94A3B8',
        },
    ];
}
