<script setup>
import { computed, ref, watch } from 'vue';
import { CalendarRange, ChevronDown } from 'lucide-vue-next';
import AppButton from './AppButton.vue';
import AppInput from './AppInput.vue';

const props = defineProps({
    /** Server-echoed period block: from, to, preset, label, days. */
    period: { type: Object, default: () => ({}) },
    presets: { type: Array, default: () => [] },
    loading: Boolean,
    showGranularity: { type: Boolean, default: true },
    granularities: { type: Array, default: () => ['auto', 'daily', 'weekly', 'monthly', 'quarterly', 'yearly'] },
    granularity: { type: String, default: 'auto' },
    compact: { type: Boolean, default: false },
});

const emit = defineEmits(['preset', 'granularity', 'custom']);

const open = ref(false);
const from = ref('');
const to = ref('');

const presetLabels = {
    today: 'Today',
    yesterday: 'Yesterday',
    this_week: 'This week',
    last_week: 'Last week',
    this_month: 'This month',
    last_month: 'Last month',
    last_3_months: 'Last 3 months',
    last_6_months: 'Last 6 months',
    last_12_months: 'Last 12 months',
    this_quarter: 'This quarter',
    this_year: 'This year',
    last_year: 'Last year',
    all: 'All time',
    custom: 'Custom',
};

const activePreset = computed(() => props.period.preset ?? 'last_6_months');
const activeLabel = computed(() => {
    if (activePreset.value === 'custom') return props.period.label ?? 'Custom range';
    return presetLabels[activePreset.value] ?? 'Custom';
});

/** The pills shown inline; the rest live in the dropdown. */
const quickPresets = ['this_month', 'last_month', 'last_3_months', 'last_6_months', 'this_year', 'all'];
const allPresets = computed(() => props.presets.filter((preset) => preset !== 'auto'));

watch(() => props.period, (period) => {
    from.value = period?.from ?? '';
    to.value = period?.to ?? '';
}, { immediate: true, deep: true });

const choose = (preset) => {
    open.value = false;
    if (preset === 'custom') {
        open.value = true;
        return;
    }
    emit('preset', preset);
};

const applyCustom = () => {
    if (!from.value) return;
    emit('custom', { from: from.value, to: to.value || from.value });
    open.value = false;
};
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <div class="flex flex-wrap items-center gap-1 rounded-xl border border-slate-200 bg-white p-1 dark:border-slate-700 dark:bg-slate-800">
            <button
                v-for="preset in quickPresets"
                :key="preset"
                type="button"
                class="rounded-lg px-3 py-1.5 text-xs font-medium transition disabled:opacity-60"
                :class="activePreset === preset ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700'"
                :disabled="loading"
                @click="choose(preset)"
            >
                {{ presetLabels[preset] }}
            </button>
        </div>

        <div class="relative">
            <button
                type="button"
                class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                @click="open = !open"
            >
                <CalendarRange class="h-4 w-4" />
                <span>{{ activeLabel }}</span>
                <ChevronDown class="h-3.5 w-3.5 transition" :class="open ? 'rotate-180' : ''" />
            </button>

            <div v-if="open" class="absolute right-0 z-30 mt-2 w-72 rounded-2xl border border-slate-200 bg-white p-4 shadow-xl dark:border-slate-700 dark:bg-slate-800">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Preset ranges</p>
                <div class="mb-4 grid grid-cols-2 gap-1.5">
                    <button
                        v-for="preset in allPresets"
                        :key="preset"
                        type="button"
                        class="rounded-lg px-2.5 py-1.5 text-left text-xs font-medium transition"
                        :class="activePreset === preset ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700'"
                        @click="choose(preset)"
                    >
                        {{ presetLabels[preset] ?? preset }}
                    </button>
                </div>

                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Custom range</p>
                <div class="grid gap-2">
                    <AppInput v-model="from" label="From" type="date" />
                    <AppInput v-model="to" label="To" type="date" />
                    <AppButton variant="secondary" :disabled="!from" @click="applyCustom">Apply range</AppButton>
                </div>
            </div>
        </div>

        <div v-if="showGranularity" class="flex flex-wrap items-center gap-1 rounded-xl border border-slate-200 bg-white p-1 dark:border-slate-700 dark:bg-slate-800">
            <button
                v-for="option in granularities"
                :key="option"
                type="button"
                class="rounded-lg px-2.5 py-1.5 text-xs font-medium capitalize transition disabled:opacity-60"
                :class="granularity === option ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700'"
                :disabled="loading"
                @click="emit('granularity', option)"
            >
                {{ option }}
            </button>
        </div>
    </div>
</template>
