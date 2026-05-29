<script setup>
import AppNavbar from './AppNavbar.vue';
import AppSidebar from './AppSidebar.vue';
import MobileBottomNav from './MobileBottomNav.vue';
</script>
<template>
    <div class="min-h-screen bg-slate-50 dark:bg-slate-900">
        <AppNavbar />
        <div class="flex">
            <AppSidebar />
            <main class="min-h-[calc(100vh-64px)] flex-1 overflow-y-auto p-4 pb-24 lg:p-6">
                <slot />
            </main>
        </div>
        <MobileBottomNav />
    </div>
</template>
