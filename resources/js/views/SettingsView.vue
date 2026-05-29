<script setup>
import { onMounted, ref } from 'vue';
import { useToast } from 'vue-toastification';
import AppLayout from '../components/layout/AppLayout.vue';
import AppCard from '../components/ui/AppCard.vue';
import AppInput from '../components/ui/AppInput.vue';
import AppButton from '../components/ui/AppButton.vue';
import api from '../services/api';
import { useAuthStore } from '../stores/auth';

const toast = useToast();
const auth = useAuthStore();
const busy = ref('');
const profile = ref({ name: '', email: '', phone: '', date_of_birth: '' });
const password = ref({ current_password: '', password: '', password_confirmation: '' });
const errors = ref({});

const run = async (key, action) => {
    busy.value = key;
    try { return await action(); } finally { busy.value = ''; }
};

onMounted(async () => {
    const { data } = await api.get('/settings/profile');
    profile.value = { ...profile.value, ...data.data, ...(data.data.profile || {}) };
});

const saveProfile = async () => {
    await run('profile', async () => {
        try {
            const { data } = await api.put('/settings/profile', profile.value);
            auth.user = data.data;
            toast.success('Profile updated');
        } catch (error) {
            errors.value = error.response?.data?.errors ?? {};
            toast.error(error.response?.data?.message ?? 'Unable to update profile');
        }
    });
};

const savePassword = async () => {
    await run('password', async () => {
        try {
            await api.put('/settings/password', password.value);
            password.value = { current_password: '', password: '', password_confirmation: '' };
            toast.success('Password updated');
        } catch (error) {
            errors.value = error.response?.data?.errors ?? {};
            toast.error(error.response?.data?.message ?? 'Unable to update password');
        }
    });
};
</script>

<template>
    <AppLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-bold tracking-tight">Profile</h1>
            <p class="text-sm text-slate-500">Signed in as {{ auth.user?.name }} · {{ auth.user?.email }}</p>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <AppCard>
                <h2 class="mb-4 font-bold">Profile Details</h2>
                <form class="grid gap-4" @submit.prevent="saveProfile">
                    <AppInput v-model="profile.name" label="Name" :error="errors.name?.[0]" />
                    <AppInput v-model="profile.email" label="Email" type="email" :error="errors.email?.[0]" />
                    <AppInput v-model="profile.phone" label="Phone" :error="errors.phone?.[0]" />
                    <AppInput v-model="profile.date_of_birth" label="Date of birth" type="date" :error="errors.date_of_birth?.[0]" />
                    <AppButton :loading="busy === 'profile'">Save Profile</AppButton>
                </form>
            </AppCard>

            <AppCard>
                <h2 class="mb-4 font-bold">Change Password</h2>
                <form class="grid gap-4" @submit.prevent="savePassword">
                    <AppInput v-model="password.current_password" label="Current password" type="password" :error="errors.current_password?.[0]" />
                    <AppInput v-model="password.password" label="New password" type="password" :error="errors.password?.[0]" />
                    <AppInput v-model="password.password_confirmation" label="Confirm password" type="password" />
                    <AppButton :loading="busy === 'password'">Update Password</AppButton>
                </form>
            </AppCard>
        </div>
    </AppLayout>
</template>
