import { defineStore } from 'pinia';
import api from '../services/api';

const CACHE_TTL_MS = 60 * 1000; // 60 seconds TTL for background revalidation

export const useFinanceStore = defineStore('finance', {
    state: () => ({
        // Summary & Analytics
        summary: null,
        monthlyTrend: [],
        categoryBreakdown: [],
        selectedMonth: new Date().toISOString().slice(0, 7), // YYYY-MM

        // Data collections
        transactions: [],
        pagination: {
            page: 1,
            limit: 20,
            total_items: 0,
            total_pages: 1,
        },
        wallets: [],
        totalBalance: 0,
        budgets: [],
        totalBudgeted: 0,
        totalSpent: 0,
        goals: [],
        categories: [],

        // Cache tracking & SWR status
        hasLoadedFeed: false,
        hasLoadedWallets: false,
        hasLoadedBudgets: false,
        hasLoadedGoals: false,
        hasLoadedTransactions: false,
        hasLoadedCategories: false,
        lastFetchedAt: {
            feed: {}, // key by month 'YYYY-MM'
            summary: {},
            categoryBreakdown: {},
            monthlyTrend: 0,
            transactions: 0,
            wallets: 0,
            budgets: 0,
            goals: 0,
            categories: 0,
        },

        // Loading states
        loadingSummary: false,
        loadingTransactions: false,
        loadingWallets: false,
        loadingBudgets: false,
        loadingGoals: false,
    }),

    actions: {
        isFresh(timestamp, ttl = CACHE_TTL_MS) {
            return timestamp && (Date.now() - timestamp < ttl);
        },

        async fetchDashboardFeed(month = this.selectedMonth, force = false) {
            const lastFetched = this.lastFetchedAt.feed[month];
            if (!force && this.hasLoadedFeed && this.isFresh(lastFetched) && this.summary) {
                // Return cached data immediately without hitting network
                return {
                    summary: this.summary,
                    category_breakdown: this.categoryBreakdown,
                    monthly_trend: this.monthlyTrend,
                    wallets: { wallets: this.wallets, total_balance: this.totalBalance },
                    budgets: { budgets: this.budgets, total_budgeted: this.totalBudgeted, total_spent: this.totalSpent },
                    goals: { goals: this.goals },
                    recent_transactions: this.transactions,
                };
            }

            this.loadingSummary = true;
            try {
                const res = await api.get('/analytics/dashboard-feed', { params: { month } });
                const data = res.data.data;
                if (data) {
                    this.summary = data.summary;
                    this.categoryBreakdown = data.category_breakdown;
                    this.monthlyTrend = data.monthly_trend;
                    this.wallets = data.wallets?.wallets || [];
                    this.totalBalance = data.wallets?.total_balance ?? 0;
                    this.budgets = data.budgets?.budgets || [];
                    this.totalBudgeted = data.budgets?.total_budgeted ?? 0;
                    this.totalSpent = data.budgets?.total_spent ?? 0;
                    this.goals = data.goals?.goals || [];
                    this.transactions = data.recent_transactions || [];

                    // Mark as loaded across all modules
                    this.hasLoadedFeed = true;
                    this.hasLoadedWallets = true;
                    this.hasLoadedBudgets = true;
                    this.hasLoadedGoals = true;
                    this.lastFetchedAt.feed[month] = Date.now();
                    this.lastFetchedAt.wallets = Date.now();
                    this.lastFetchedAt.budgets = Date.now();
                    this.lastFetchedAt.goals = Date.now();
                    this.lastFetchedAt.monthlyTrend = Date.now();
                }
                return data;
            } catch (err) {
                console.error('Error fetching dashboard feed:', err);
                throw err;
            } finally {
                this.loadingSummary = false;
            }
        },

        async fetchSummary(month = this.selectedMonth, force = false) {
            const lastFetched = this.lastFetchedAt.summary[month];
            if (!force && this.summary && this.isFresh(lastFetched)) {
                return this.summary;
            }

            this.loadingSummary = true;
            try {
                const res = await api.get('/analytics/summary', { params: { month } });
                this.summary = res.data.data;
                this.lastFetchedAt.summary[month] = Date.now();
                return this.summary;
            } catch (err) {
                console.error('Error fetching summary:', err);
                throw err;
            } finally {
                this.loadingSummary = false;
            }
        },

        async fetchCategoryBreakdown(month = this.selectedMonth, force = false) {
            const lastFetched = this.lastFetchedAt.categoryBreakdown[month];
            if (!force && this.categoryBreakdown.length > 0 && this.isFresh(lastFetched)) {
                return this.categoryBreakdown;
            }

            try {
                const res = await api.get('/analytics/category-breakdown', { params: { month } });
                this.categoryBreakdown = res.data.data;
                this.lastFetchedAt.categoryBreakdown[month] = Date.now();
                return this.categoryBreakdown;
            } catch (err) {
                console.error('Error fetching category breakdown:', err);
                throw err;
            }
        },

        async fetchMonthlyTrend(force = false) {
            if (!force && this.monthlyTrend.length > 0 && this.isFresh(this.lastFetchedAt.monthlyTrend)) {
                return this.monthlyTrend;
            }

            try {
                const res = await api.get('/analytics/monthly-trend');
                this.monthlyTrend = res.data.data;
                this.lastFetchedAt.monthlyTrend = Date.now();
                return this.monthlyTrend;
            } catch (err) {
                console.error('Error fetching monthly trend:', err);
                throw err;
            }
        },

        async fetchTransactions(filters = {}, force = false) {
            // Only use cached transactions if no custom filter/search/pagination is requested
            const isDefaultFilter = !filters.search && !filters.type && !filters.category_id && !filters.start_date && !filters.end_date && (!filters.page || filters.page === 1);
            if (!force && isDefaultFilter && this.hasLoadedTransactions && this.isFresh(this.lastFetchedAt.transactions)) {
                return this.transactions;
            }

            this.loadingTransactions = true;
            try {
                const params = {
                    page: filters.page || 1,
                    limit: filters.limit || 20,
                    type: filters.type || undefined,
                    category_id: filters.category_id || undefined,
                    start_date: filters.start_date || undefined,
                    end_date: filters.end_date || undefined,
                    search: filters.search || undefined,
                };
                const res = await api.get('/transactions', { params });
                this.transactions = res.data.data.items;
                this.pagination = res.data.data.pagination;
                this.hasLoadedTransactions = true;
                this.lastFetchedAt.transactions = Date.now();
                return this.transactions;
            } catch (err) {
                console.error('Error fetching transactions:', err);
                throw err;
            } finally {
                this.loadingTransactions = false;
            }
        },

        async createTransaction(payload) {
            const res = await api.post('/transactions', payload);
            await this.invalidateAllAndRefresh();
            return res.data;
        },

        async updateTransaction(id, payload) {
            const res = await api.put(`/transactions/${id}`, payload);
            await this.invalidateAllAndRefresh();
            return res.data;
        },

        async deleteTransaction(id) {
            const res = await api.delete(`/transactions/${id}`);
            await this.invalidateAllAndRefresh();
            return res.data;
        },

        async fetchWallets(force = false) {
            if (!force && this.hasLoadedWallets && this.isFresh(this.lastFetchedAt.wallets)) {
                return this.wallets;
            }

            this.loadingWallets = true;
            try {
                const res = await api.get('/wallets');
                this.wallets = res.data.data.wallets;
                this.totalBalance = res.data.data.total_balance;
                this.hasLoadedWallets = true;
                this.lastFetchedAt.wallets = Date.now();
                return this.wallets;
            } catch (err) {
                console.error('Error fetching wallets:', err);
                throw err;
            } finally {
                this.loadingWallets = false;
            }
        },

        async createWallet(payload) {
            const res = await api.post('/wallets', payload);
            await this.fetchWallets(true);
            this.invalidateCache();
            return res.data;
        },

        async transferFunds(payload) {
            const res = await api.post('/wallets/transfer', payload);
            await this.invalidateAllAndRefresh();
            return res.data;
        },

        async fetchBudgets(force = false) {
            if (!force && this.hasLoadedBudgets && this.isFresh(this.lastFetchedAt.budgets)) {
                return this.budgets;
            }

            this.loadingBudgets = true;
            try {
                const res = await api.get('/budgets');
                this.budgets = res.data.data.budgets;
                this.totalBudgeted = res.data.data.total_budgeted;
                this.totalSpent = res.data.data.total_spent;
                this.hasLoadedBudgets = true;
                this.lastFetchedAt.budgets = Date.now();
                return this.budgets;
            } catch (err) {
                console.error('Error fetching budgets:', err);
                throw err;
            } finally {
                this.loadingBudgets = false;
            }
        },

        async createBudget(payload) {
            const res = await api.post('/budgets', payload);
            await this.fetchBudgets(true);
            this.invalidateCache();
            return res.data;
        },

        async fetchGoals(force = false) {
            if (!force && this.hasLoadedGoals && this.isFresh(this.lastFetchedAt.goals)) {
                return this.goals;
            }

            this.loadingGoals = true;
            try {
                const res = await api.get('/goals');
                this.goals = res.data.data.goals;
                this.hasLoadedGoals = true;
                this.lastFetchedAt.goals = Date.now();
                return this.goals;
            } catch (err) {
                console.error('Error fetching goals:', err);
                throw err;
            } finally {
                this.loadingGoals = false;
            }
        },

        async createGoal(payload) {
            const res = await api.post('/goals', payload);
            await this.fetchGoals(true);
            this.invalidateCache();
            return res.data;
        },

        async contributeGoal(id, payload) {
            const res = await api.post(`/goals/${id}/contribute`, payload);
            await this.invalidateAllAndRefresh();
            return res.data;
        },

        async fetchCategories(force = false) {
            if (!force && this.hasLoadedCategories && this.categories.length > 0) {
                return this.categories;
            }

            try {
                const res = await api.get('/categories');
                this.categories = res.data.data;
                this.hasLoadedCategories = true;
                this.lastFetchedAt.categories = Date.now();
                return this.categories;
            } catch (err) {
                console.error('Error fetching categories:', err);
                throw err;
            }
        },

        invalidateCache() {
            this.lastFetchedAt.feed = {};
            this.lastFetchedAt.summary = {};
            this.lastFetchedAt.categoryBreakdown = {};
            this.lastFetchedAt.monthlyTrend = 0;
            this.lastFetchedAt.transactions = 0;
            this.lastFetchedAt.wallets = 0;
            this.lastFetchedAt.budgets = 0;
            this.lastFetchedAt.goals = 0;
        },

        async invalidateAllAndRefresh() {
            this.invalidateCache();
            await this.fetchDashboardFeed(this.selectedMonth, true);
        },

        async refreshAllData() {
            await this.invalidateAllAndRefresh();
        },

        async loadAll() {
            return this.refreshAllData();
        },
    },
});
