<script setup>
defineProps({ modelValue: [String, Number], label: String, options: { type: Array, default: () => [] } });
defineEmits(['update:modelValue']);
</script>
<template>
    <label class="grid gap-1.5 text-sm font-medium text-slate-700 dark:text-slate-200">
        <span>{{ label }}</span>
        <select class="fw-input" :value="modelValue" @change="$emit('update:modelValue', $event.target.value)">
            <option v-for="option in options" :key="option.value ?? option" :value="option.value ?? option">{{ option.label ?? option }}</option>
        </select>
    </label>
</template>
