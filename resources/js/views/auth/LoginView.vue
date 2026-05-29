<script setup>
import { ref } from 'vue';
import { useRouter, RouterLink } from 'vue-router';
import { useToast } from 'vue-toastification';
import { useAuthStore } from '../../stores/auth';
import AppInput from '../../components/ui/AppInput.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AuthShell from './AuthShell.vue';

const auth = useAuthStore();
const router = useRouter();
const toast = useToast();
const form = ref({ email: 'demo@finwise.com', password: 'demo1234', remember: true });
const show = ref(false);
const submit = async () => {
    try { await auth.login(form.value); toast.success('Welcome back to FinWise'); router.push('/dashboard'); }
    catch { toast.error('Login failed. Check your credentials.'); }
};
</script>
<template>
    <AuthShell>
        <h1 class="text-2xl font-bold tracking-tight">Login</h1>
        <form class="mt-6 grid gap-4" @submit.prevent="submit">
            <AppInput v-model="form.email" label="Email" type="email" />
            <div class="relative">
                <AppInput v-model="form.password" label="Password" :type="show ? 'text' : 'password'" />
                <button type="button" class="absolute bottom-2.5 right-3 text-sm text-indigo-600" @click="show = !show">{{ show ? 'Hide' : 'Show' }}</button>
            </div>
            <div class="flex items-center justify-between text-sm">
                <label class="flex items-center gap-2"><input v-model="form.remember" type="checkbox"> Remember me</label>
                <RouterLink to="/forgot-password" class="text-indigo-600">Forgot password?</RouterLink>
            </div>
            <AppButton :loading="auth.loading">Login</AppButton>
            <p class="text-center text-sm text-slate-500">New here? <RouterLink class="text-indigo-600" to="/register">Create account</RouterLink></p>
        </form>
    </AuthShell>
</template>
