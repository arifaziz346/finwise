<script setup>
import { computed } from 'vue';
import { ArrowDownRight, ArrowUpRight, Minus } from 'lucide-vue-next';

const props = defineProps({
    label: String,
    value: [String, Number],
    raw: { type: Number, default: null },
    previous: { type: Number, default: null },
    /** Set true when a rise is a good thing (income) vs bad (expenses). */
    riseIsGood: { type: Boolean, default: true },
    tone: { type: String, default: 'indigo' },
    hint: String,
    footnote: String,
    progress: { type: Number, default: null },
    progressLabel: String,
});

const tones = {
    emerald: { bg: 'bg-emerald-50 dark:bg-emerald-500/10', text: 'text-emerald-600 dark:text-emerald-400', bar: 'bg-emerald-500' },
    red: { bg: 'bg-red-50 dark:bg-red-500/10', text: 'text-red-500 dark:text-red-400', bar: 'bg-red-500' },
    indigo: { bg: 'bg-indigo-50 dark:bg-indigo-500/10', text: 'text-indigo-600 dark:text-indigo-400', bar: 'bg-indigo-500' },
    sky: { bg: 'bg-sky-50 dark:bg-sky-500/10', text: 'text-sky-600 dark:text-sky-400', bar: 'bg-sky-500' },
    amber: { bg: 'bg-amber-50 dark:bg-amber-500/10', text: 'text-amber-600 dark:text-amber-400', bar: 'bg-amber-500' },
    slate: { bg: 'bg-slate-100 dark:bg-slate-700/40', text: 'text-slate-600 dark:text-slate-300', bar: 'bg-slate-400' },
};

const tone = computed(() => tones[props.tone] ?? tones.indigo);

const delta = computed(() => {
    if (props.raw === null || props.previous === null || props.previous === 0) return null;
    return Math.round(((props.raw - props.previous) / Math.abs(props.previous)) * 1000) / 10;
});

const deltaTone = computed(() => {
    if (delta.value === null) return 'text-slate-400';
    if (Math.abs(delta.value) < 0.5) return 'text-slate-400';
    const good = delta.value > 0 ? props.riseIsGood : !props.riseIsGood;
    return good ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500 dark:text-red-400';
});

const progressWidth = computed(() => Math.min(Math.max(props.progress ?? 0, 0), 100));
</script>

<template>
    <div class="fw-card flex flex-col gap-3 !p-5">
        <div class="flex items-start justify-between gap-3">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ label }}</p>
            <span v-if="$slots.icon" class="grid h-9 w-9 shrink-0 place-items-center rounded-xl" :class="[tone.bg, tone.text]">
                <slot name="icon" />
            </span>
        </div>

        <div>
            <p class="font-mono text-2xl font-semibold leading-tight tracking-tight">{{ value }}</p>
            <p v-if="hint" class="mt-1 text-xs text-slate-500">{{ hint }}</p>
        </div>

        <div v-if="progress !== null" class="mt-auto">
            <div class="mb-1 flex items-center justify-between text-[11px] text-slate-500">
                <span>{{ progressLabel }}</span>
                <span class="font-medium">{{ progressWidth.toFixed(0) }}%</span>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-700">
                <div class="h-full rounded-full transition-all duration-500" :class="progressWidth >= 100 ? 'bg-red-500' : progressWidth >= 80 ? 'bg-amber-500' : tone.bar" :style="{ width: `${progressWidth}%` }" />
            </div>
        </div>

        <div class="mt-auto flex items-center gap-2 text-xs">
            <template v-if="delta !== null">
                <span class="inline-flex items-center gap-0.5 font-medium" :class="deltaTone">
                    <ArrowUpRight v-if="delta > 0.5" class="h-3.5 w-3.5" />
                    <ArrowDownRight v-else-if="delta < -0.5" class="h-3.5 w-3.5" />
                    <Minus v-else class="h-3.5 w-3.5" />
                    {{ Math.abs(delta).toFixed(1) }}%
                </span>
                <span class="text-slate-400">vs previous period</span>
            </template>
            <span v-else-if="footnote" class="text-slate-400">{{ footnote }}</span>
        </div>
    </div>
</template>
