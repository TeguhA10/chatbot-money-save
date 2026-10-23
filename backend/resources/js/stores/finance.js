import { defineStore } from 'pinia';
import api from '../services/api';

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

        // Loading states
        loadingSummary: false,
        loadingTransactions: false,
        loadingWallets: false,
        loadingBudgets: false,
        loadingGoals: false,
    }),

    actions: {
        async fetchDashboardFeed(month = this.selectedMonth) {
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
                }
                return data;
            } catch (err) {
                console.error('Error fetching dashboard feed:', err);
            } finally {
                this.loadingSummary = false;
            }
        },

        async fetchSummary(month = this.selectedMonth) {
            this.loadingSummary = true;
            try {
                const res = await api.get('/analytics/summary', { params: { month } });
                this.summary = res.data.data;
            } catch (err) {
                console.error('Error fetching summary:', err);
            } finally {
                this.loadingSummary = false;
            }
        },

        async fetchCategoryBreakdown(month = this.selectedMonth) {
            try {
                const res = await api.get('/analytics/category-breakdown', { params: { month } });
                this.categoryBreakdown = res.data.data;
            } catch (err) {
                console.error('Error fetching category breakdown:', err);
            }
        },

        async fetchMonthlyTrend() {
            try {
                const res = await api.get('/analytics/monthly-trend');
                this.monthlyTrend = res.data.data;
            } catch (err) {
                console.error('Error fetching monthly trend:', err);
            }
        },

        async fetchTransactions(filters = {}) {
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
            } catch (err) {
                console.error('Error fetching transactions:', err);
            } finally {
                this.loadingTransactions = false;
            }
        },

        async createTransaction(payload) {
            const res = await api.post('/transactions', payload);
            await this.refreshAllData();
            return res.data;
        },

        async updateTransaction(id, payload) {
            const res = await api.put(`/transactions/${id}`, payload);
            await this.refreshAllData();
            return res.data;
        },

        async deleteTransaction(id) {
            const res = await api.delete(`/transactions/${id}`);
            await this.refreshAllData();
            return res.data;
        },

        async fetchWallets() {
            this.loadingWallets = true;
            try {
                const res = await api.get('/wallets');
                this.wallets = res.data.data.wallets;
                this.totalBalance = res.data.data.total_balance;
            } catch (err) {
                console.error('Error fetching wallets:', err);
            } finally {
                this.loadingWallets = false;
            }
        },

        async createWallet(payload) {
            const res = await api.post('/wallets', payload);
            await this.fetchWallets();
            return res.data;
        },

        async transferFunds(payload) {
            const res = await api.post('/wallets/transfer', payload);
            await this.refreshAllData();
            return res.data;
        },

        async fetchBudgets() {
            this.loadingBudgets = true;
            try {
                const res = await api.get('/budgets');
                this.budgets = res.data.data.budgets;
                this.totalBudgeted = res.data.data.total_budgeted;
                this.totalSpent = res.data.data.total_spent;
            } catch (err) {
                console.error('Error fetching budgets:', err);
            } finally {
                this.loadingBudgets = false;
            }
        },

        async createBudget(payload) {
            const res = await api.post('/budgets', payload);
            await this.fetchBudgets();
            return res.data;
        },

        async fetchGoals() {
            this.loadingGoals = true;
            try {
                const res = await api.get('/goals');
                this.goals = res.data.data.goals;
            } catch (err) {
                console.error('Error fetching goals:', err);
            } finally {
                this.loadingGoals = false;
            }
        },

        async createGoal(payload) {
            const res = await api.post('/goals', payload);
            await this.fetchGoals();
            return res.data;
        },

        async contributeGoal(id, payload) {
            const res = await api.post(`/goals/${id}/contribute`, payload);
            await this.refreshAllData();
            return res.data;
        },

        async fetchCategories() {
            try {
                const res = await api.get('/categories');
                this.categories = res.data.data;
            } catch (err) {
                console.error('Error fetching categories:', err);
            }
        },

        async refreshAllData() {
            await this.fetchDashboardFeed();
        },

        async loadAll() {
            return this.refreshAllData();
        },
    },
});
