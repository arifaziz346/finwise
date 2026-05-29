<script setup>
import { useRouter } from 'vue-router';
import { LogOut, Menu, Moon, Sun, UserRound } from 'lucide-vue-next';
import { useAuthStore } from '../../stores/auth';
import { useUiStore } from '../../stores/ui';

const auth = useAuthStore();
const ui = useUiStore();
const router = useRouter();
const logout = async () => { await auth.logout(); router.push('/login'); };
</script>
<template>
    <header class="sticky top-0 z-30 flex h-16 items-center gap-4 border-b border-slate-200 bg-white px-4 dark:border-slate-800 dark:bg-slate-900 lg:px-6">
        <button class="rounded-xl p-2 lg:hidden" @click="ui.toggleSidebar"><Menu class="h-6 w-6" /></button>
        <RouterLink to="/dashboard" class="flex items-center gap-2 font-bold tracking-tight">
            <span class="grid h-9 w-9 place-items-center rounded-xl bg-indigo-600 text-white">F</span>
            <span>FinWise</span>
        </RouterLink>
        <div class="flex-1" />
        <div class="hidden text-right leading-tight sm:block">
            <p class="text-sm font-semibold">{{ auth.user?.name ?? 'User' }}</p>
            <p class="text-xs text-slate-500">{{ auth.user?.email }}</p>
        </div>
        <RouterLink to="/settings" class="rounded-xl p-2 hover:bg-slate-100 dark:hover:bg-slate-800" title="Profile"><UserRound class="h-5 w-5" /></RouterLink>
        <button class="rounded-xl p-2 hover:bg-slate-100 dark:hover:bg-slate-800" @click="ui.toggleDark"><Sun v-if="ui.dark" class="h-5 w-5" /><Moon v-else class="h-5 w-5" /></button>
        <button class="rounded-xl p-2 hover:bg-slate-100 dark:hover:bg-slate-800" title="Logout" @click="logout"><LogOut class="h-5 w-5" /></button>
    </header>
</template>
