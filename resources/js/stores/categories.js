import { makeResourceStore } from './resourceStore';
export const useCategoriesStore = makeResourceStore('categories', '/expense-categories');
