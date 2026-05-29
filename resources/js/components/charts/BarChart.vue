<script setup>
import { Bar } from 'vue-chartjs';
import { BarElement, CategoryScale, Chart as ChartJS, Legend, LinearScale, Tooltip } from 'chart.js';
ChartJS.register(BarElement, CategoryScale, LinearScale, Tooltip, Legend);
defineProps({ data: Object, options: Object });
</script>
<template><Bar :data="data" :options="options" /></template>
