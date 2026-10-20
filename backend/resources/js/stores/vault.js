import { defineStore } from 'pinia';
import api from '../services/api';

export const useVaultStore = defineStore('vault', {
    state: () => ({
        pinStatus: 'PENDING_SETUP', // 'ACTIVE' | 'PENDING_SETUP'
        isUnlocked: false,
        isMasked: localStorage.getItem('vault_masked') === 'true',
        salt: null,
        recoveryCode: null,
        loading: false,
        error: '',
        remainingAttempts: null,
        cooldownSeconds: 0,
        cooldownTimer: null,
        inactivityTimer: null,
        isModalDismissed: true,
        forceShowModal: false,
    }),

    getters: {
        isLocked: (state) => !state.isUnlocked,
        isSetupNeeded: (state) => state.pinStatus === 'PENDING_SETUP',
        isModalOpen: (state) => {
            const token = localStorage.getItem('token');
            if (!token) return false;
            if (state.forceShowModal) return true;
            if (state.isUnlocked) return false;
            if (state.isModalDismissed) return false;
            return state.isSetupNeeded || !state.isUnlocked;
        },
    },

    actions: {
        openModal() {
            this.isModalDismissed = false;
            this.forceShowModal = true;
        },

        closeModal() {
            this.isModalDismissed = true;
            this.forceShowModal = false;
        },

        async fetchStatus() {
            const token = localStorage.getItem('token');
            if (!token) return;
            try {
                const res = await api.get('/vault/status');
                if (res.data.success) {
                    this.pinStatus = res.data.data.pin_status;
                    this.isUnlocked = Boolean(res.data.data.is_unlocked);
                    this.salt = res.data.data.encryption_salt;
                    if (this.isUnlocked) {
                        this.isModalDismissed = true;
                        this.forceShowModal = false;
                        this.resetInactivityTimer();
                    } else if (this.isSetupNeeded) {
                        this.isModalDismissed = false;
                    }
                }
            } catch (err) {
                // ignore if unauthenticated
            }
        },

        async unlock(pin) {
            this.loading = true;
            this.error = '';
            try {
                const res = await api.post('/vault/unlock', { pin });
                if (res.data.success) {
                    this.isUnlocked = true;
                    this.isModalDismissed = true;
                    this.forceShowModal = false;
                    this.error = '';
                    this.remainingAttempts = null;
                    this.cooldownSeconds = 0;
                    this.resetInactivityTimer();
                    return true;
                }
                return false;
            } catch (err) {
                const data = err.response?.data;
                this.error = data?.message || 'Gagal membuka vault.';
                if (data?.error?.remaining_attempts !== undefined) {
                    this.remainingAttempts = data.error.remaining_attempts;
                }
                if (data?.error?.retry_after) {
                    this.startCooldown(data.error.retry_after);
                }
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async setup(pin) {
            this.loading = true;
            this.error = '';
            try {
                const res = await api.post('/vault/setup', { pin });
                if (res.data.success) {
                    this.pinStatus = 'ACTIVE';
                    this.isUnlocked = true;
                    this.recoveryCode = res.data.data.recovery_code;
                    this.salt = res.data.data.encryption_salt;
                    this.resetInactivityTimer();
                    return res.data.data.recovery_code;
                }
                return null;
            } catch (err) {
                this.error = err.response?.data?.message || 'Gagal membuat Master PIN / Passphrase.';
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async lock() {
            try {
                await api.post('/vault/lock');
            } catch (err) {
                // ignore
            } finally {
                this.isUnlocked = false;
                this.isModalDismissed = false;
                this.forceShowModal = true;
                this.clearInactivityTimer();
            }
        },

        async reset(recoveryCode, newPin) {
            this.loading = true;
            this.error = '';
            try {
                const res = await api.post('/vault/reset', {
                    recovery_code: recoveryCode,
                    new_pin: newPin,
                });
                if (res.data.success) {
                    this.pinStatus = 'ACTIVE';
                    this.isUnlocked = true;
                    this.isModalDismissed = true;
                    this.forceShowModal = false;
                    this.resetInactivityTimer();
                    return true;
                }
                return false;
            } catch (err) {
                this.error = err.response?.data?.message || 'Gagal mereset PIN dengan Recovery Code.';
                throw err;
            } finally {
                this.loading = false;
            }
        },

        toggleMask() {
            this.isMasked = !this.isMasked;
            localStorage.setItem('vault_masked', this.isMasked ? 'true' : 'false');
        },

        startCooldown(seconds) {
            this.cooldownSeconds = seconds;
            if (this.cooldownTimer) clearInterval(this.cooldownTimer);
            this.cooldownTimer = setInterval(() => {
                if (this.cooldownSeconds > 0) {
                    this.cooldownSeconds -= 1;
                } else {
                    clearInterval(this.cooldownTimer);
                    this.cooldownTimer = null;
                }
            }, 1000);
        },

        resetInactivityTimer() {
            this.clearInactivityTimer();
            // Auto lock after 5 minutes of inactivity
            this.inactivityTimer = setTimeout(() => {
                if (this.isUnlocked) {
                    this.lock();
                }
            }, 5 * 60 * 1000);
        },

        clearInactivityTimer() {
            if (this.inactivityTimer) {
                clearTimeout(this.inactivityTimer);
                this.inactivityTimer = null;
            }
        },
    },
});
