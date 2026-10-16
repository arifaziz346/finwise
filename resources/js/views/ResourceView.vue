<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useToast } from 'vue-toastification';
import { Plus } from 'lucide-vue-next';
import AppLayout from '../components/layout/AppLayout.vue';
import AppCard from '../components/ui/AppCard.vue';
import AppButton from '../components/ui/AppButton.vue';
import AppInput from '../components/ui/AppInput.vue';
import AppSelect from '../components/ui/AppSelect.vue';
import AppModal from '../components/ui/AppModal.vue';
import AppTable from '../components/ui/AppTable.vue';
import AppBadge from '../components/ui/AppBadge.vue';
import AppConfirmModal from '../components/ui/AppConfirmModal.vue';
import ExpenseFormSheet from '../components/ui/ExpenseFormSheet.vue';
import api from '../services/api';
import { money } from '../utils/currency';
import { date } from '../utils/date';

const props = defineProps({ slug: String });
const toast = useToast();
const mode = ref(['categories', 'income-categories'].includes(props.slug) ? 'categories' : 'entries');
const rows = ref([]);
const categories = ref([]);
const budgetRows = ref([]);
const summary = ref(null);
const report = ref(null);
const meta = ref({});
const loading = ref(false);
const busy = ref('');
const modal = ref(false);
const editing = ref(null);
const pending = ref(null);
const errors = ref({});
const filters = reactive({ search: '', category_id: '', source_id: '', date_from: '', date_to: '', is_blocked: '', month: new Date().toISOString().slice(0, 7), page: 1 });
const form = ref({});
const selectedIds = ref([]);

// Expense entry gets its own mobile-first sheet with category suggestions.
const expenseSheetOpen = ref(false);
const expenseEditing = ref(null);
const expenseErrors = ref({});
const expenseOptions = ref({ suggestions: [], categories: [], defaults: {}, payment_methods: [] });

const isIncome = computed(() => props.slug === 'income' || props.slug === 'income-categories');
const isExpense = computed(() => props.slug === 'expenses' || props.slug === 'categories');
const isBudget = computed(() => props.slug === 'budgets');
const categoryEndpoint = computed(() => isIncome.value ? '/income-sources' : '/expense-categories');
const entryEndpoint = computed(() => isIncome.value ? '/income' : isExpense.value ? '/expenses' : '/budgets');
const title = computed(() => isIncome.value ? 'Income' : isExpense.value ? 'Expense' : 'Budget');
const activeCategories = computed(() => categories.value.filter((item) => !item.is_blocked));
const categoryOptions = computed(() => [{ label: 'All categories', value: '' }, ...categories.value.map((item) => ({ label: item.name, value: item.id }))]);
const activeCategoryOptions = computed(() => activeCategories.value.map((item) => ({ label: item.name, value: item.id })));
const statusOptions = [{ label: 'All statuses', value: '' }, { label: 'Active', value: 0 }, { label: 'Blocked', value: 1 }];
const currentEndpoint = computed(() => mode.value === 'categories' ? categoryEndpoint.value : entryEndpoint.value);
const visibleIds = computed(() => rows.value.map((row) => row.id));
const selectedRows = computed(() => rows.value.filter((row) => selectedIds.value.includes(row.id)));
const allVisibleSelected = computed(() => visibleIds.value.length > 0 && visibleIds.value.every((id) => selectedIds.value.includes(id)));

const resetForm = () => {
    errors.value = {};
    editing.value = null;
    if (mode.value === 'categories') form.value = { name: '', type: isIncome.value ? 'active' : undefined, color: '#2563EB' };
    else if (isBudget.value) form.value = { category_id: '', amount: '', start_date: `${filters.month}-01`, end_date: '', is_blocked: false };
    else form.value = { amount: '', [isIncome.value ? 'source_id' : 'category_id']: '', [isIncome.value ? 'received_date' : 'expense_date']: new Date().toISOString().slice(0, 10), description: '', is_blocked: false };
};

const loadCategories = async () => {
    const { data } = await api.get(categoryEndpoint.value, { params: { per_page: 100, search: filters.search, is_blocked: filters.is_blocked } });
    categories.value = data.data ?? data;
};

/** Category suggestions + smart defaults for the quick-entry sheet. */
const loadExpenseOptions = async () => {
    const { data } = await api.get('/expenses/form-options');
    expenseOptions.value = data;
};

const load = async () => {
    loading.value = true;
    selectedIds.value = [];
    await loadCategories();
    if (mode.value === 'categories') {
        rows.value = categories.value;
        meta.value = {};
    } else if (isBudget.value) {
        const params = { category_id: filters.category_id, month: filters.month };
        const [list, status] = await Promise.all([api.get('/budgets', { params: { ...params, search: filters.search, is_blocked: filters.is_blocked, page: filters.page } }), api.get('/budgets/status', { params })]);
        rows.value = list.data.data ?? list.data;
        meta.value = list.data.meta ?? {};
        budgetRows.value = status.data.data ?? status.data;
    } else {
        const params = { search: filters.search, date_from: filters.date_from, date_to: filters.date_to, is_blocked: filters.is_blocked, page: filters.page };
        params[isIncome.value ? 'source_id' : 'category_id'] = filters.category_id || filters.source_id;
        const [list, extra] = await Promise.all([
            api.get(entryEndpoint.value, { params }),
            api.get(isIncome.value ? '/income/summary' : '/expenses-report', { params }),
        ]);
        rows.value = list.data.data ?? list.data;
        meta.value = list.data.meta ?? {};
        summary.value = isIncome.value ? extra.data : null;
        report.value = isExpense.value ? extra.data : null;
    }
    loading.value = false;
};

const runWithProgress = async (key, action) => {
    busy.value = key;
    try {
        return await action();
    } finally {
        busy.value = '';
    }
};

// ------------------------------------------------------- expense quick entry

const openExpenseSheet = (row = null) => {
    expenseErrors.value = {};
    expenseEditing.value = row;
    expenseSheetOpen.value = true;
};

const closeExpenseSheet = () => {
    expenseSheetOpen.value = false;
    expenseEditing.value = null;
    expenseErrors.value = {};
};

const saveExpense = async (payload) => {
    await runWithProgress('expense-save', async () => {
        const { category_name, ...body } = payload;

        try {
            if (expenseEditing.value) {
                await api.put(`/expenses/${expenseEditing.value.id}`, body);
                toast.success('Expense updated');
            } else {
                await api.post('/expenses', body);
                toast.success(`${category_name ?? 'Expense'} · ${money(body.amount)} added`);
            }

            closeExpenseSheet();
            // Refresh suggestions so the sheet learns from what was just logged.
            await Promise.all([load(), loadExpenseOptions()]);
        } catch (error) {
            expenseErrors.value = error.response?.data?.errors ?? {};
            toast.error(error.response?.data?.message ?? 'Unable to save the expense');
        }
    });
};

const toggleSelectAll = () => {
    selectedIds.value = allVisibleSelected.value ? [] : [...visibleIds.value];
};

const validate = () => {
    const next = {};
    if (mode.value === 'categories' && !form.value.name) next.name = 'Name is required.';
    if (mode.value === 'entries') {
        if (!form.value.amount || Number(form.value.amount) <= 0) next.amount = 'Amount must be greater than zero.';
        if (isBudget.value && !form.value.category_id) next.category_id = 'Category is required.';
        if (!isBudget.value && !form.value[isIncome.value ? 'source_id' : 'category_id']) next.category_id = 'Category is required.';
        if (isBudget.value && !form.value.start_date) next.start_date = 'Start date is required.';
        if (isIncome.value && !form.value.received_date) next.received_date = 'Date is required.';
        if (isExpense.value && !form.value.expense_date) next.expense_date = 'Date is required.';
    }
    errors.value = next;
    return !Object.keys(next).length;
};

const openCreate = () => {
    // Expenses use the dedicated mobile-first sheet with category suggestions.
    if (isExpense.value && mode.value === 'entries') {
        openExpenseSheet(null);
        return;
    }
    resetForm();
    modal.value = true;
};

const openEdit = (row) => {
    if (isExpense.value && mode.value === 'entries') {
        openExpenseSheet(row);
        return;
    }
    editing.value = row;
    errors.value = {};
    form.value = { ...row, source_id: row.source_id ?? row.source?.id, category_id: row.category_id ?? row.category?.id };
    modal.value = true;
};

const save = async () => {
    if (!validate()) return;
    await runWithProgress('save', async () => {
        const endpoint = mode.value === 'categories' ? categoryEndpoint.value : entryEndpoint.value;
        const payload = { ...form.value };
        if (isBudget.value) payload.period = 'monthly';
        try {
            if (editing.value) await api.put(`${endpoint}/${editing.value.id}`, payload);
            else await api.post(endpoint, payload);
            toast.success(`${title.value} saved`);
            modal.value = false;
            await load();
        } catch (error) {
            errors.value = error.response?.data?.errors ?? {};
            toast.error(error.response?.data?.message ?? 'Unable to save');
        }
    });
};

const ask = (action, row = null) => {
    const rowsForAction = row ? [row] : selectedRows.value;
    if (!rowsForAction.length) {
        toast.error('Select at least one row first');
        return;
    }
    pending.value = { action, rows: rowsForAction, row };
};
const confirmAction = async () => {
    await runWithProgress('confirm', async () => {
        const { action, rows: targetRows } = pending.value;
        const endpoint = currentEndpoint.value;

        if (action === 'delete') {
            await Promise.all(targetRows.map((row) => api.delete(`${endpoint}/${row.id}`)));
        }

        if (action === 'toggle') {
            await Promise.all(targetRows.map((row) => api.put(`${endpoint}/${row.id}`, { is_blocked: !row.is_blocked })));
        }

        if (action === 'block') {
            await Promise.all(targetRows.map((row) => api.put(`${endpoint}/${row.id}`, { is_blocked: true })));
        }

        if (action === 'unblock') {
            await Promise.all(targetRows.map((row) => api.put(`${endpoint}/${row.id}`, { is_blocked: false })));
        }

        toast.success(action === 'delete' ? 'Deleted' : 'Status updated');
        pending.value = null;
        selectedIds.value = [];
        await load();
    });
};

const downloadReport = async (type) => {
    await runWithProgress(`download-${type}`, async () => {
        const params = { category_id: filters.category_id, date_from: filters.date_from, date_to: filters.date_to };
        const { data } = await api.get(`/expenses-report/${type}`, { params, responseType: 'blob' });
        const url = URL.createObjectURL(data);
        const link = document.createElement('a');
        link.href = url;
        link.download = `expense-report.${type}`;
        link.click();
        URL.revokeObjectURL(url);
    });
};

const applyFilters = () => {
    filters.page = 1;
    return runWithProgress('apply', load);
};

const changePage = (direction) => {
    filters.page += direction;
    return runWithProgress(direction < 0 ? 'previous' : 'next', load);
};

const needsExpenseOptions = computed(() => props.slug === 'expenses');

const bootstrap = async () => {
    resetForm();
    await load();
    if (needsExpenseOptions.value) await loadExpenseOptions();
};

watch(() => props.slug, async () => {
    mode.value = ['categories', 'income-categories'].includes(props.slug) ? 'categories' : 'entries';
    filters.page = 1;
    selectedIds.value = [];
    expenseSheetOpen.value = false;
    await bootstrap();
});

watch(mode, async () => {
    filters.page = 1;
    selectedIds.value = [];
    resetForm();
    await load();
    if (needsExpenseOptions.value && mode.value === 'entries') await loadExpenseOptions();
});

onMounted(bootstrap);
</script>

<template>
    <AppLayout>
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">{{ title }}</h1>
                <p class="text-sm text-slate-500">{{ isBudget ? 'Category budget limits and progress.' : 'Manage entries and categories.' }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <AppButton v-if="!isBudget" :variant="mode === 'entries' ? 'primary' : 'secondary'" @click="mode = 'entries'">Entries</AppButton>
                <AppButton v-if="!isBudget" :variant="mode === 'categories' ? 'primary' : 'secondary'" @click="mode = 'categories'">Categories</AppButton>
                <AppButton class="hidden lg:inline-flex" @click="openCreate">
                    {{ isExpense && mode === 'entries' ? 'Add expense' : `Add ${mode === 'categories' ? 'Category' : title}` }}
                </AppButton>
            </div>
        </div>

        <AppCard class="mb-6">
            <div class="grid gap-3 md:grid-cols-5">
                <AppInput v-model="filters.search" label="Search" />
                <AppSelect v-if="mode === 'entries'" v-model="filters.category_id" label="Category" :options="categoryOptions" />
                <AppInput v-if="!isBudget" v-model="filters.date_from" label="From" type="date" />
                <AppInput v-if="!isBudget" v-model="filters.date_to" label="To" type="date" />
                <AppInput v-if="isBudget" v-model="filters.month" label="Month" type="month" />
                <AppSelect v-model="filters.is_blocked" label="Status" :options="statusOptions" />
                <AppButton variant="secondary" class="self-end" :loading="busy === 'apply'" @click="applyFilters">Apply</AppButton>
            </div>
        </AppCard>

        <div v-if="isIncome && mode === 'entries' && summary" class="mb-6 grid gap-6 md:grid-cols-2">
            <AppCard><p class="text-sm text-slate-500">Total income for period</p><p class="mt-2 font-mono text-2xl font-semibold">{{ money(summary.total) }}</p></AppCard>
            <AppCard><p class="mb-3 text-sm text-slate-500">Breakdown by category</p><div v-for="row in summary.by_category" :key="row.category" class="flex justify-between py-1"><span>{{ row.category }}</span><strong>{{ money(row.total) }}</strong></div></AppCard>
        </div>

        <AppCard v-if="isExpense && mode === 'entries' && report" class="mb-6">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3"><h2 class="font-bold">Expense Report</h2><div class="flex gap-2"><AppButton variant="secondary" :loading="busy === 'download-csv'" @click="downloadReport('csv')">CSV</AppButton><AppButton variant="secondary" :loading="busy === 'download-pdf'" @click="downloadReport('pdf')">PDF</AppButton></div></div>
            <div v-for="row in report.categories" :key="row.category" class="flex justify-between border-b border-slate-100 py-2 dark:border-slate-700"><span>{{ row.category }}</span><strong>{{ money(row.total) }}</strong></div>
            <div class="flex justify-between pt-3 font-bold"><span>Grand Total</span><span>{{ money(report.grand_total) }}</span></div>
        </AppCard>

        <AppCard v-if="isBudget" class="mb-6">
            <h2 class="mb-4 font-bold">Budget Progress</h2>
            <div v-for="budget in budgetRows" :key="budget.id" class="mb-4">
                <div class="mb-1 flex justify-between text-sm"><span>{{ budget.category?.name }}</span><span>{{ money(budget.spent) }} / {{ money(budget.amount) }}</span></div>
                <div class="h-2 rounded-full bg-slate-100 dark:bg-slate-700"><div class="h-2 rounded-full" :class="budget.used_percent >= 100 ? 'bg-red-500' : budget.used_percent >= 80 ? 'bg-amber-500' : 'bg-emerald-500'" :style="{ width: `${Math.min(budget.used_percent, 100)}%` }" /></div>
                <p class="mt-1 text-xs" :class="budget.remaining < 0 ? 'text-red-500' : 'text-slate-500'">Remaining: {{ money(budget.remaining) }}</p>
            </div>
        </AppCard>

        <AppCard>
            <div v-if="loading" class="py-10 text-center text-slate-500">Loading...</div>
            <div v-if="!loading && selectedIds.length" class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800">
                <span class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ selectedIds.length }} selected</span>
                <div class="flex flex-wrap gap-2">
                    <AppButton variant="secondary" @click="ask('block')">Block</AppButton>
                    <AppButton variant="secondary" @click="ask('unblock')">Unblock</AppButton>
                    <AppButton variant="secondary" @click="ask('delete')">Delete</AppButton>
                </div>
            </div>
            <!-- Phones: card list. Tablets and up: the dense table. -->
            <div v-if="!loading && mode === 'entries' && !rows.length" class="py-12 text-center text-sm text-slate-500">
                Nothing here yet. Tap “Add expense” to record your first entry.
            </div>

            <ul v-if="!loading && mode === 'entries' && rows.length" class="grid gap-2 lg:hidden">
                <li
                    v-for="row in rows"
                    :key="row.id"
                    class="flex items-center gap-3 rounded-2xl border border-slate-200 p-3 transition active:bg-slate-50 dark:border-slate-700 dark:active:bg-slate-700/40"
                    @click="openEdit(row)"
                >
                    <span
                        v-if="isExpense"
                        class="grid h-10 w-10 shrink-0 place-items-center rounded-xl text-xs font-bold text-white"
                        :style="{ backgroundColor: row.category?.color || '#6366F1' }"
                    >
                        {{ (row.category?.name || '?').charAt(0).toUpperCase() }}
                    </span>
                    <span v-else class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-emerald-50 text-xs font-bold text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                        {{ (row.source?.name || '?').charAt(0).toUpperCase() }}
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold">{{ row.category?.name || row.source?.name || 'Uncategorised' }}</p>
                        <p class="truncate text-xs text-slate-500">
                            {{ date(row.expense_date || row.received_date) }}<template v-if="row.description"> · {{ row.description }}</template>
                        </p>
                    </div>

                    <div class="shrink-0 text-right">
                        <p class="font-mono text-sm font-semibold" :class="isIncome ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-900 dark:text-slate-100'">{{ money(row.amount) }}</p>
                        <p v-if="row.is_blocked" class="text-[11px] font-medium text-amber-600 dark:text-amber-400">Blocked</p>
                    </div>
                </li>
            </ul>

            <AppTable v-if="!loading && mode === 'categories'" class="hidden lg:block">
                <template #head>
                    <tr>
                        <th class="w-12 px-4 py-3">
                            <input class="h-4 w-4 rounded border-slate-300 text-indigo-600" type="checkbox" :checked="allVisibleSelected" @change="toggleSelectAll">
                        </th>
                        <th class="px-4 py-3">{{ mode === 'categories' ? 'Name' : 'Category' }}</th>
                        <th v-if="mode === 'entries'" class="px-4 py-3">Amount</th>
                        <th v-if="mode === 'entries'" class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Actions</th>
                    </tr>
                </template>
                <tr v-for="row in rows" :key="row.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                    <td class="px-4 py-3">
                        <input v-model="selectedIds" class="h-4 w-4 rounded border-slate-300 text-indigo-600" type="checkbox" :value="row.id">
                    </td>
                    <td class="px-4 py-3">{{ mode === 'categories' ? row.name : row.source?.name || row.category?.name }}</td>
                    <td v-if="mode === 'entries'" class="px-4 py-3 font-mono font-semibold">{{ money(row.amount) }}</td>
                    <td v-if="mode === 'entries'" class="px-4 py-3">{{ date(row.received_date || row.expense_date || row.start_date) }}</td>
                    <td class="px-4 py-3"><AppBadge>{{ row.is_blocked ? 'Blocked' : 'Active' }}</AppBadge></td>
                    <td class="px-4 py-3"><div class="flex flex-wrap gap-3"><button class="text-indigo-600" @click="openEdit(row)">Edit</button><button class="text-amber-600" @click="ask('toggle', row)">{{ row.is_blocked ? 'Unblock' : 'Block' }}</button><button class="text-red-500" @click="ask('delete', row)">Delete</button></div></td>
                </tr>
            </AppTable>

            <!-- Desktop table for expenses (the mobile list above replaces it) -->
            <AppTable v-if="!loading && mode === 'entries' && isExpense && rows.length" class="hidden lg:block">
                <template #head>
                    <tr>
                        <th class="w-12 px-4 py-3">
                            <input class="h-4 w-4 rounded border-slate-300 text-indigo-600" type="checkbox" :checked="allVisibleSelected" @change="toggleSelectAll">
                        </th>
                        <th class="px-4 py-3">Category</th>
                        <th class="px-4 py-3">Note</th>
                        <th class="px-4 py-3">Amount</th>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Method</th>
                        <th class="px-4 py-3">Actions</th>
                    </tr>
                </template>
                <tr v-for="row in rows" :key="row.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                    <td class="px-4 py-3">
                        <input v-model="selectedIds" class="h-4 w-4 rounded border-slate-300 text-indigo-600" type="checkbox" :value="row.id">
                    </td>
                    <td class="px-4 py-3">
                        <span class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: row.category?.color || '#6366F1' }" />
                            {{ row.category?.name || 'Uncategorised' }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-slate-500">{{ row.description || '—' }}</td>
                    <td class="px-4 py-3 font-mono font-semibold">{{ money(row.amount) }}</td>
                    <td class="px-4 py-3">{{ date(row.expense_date) }}</td>
                    <td class="px-4 py-3 capitalize text-slate-500">{{ row.payment_method || 'cash' }}<span v-if="row.is_blocked" class="ml-2 text-amber-600">blocked</span></td>
                    <td class="px-4 py-3"><div class="flex flex-wrap gap-3"><button class="text-indigo-600" @click="openEdit(row)">Edit</button><button class="text-amber-600" @click="ask('toggle', row)">{{ row.is_blocked ? 'Unblock' : 'Block' }}</button><button class="text-red-500" @click="ask('delete', row)">Delete</button></div></td>
                </tr>
            </AppTable>
            <div class="mt-4 flex items-center justify-between text-sm text-slate-500">
                <span>Page {{ meta.current_page ?? 1 }} of {{ meta.last_page ?? 1 }}</span>
                <div class="flex gap-2"><AppButton variant="secondary" :loading="busy === 'previous'" :disabled="(meta.current_page ?? 1) <= 1" @click="changePage(-1)">Previous</AppButton><AppButton variant="secondary" :loading="busy === 'next'" :disabled="(meta.current_page ?? 1) >= (meta.last_page ?? 1)" @click="changePage(1)">Next</AppButton></div>
            </div>
        </AppCard>

        <AppModal :open="modal" :title="`${editing ? 'Edit' : 'Add'} ${mode === 'categories' ? 'Category' : title}`" @close="modal = false">
            <form class="grid gap-4" @submit.prevent="save">
                <template v-if="mode === 'categories'">
                    <AppInput v-model="form.name" label="Name" :error="errors.name?.[0] || errors.name" />
                    <AppSelect v-if="isIncome" v-model="form.type" label="Type" :options="[{ label: 'Active', value: 'active' }, { label: 'Passive', value: 'passive' }]" />
                    <AppInput v-if="isExpense" v-model="form.color" label="Color" type="color" />
                </template>
                <template v-else-if="isIncome">
                    <AppInput v-model="form.amount" label="Amount" type="number" :error="errors.amount?.[0] || errors.amount" />
                    <AppSelect v-model="form.source_id" label="Category" :options="activeCategoryOptions" :error="errors.source_id?.[0] || errors.category_id?.[0]" />
                    <AppInput v-model="form.received_date" label="Date" type="date" :error="errors.received_date?.[0] || errors.received_date" />
                    <AppInput v-model="form.description" label="Note" />
                </template>
                <template v-else-if="isBudget">
                    <AppInput v-model="form.amount" label="Amount" type="number" :error="errors.amount?.[0] || errors.amount" />
                    <AppSelect v-model="form.category_id" label="Category" :options="activeCategoryOptions" :error="errors.category_id?.[0]" />
                    <AppInput v-model="form.start_date" label="Start Date" type="date" :error="errors.start_date?.[0] || errors.start_date" />
                    <AppInput v-model="form.end_date" label="End Date" type="date" />
                </template>
                <AppButton :loading="busy === 'save'">Save</AppButton>
            </form>
        </AppModal>
        <AppConfirmModal
            :open="Boolean(pending)"
            :title="pending?.action === 'delete' ? 'Confirm delete' : 'Confirm status change'"
            :action-label="pending?.action === 'delete' ? 'Delete' : (pending?.action === 'unblock' || pending?.row?.is_blocked ? 'Unblock' : 'Block')"
            :message="pending?.action === 'delete' ? `This will permanently delete ${pending?.rows?.length ?? 1} selected item(s).` : `This will update ${pending?.rows?.length ?? 1} selected item(s).`"
            :loading="busy === 'confirm'"
            @close="pending = null"
            @confirm="confirmAction"
        />

        <!-- Thumb-reachable quick entry on phones -->
        <button
            v-if="isExpense && mode === 'entries'"
            type="button"
            class="fixed bottom-20 right-4 z-40 grid h-14 w-14 place-items-center rounded-2xl bg-indigo-600 text-white shadow-xl shadow-indigo-600/30 transition active:scale-95 lg:hidden"
            aria-label="Add expense"
            @click="openExpenseSheet(null)"
        >
            <Plus class="h-7 w-7" />
        </button>

        <ExpenseFormSheet
            v-if="isExpense && mode === 'entries'"
            :open="expenseSheetOpen"
            :options="expenseOptions"
            :editing="expenseEditing"
            :loading="busy === 'expense-save'"
            :errors="expenseErrors"
            @close="closeExpenseSheet"
            @saved="saveExpense"
        />
    </AppLayout>
</template>
