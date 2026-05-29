<script setup>defineProps({ title: String, text: String });</script>
<template>
    <div class="grid place-items-center rounded-2xl border border-dashed border-slate-300 p-10 text-center dark:border-slate-700">
        <div class="mb-4 grid h-16 w-16 place-items-center rounded-2xl bg-indigo-50 text-2xl dark:bg-indigo-900/30">+</div>
        <h3 class="font-bold">{{ title }}</h3>
        <p class="mt-1 max-w-sm text-sm text-slate-500">{{ text }}</p>
    </div>
</template>
