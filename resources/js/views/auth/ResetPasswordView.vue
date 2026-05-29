<script setup>
import { ref } from 'vue';
import api from '../../services/api';
import AppInput from '../../components/ui/AppInput.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AuthShell from './AuthShell.vue';
const form = ref({ email: '', token: '', password: '', password_confirmation: '' });
const submit = async () => api.post('/auth/reset-password', form.value);
</script>
<template><AuthShell><h1 class="text-2xl font-bold">Reset Password</h1><form class="mt-6 grid gap-4" @submit.prevent="submit"><AppInput v-model="form.email" label="Email" /><AppInput v-model="form.token" label="Token" /><AppInput v-model="form.password" label="New password" type="password" /><AppInput v-model="form.password_confirmation" label="Confirm password" type="password" /><AppButton>Reset password</AppButton></form></AuthShell></template>
