import { defineStore } from 'pinia';

export const useUiStore = defineStore('ui', {
    state: () => ({
        dark: localStorage.getItem('finwise_dark') === 'true',
        sidebarOpen: false,
    }),
    actions: {
        initTheme() {
            document.documentElement.classList.toggle('dark', this.dark);
        },
        toggleDark() {
            this.dark = !this.dark;
            localStorage.setItem('finwise_dark', this.dark);
            this.initTheme();
        },
        toggleSidebar() {
            this.sidebarOpen = !this.sidebarOpen;
        },
        closeSidebar() {
            this.sidebarOpen = false;
        },
    },
});
