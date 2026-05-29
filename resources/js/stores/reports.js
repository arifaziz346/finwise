import { defineStore } from 'pinia';
import api from '../services/api';
export const useReportsStore = defineStore('reports', { state: () => ({ rows: [] }), actions: { async fetch(path = '/reports/income-expense') { this.rows = (await api.get(path)).data; } } });
