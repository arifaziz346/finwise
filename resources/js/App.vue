<script setup>
import { onMounted } from 'vue';
import { RouterView } from 'vue-router';
import { useUiStore } from './stores/ui';

const ui = useUiStore();
onMounted(() => ui.initTheme());
</script>

<template>
    <RouterView />
</template>
