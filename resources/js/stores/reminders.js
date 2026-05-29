import { makeResourceStore } from './resourceStore';
export const useRemindersStore = makeResourceStore('reminders', '/reminders');
