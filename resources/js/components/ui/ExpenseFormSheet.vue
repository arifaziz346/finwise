<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import {
    Banknote, CalendarDays, Check, ChevronDown, CreditCard, Globe, Landmark,
    MapPin, Search, StickyNote, Wallet, X,
} from 'lucide-vue-next';
import AppButton from './AppButton.vue';

/**
 * Mobile-first expense entry.
 *
 * Opens as a bottom sheet on phones and a centred card on desktop. The three
 * required fields (amount, category, date) are always visible; everything else
 * lives behind "More details" so the common case stays a two-tap job.
 */
const props = defineProps({
    open: Boolean,
    /** { suggestions, categories, defaults, payment_methods } from the API. */
    options: { type: Object, default: () => ({}) },
    editing: { type: Object, default: null },
    loading: Boolean,
    /** Server-side validation errors keyed by field. */
    errors: { type: Object, default: () => ({}) },
    currency: { type: String, default: 'PKR' },
});

const emit = defineEmits(['close', 'saved']);

const amount = ref('');
const categoryId = ref('');
const date = ref('');
const description = ref('');
const paymentMethod = ref('cash');
const locationValue = ref('');
const showAllCategories = ref(false);
const showMore = ref(false);
const categorySearch = ref('');
const touched = ref(false);
const amountInput = ref(null);

const suggestions = computed(() => props.options.suggestions ?? []);
const categories = computed(() => props.options.categories ?? []);
const paymentMethods = computed(() => (props.options.payment_methods ?? ['cash', 'card', 'online', 'cheque']));

/** Six quick chips, always including whatever is currently selected. */
const quickCategories = computed(() => {
    const chips = [];
    const seen = new Set();

    const push = (category) => {
        if (!category || seen.has(String(category.id))) return;
        seen.add(String(category.id));
        chips.push(category);
    };

    suggestions.value.forEach((category) => chips.length < 6 && push(category));
    categories.value.forEach((category) => chips.length < 6 && push(category));

    // The current pick must stay visible even when it sits outside the top six.
    push(categories.value.find((category) => String(category.id) === String(categoryId.value)));

    return chips.slice(0, 7);
});

const pickerCategories = computed(() => {
    const term = categorySearch.value.trim().toLowerCase();
    const rows = term ? categories.value.filter((category) => category.name.toLowerCase().includes(term)) : categories.value;

    return [...rows].sort((a, b) => a.name.localeCompare(b.name));
});

const selectedCategory = computed(() => categories.value.find((category) => String(category.id) === String(categoryId.value)) ?? null);

const isToday = computed(() => date.value === today());
const isYesterday = computed(() => date.value === shiftDays(today(), -1));
const inFuture = computed(() => Boolean(date.value) && date.value > today());

const amountError = computed(() => props.errors.amount?.[0] ?? props.errors.amount ?? (touched.value && !Number(amount.value) ? 'Enter an amount greater than zero' : ''));
const categoryError = computed(() => props.errors.category_id?.[0] ?? props.errors.category_id ?? (touched.value && !categoryId.value ? 'Pick a category' : ''));
const dateError = computed(() => props.errors.expense_date?.[0] ?? props.errors.expense_date ?? '');

const methodMeta = {
    cash: { label: 'Cash', icon: Banknote },
    card: { label: 'Card', icon: CreditCard },
    online: { label: 'Online', icon: Globe },
    cheque: { label: 'Cheque', icon: Landmark },
};

const methodLabel = (method) => methodMeta[method]?.label ?? method;

const toIso = (value) => {
    const day = new Date(value);
    return `${day.getFullYear()}-${String(day.getMonth() + 1).padStart(2, '0')}-${String(day.getDate()).padStart(2, '0')}`;
};

const today = () => toIso(new Date());

const shiftDays = (iso, days) => {
    const day = new Date(`${iso}T00:00:00`);
    day.setDate(day.getDate() + days);
    return toIso(day);
};

const fill = () => {
    const defaults = props.options.defaults ?? {};

    if (props.editing) {
        amount.value = props.editing.amount ?? '';
        categoryId.value = String(props.editing.category_id ?? props.editing.category?.id ?? '');
        date.value = (props.editing.expense_date ?? '').toString().slice(0, 10);
        description.value = props.editing.description ?? '';
        paymentMethod.value = props.editing.payment_method ?? 'cash';
        locationValue.value = props.editing.location ?? '';
        return;
    }

    amount.value = '';
    description.value = '';
    locationValue.value = defaults.location ?? '';
    paymentMethod.value = defaults.payment_method ?? 'cash';
    date.value = defaults.expense_date ?? today();
    categoryId.value = String(defaults.category_id ?? '');
};

const chosenCategory = () => categories.value.find((category) => String(category.id) === String(categoryId.value));

const submit = () => {
    touched.value = true;

    if (!Number(amount.value) || !categoryId.value || !date.value) {
        amountInput.value?.focus?.();
        return;
    }

    const category = chosenCategory();

    emit('saved', {
        amount: Number(amount.value),
        category_id: Number(categoryId.value),
        expense_date: date.value,
        description: description.value || null,
        payment_method: paymentMethod.value || 'cash',
        location: locationValue.value || null,
        // Lets the list show a friendly name immediately without a refetch.
        category_name: category?.name ?? null,
    });
};

const bump = (step) => {
    amount.value = String(Math.max(0, Math.round((Number(amount.value || 0) + step) * 100) / 100));
};

watch(() => props.open, async (open) => {
    if (!open) return;

    touched.value = false;
    showMore.value = false;
    showAllCategories.value = false;
    categorySearch.value = '';
    fill();

    await nextTick();
    // Only steal focus on pointer devices, so phones do not pop the keypad over the sheet.
    if (window.matchMedia?.('(pointer: fine)').matches) amountInput.value?.focus?.();
});

watch([suggestions, categories], () => {
    if (!categoryId.value) categoryId.value = String(props.options.defaults?.category_id ?? '');
    const stillThere = categories.value.some((category) => String(category.id) === String(categoryId.value));
    if (!stillThere) categoryId.value = String(suggestions.value[0]?.id ?? categories.value[0]?.id ?? '');
}, { deep: true });

watch(() => props.options.defaults, (defaults) => {
    if (!props.open || props.editing || !defaults) return;
    if (!locationValue.value && defaults.location) locationValue.value = defaults.location;
});
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-50 flex items-end justify-center bg-slate-950/50 sm:items-center sm:p-4" @click.self="$emit('close')">
        <section class="flex max-h-[92vh] w-full flex-col rounded-t-3xl bg-white shadow-2xl dark:bg-slate-800 sm:max-w-lg sm:rounded-3xl">
            <!-- Handle + header -->
            <header class="flex items-start justify-between gap-3 px-5 pt-4">
                <div>
                    <div class="mx-auto mb-3 h-1.5 w-12 rounded-full bg-slate-200 dark:bg-slate-600 sm:hidden" />
                    <h2 class="text-lg font-bold tracking-tight">{{ editing ? 'Edit expense' : 'Add expense' }}</h2>
                    <p class="text-xs text-slate-500">{{ editing ? 'Update the details below.' : 'Pick a category, type the amount, done.' }}</p>
                </div>
                <button type="button" class="-mr-1 rounded-xl p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700" aria-label="Close" @click="$emit('close')">
                    <X class="h-5 w-5" />
                </button>
            </header>

            <div class="min-h-0 flex-1 overflow-y-auto px-5 pb-4 pt-4">
                <!-- Amount -->
                <div
                    class="rounded-2xl border-2 bg-slate-50 p-4 transition dark:bg-slate-900/40"
                    :class="amountError ? 'border-red-300 dark:border-red-500/50' : 'border-slate-200 focus-within:border-indigo-500 dark:border-slate-700'"
                >
                    <label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500" for="expense-amount">Amount</label>
                    <div class="mt-1 flex items-baseline gap-2">
                        <span class="text-sm font-semibold text-slate-400">{{ currency }}</span>
                        <input
                            id="expense-amount"
                            ref="amountInput"
                            v-model="amount"
                            type="text"
                            inputmode="decimal"
                            autocomplete="off"
                            placeholder="0"
                            class="w-full min-w-0 bg-transparent font-mono text-3xl font-semibold tracking-tight outline-none placeholder:text-slate-300 dark:placeholder:text-slate-600"
                            @input="amount = String($event.target.value).replace(/[^0-9.]/g, '')"
                        >
                    </div>
                    <p v-if="amountError" class="mt-1 text-xs font-medium text-red-500">{{ amountError }}</p>
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                    <button v-for="step in [100, 500, 1000, 5000]" :key="step" type="button" class="min-h-[40px] rounded-full border border-slate-200 px-3.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-100 active:scale-95 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-700" @click="bump(step)">
                        +{{ step }}
                    </button>
                    <button type="button" class="min-h-[40px] rounded-full border border-transparent px-3.5 text-xs font-semibold text-slate-400 transition hover:text-slate-600 active:scale-95 dark:hover:text-slate-200" @click="amount = ''">Clear</button>
                </div>

                <!-- Category suggestions -->
                <div class="mt-5">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Category</span>
                        <span v-if="suggestions.length" class="text-[11px] text-slate-400">Suggested from your history</span>
                    </div>

                    <p v-if="!categories.length" class="rounded-xl border border-dashed border-slate-200 p-4 text-center text-sm text-slate-500 dark:border-slate-700">
                        No categories yet. Add one from the Categories tab first.
                    </p>

                    <div v-else class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        <button
                            v-for="category in quickCategories"
                            :key="category.id"
                            type="button"
                            class="flex min-h-[48px] items-center gap-2 rounded-xl border-2 px-3 py-2.5 text-left text-sm font-medium transition active:scale-[0.98]"
                            :class="String(categoryId) === String(category.id) ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300' : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-700/50'"
                            @click="categoryId = String(category.id)"
                        >
                            <span class="h-3 w-3 shrink-0 rounded-full" :style="{ backgroundColor: category.color }" />
                            <span class="min-w-0 flex-1 truncate">{{ category.name }}</span>
                            <Check v-if="String(categoryId) === String(category.id)" class="h-4 w-4 shrink-0" />
                        </button>
                    </div>

                    <p v-if="selectedCategory && !suggestions.length" class="mt-2 text-[11px] text-slate-400">Your most recent category is pre-selected.</p>

                    <button
                        v-if="categories.length > quickCategories.length || showAllCategories"
                        type="button"
                        class="mt-2 flex w-full items-center justify-center gap-1.5 rounded-xl border border-dashed border-slate-300 py-2.5 text-xs font-semibold text-slate-500 transition hover:border-slate-400 hover:text-slate-700 dark:border-slate-600 dark:hover:text-slate-200"
                        @click="showAllCategories = !showAllCategories"
                    >
                        {{ showAllCategories ? 'Hide full list' : `All ${categories.length} categories` }}
                        <ChevronDown class="h-3.5 w-3.5 transition" :class="showAllCategories ? 'rotate-180' : ''" />
                    </button>

                    <p v-if="categoryError" class="mt-2 text-xs font-medium text-red-500">{{ categoryError }}</p>

                    <!-- Full picker -->
                    <div v-if="showAllCategories" class="mt-3 rounded-2xl border border-slate-200 p-3 dark:border-slate-700">
                        <div class="relative mb-2">
                            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input v-model="categorySearch" type="search" placeholder="Search categories" class="fw-input w-full pl-9 text-sm">
                        </div>
                        <div class="grid max-h-56 grid-cols-2 gap-1.5 overflow-y-auto sm:grid-cols-3">
                            <button
                                v-for="category in pickerCategories"
                                :key="category.id"
                                type="button"
                                class="flex items-center gap-2 rounded-lg px-2.5 py-2 text-left text-xs font-medium transition"
                                :class="String(categoryId) === String(category.id) ? 'bg-indigo-600 text-white' : 'hover:bg-slate-100 dark:hover:bg-slate-700'"
                                @click="categoryId = String(category.id); showAllCategories = false; categorySearch = ''"
                            >
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ backgroundColor: category.color }" />
                                <span class="min-w-0 truncate">{{ category.name }}</span>
                            </button>
                        </div>
                        <p v-if="!pickerCategories.length" class="py-4 text-center text-xs text-slate-500">Nothing matches “{{ categorySearch }}”.</p>
                    </div>
                </div>

                <!-- Date -->
                <div class="mt-5">
                    <span class="mb-2 block text-[11px] font-semibold uppercase tracking-wide text-slate-500">Date</span>
                    <div class="flex gap-2">
                        <button type="button" class="min-h-[40px] rounded-xl border-2 px-3.5 text-xs font-semibold transition" :class="isToday ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300' : 'border-slate-200 text-slate-600 dark:border-slate-700 dark:text-slate-300'" @click="date = today()">Today</button>
                        <button type="button" class="min-h-[40px] rounded-xl border-2 px-3.5 text-xs font-semibold transition" :class="isYesterday ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300' : 'border-slate-200 text-slate-600 dark:border-slate-700 dark:text-slate-300'" @click="date = shiftDays(today(), -1)">Yesterday</button>
                        <div class="relative flex-1">
                            <CalendarDays class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input v-model="date" type="date" class="fw-input w-full pl-9 text-sm">
                        </div>
                    </div>
                    <p v-if="dateError" class="mt-1 text-xs font-medium text-red-500">{{ dateError }}</p>
                    <p v-else-if="inFuture" class="mt-1 text-xs text-amber-600 dark:text-amber-400">This date is in the future.</p>
                </div>

                <!-- Optional extras -->
                <button type="button" class="mt-5 flex w-full items-center justify-between rounded-xl bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-600 transition dark:bg-slate-900/40 dark:text-slate-300" @click="showMore = !showMore">
                    More details <span class="text-xs font-normal text-slate-400">{{ showMore ? 'Hide' : 'Note, payment method, place' }}</span>
                    <ChevronDown class="h-4 w-4 transition" :class="showMore ? 'rotate-180' : ''" />
                </button>

                <div v-if="showMore" class="mt-3 grid gap-3">
                    <label class="grid gap-1.5 text-sm font-medium text-slate-700 dark:text-slate-200">
                        <span class="flex items-center gap-1.5 text-xs uppercase tracking-wide text-slate-500"><StickyNote class="h-3.5 w-3.5" /> Note</span>
                        <input v-model="description" type="text" placeholder="What was it for?" class="fw-input text-sm">
                    </label>

                    <div>
                        <span class="mb-1.5 flex items-center gap-1.5 text-xs uppercase tracking-wide text-slate-500"><Wallet class="h-3.5 w-3.5" /> Payment method</span>
                        <div class="grid grid-cols-4 gap-1.5">
                            <button
                                v-for="method in paymentMethods"
                                :key="method"
                                type="button"
                                class="flex flex-col items-center gap-1 rounded-xl border-2 py-2 text-[11px] font-semibold transition"
                                :class="paymentMethod === method ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300' : 'border-slate-200 text-slate-600 dark:border-slate-700 dark:text-slate-300'"
                                @click="paymentMethod = method"
                            >
                                <component :is="methodMeta[method]?.icon ?? Banknote" class="h-4 w-4" />
                                {{ methodLabel(method) }}
                            </button>
                        </div>
                    </div>

                    <label class="grid gap-1.5 text-sm font-medium text-slate-700 dark:text-slate-200">
                        <span class="flex items-center gap-1.5 text-xs uppercase tracking-wide text-slate-500"><MapPin class="h-3.5 w-3.5" /> Place <span class="font-normal normal-case text-slate-400">(optional)</span></span>
                        <input v-model="locationValue" type="text" placeholder="e.g. Peshawar" class="fw-input text-sm">
                    </label>
                </div>
            </div>

            <!-- Actions -->
            <footer class="flex gap-3 border-t border-slate-100 px-5 py-4 dark:border-slate-700">
                <AppButton variant="secondary" class="flex-1" @click="$emit('close')">Cancel</AppButton>
                <AppButton class="flex-[2]" :loading="loading" @click="submit">{{ editing ? 'Save changes' : 'Add expense' }}</AppButton>
            </footer>
        </section>
    </div>
</template>
