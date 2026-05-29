<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useToast } from 'vue-toastification';
import AppLayout from '../components/layout/AppLayout.vue';
import AppCard from '../components/ui/AppCard.vue';
import AppButton from '../components/ui/AppButton.vue';
import AppInput from '../components/ui/AppInput.vue';
import AppSelect from '../components/ui/AppSelect.vue';
import AppModal from '../components/ui/AppModal.vue';
import AppTable from '../components/ui/AppTable.vue';
import AppBadge from '../components/ui/AppBadge.vue';
import AppConfirmModal from '../components/ui/AppConfirmModal.vue';
import api from '../services/api';
import { date } from '../utils/date';

const toast = useToast();
const rows = ref([]);
const meta = ref({});
const loading = ref(false);
const busy = ref('');
const modal = ref(false);
const editing = ref(null);
const pending = ref(null);
const errors = ref({});
const selectedIds = ref([]);
const filters = reactive({ search: '', date_from: '', date_to: '', is_paid: '', page: 1 });
const form = ref({});

const statusOptions = [{ label: 'All statuses', value: '' }, { label: 'Pending', value: 0 }, { label: 'Complete', value: 1 }];
const visibleIds = computed(() => rows.value.map((row) => row.id));
const selectedRows = computed(() => rows.value.filter((row) => selectedIds.value.includes(row.id)));
const allVisibleSelected = computed(() => visibleIds.value.length > 0 && visibleIds.value.every((id) => selectedIds.value.includes(id)));

const resetForm = () => {
    editing.value = null;
    errors.value = {};
    form.value = { title: '', amount: 0, due_date: new Date().toISOString().slice(0, 10), due_time: '', notify_before_days: 1, frequency: 'once', is_paid: false };
};

const run = async (key, action) => {
    busy.value = key;
    try { return await action(); } finally { busy.value = ''; }
};

const load = async () => {
    loading.value = true;
    selectedIds.value = [];
    const { data } = await api.get('/reminders', { params: filters });
    rows.value = data.data ?? data;
    meta.value = data.meta ?? {};
    loading.value = false;
};

const applyFilters = () => { filters.page = 1; return run('apply', load); };
const changePage = (direction) => { filters.page += direction; return run(direction < 0 ? 'previous' : 'next', load); };
const toggleSelectAll = () => { selectedIds.value = allVisibleSelected.value ? [] : [...visibleIds.value]; };
const openCreate = () => { resetForm(); modal.value = true; };
const openEdit = (row) => { editing.value = row; errors.value = {}; form.value = { ...row, due_date: String(row.due_date).slice(0, 10), due_time: row.due_time || '' }; modal.value = true; };

const validate = () => {
    const next = {};
    if (!form.value.title) next.title = 'Title is required.';
    if (!form.value.due_date) next.due_date = 'Date is required.';
    errors.value = next;
    return !Object.keys(next).length;
};

const save = async () => {
    if (!validate()) return;
    await run('save', async () => {
        try {
            if (editing.value) await api.put(`/reminders/${editing.value.id}`, form.value);
            else await api.post('/reminders', form.value);
            toast.success('Reminder saved');
            modal.value = false;
            await load();
        } catch (error) {
            errors.value = error.response?.data?.errors ?? {};
            toast.error(error.response?.data?.message ?? 'Unable to save');
        }
    });
};

const ask = (action, row = null) => {
    const targetRows = row ? [row] : selectedRows.value;
    if (!targetRows.length) {
        toast.error('Select at least one row first');
        return;
    }
    pending.value = { action, rows: targetRows };
};

const confirmAction = async () => {
    await run('confirm', async () => {
        const { action, rows: targetRows } = pending.value;
        if (action === 'delete') await Promise.all(targetRows.map((row) => api.delete(`/reminders/${row.id}`)));
        if (action === 'complete') await Promise.all(targetRows.map((row) => api.put(`/reminders/${row.id}`, { is_paid: true })));
        if (action === 'pending') await Promise.all(targetRows.map((row) => api.put(`/reminders/${row.id}`, { is_paid: false })));
        toast.success(action === 'delete' ? 'Deleted' : 'Status updated');
        pending.value = null;
        await load();
    });
};

onMounted(() => { resetForm(); load(); });
</script>

<template>
    <AppLayout>
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
            <div><h1 class="text-2xl font-bold tracking-tight">Reminder</h1><p class="text-sm text-slate-500">Set date or time reminders and track completion.</p></div>
            <AppButton @click="openCreate">Add Reminder</AppButton>
        </div>

        <AppCard class="mb-6">
            <div class="grid gap-3 md:grid-cols-5">
                <AppInput v-model="filters.search" label="Search" />
                <AppInput v-model="filters.date_from" label="From" type="date" />
                <AppInput v-model="filters.date_to" label="To" type="date" />
                <AppSelect v-model="filters.is_paid" label="Status" :options="statusOptions" />
                <AppButton variant="secondary" class="self-end" :loading="busy === 'apply'" @click="applyFilters">Apply</AppButton>
            </div>
        </AppCard>

        <AppCard>
            <div v-if="loading" class="py-10 text-center text-slate-500">Loading...</div>
            <div v-if="!loading && selectedIds.length" class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800">
                <span class="text-sm font-medium">{{ selectedIds.length }} selected</span>
                <div class="flex flex-wrap gap-2"><AppButton variant="secondary" @click="ask('complete')">Mark Complete</AppButton><AppButton variant="secondary" @click="ask('pending')">Mark Pending</AppButton><AppButton variant="secondary" @click="ask('delete')">Delete</AppButton></div>
            </div>
            <AppTable v-if="!loading">
                <template #head><tr><th class="w-12 px-4 py-3"><input type="checkbox" :checked="allVisibleSelected" @change="toggleSelectAll"></th><th class="px-4 py-3">Reminder</th><th class="px-4 py-3">Due</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Actions</th></tr></template>
                <tr v-for="row in rows" :key="row.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                    <td class="px-4 py-3"><input v-model="selectedIds" type="checkbox" :value="row.id"></td>
                    <td class="px-4 py-3"><div class="font-medium">{{ row.title }}</div><div class="text-xs text-slate-500">Notify {{ row.notify_before_days ?? 0 }} day(s) before</div></td>
                    <td class="px-4 py-3">{{ date(row.due_date) }} <span v-if="row.due_time">at {{ row.due_time }}</span></td>
                    <td class="px-4 py-3"><label class="inline-flex items-center gap-2"><input type="checkbox" :checked="row.is_paid" @change="ask(row.is_paid ? 'pending' : 'complete', row)"><AppBadge>{{ row.is_paid ? 'Complete' : 'Pending' }}</AppBadge></label></td>
                    <td class="px-4 py-3"><div class="flex gap-3"><button class="text-indigo-600" @click="openEdit(row)">Edit</button><button class="text-red-500" @click="ask('delete', row)">Delete</button></div></td>
                </tr>
            </AppTable>
            <div class="mt-4 flex items-center justify-between text-sm text-slate-500"><span>Page {{ meta.current_page ?? 1 }} of {{ meta.last_page ?? 1 }}</span><div class="flex gap-2"><AppButton variant="secondary" :loading="busy === 'previous'" :disabled="(meta.current_page ?? 1) <= 1" @click="changePage(-1)">Previous</AppButton><AppButton variant="secondary" :loading="busy === 'next'" :disabled="(meta.current_page ?? 1) >= (meta.last_page ?? 1)" @click="changePage(1)">Next</AppButton></div></div>
        </AppCard>

        <AppModal :open="modal" :title="`${editing ? 'Edit' : 'Add'} Reminder`" @close="modal = false">
            <form class="grid gap-4" @submit.prevent="save">
                <AppInput v-model="form.title" label="Title" :error="errors.title?.[0] || errors.title" />
                <AppInput v-model="form.due_date" label="Date" type="date" :error="errors.due_date?.[0] || errors.due_date" />
                <AppInput v-model="form.due_time" label="Time" type="time" />
                <AppInput v-model="form.notify_before_days" label="Notify before days" type="number" />
                <AppInput v-model="form.amount" label="Amount" type="number" />
                <label class="flex items-center gap-2 text-sm font-medium"><input v-model="form.is_paid" type="checkbox"> Complete</label>
                <AppButton :loading="busy === 'save'">Save</AppButton>
            </form>
        </AppModal>
        <AppConfirmModal :open="Boolean(pending)" :title="pending?.action === 'delete' ? 'Confirm delete' : 'Confirm status change'" :action-label="pending?.action === 'delete' ? 'Delete' : 'Confirm'" :message="pending?.action === 'delete' ? `Delete ${pending?.rows?.length ?? 1} reminder(s)?` : `Update ${pending?.rows?.length ?? 1} reminder(s)?`" :loading="busy === 'confirm'" @close="pending = null" @confirm="confirmAction" />
    </AppLayout>
</template>
