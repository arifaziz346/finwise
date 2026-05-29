import { defineStore } from 'pinia';
import api from '../services/api';

export const useAuthStore = defineStore('auth', {
    state: () => ({ user: null, token: localStorage.getItem('finwise_token'), loading: false }),
    getters: { isAuthenticated: (state) => Boolean(state.token) },
    actions: {
        async login(payload) {
            this.loading = true;
            const { data } = await api.post('/auth/login', payload);
            this.token = data.token;
            this.user = data.user.data;
            localStorage.setItem('finwise_token', data.token);
            this.loading = false;
        },
        async register(payload) {
            this.loading = true;
            const { data } = await api.post('/auth/register', payload);
            this.token = data.token;
            this.user = data.user.data;
            localStorage.setItem('finwise_token', data.token);
            this.loading = false;
        },
        async fetchUser() {
            if (!this.token) return;
            const { data } = await api.get('/auth/me');
            this.user = data.data;
        },
        async logout() {
            if (this.token) await api.post('/auth/logout');
            this.user = null;
            this.token = null;
            localStorage.removeItem('finwise_token');
        },
    },
});
