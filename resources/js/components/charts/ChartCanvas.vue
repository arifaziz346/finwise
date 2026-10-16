<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Chart, registerables } from 'chart.js';

// Register the full chart.js surface once, with app-wide typography defaults.
Chart.register(...registerables);
Chart.defaults.font.family = 'Inter, ui-sans-serif, system-ui, sans-serif';
Chart.defaults.color = '#64748B';

const props = defineProps({
    type: { type: String, default: 'bar' },
    data: { type: Object, required: true },
    options: { type: Object, default: () => ({}) },
    height: { type: String, default: '320px' },
    /** Rendered instead of the canvas when there is nothing to plot. */
    empty: Boolean,
    emptyText: { type: String, default: 'No data for this period.' },
});

const canvas = ref(null);
let chart = null;

const render = () => {
    if (!canvas.value || props.empty) return;

    if (chart) {
        // Mutating in place lets chart.js animate between periods instead of flashing.
        chart.data = props.data;
        chart.options = props.options;
        chart.update();
        return;
    }

    chart = new Chart(canvas.value, {
        type: props.type,
        data: props.data,
        options: props.options,
    });
};

const destroy = () => {
    chart?.destroy();
    chart = null;
};

onMounted(render);
watch(() => props.data, render, { deep: true });
watch(() => props.options, render, { deep: true });
watch(() => props.empty, (isEmpty) => (isEmpty ? destroy() : render()));
onBeforeUnmount(destroy);
</script>

<template>
    <div class="relative w-full" :style="{ height }">
        <div v-if="empty" class="grid h-full place-items-center rounded-xl border border-dashed border-slate-200 px-6 text-center text-sm text-slate-500 dark:border-slate-700">
            {{ emptyText }}
        </div>
        <canvas v-show="!empty" ref="canvas" />
    </div>
</template>
