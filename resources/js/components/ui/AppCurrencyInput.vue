<script setup>
import AppInput from './AppInput.vue';
defineProps({ modelValue: [String, Number], label: String });
defineEmits(['update:modelValue']);
</script>
<template><AppInput type="number" :label="label" :model-value="modelValue" @update:model-value="$emit('update:modelValue', $event)" /></template>
