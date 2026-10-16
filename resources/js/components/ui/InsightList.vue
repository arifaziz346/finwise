<script setup>
import { AlertTriangle, CheckCircle2, Info, Lightbulb, OctagonAlert } from 'lucide-vue-next';

defineProps({
    insights: { type: Array, default: () => [] },
    title: { type: String, default: 'What the numbers say' },
    limit: { type: Number, default: 8 },
});

const styles = {
    ok: { icon: CheckCircle2, ring: 'border-emerald-200 bg-emerald-50/60 dark:border-emerald-500/30 dark:bg-emerald-500/10', iconTone: 'text-emerald-600 dark:text-emerald-400' },
    info: { icon: Info, ring: 'border-sky-200 bg-sky-50/60 dark:border-sky-500/30 dark:bg-sky-500/10', iconTone: 'text-sky-600 dark:text-sky-400' },
    warn: { icon: AlertTriangle, ring: 'border-amber-200 bg-amber-50/60 dark:border-amber-500/30 dark:bg-amber-500/10', iconTone: 'text-amber-600 dark:text-amber-400' },
    danger: { icon: OctagonAlert, ring: 'border-red-200 bg-red-50/60 dark:border-red-500/30 dark:bg-red-500/10', iconTone: 'text-red-600 dark:text-red-400' },
};

const styleFor = (severity) => styles[severity] ?? styles.info;
</script>

<template>
    <div>
        <div v-if="title" class="mb-3 flex items-center gap-2">
            <Lightbulb class="h-4 w-4 text-amber-500" />
            <h2 class="font-bold">{{ title }}</h2>
        </div>

        <div v-if="!insights.length" class="rounded-xl border border-dashed border-slate-200 p-5 text-center text-sm text-slate-500 dark:border-slate-700">
            No observations for this period yet — record some income and expenses and insights will appear here.
        </div>

        <ul v-else class="grid gap-2.5">
            <li v-for="insight in insights.slice(0, limit)" :key="insight.key" class="flex gap-3 rounded-xl border p-3" :class="styleFor(insight.severity).ring">
                <component :is="styleFor(insight.severity).icon" class="mt-0.5 h-4 w-4 shrink-0" :class="styleFor(insight.severity).iconTone" />
                <div class="min-w-0">
                    <p class="text-sm font-semibold leading-tight">{{ insight.title }}</p>
                    <p class="mt-1 text-xs leading-relaxed text-slate-600 dark:text-slate-300">{{ insight.message }}</p>
                </div>
            </li>
        </ul>
    </div>
</template>
