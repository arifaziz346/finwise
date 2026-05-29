import { makeResourceStore } from './resourceStore';
export const useBusinessStore = makeResourceStore('business', '/businesses');
