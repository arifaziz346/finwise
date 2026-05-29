<script setup>
import { RouterLink } from 'vue-router';
import { Bell, LayoutDashboard, Target, TrendingDown, TrendingUp } from 'lucide-vue-next';
const items = [['/dashboard', LayoutDashboard], ['/expenses', TrendingDown], ['/income', TrendingUp], ['/budgets', Target], ['/reminders', Bell]];
</script>
<template>
    <nav class="fixed inset-x-0 bottom-0 z-40 grid h-16 grid-cols-5 border-t border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900 lg:hidden">
        <RouterLink v-for="[to, Icon] in items" :key="to" :to="to" class="grid place-items-center text-slate-500" active-class="text-indigo-600">
            <Icon class="h-6 w-6" />
        </RouterLink>
    </nav>
</template>
