<template>
    <main class="grid min-h-screen bg-slate-50 dark:bg-slate-900 lg:grid-cols-[1fr_520px]">
        <section class="hidden bg-indigo-600 p-10 text-white lg:flex lg:flex-col lg:justify-between">
            <div class="text-2xl font-bold tracking-tight">FinWise</div>
            <div>
                <h1 class="max-w-xl text-5xl font-bold tracking-tight">Personal finance, clear enough to act on.</h1>
                <div class="mt-6 grid gap-3 text-indigo-50">
                    <p><strong>Dashboard:</strong> monthly income, expense, balance, budget status, and charts.</p>
                    <p><strong>Income:</strong> income categories, entries, filters, and category summary.</p>
                    <p><strong>Expense:</strong> expense categories, entries, filters, reports, CSV, and PDF.</p>
                    <p><strong>Budget:</strong> category limits, spent amount, remaining balance, and progress.</p>
                    <p><strong>Reminder:</strong> date/time reminders with pending or complete status.</p>
                    <p><strong>Profile:</strong> current user details and password update.</p>
                </div>
            </div>
        </section>
        <section class="flex items-center justify-center p-4">
            <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <slot />
            </div>
        </section>
    </main>
</template>
