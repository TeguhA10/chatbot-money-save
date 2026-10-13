import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const routes = [
    {
        path: '/login',
        name: 'login',
        component: () => import('../views/LoginView.vue'),
        meta: { guestOnly: true },
    },
    {
        path: '/',
        redirect: '/dashboard',
    },
    {
        path: '/dashboard',
        name: 'dashboard',
        component: () => import('../views/DashboardView.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/transactions',
        name: 'transactions',
        component: () => import('../views/TransactionsView.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/wallets',
        name: 'wallets',
        component: () => import('../views/WalletsView.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/budgets',
        name: 'budgets',
        component: () => import('../views/BudgetsView.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/goals',
        name: 'goals',
        component: () => import('../views/GoalsView.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/analytics',
        name: 'analytics',
        component: () => import('../views/AnalyticsView.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/:pathMatch(.*)*',
        redirect: '/dashboard',
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach((to, from, next) => {
    const authStore = useAuthStore();
    if (to.meta.requiresAuth && !authStore.isAuthenticated) {
        return next({ name: 'login' });
    }
    if (to.meta.guestOnly && authStore.isAuthenticated) {
        return next({ name: 'dashboard' });
    }
    next();
});

export default router;
