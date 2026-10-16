<script setup>
import { computed } from 'vue';
import { compactMoney, money } from '../../utils/currency';

const props = defineProps({
    rows: { type: Array, default: () => [] },
    /** "total" (amount) or "share" (percentage) as the bar basis. */
    metric: { type: String, default: 'share' },
    max: { type: Number, default: null },
    currency: { type: String, default: 'PKR' },
    emptyText: { type: String, default: 'Nothing recorded in this period.' },
    clickable: { type: Boolean, default: false },
});

const emit = defineEmits(['select']);

const peak = computed(() => props.max ?? Math.max(1, ...props.rows.map((row) => Number(row[props.metric] ?? 0))));

const width = (row) => {
    const value = Number(row[props.metric] ?? 0);
    return `${Math.max((value / peak.value) * 100, 1.5)}%`;
};

const label = (row) => (props.metric === 'share' ? `${Number(row.share ?? 0).toFixed(1)}%` : compactMoney(row.total, props.currency));
</script>

<template>
    <div>
        <p v-if="!rows.length" class="py-6 text-center text-sm text-slate-500">{{ emptyText }}</p>

        <ul v-else class="grid gap-3">
            <li
                v-for="row in rows"
                :key="row.category ?? row.category_id"
                class="group"
                :class="clickable ? 'cursor-pointer' : ''"
                @click="clickable && emit('select', row)"
            >
                <div class="mb-1.5 flex items-baseline justify-between gap-3">
                    <span class="truncate text-sm font-medium" :title="row.category">{{ row.category }}</span>
                    <span class="shrink-0 font-mono text-xs font-semibold text-slate-600 dark:text-slate-300">{{ money(row.total, currency) }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-700">
                        <div class="h-full rounded-full transition-all duration-500" :style="{ width: width(row), backgroundColor: row.color || '#6366F1' }" />
                    </div>
                    <span class="w-12 shrink-0 text-right text-[11px] tabular-nums text-slate-500">{{ label(row) }}</span>
                </div>
                <p v-if="row.entries" class="mt-1 text-[11px] text-slate-400">{{ row.entries }} entr{{ row.entries === 1 ? 'y' : 'ies' }}</p>
            </li>
        </ul>
    </div>
</template>
