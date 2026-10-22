<template>
  <div class="min-h-screen bg-slate-950 flex items-center justify-center p-4 relative overflow-hidden">
    <!-- Ambient glowing backgrounds -->
    <div class="absolute -top-32 -left-32 w-96 h-96 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-32 -right-32 w-96 h-96 bg-teal-500/10 rounded-full blur-3xl pointer-events-none"></div>

    <div class="w-full max-w-md relative z-10">
      <!-- Brand Logo -->
      <div class="text-center mb-8">
        <div class="inline-flex w-20 h-20 rounded-3xl overflow-hidden bg-slate-900 border border-emerald-500/30 items-center justify-center p-2 shadow-2xl shadow-emerald-500/25 mb-4 hover:scale-105 transition">
          <img src="/images/logo.png" alt="HematWoy Logo" class="w-full h-full object-contain" />
        </div>
        <h1 class="text-2xl font-black tracking-tight text-white">HematWoy Web</h1>
        <p class="text-sm text-slate-400 mt-1">Kelola keuangan cerdas terhubung bot WhatsApp</p>
      </div>

      <!-- Main Card -->
      <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl backdrop-blur-xl">
        <!-- Error Alert -->
        <div v-if="errorMessage" class="mb-5 p-3.5 rounded-xl bg-rose-500/15 border border-rose-500/30 text-rose-300 text-xs flex items-center gap-2">
          <span>⚠️</span>
          <span>{{ errorMessage }}</span>
        </div>

        <!-- Success Alert -->
        <div v-if="successMessage" class="mb-5 p-3.5 rounded-xl bg-emerald-500/15 border border-emerald-500/30 text-emerald-300 text-xs flex items-center gap-2">
          <span>✅</span>
          <span>{{ successMessage }}</span>
        </div>

        <!-- STEP 1: Phone Input -->
        <div v-if="step === 1">
          <h2 class="text-lg font-bold text-white mb-1">Masuk dengan WhatsApp</h2>
          <p class="text-xs text-slate-400 mb-6">
            Masukkan nomor WhatsApp yang terdaftar pada bot untuk menerima kode OTP masuk.
          </p>

          <form @submit.prevent="handleRequestOtp" class="space-y-4">
            <div>
              <label class="block text-xs font-semibold text-slate-300 mb-1.5">Nomor WhatsApp</label>
              <div class="relative">
                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm font-semibold">🇮🇩</span>
                <input
                  type="tel"
                  v-model="phone"
                  required
                  placeholder="08123456789 atau 628..."
                  class="w-full pl-10 pr-4 py-3 rounded-xl bg-slate-950 border border-slate-800 text-white font-medium text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition"
                />
              </div>
              <p class="text-[11px] text-slate-500 mt-1.5">Contoh: 08123456789 atau 628123456789</p>
            </div>

            <button
              type="submit"
              :disabled="loading || !phone"
              class="w-full py-3.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-slate-950 font-bold text-sm shadow-lg shadow-emerald-500/20 transition-all cursor-pointer disabled:opacity-50 active:scale-98"
            >
              {{ loading ? 'Mengirim OTP...' : 'Kirim Kode Masuk (OTP)' }}
            </button>
          </form>
        </div>

        <!-- STEP 2: OTP Verification -->
        <div v-else-if="step === 2">
          <div class="flex items-center justify-between mb-1">
            <h2 class="text-lg font-bold text-white">Verifikasi Kode OTP</h2>
            <button
              @click="step = 1; errorMessage = '';"
              class="text-xs text-emerald-400 hover:underline"
            >
              Ubah Nomor
            </button>
          </div>
          <p class="text-xs text-slate-400 mb-6">
            Kode 6 digit telah dikirim melalui WhatsApp ke <b>{{ phone }}</b>.
          </p>

          <form @submit.prevent="handleVerifyOtp" class="space-y-4">
            <div>
              <label class="block text-xs font-semibold text-slate-300 mb-1.5">Kode OTP 6 Digit</label>
              <input
                type="text"
                v-model="otp"
                required
                maxlength="6"
                placeholder="000000"
                class="w-full px-4 py-3 rounded-xl bg-slate-950 border border-slate-800 text-white font-mono text-center tracking-widest text-2xl font-bold focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
              />
            </div>

            <button
              type="submit"
              :disabled="loading || otp.length !== 6"
              class="w-full py-3.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-slate-950 font-bold text-sm shadow-lg shadow-emerald-500/20 transition-all cursor-pointer disabled:opacity-50 active:scale-98"
            >
              {{ loading ? 'Memverifikasi...' : 'Masuk ke Dashboard' }}
            </button>

            <!-- Resend OTP button -->
            <div class="text-center pt-2">
              <button
                type="button"
                @click="handleRequestOtp"
                :disabled="resendCooldown > 0 || loading"
                class="text-xs text-slate-400 hover:text-emerald-400 transition disabled:opacity-50"
              >
                {{ resendCooldown > 0 ? `Kirim ulang OTP dalam ${resendCooldown} detik` : 'Kirim Ulang Kode OTP' }}
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Footer Info -->
      <div class="text-center mt-6 text-xs text-slate-500">
        HematWoy &bull; Kelola Catatan Keuangan dengan Praktis & Aman
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const router = useRouter();
const authStore = useAuthStore();

const step = ref(1);
const phone = ref('');
const otp = ref('');
const loading = ref(false);
const errorMessage = ref('');
const successMessage = ref('');
const resendCooldown = ref(0);

let timer = null;

function startCooldown() {
  resendCooldown.value = 60;
  clearInterval(timer);
  timer = setInterval(() => {
    if (resendCooldown.value > 0) {
      resendCooldown.value--;
    } else {
      clearInterval(timer);
    }
  }, 1000);
}

function normalizePhone(val) {
  let cleaned = val.replace(/\D/g, '');
  if (cleaned.startsWith('0')) {
    cleaned = '62' + cleaned.substring(1);
  }
  return cleaned;
}

async function handleRequestOtp() {
  errorMessage.value = '';
  successMessage.value = '';
  const normalized = normalizePhone(phone.value);
  if (normalized.length < 9) {
    errorMessage.value = 'Nomor telepon tidak valid.';
    return;
  }

  loading.value = true;
  try {
    await authStore.requestOtp(normalized);
    step.value = 2;
    successMessage.value = 'Kode OTP berhasil dikirim ke WhatsApp Anda!';
    startCooldown();
  } catch (err) {
    errorMessage.value = authStore.error || 'Gagal mengirim OTP.';
  } finally {
    loading.value = false;
  }
}

async function handleVerifyOtp() {
  errorMessage.value = '';
  successMessage.value = '';
  const normalized = normalizePhone(phone.value);
  const cleanOtp = (otp.value || '').trim();

  if (cleanOtp.length < 6) {
    errorMessage.value = 'Kode OTP harus berupa 6 digit angka.';
    return;
  }

  loading.value = true;
  try {
    await authStore.verifyOtp(normalized, cleanOtp);
    sessionStorage.removeItem('pending_phone');
    // Direct browser redirect to dashboard for fresh session load
    window.location.href = '/dashboard';
  } catch (err) {
    errorMessage.value = authStore.error || err.response?.data?.message || err.response?.data?.error?.message || 'Kode OTP salah atau kedaluwarsa.';
  } finally {
    loading.value = false;
  }
}
</script>
