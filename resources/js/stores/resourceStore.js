import { defineStore } from 'pinia';
import api from '../services/api';

export const makeResourceStore = (id, endpoint) => defineStore(id, {
    state: () => ({ rows: [], meta: {}, loading: false }),
    actions: {
        async fetch(params = {}) {
            this.loading = true;
            const { data } = await api.get(endpoint, { params });
            this.rows = data.data ?? data;
            this.meta = data.meta ?? {};
            this.loading = false;
        },
        async create(payload) {
            const { data } = await api.post(endpoint, payload);
            this.rows.unshift(data.data ?? data);
        },
        async remove(id) {
            await api.delete(`${endpoint}/${id}`);
            this.rows = this.rows.filter((row) => row.id !== id);
        },
    },
});
