<script setup>
import { Line } from 'vue-chartjs';
import { CategoryScale, Chart as ChartJS, Legend, LinearScale, LineElement, PointElement, Tooltip } from 'chart.js';
ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Legend);
defineProps({ data: Object, options: Object });
</script>
<template><Line :data="data" :options="options" /></template>
