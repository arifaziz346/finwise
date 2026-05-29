<template>
    <div class="overflow-auto rounded-2xl border border-slate-200 dark:border-slate-700">
        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
            <thead class="sticky top-0 bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-900">
                <slot name="head" />
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                <slot />
            </tbody>
        </table>
    </div>
</template>
