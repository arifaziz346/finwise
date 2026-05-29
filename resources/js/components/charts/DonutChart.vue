<script setup>
import { Doughnut } from 'vue-chartjs';
import { ArcElement, Chart as ChartJS, Legend, Tooltip } from 'chart.js';
ChartJS.register(ArcElement, Tooltip, Legend);
defineProps({ data: Object, options: Object });
</script>
<template><Doughnut :data="data" :options="options" /></template>
