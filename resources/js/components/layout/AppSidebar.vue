<script setup>
import { RouterLink } from 'vue-router';
import { LayoutDashboard, TrendingUp, TrendingDown, Target, X, Bell, UserRound } from 'lucide-vue-next';
import { useUiStore } from '../../stores/ui';

const ui = useUiStore();

const items = [
    ['Dashboard', '/dashboard', LayoutDashboard],
    ['Income', '/income', TrendingUp],
    ['Expenses', '/expenses', TrendingDown],
    ['Budgets', '/budgets', Target],
    ['Reminder', '/reminders', Bell],
    ['Profile', '/settings', UserRound],
];
</script>
<template>
    <div v-if="ui.sidebarOpen" class="fixed inset-0 z-40 bg-slate-950/40 lg:hidden" @click="ui.closeSidebar" />
    <aside
        class="fixed inset-y-0 left-0 z-50 w-[260px] shrink-0 overflow-y-auto border-r border-slate-200 bg-white p-4 transition-transform duration-200 dark:border-slate-800 dark:bg-slate-900 lg:static lg:z-auto lg:block lg:h-[calc(100vh-64px)] lg:translate-x-0"
        :class="ui.sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
    >
        <div class="mb-4 flex items-center justify-between lg:hidden">
            <span class="font-bold tracking-tight">Menu</span>
            <button class="rounded-lg p-2 hover:bg-slate-100 dark:hover:bg-slate-800" @click="ui.closeSidebar">
                <X class="h-5 w-5" />
            </button>
        </div>
        <nav class="space-y-1">
            <RouterLink v-for="[label, to, Icon] in items" :key="to" :to="to" class="flex min-h-11 items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800" active-class="!bg-indigo-50 !text-indigo-600 dark:!bg-indigo-900/30" @click="ui.closeSidebar">
                <Icon class="h-5 w-5" /> <span>{{ label }}</span>
            </RouterLink>
        </nav>
    </aside>
</template>
