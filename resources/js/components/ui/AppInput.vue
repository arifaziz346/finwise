<script setup>
defineProps({ modelValue: [String, Number], label: String, type: { type: String, default: 'text' }, error: String });
defineEmits(['update:modelValue']);
</script>
<template>
    <label class="grid gap-1.5 text-sm font-medium text-slate-700 dark:text-slate-200">
        <span v-if="label">{{ label }}</span>
        <input class="fw-input" :type="type" :value="modelValue" @input="$emit('update:modelValue', $event.target.value)">
        <span v-if="error" class="text-xs text-red-500">{{ error }}</span>
    </label>
</template>
