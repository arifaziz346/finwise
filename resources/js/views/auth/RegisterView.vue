<script setup>
import { ref } from 'vue';
import { useRouter, RouterLink } from 'vue-router';
import { useToast } from 'vue-toastification';
import { useAuthStore } from '../../stores/auth';
import AppInput from '../../components/ui/AppInput.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AuthShell from './AuthShell.vue';

const auth = useAuthStore();
const router = useRouter();
const toast = useToast();
const form = ref({ name: '', email: '', password: '', password_confirmation: '', currency: 'PKR', timezone: 'Asia/Karachi', terms: false });
const submit = async () => {
    if (!form.value.terms) return toast.error('Please accept the terms.');
    try { await auth.register(form.value); toast.success('Account created'); router.push('/dashboard'); }
    catch { toast.error('Registration failed.'); }
};
</script>
<template>
    <AuthShell>
        <h1 class="text-2xl font-bold tracking-tight">Create Account</h1>
        <form class="mt-6 grid gap-4" @submit.prevent="submit">
            <AppInput v-model="form.name" label="Full name" />
            <AppInput v-model="form.email" label="Email" type="email" />
            <AppInput v-model="form.password" label="Password" type="password" />
            <AppInput v-model="form.password_confirmation" label="Confirm password" type="password" />
            <AppSelect v-model="form.currency" label="Currency" :options="['PKR','USD','EUR','GBP','AED']" />
            <AppInput v-model="form.timezone" label="Timezone" />
            <label class="flex items-center gap-2 text-sm"><input v-model="form.terms" type="checkbox"> I agree to the terms and conditions</label>
            <AppButton :loading="auth.loading">Register</AppButton>
            <div class="grid grid-cols-2 gap-3"><button class="rounded-xl border p-2.5">Google</button><button class="rounded-xl border p-2.5">GitHub</button></div>
            <p class="text-center text-sm text-slate-500">Already have an account? <RouterLink class="text-indigo-600" to="/login">Login</RouterLink></p>
        </form>
    </AuthShell>
</template>
