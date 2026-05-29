import { makeResourceStore } from './resourceStore';
export const useExpensesStore = makeResourceStore('expenses', '/expenses');
