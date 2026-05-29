<script setup>
import { ref } from 'vue';
import api from '../../services/api';
import AppInput from '../../components/ui/AppInput.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AuthShell from './AuthShell.vue';
const email = ref('');
const sent = ref(false);
const submit = async () => { await api.post('/auth/forgot-password', { email: email.value }); sent.value = true; };
</script>
<template><AuthShell><h1 class="text-2xl font-bold">Forgot Password</h1><form class="mt-6 grid gap-4" @submit.prevent="submit"><AppInput v-model="email" label="Email" type="email" /><AppButton>Send reset link</AppButton><p v-if="sent" class="text-sm text-emerald-600">Reset link requested.</p></form></AuthShell></template>
