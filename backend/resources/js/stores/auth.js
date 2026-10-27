import { defineStore } from 'pinia';
import api from '../services/api';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        token: localStorage.getItem('token') || null,
        user: (() => {
            try {
                const u = localStorage.getItem('user');
                return u && u !== 'undefined' ? JSON.parse(u) : null;
            } catch {
                return null;
            }
        })(),
        subscription: null,
        showPricingModal: false,
        pricingPlan: 'monthly',
        loading: false,
        error: null,
    }),

    getters: {
        isAuthenticated: (state) => !!state.token,
        isPro: (state) => {
            if (state.subscription?.is_pro) return true;
            if (state.user?.is_pro === true || state.user?.is_pro === 1 || state.user?.is_pro === '1' || state.user?.is_pro === 'true') return true;
            if (state.user?.tier === 'PREMIUM') {
                if (!state.user.subscription_expires_at) return true;
                return new Date(state.user.subscription_expires_at) > new Date();
            }
            return false;
        },
    },

    actions: {
        async requestOtp(phone) {
            this.loading = true;
            this.error = null;
            try {
                const response = await api.post('/auth/request-otp', { phone });
                return response.data;
            } catch (err) {
                this.error = err.response?.data?.message || err.response?.data?.error?.message || 'Gagal mengirim kode OTP. Pastikan nomor terdaftar.';
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async verifyOtp(phone, otp) {
            this.loading = true;
            this.error = null;
            try {
                const response = await api.post('/auth/verify-otp', { phone, otp });
                const { token, user } = response.data.data;
                this.token = token;
                this.user = user;
                localStorage.setItem('token', token);
                localStorage.setItem('user', JSON.stringify(user));
                return response.data;
            } catch (err) {
                this.error = err.response?.data?.message || err.response?.data?.error?.message || 'Kode OTP tidak valid atau kedaluwarsa.';
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async fetchProfile() {
            if (!this.token) return;
            try {
                const response = await api.get('/profile');
                this.user = response.data.data;
                localStorage.setItem('user', JSON.stringify(this.user));
            } catch (err) {
                console.error('Failed to fetch profile', err);
            }
        },

        async fetchSubscription() {
            if (!this.token) return null;
            try {
                const res = await api.get('/subscription/status');
                this.subscription = res.data.data;
                return this.subscription;
            } catch (err) {
                console.error('Failed to fetch subscription status', err);
                return null;
            }
        },

        openPricing(plan = 'monthly') {
            this.pricingPlan = plan;
            this.showPricingModal = true;
        },

        closePricing() {
            this.showPricingModal = false;
        },

        async logout() {
            try {
                if (this.token) {
                    await api.post('/auth/logout');
                }
            } catch (err) {
                // Ignore network/server errors during logout
            } finally {
                this.token = null;
                this.user = null;
                localStorage.removeItem('token');
                localStorage.removeItem('user');
                window.location.href = '/login';
            }
        },
    },
});
