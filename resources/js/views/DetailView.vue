<script setup>
import AppLayout from '../components/layout/AppLayout.vue';
import AppCard from '../components/ui/AppCard.vue';
defineProps({ type: String });
</script>
<template><AppLayout><AppCard><h1 class="text-2xl font-bold tracking-tight">{{ type }} Detail</h1><p class="mt-2 text-slate-500">Balance history, transactions, and detailed analytics live here.</p></AppCard></AppLayout></template>
