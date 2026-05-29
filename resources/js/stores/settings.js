import { defineStore } from 'pinia';
import api from '../services/api';
export const useSettingsStore = defineStore('settings', { state: () => ({ profile: null }), actions: { async fetch() { this.profile = (await api.get('/settings/profile')).data.data; } } });
