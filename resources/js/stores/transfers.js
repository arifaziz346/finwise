import { makeResourceStore } from './resourceStore';
export const useTransfersStore = makeResourceStore('transfers', '/transfers');
