<script setup>
import AppModal from './AppModal.vue';
import AppButton from './AppButton.vue';
defineProps({ open: Boolean, title: { type: String, default: 'Confirm action' }, actionLabel: { type: String, default: 'Confirm' }, message: { type: String, default: 'Please confirm before continuing.' }, loading: Boolean });
defineEmits(['close', 'confirm']);
</script>
<template>
    <AppModal :open="open" :title="title" @close="$emit('close')">
        <p class="text-sm text-slate-500">{{ message }}</p>
        <div class="mt-6 flex justify-end gap-3">
            <button class="rounded-xl px-4 py-2.5 hover:bg-slate-100 dark:hover:bg-slate-700" @click="$emit('close')">Cancel</button>
            <AppButton :loading="loading" @click="$emit('confirm')">{{ actionLabel }}</AppButton>
        </div>
    </AppModal>
</template>
