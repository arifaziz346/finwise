import { makeResourceStore } from './resourceStore';
export const useAccountsStore = makeResourceStore('accounts', '/accounts');
