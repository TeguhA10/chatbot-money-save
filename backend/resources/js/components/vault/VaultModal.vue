<template>
  <div v-if="isOpen" class="vault-overlay">
    <div class="vault-card" role="dialog" aria-modal="true">
      <!-- Close Button -->
      <button
        v-if="mode !== 'recovery_success'"
        type="button"
        class="vault-modal-close"
        @click="dismissModal"
        title="Tutup & Tetap Sembunyikan Saldo"
        aria-label="Tutup"
      >
        ✕
      </button>

      <!-- Header Icon -->
      <div class="vault-icon-wrapper" :class="{ 'is-locked': mode === 'unlock', 'is-setup': mode === 'setup', 'is-success': mode === 'recovery_success' }">
        <svg v-if="mode === 'unlock'" class="vault-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
          <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
        </svg>
        <svg v-else-if="mode === 'setup'" class="vault-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
          <circle cx="12" cy="11" r="3"></circle>
        </svg>
        <svg v-else class="vault-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
          <polyline points="22 4 12 14.01 9 11.01"></polyline>
        </svg>
      </div>

      <!-- 1. MODE UNLOCK -->
      <div v-if="mode === 'unlock'">
        <h2 class="vault-title">Buka Sensor Saldo</h2>
        <p class="vault-subtitle">
          Masukkan PIN Anda untuk melihat nominal saldo di layar.
        </p>

        <form @submit.prevent="handleUnlock" class="vault-form">
          <div class="form-group">
            <label class="form-label" for="unlock-pin">PIN Keamanan</label>
            <div class="input-password-wrapper">
              <input
                id="unlock-pin"
                v-model="pinInput"
                :type="showPassword ? 'text' : 'password'"
                class="form-input"
                placeholder="Masukkan PIN Anda..."
                autocomplete="current-password"
                :disabled="vaultStore.cooldownSeconds > 0 || vaultStore.loading"
                required
              />
              <button type="button" class="btn-toggle-eye" @click="showPassword = !showPassword" tabindex="-1">
                {{ showPassword ? '👁️' : '👁️‍🗨️' }}
              </button>
            </div>
          </div>

          <!-- Error & Rate Limit Alerts -->
          <div v-if="vaultStore.cooldownSeconds > 0" class="alert-cooldown">
            ⚠️ Terlalu banyak salah. Tunggu <strong>{{ vaultStore.cooldownSeconds }} detik</strong> lagi.
          </div>
          <div v-else-if="localError || vaultStore.error" class="alert-error">
            {{ localError || vaultStore.error }}
          </div>

          <div class="vault-actions">
            <button
              type="submit"
              class="btn-primary-vault"
              :disabled="vaultStore.cooldownSeconds > 0 || vaultStore.loading || !pinInput"
            >
              <span v-if="vaultStore.loading" class="spinner"></span>
              <span v-else>🔓 Buka Saldo</span>
            </button>
            <button
              type="button"
              class="btn-dismiss-vault"
              @click="dismissModal"
            >
              Batal
            </button>
          </div>

          <div class="vault-footer-links">
            <button type="button" class="btn-link" @click="mode = 'reset'">
              Lupa PIN? Gunakan Kode Pemulihan
            </button>
          </div>
        </form>
      </div>

      <!-- 2. MODE SETUP -->
      <div v-else-if="mode === 'setup'">
        <h2 class="vault-title">Buat PIN Keamanan</h2>
        <p class="vault-subtitle">
          Atur PIN untuk menyembunyikan dan membuka nominal saldo Anda saat dibutuhkan.
        </p>

        <form @submit.prevent="handleSetup" class="vault-form">
          <div class="form-group">
            <label class="form-label" for="setup-pin">Buat PIN (Minimal 6 Digit / Karakter)</label>
            <div class="input-password-wrapper">
              <input
                id="setup-pin"
                v-model="pinInput"
                :type="showPassword ? 'text' : 'password'"
                class="form-input"
                placeholder="Contoh: 123456 atau kata sandi Anda"
                autocomplete="new-password"
                minlength="6"
                required
              />
              <button type="button" class="btn-toggle-eye" @click="showPassword = !showPassword" tabindex="-1">
                {{ showPassword ? '👁️' : '👁️‍🗨️' }}
              </button>
            </div>
            
            <!-- Password Strength Bar -->
            <div class="strength-meter" v-if="pinInput">
              <div class="strength-bar" :class="strengthClass" :style="{ width: strengthWidth }"></div>
            </div>
            <div class="strength-label" v-if="pinInput">
              Keamanan PIN: <strong>{{ strengthLabel }}</strong>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="setup-pin-confirm">Ketik Ulang PIN</label>
            <input
              id="setup-pin-confirm"
              v-model="pinConfirmInput"
              :type="showPassword ? 'text' : 'password'"
              class="form-input"
              placeholder="Ketik ulang PIN yang sama..."
              autocomplete="new-password"
              required
            />
          </div>

          <div v-if="localError" class="alert-error">
            {{ localError }}
          </div>

          <div class="vault-actions">
            <button type="submit" class="btn-primary-vault" :disabled="vaultStore.loading || !canSubmitSetup">
              <span v-if="vaultStore.loading" class="spinner"></span>
              <span v-else>✨ Simpan PIN</span>
            </button>
            <button
              type="button"
              class="btn-dismiss-vault"
              @click="dismissModal"
            >
              Batal
            </button>
          </div>
        </form>
      </div>

      <!-- 3. MODE RECOVERY SUCCESS -->
      <div v-else-if="mode === 'recovery_success'">
        <h2 class="vault-title" style="color: #10b981;">PIN Berhasil Dibuat! 🎉</h2>
        <p class="vault-subtitle">
          Simpan <strong>Kode Pemulihan</strong> di bawah ini. Kode ini berguna jika suatu saat Anda lupa PIN.
        </p>

        <div class="recovery-box">
          <div class="recovery-code-display">{{ newlyGeneratedRecoveryCode }}</div>
          <button type="button" class="btn-copy" @click="copyRecoveryCode">
            {{ copied ? '✅ Berhasil Disalin!' : '📋 Salin Kode' }}
          </button>
        </div>

        <div class="alert-warning">
          💡 <strong>Tips:</strong> Salin dan simpan kode ini di catatan HP Anda agar mudah dicari jika lupa PIN.
        </div>

        <div class="vault-actions" style="margin-top: 20px;">
          <button type="button" class="btn-primary-vault" @click="finishSetup">
            Selesai & Lanjutkan 🚀
          </button>
        </div>
      </div>

      <!-- 4. MODE RESET WITH RECOVERY CODE -->
      <div v-else-if="mode === 'reset'">
        <h2 class="vault-title">Atur Ulang PIN</h2>
        <p class="vault-subtitle">
          Masukkan Kode Pemulihan Anda untuk membuat PIN baru.
        </p>

        <form @submit.prevent="handleReset" class="vault-form">
          <div class="form-group">
            <label class="form-label" for="recovery-code">Kode Pemulihan (16 Karakter)</label>
            <input
              id="recovery-code"
              v-model="recoveryInput"
              type="text"
              class="form-input recovery-input-field"
              placeholder="Contoh: 1A2B3C4D5E6F7G8H"
              maxlength="16"
              required
            />
          </div>

          <div class="form-group">
            <label class="form-label" for="new-pin">Buat PIN Baru</label>
            <input
              id="new-pin"
              v-model="pinInput"
              :type="showPassword ? 'text' : 'password'"
              class="form-input"
              placeholder="Minimal 6 karakter..."
              minlength="6"
              required
            />
          </div>

          <div v-if="localError" class="alert-error">
            {{ localError }}
          </div>

          <div class="vault-actions">
            <button type="submit" class="btn-primary-vault" :disabled="vaultStore.loading || !recoveryInput || !pinInput">
              <span v-if="vaultStore.loading" class="spinner"></span>
              <span v-else>🔄 Simpan PIN Baru</span>
            </button>
            <button
              type="button"
              class="btn-dismiss-vault"
              @click="mode = 'unlock'"
            >
              Kembali
            </button>
          </div>
        </form>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useVaultStore } from '../../stores/vault';
import { useFinanceStore } from '../../stores/finance';

const vaultStore = useVaultStore();
const financeStore = useFinanceStore();

const pinInput = ref('');
const pinConfirmInput = ref('');
const recoveryInput = ref('');
const showPassword = ref(false);
const localError = ref('');
const copied = ref(false);
const newlyGeneratedRecoveryCode = ref('');

// Modes: 'unlock', 'setup', 'recovery_success', 'reset'
const mode = ref('unlock');

const isOpen = computed(() => {
  return vaultStore.isModalOpen;
});

function dismissModal() {
  vaultStore.closeModal();
}

watch(
  () => [vaultStore.pinStatus, vaultStore.isLocked],
  ([status]) => {
    if (status === 'PENDING_SETUP') {
      mode.value = 'setup';
    } else if (mode.value !== 'recovery_success') {
      mode.value = 'unlock';
    }
  },
  { immediate: true }
);

// Password strength calculation
const strengthScore = computed(() => {
  const p = pinInput.value;
  if (!p || p.length < 6) return 0;
  let score = 1;
  if (p.length >= 8) score++;
  if (/[A-Z]/.test(p) && /[a-z]/.test(p)) score++;
  if (/\d/.test(p)) score++;
  if (/[^A-Za-z0-9]/.test(p)) score++;
  return score; // max 5
});

const strengthWidth = computed(() => {
  return `${(strengthScore.value / 5) * 100}%`;
});

const strengthClass = computed(() => {
  if (strengthScore.value <= 1) return 'str-weak';
  if (strengthScore.value <= 3) return 'str-medium';
  return 'str-strong';
});

const strengthLabel = computed(() => {
  if (strengthScore.value <= 1) return 'Cukup (minimal 6 karakter)';
  if (strengthScore.value <= 3) return 'Bagus & Aman 👍';
  return 'Sangat Kuat & Aman 🛡️';
});

const canSubmitSetup = computed(() => {
  return pinInput.value.length >= 6 && pinInput.value === pinConfirmInput.value;
});

async function handleUnlock() {
  localError.value = '';
  try {
    const success = await vaultStore.unlock(pinInput.value);
    if (success) {
      pinInput.value = '';
      localError.value = '';
      vaultStore.isMasked = false;
      localStorage.setItem('vault_masked', 'false');
      vaultStore.closeModal();
      try {
        await financeStore.refreshAllData();
      } catch (e) {
        console.error('Error reloading financial data:', e);
      }
    }
  } catch (err) {
    localError.value = vaultStore.error || 'PIN / Passphrase tidak sesuai.';
  }
}

async function handleSetup() {
  localError.value = '';
  if (pinInput.value !== pinConfirmInput.value) {
    localError.value = 'Konfirmasi PIN / Passphrase tidak cocok.';
    return;
  }
  try {
    const code = await vaultStore.setup(pinInput.value);
    newlyGeneratedRecoveryCode.value = code;
    mode.value = 'recovery_success';
  } catch (err) {
    localError.value = vaultStore.error || 'Gagal mengatur PIN.';
  }
}

function copyRecoveryCode() {
  if (!newlyGeneratedRecoveryCode.value) return;
  navigator.clipboard.writeText(newlyGeneratedRecoveryCode.value);
  copied.value = true;
  setTimeout(() => {
    copied.value = false;
  }, 2500);
}

async function finishSetup() {
  pinInput.value = '';
  pinConfirmInput.value = '';
  vaultStore.isMasked = false;
  localStorage.setItem('vault_masked', 'false');
  vaultStore.closeModal();
  try {
    await financeStore.refreshAllData();
  } catch (e) {
    console.error('Error reloading financial data:', e);
  }
}

async function handleReset() {
  localError.value = '';
  try {
    const success = await vaultStore.reset(recoveryInput.value, pinInput.value);
    if (success) {
      recoveryInput.value = '';
      pinInput.value = '';
      localError.value = '';
      vaultStore.isMasked = false;
      localStorage.setItem('vault_masked', 'false');
      vaultStore.closeModal();
      try {
        await financeStore.refreshAllData();
      } catch (e) {
        console.error('Error reloading financial data:', e);
      }
    }
  } catch (err) {
    localError.value = vaultStore.error || 'Recovery code tidak valid.';
  }
}

onMounted(() => {
  vaultStore.fetchStatus();
});
</script>

<style scoped>
.vault-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(10, 15, 29, 0.88);
  backdrop-filter: blur(12px);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
  overflow-y: auto;
}

.vault-card {
  position: relative;
  width: 100%;
  max-width: 480px;
  max-height: 90vh;
  overflow-y: auto;
  background: #111827;
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 20px;
  padding: 32px 28px;
  box-shadow: 0 25px 60px rgba(0, 0, 0, 0.6);
  text-align: center;
  animation: cardIn 0.25s ease-out;
  margin: auto;
}

.vault-modal-close {
  position: absolute;
  top: 16px;
  right: 16px;
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.1);
  color: #9ca3af;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.2s;
  z-index: 10;
}

.vault-modal-close:hover {
  background: rgba(255, 255, 255, 0.15);
  color: #ffffff;
}

.btn-dismiss-vault {
  width: 100%;
  padding: 10px 16px;
  margin-top: 10px;
  background: transparent;
  border: 1px solid rgba(255, 255, 255, 0.15);
  color: #9ca3af;
  border-radius: 10px;
  font-size: 0.85rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.btn-dismiss-vault:hover {
  background: rgba(255, 255, 255, 0.05);
  color: #e5e7eb;
  border-color: rgba(255, 255, 255, 0.25);
}

@keyframes cardIn {
  from { opacity: 0; transform: scale(0.96) translateY(10px); }
  to { opacity: 1; transform: scale(1) translateY(0); }
}

.vault-icon-wrapper {
  width: 68px;
  height: 68px;
  margin: 0 auto 18px;
  border-radius: 18px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;
}

.vault-icon-wrapper.is-locked {
  background: rgba(239, 68, 68, 0.12);
  color: #ef4444;
  border: 1px solid rgba(239, 68, 68, 0.25);
}

.vault-icon-wrapper.is-setup {
  background: rgba(16, 185, 129, 0.12);
  color: #10b981;
  border: 1px solid rgba(16, 185, 129, 0.25);
}

.vault-icon-wrapper.is-success {
  background: rgba(16, 185, 129, 0.12);
  color: #10b981;
  border: 1px solid rgba(16, 185, 129, 0.25);
}

.vault-icon {
  width: 32px;
  height: 32px;
}

.vault-title {
  font-size: 1.45rem;
  font-weight: 700;
  color: #ffffff;
  margin-bottom: 8px;
}

.vault-subtitle {
  font-size: 0.88rem;
  color: #9ca3af;
  line-height: 1.5;
  margin-bottom: 24px;
}

.vault-form {
  text-align: left;
}

.form-group {
  margin-bottom: 18px;
}

.form-label {
  display: block;
  font-size: 0.82rem;
  font-weight: 600;
  color: #d1d5db;
  margin-bottom: 6px;
}

.input-password-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.form-input {
  width: 100%;
  padding: 12px 42px 12px 14px;
  background: #1f2937;
  border: 1px solid rgba(255, 255, 255, 0.15);
  border-radius: 10px;
  color: #ffffff;
  font-size: 0.95rem;
  outline: none;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.form-input:focus {
  border-color: #10b981;
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.25);
}

.recovery-input-field {
  text-transform: uppercase;
  letter-spacing: 2px;
  font-family: monospace;
  font-weight: bold;
}

.btn-toggle-eye {
  position: absolute;
  right: 12px;
  background: none;
  border: none;
  cursor: pointer;
  font-size: 1rem;
  opacity: 0.7;
}

.btn-toggle-eye:hover {
  opacity: 1;
}

.strength-meter {
  height: 4px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 2px;
  margin-top: 8px;
  overflow: hidden;
}

.strength-bar {
  height: 100%;
  transition: width 0.3s ease, background-color 0.3s ease;
}

.str-weak { background-color: #ef4444; }
.str-medium { background-color: #f59e0b; }
.str-strong { background-color: #10b981; }

.strength-label {
  font-size: 0.75rem;
  color: #9ca3af;
  margin-top: 4px;
}

.alert-error {
  background: rgba(239, 68, 68, 0.15);
  border: 1px solid rgba(239, 68, 68, 0.3);
  color: #fca5a5;
  padding: 10px 14px;
  border-radius: 8px;
  font-size: 0.85rem;
  margin-bottom: 16px;
}

.alert-cooldown {
  background: rgba(245, 158, 11, 0.15);
  border: 1px solid rgba(245, 158, 11, 0.3);
  color: #fcd34d;
  padding: 10px 14px;
  border-radius: 8px;
  font-size: 0.85rem;
  margin-bottom: 16px;
}

.alert-warning {
  background: rgba(245, 158, 11, 0.12);
  border: 1px solid rgba(245, 158, 11, 0.25);
  color: #fef08a;
  padding: 12px;
  border-radius: 10px;
  font-size: 0.82rem;
  line-height: 1.45;
  text-align: left;
}

.btn-primary-vault {
  width: 100%;
  padding: 13px;
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: #022c22;
  border: none;
  border-radius: 10px;
  font-size: 0.95rem;
  font-weight: 800;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: opacity 0.2s, transform 0.1s;
  box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3);
}

.btn-primary-vault:hover:not(:disabled) {
  opacity: 0.95;
  transform: translateY(-1px);
}

.btn-primary-vault:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.vault-footer-links {
  text-align: center;
  margin-top: 16px;
}

.btn-link {
  background: none;
  border: none;
  color: #10b981;
  font-size: 0.82rem;
  font-weight: 600;
  cursor: pointer;
  transition: opacity 0.2s;
}

.btn-link:hover {
  text-decoration: underline;
  opacity: 0.85;
}

.recovery-box {
  background: #030712;
  border: 1px dashed rgba(255, 255, 255, 0.2);
  padding: 16px;
  border-radius: 12px;
  margin-bottom: 16px;
}

.recovery-code-display {
  font-family: monospace;
  font-size: 1.5rem;
  letter-spacing: 4px;
  color: #34d399;
  font-weight: 700;
  margin-bottom: 10px;
}

.btn-copy {
  padding: 6px 14px;
  background: #1f2937;
  border: 1px solid rgba(255, 255, 255, 0.15);
  color: #e5e7eb;
  border-radius: 6px;
  font-size: 0.8rem;
  cursor: pointer;
}

.btn-copy:hover {
  background: #374151;
}

.spinner {
  width: 18px;
  height: 18px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: #ffffff;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
