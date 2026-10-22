<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
    <!-- Backdrop Blur Overlay -->
    <div
      class="fixed inset-0 bg-slate-950/80 backdrop-blur-md transition-opacity animate-fade-in"
      @click="handleClose"
    ></div>

    <!-- Modal Box -->
    <div
      class="relative w-full max-w-lg bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-7 shadow-2xl text-slate-100 z-10 my-8 overflow-hidden animate-scale-up"
    >
      <!-- Ambient Glow Behind Modal -->
      <div class="absolute -top-24 -right-24 w-60 h-60 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
      <div class="absolute -bottom-24 -left-24 w-60 h-60 bg-teal-500/10 rounded-full blur-3xl pointer-events-none"></div>

      <!-- Header -->
      <div class="flex items-center justify-between pb-4 border-b border-slate-800/80 mb-5">
        <div class="flex items-center gap-2.5">
          <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-emerald-500/20 to-teal-500/10 border border-emerald-500/30 flex items-center justify-center p-1.5 shadow-md shadow-emerald-500/10">
            <img src="/images/logo.png" alt="HematWoy Logo" class="w-full h-full object-contain" />
          </div>
          <div>
            <h3 class="font-bold text-base text-white tracking-tight">Checkout Pembayaran</h3>
            <p class="text-[11px] text-slate-400">Order #{{ orderId }}</p>
          </div>
        </div>
        <button
          @click="handleClose"
          class="w-8 h-8 rounded-full bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white flex items-center justify-center transition text-sm cursor-pointer"
        >
          ✕
        </button>
      </div>

      <!-- SUCCESS STATE -->
      <div v-if="isSettled" class="py-8 text-center space-y-4">
        <div class="w-16 h-16 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-3xl mx-auto border border-emerald-500/40 shadow-xl shadow-emerald-500/20 animate-bounce">
          ✓
        </div>
        <div>
          <h4 class="text-xl font-black text-white">Pembayaran Berhasil Dikonfirmasi!</h4>
          <p class="text-xs text-slate-300 mt-1 max-w-sm mx-auto">
            Selamat! Akun HematWoy Pro kamu sudah aktif. Seluruh fitur pencatatan tanpa batas dan multi-dompet kini bisa langsung digunakan.
          </p>
        </div>
        <div class="pt-4 flex flex-col sm:flex-row gap-2.5 justify-center">
          <button
            @click="handleSuccessAction"
            class="py-3 px-6 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-400 hover:from-emerald-400 hover:to-teal-300 text-slate-950 font-bold text-sm shadow-lg shadow-emerald-500/25 transition cursor-pointer"
          >
            🚀 Mulai Gunakan Sekarang
          </button>
        </div>
      </div>

      <!-- INSTRUCTION / DETAILS STATE (AFTER METHOD SELECTED) -->
      <div v-else-if="chargeResult" class="space-y-5">
        <!-- Back button & total -->
        <div class="flex items-center justify-between">
          <button
            @click="chargeResult = null"
            class="text-xs text-emerald-400 hover:text-emerald-300 flex items-center gap-1 font-medium transition cursor-pointer"
          >
            <span>←</span>
            <span>Ganti Metode</span>
          </button>
          <div class="text-right">
            <span class="text-[10px] text-slate-400 block">Total Tagihan:</span>
            <span class="text-base font-black text-emerald-400">Rp {{ formatNumber(grossAmount) }}</span>
          </div>
        </div>

        <!-- QRIS DISPLAY -->
        <div v-if="selectedMethod === 'qris'" class="text-center space-y-3.5">
          <div class="p-3 bg-slate-950/80 border border-slate-800 rounded-2xl max-w-[270px] mx-auto shadow-inner text-center">
            <!-- QR Image -->
            <div class="bg-white p-3 rounded-xl mx-auto inline-block shadow-md">
              <img
                :src="chargeResult.qr_url"
                alt="QRIS Code"
                class="w-48 h-48 object-contain mx-auto"
              />
            </div>
            <div class="flex items-center justify-center gap-1.5 mt-2.5 text-[11px] font-semibold text-slate-300">
              <span class="inline-block w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
              <span>Scan QRIS dengan aplikasi apa saja</span>
            </div>
            <p class="text-[10px] text-slate-400 mt-0.5">BCA Mobile, GoPay, OVO, DANA, ShopeePay, LinkAja</p>
          </div>

          <!-- Countdown & Polling Status -->
          <div class="bg-slate-950/50 border border-slate-800/80 rounded-xl p-3 flex items-center justify-between text-xs">
            <span class="text-slate-400 flex items-center gap-1.5">
              <span class="text-emerald-400">⏱️</span>
              <span>Sisa Waktu:</span>
            </span>
            <span class="font-mono font-bold text-amber-400">{{ countdownText }}</span>
          </div>

          <!-- Action Buttons -->
          <div class="flex gap-2 justify-center">
            <a
              v-if="chargeResult.qr_url"
              :href="chargeResult.qr_url"
              download="hematwoy-qris.png"
              target="_blank"
              class="py-2.5 px-4 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-200 transition flex items-center gap-1.5"
            >
              <span>📥</span>
              <span>Unduh QR</span>
            </a>
            <button
              @click="checkStatus"
              :disabled="checkingStatus"
              class="py-2.5 px-4 rounded-xl bg-emerald-500/15 hover:bg-emerald-500/25 border border-emerald-500/30 text-xs font-semibold text-emerald-300 transition flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
            >
              <span :class="{ 'animate-spin': checkingStatus }">🔄</span>
              <span>{{ checkingStatus ? 'Memeriksa...' : 'Cek Status Bayar' }}</span>
            </button>
          </div>
        </div>

        <!-- VIRTUAL ACCOUNT / BILL DISPLAY -->
        <div v-else class="space-y-4">
          <!-- Bank VA Card -->
          <div class="p-4 bg-slate-950 border border-slate-800 rounded-2xl space-y-3">
            <div class="flex items-center justify-between">
              <span class="text-xs text-slate-400 uppercase tracking-wider font-semibold">
                {{ formatMethodName(selectedMethod) }}
              </span>
              <span class="text-[10px] px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-400 font-bold">
                Otomatis Terverifikasi
              </span>
            </div>

            <!-- VA Number Box -->
            <div class="bg-slate-900 border border-slate-800 rounded-xl p-3 flex items-center justify-between">
              <div>
                <span class="text-[10px] text-slate-400 block">Nomor Virtual Account:</span>
                <span class="font-mono text-lg font-black text-white tracking-wider">
                  {{ chargeResult.va_number || chargeResult.bill_key || 'Menunggu VA...' }}
                </span>
                <span v-if="chargeResult.biller_code" class="text-[11px] text-slate-400 block mt-0.5">
                  Kode Perusahaan: <strong class="text-slate-200">{{ chargeResult.biller_code }}</strong>
                </span>
              </div>
              <button
                @click="copyToClipboard(chargeResult.va_number || chargeResult.bill_key)"
                class="py-2 px-3 rounded-lg bg-emerald-500/15 hover:bg-emerald-500/25 text-emerald-300 font-bold text-xs flex items-center gap-1 transition cursor-pointer active:scale-95"
              >
                <span>{{ copied ? '✓ Tersalin' : 'Salin' }}</span>
              </button>
            </div>
          </div>

          <!-- Countdown & Polling Status -->
          <div class="bg-slate-950/50 border border-slate-800/80 rounded-xl p-3 flex items-center justify-between text-xs">
            <span class="text-slate-400 flex items-center gap-1.5">
              <span class="text-emerald-400">⏱️</span>
              <span>Sisa Waktu Pembayaran:</span>
            </span>
            <span class="font-mono font-bold text-amber-400">{{ countdownText }}</span>
          </div>

          <!-- Check Status Button -->
          <button
            @click="checkStatus"
            :disabled="checkingStatus"
            class="w-full py-3 px-4 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-200 transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
          >
            <span :class="{ 'animate-spin': checkingStatus }">🔄</span>
            <span>{{ checkingStatus ? 'Memeriksa Pembayaran...' : 'Saya Sudah Bayar (Cek Status)' }}</span>
          </button>
        </div>

        <!-- Simulation button in Sandbox -->
        <div v-if="showSimulateButton" class="pt-2 border-t border-slate-800/60 text-center">
          <button
            @click="simulatePayment"
            :disabled="simulating"
            class="text-[11px] text-amber-400/90 hover:text-amber-300 underline font-medium cursor-pointer"
          >
            {{ simulating ? 'Memproses simulasi...' : '🧪 Klik di sini untuk simulasi bayar instan (Sandbox Mode)' }}
          </button>
        </div>
      </div>

      <!-- SELECTION STATE (STEP 1: CHOOSE PAYMENT METHOD) -->
      <div v-else class="space-y-4">
        <!-- Order Summary Pill -->
        <div class="p-3.5 bg-slate-950/70 border border-slate-800 rounded-2xl flex items-center justify-between">
          <div>
            <span class="text-[11px] text-slate-400 block">{{ planName }}</span>
            <span class="text-xs font-bold text-white">Langganan Otomatis Aktif</span>
          </div>
          <div class="text-right">
            <span class="text-base font-black text-emerald-400">Rp {{ formatNumber(grossAmount) }}</span>
          </div>
        </div>

        <div>
          <span class="text-xs font-bold text-slate-300 block mb-2.5">Pilih Cara Pembayaran:</span>
          <div class="grid grid-cols-1 gap-2">
            <!-- QRIS Option (Highlighted) -->
            <button
              v-for="m in paymentMethods"
              :key="m.id"
              @click="selectPaymentMethod(m.id)"
              :disabled="loadingMethod === m.id"
              class="w-full p-3.5 rounded-2xl border text-left flex items-center justify-between transition cursor-pointer group"
              :class="[
                m.isRecommended
                  ? 'bg-gradient-to-r from-emerald-950/40 via-slate-900 to-slate-900 border-emerald-500/40 hover:border-emerald-400 shadow-lg shadow-emerald-500/5'
                  : 'bg-slate-950/60 border-slate-800 hover:border-slate-700 hover:bg-slate-900/60'
              ]"
            >
              <div class="flex items-center gap-3">
                <span class="text-2xl">{{ m.icon }}</span>
                <div>
                  <div class="flex items-center gap-2">
                    <span class="font-bold text-xs text-white group-hover:text-emerald-300 transition">{{ m.name }}</span>
                    <span v-if="m.badge" class="text-[9px] font-bold px-1.5 py-0.2 rounded-full bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                      {{ m.badge }}
                    </span>
                  </div>
                  <span class="text-[10px] text-slate-400 block mt-0.5">{{ m.desc }}</span>
                </div>
              </div>
              <div class="text-right">
                <span v-if="loadingMethod === m.id" class="text-xs text-emerald-400 animate-spin">⏳</span>
                <span v-else class="text-xs text-slate-500 group-hover:text-emerald-400 transition">→</span>
              </div>
            </button>
          </div>
        </div>

        <!-- Error Message -->
        <div v-if="errorMessage" class="p-3 rounded-xl bg-rose-500/15 border border-rose-500/30 text-rose-300 text-xs">
          {{ errorMessage }}
        </div>

        <div class="pt-2 text-center text-[10px] text-slate-500">
          🔒 Pembayaran aman & diproses otomatis oleh Midtrans payment gateway
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false,
  },
  orderId: {
    type: String,
    required: true,
  },
  grossAmount: {
    type: Number,
    default: 25000,
  },
  planName: {
    type: String,
    default: 'HematWoy Pro',
  },
  isProduction: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['close', 'settled']);

const selectedMethod = ref('');
const loadingMethod = ref('');
const chargeResult = ref(null);
const errorMessage = ref('');
const isSettled = ref(false);
const copied = ref(false);
const checkingStatus = ref(false);
const simulating = ref(false);
const remainingSeconds = ref(900); // 15 mins default
let timerInterval = null;
let pollInterval = null;

const paymentMethods = [
  {
    id: 'qris',
    name: 'QRIS (Semua E-Wallet & M-Banking)',
    desc: 'BCA, Mandiri, GoPay, OVO, DANA, ShopeePay, LinkAja',
    icon: '📱',
    badge: 'Disarankan',
    isRecommended: true,
  },
  {
    id: 'bca_va',
    name: 'BCA Virtual Account',
    desc: 'Transfer cepat via BCA Mobile / KlikBCA / ATM',
    icon: '🏦',
    isRecommended: false,
  },
  {
    id: 'mandiri_va',
    name: 'Mandiri Bill',
    desc: 'Bayar via Livin by Mandiri / ATM Mandiri',
    icon: '🏦',
    isRecommended: false,
  },
  {
    id: 'bni_va',
    name: 'BNI Virtual Account',
    desc: 'Bayar via BNI Mobile Banking / ATM',
    icon: '🏦',
    isRecommended: false,
  },
  {
    id: 'bri_va',
    name: 'BRI Virtual Account',
    desc: 'Bayar via BRImo / ATM BRI',
    icon: '🏦',
    isRecommended: false,
  },
  {
    id: 'permata_va',
    name: 'Permata Virtual Account',
    desc: 'Bayar via PermataMobile X / ATM',
    icon: '🏦',
    isRecommended: false,
  },
];

const showSimulateButton = computed(() => {
  return !props.isProduction;
});

const countdownText = computed(() => {
  const m = Math.floor(remainingSeconds.value / 60);
  const s = remainingSeconds.value % 60;
  return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
});

function formatNumber(num) {
  return new Intl.NumberFormat('id-ID').format(num || 0);
}

function formatMethodName(id) {
  const m = paymentMethods.find(x => x.id === id);
  return m ? m.name : id.toUpperCase();
}

async function selectPaymentMethod(methodId) {
  selectedMethod.value = methodId;
  loadingMethod.value = methodId;
  errorMessage.value = '';

  try {
    const res = await axios.post(`/api/v1/payment/charge/${props.orderId}`, {
      payment_method: methodId,
    });

    if (res.data.status === 'SETTLEMENT') {
      isSettled.value = true;
      emit('settled');
      return;
    }

    if (res.data.success) {
      chargeResult.value = res.data;
      startCountdown();
      startPolling();
    } else {
      // In sandbox mode fallback simulation
      if (res.data.fallback_simulated) {
        chargeResult.value = {
          success: true,
          qr_url: `https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=SIMULATED_ORDER_${props.orderId}`,
          va_number: '88012' + Math.floor(10000000 + Math.random() * 90000000),
          expiry_time: '15 Menit',
        };
        startCountdown();
        startPolling();
      } else {
        errorMessage.value = res.data.message || 'Gagal menyiapkan metode pembayaran.';
      }
    }
  } catch (err) {
    errorMessage.value = err.response?.data?.message || err.response?.data?.error || 'Gagal menghubungi server pembayaran.';
  } finally {
    loadingMethod.value = '';
  }
}

async function checkStatus() {
  checkingStatus.value = true;
  try {
    const res = await axios.get(`/api/v1/payment/order/${props.orderId}`);
    if (res.data.status === 'SETTLEMENT') {
      isSettled.value = true;
      stopPolling();
      emit('settled');
    }
  } catch (err) {
    // silent check
  } finally {
    checkingStatus.value = false;
  }
}

async function simulatePayment() {
  simulating.value = true;
  try {
    const res = await axios.post(`/api/v1/payment/simulate/${props.orderId}`);
    if (res.data.order?.status === 'SETTLEMENT') {
      isSettled.value = true;
      stopPolling();
      emit('settled');
    }
  } catch (err) {
    alert('Simulasi gagal: ' + (err.response?.data?.error || err.message));
  } finally {
    simulating.value = false;
  }
}

function startCountdown() {
  remainingSeconds.value = 900;
  if (timerInterval) clearInterval(timerInterval);
  timerInterval = setInterval(() => {
    if (remainingSeconds.value > 0) {
      remainingSeconds.value--;
    } else {
      clearInterval(timerInterval);
    }
  }, 1000);
}

function startPolling() {
  stopPolling();
  pollInterval = setInterval(async () => {
    await checkStatus();
  }, 3500);
}

function stopPolling() {
  if (pollInterval) clearInterval(pollInterval);
  if (timerInterval) clearInterval(timerInterval);
}

function copyToClipboard(text) {
  if (!text) return;
  navigator.clipboard.writeText(String(text));
  copied.value = true;
  setTimeout(() => {
    copied.value = false;
  }, 2500);
}

function handleClose() {
  stopPolling();
  emit('close');
}

function handleSuccessAction() {
  emit('settled');
  handleClose();
}

watch(() => props.isOpen, (newVal) => {
  if (!newVal) {
    stopPolling();
    chargeResult.value = null;
    errorMessage.value = '';
    isSettled.value = false;
  }
});

onUnmounted(() => {
  stopPolling();
});
</script>
