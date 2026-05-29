<script setup>
defineProps({ open: Boolean, title: String });
defineEmits(['close']);
</script>
<template>
    <div v-if="open" class="fixed inset-0 z-50 grid place-items-end bg-slate-950/50 p-4 sm:place-items-center" @click.self="$emit('close')">
        <section class="w-full max-w-xl rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-800">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-bold tracking-tight">{{ title }}</h2>
                <button class="rounded-xl p-2 hover:bg-slate-100 dark:hover:bg-slate-700" @click="$emit('close')">x</button>
            </div>
            <slot />
        </section>
    </div>
</template>
