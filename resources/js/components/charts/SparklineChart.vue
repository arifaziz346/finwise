<script setup>
import LineChart from './LineChart.vue';
const data = { labels: ['', '', '', '', ''], datasets: [{ data: [2, 5, 3, 7, 9], borderColor: '#6366F1', tension: 0.4, pointRadius: 0 }] };
const options = { responsive: true, plugins: { legend: { display: false } }, scales: { x: { display: false }, y: { display: false } } };
</script>
<template><LineChart :data="data" :options="options" /></template>
