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
        name: 'landing',
        component: () => import('../views/LandingPageView.vue'),
    },
    {
        path: '/pay/:orderId',
        name: 'checkout',
        component: () => import('../views/CheckoutView.vue'),
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
        path: '/dokumentasi',
        name: 'documentation',
        component: () => import('../views/DocumentationView.vue'),
        meta: { isPublic: true },
    },
    {
        path: '/docs',
        redirect: '/dokumentasi',
    },
    {
        path: '/privasi',
        name: 'privacy',
        component: () => import('../views/PrivacyView.vue'),
        meta: { isPublic: true },
    },
    {
        path: '/kebijakan-privasi',
        redirect: '/privasi',
    },
    {
        path: '/:pathMatch(.*)*',
        redirect: '/dashboard',
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior(to, from, savedPosition) {
        if (savedPosition) {
            return savedPosition;
        }
        if (to.hash) {
            return {
                el: to.hash,
                behavior: 'smooth',
            };
        }
        return { top: 0, behavior: 'smooth' };
    },
});

router.beforeEach((to, from, next) => {
    const authStore = useAuthStore();
    if (to.meta.requiresAuth && !authStore.isAuthenticated) {
        return next({ name: 'login' });
    }
    if (to.meta.requiresAuth && authStore.isAuthenticated && !authStore.isPro) {
        authStore.logout();
        return next({ name: 'login', query: { error: 'subscription_required' } });
    }
    if (to.meta.guestOnly && authStore.isAuthenticated) {
        if (authStore.isPro) {
            return next({ name: 'dashboard' });
        } else {
            authStore.logout();
        }
    }
    next();
});

export default router;
