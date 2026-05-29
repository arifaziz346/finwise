import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const auth = (name) => () => import(`../views/auth/${name}.vue`);
const DashboardView = () => import('../views/DashboardView.vue');
const ResourceView = () => import('../views/ResourceView.vue');
const ReminderView = () => import('../views/ReminderView.vue');
const SettingsView = () => import('../views/SettingsView.vue');

const routes = [
    { path: '/', redirect: '/dashboard' },
    { path: '/login', component: auth('LoginView') },
    { path: '/register', component: auth('RegisterView') },
    { path: '/forgot-password', component: auth('ForgotPasswordView') },
    { path: '/reset-password', component: auth('ResetPasswordView') },
    { path: '/dashboard', component: DashboardView, meta: { auth: true } },
    ...['income', 'expenses', 'budgets'].map((slug) => ({
        path: `/${slug}`,
        component: ResourceView,
        props: { slug },
        meta: { auth: true },
    })),
    { path: '/reminders', component: ReminderView, meta: { auth: true } },
    { path: '/settings', component: SettingsView, meta: { auth: true } },
    { path: '/expenses/categories', component: ResourceView, props: { slug: 'categories' }, meta: { auth: true } },
    { path: '/income/categories', component: ResourceView, props: { slug: 'income-categories' }, meta: { auth: true } },
    { path: '/:pathMatch(.*)*', redirect: '/dashboard' },
];

const router = createRouter({ history: createWebHistory(), routes });

router.beforeEach((to) => {
    const authStore = useAuthStore();
    if (to.meta.auth && !authStore.isAuthenticated) return '/login';
    if (['/login', '/register'].includes(to.path) && authStore.isAuthenticated) return '/dashboard';
});

export default router;
