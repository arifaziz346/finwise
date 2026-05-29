<script setup>
defineProps({ variant: { type: String, default: 'primary' }, loading: Boolean, disabled: Boolean });
</script>
<template>
    <button
        :disabled="loading || disabled"
        class="relative overflow-hidden"
        :class="variant === 'primary' ? 'fw-btn-primary' : 'rounded-xl border border-slate-200 px-4 py-2.5 font-medium transition hover:bg-slate-50 disabled:cursor-wait disabled:opacity-80 dark:border-slate-700 dark:hover:bg-slate-700'"
    >
        <span :class="{ 'opacity-60': loading }"><slot /></span>
        <span v-if="loading" class="absolute inset-x-0 bottom-0 h-1 overflow-hidden bg-white/30 dark:bg-slate-950/20">
            <span class="fw-button-progress block h-full w-1/2 bg-white dark:bg-indigo-400" />
        </span>
    </button>
</template>
