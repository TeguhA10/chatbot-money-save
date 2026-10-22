<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-950/85 backdrop-blur-md overflow-y-auto"
    @click.self="close"
  >
    <div
      class="bg-slate-900/95 border border-slate-800 rounded-3xl max-w-4xl w-full p-5 sm:p-8 shadow-2xl relative overflow-hidden backdrop-blur-xl animate-in fade-in zoom-in-95 duration-200 my-auto text-slate-100 max-h-[92vh] flex flex-col"
    >
      <!-- Ambient Decorative Gradients -->
      <div class="absolute -top-32 -left-32 w-64 h-64 bg-emerald-500/15 rounded-full blur-3xl pointer-events-none"></div>
      <div class="absolute -bottom-32 -right-32 w-64 h-64 bg-teal-500/15 rounded-full blur-3xl pointer-events-none"></div>

      <!-- Close Button -->
      <button
        @click="close"
        class="absolute top-4 right-4 sm:top-5 sm:right-5 w-8 h-8 rounded-full bg-slate-800/80 hover:bg-slate-700 text-slate-400 hover:text-white flex items-center justify-center transition cursor-pointer z-10"
        title="Tutup"
      >
        ✕
      </button>

      <!-- SUCCESS / SETTLED STATE -->
      <div v-if="success" class="text-center py-6 space-y-4 overflow-y-auto pr-1 flex-1">
        <div class="w-16 h-16 rounded-3xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-3xl mx-auto shadow-lg shadow-emerald-500/20 border border-emerald-500/30">
          🎉
        </div>
        <div class="inline-block px-3 py-1 bg-emerald-500/15 border border-emerald-500/30 rounded-full text-emerald-400 text-xs font-bold">
          PEMBAYARAN BERHASIL
        </div>
        <h3 class="text-2xl sm:text-3xl font-black text-white tracking-tight">
          Selamat! Akses Web Dashboard Terbuka
        </h3>
        <p class="text-xs sm:text-sm text-slate-300 max-w-md mx-auto leading-relaxed">
          Akun HematWoy Pro Anda kini aktif. Nikmati kemudahan memantau mutasi saldo, analitik grafik, multi-dompet, dan ekspor spreadsheet tanpa batas!
        </p>

        <div class="pt-4 flex flex-col sm:flex-row gap-3 justify-center">
          <button
            @click="goToDashboard"
            class="px-8 py-3.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-400 text-slate-950 font-black text-sm shadow-xl shadow-emerald-500/25 hover:scale-105 active:scale-95 transition cursor-pointer"
          >
            Buka Web Dashboard Sekarang 📊
          </button>
        </div>
      </div>

      <!-- MAIN COMMERCIAL CONTENT -->
      <div v-else class="space-y-6 overflow-y-auto pr-1 flex-1">
        <!-- Header -->
        <div class="text-center space-y-2">
          <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-gradient-to-r from-emerald-500/20 via-teal-500/20 to-cyan-500/20 border border-emerald-500/40 text-emerald-400 text-[11px] font-black uppercase tracking-wider shadow-sm">
            <span>✨</span>
            <span>Akses Eksklusif Web Dashboard</span>
          </div>
          <h2 class="text-2xl sm:text-3xl font-black text-white tracking-tight leading-tight">
            Pantau Seluruh Keuanganmu dalam
            <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 via-teal-300 to-cyan-400">
              Satu Dashboard
            </span>
          </h2>
          <p class="text-xs sm:text-sm text-slate-400 max-w-lg mx-auto leading-relaxed">
            Akses Web Dashboard interaktif dilengkapi grafik analitik arus kas, kontrol multi-dompet, dan ekspor Excel 1-klik dirancang khusus untuk pengguna HematWoy.
          </p>
        </div>

        <!-- Dashboard Feature Value Proof -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 text-left">
          <div class="p-3 rounded-2xl bg-slate-950/70 border border-slate-800/80">
            <span class="text-lg block mb-1">📈</span>
            <span class="text-xs font-bold text-white block">Visual Analytics</span>
            <span class="text-[10px] text-slate-400 block mt-0.5">Grafik cashflow & tren</span>
          </div>
          <div class="p-3 rounded-2xl bg-slate-950/70 border border-slate-800/80">
            <span class="text-lg block mb-1">👛</span>
            <span class="text-xs font-bold text-white block">Multi-Dompet</span>
            <span class="text-[10px] text-slate-400 block mt-0.5">BCA, Mandiri, e-Wallet</span>
          </div>
          <div class="p-3 rounded-2xl bg-slate-950/70 border border-slate-800/80">
            <span class="text-lg block mb-1">🎯</span>
            <span class="text-xs font-bold text-white block">Plafon Budget</span>
            <span class="text-[10px] text-slate-400 block mt-0.5">Peringatan anti-boncos</span>
          </div>
          <div class="p-3 rounded-2xl bg-slate-950/70 border border-slate-800/80">
            <span class="text-lg block mb-1">📑</span>
            <span class="text-xs font-bold text-white block">Ekspor Excel</span>
            <span class="text-[10px] text-slate-400 block mt-0.5">Laporan multi-sheet</span>
          </div>
        </div>

        <!-- DUAL CONVERSION TRACKS: Track A (Try WhatsApp Free) & Track B (Subscribe Pro) -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-stretch">
          
          <!-- OPTION 1: COBA DULU GRATIS DI WHATSAPP -->
          <div class="p-5 rounded-2xl bg-slate-950/80 border border-slate-800 hover:border-slate-700 transition flex flex-col justify-between relative group">
            <div>
              <div class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-emerald-500/15 text-emerald-400 text-[10px] font-bold mb-3 border border-emerald-500/30">
                <span>⭐</span>
                <span>Paling Mudah & Gratis</span>
              </div>
              <h3 class="text-base font-bold text-white mb-1">Coba Dulu di WhatsApp</h3>
              <p class="text-xs text-slate-400 mb-4 leading-relaxed">
                Belum yakin? Rasakan dulu sensasi catat transaksi semudah kirim chat ke bot WhatsApp kami.
              </p>

              <ul class="space-y-2 text-xs text-slate-300 mb-4">
                <li class="flex items-center gap-2">
                  <span class="text-emerald-400 font-bold">✓</span>
                  <span><strong>{{ freeLimit }} Transaksi Chat Gratis</strong></span>
                </li>
                <li class="flex items-center gap-2">
                  <span class="text-emerald-400 font-bold">✓</span>
                  <span>Aktif seketika tanpa isi kartu kredit</span>
                </li>
                <li class="flex items-center gap-2">
                  <span class="text-emerald-400 font-bold">✓</span>
                  <span>Bebas coba mutasi kas & rekap harian</span>
                </li>
                <li class="flex items-center gap-2 text-slate-500 text-[11px]">
                  <span>ℹ️</span>
                  <span>Web Dashboard bisa dibuka saat upgrade Pro</span>
                </li>
              </ul>
            </div>

            <a
              :href="whatsappDirectUrl"
              target="_blank"
              class="w-full py-3 px-4 rounded-xl bg-slate-800 hover:bg-slate-750 border border-slate-700 hover:border-emerald-500/40 text-slate-100 font-bold text-xs text-center flex items-center justify-center gap-2 transition cursor-pointer hover:shadow-lg hover:shadow-emerald-500/10 active:scale-98"
            >
              <span>💬 Coba Gratis di WhatsApp</span>
              <span class="text-emerald-400 font-mono">➔</span>
            </a>
          </div>

          <!-- OPTION 2: BERLANGGANAN HEMATWOY PRO -->
          <div class="p-5 rounded-2xl bg-gradient-to-b from-slate-900 to-emerald-950/40 border-2 border-emerald-500/60 shadow-xl shadow-emerald-500/10 flex flex-col justify-between relative">
            <div class="absolute -top-3 right-4 px-2.5 py-0.5 bg-gradient-to-r from-emerald-500 to-teal-400 text-slate-950 font-black text-[9px] uppercase tracking-wider rounded-full shadow-md">
              Akses Penuh
            </div>

            <div>
              <div class="flex items-center justify-between mb-2">
                <h3 class="text-base font-bold text-white">Langganan Paket Pro</h3>
                <span class="text-[11px] font-bold text-emerald-400">Unlimited</span>
              </div>
              <p class="text-xs text-slate-400 mb-3">
                Buka akses penuh Web Dashboard & hilangkan seluruh batasan.
              </p>

              <!-- Plan Selector -->
              <div class="grid grid-cols-2 gap-2 mb-4">
                <button
                  v-for="plan in plans"
                  :key="plan.code"
                  type="button"
                  @click="selectedPlan = plan.code"
                  class="p-2.5 rounded-xl border text-left transition cursor-pointer relative"
                  :class="selectedPlan === plan.code ? (plan.code === 'annual' ? 'bg-teal-500/15 border-teal-400 ring-1 ring-teal-400/40' : 'bg-emerald-500/15 border-emerald-500 ring-1 ring-emerald-500/40') : 'bg-slate-950/60 border-slate-800 hover:border-slate-700'"
                >
                  <div class="text-[11px] font-bold flex items-center justify-between" :class="plan.code === 'annual' ? 'text-white' : 'text-slate-200'">
                    <span>{{ plan.name }}</span>
                    <span v-if="plan.badge" class="text-[8px] px-1 py-0.2 bg-teal-400 text-slate-950 font-black rounded">{{ plan.badge }}</span>
                  </div>
                  <div class="text-sm font-black" :class="plan.code === 'annual' ? 'text-teal-400' : 'text-emerald-400'">{{ plan.formatted_price }}</div>
                  <div class="text-[9px] text-slate-400">/ {{ plan.duration_days >= 365 ? '365 hari' : plan.duration_days + ' hari' }}</div>
                </button>
              </div>

              <div class="space-y-1.5 text-[11px] text-slate-300 mb-4 bg-slate-950/60 p-2.5 rounded-xl border border-slate-800/80">
                <div class="flex items-center gap-1.5">
                  <span class="text-emerald-400">✓</span>
                  <span>Akses Web Dashboard + Ekspor Excel</span>
                </div>
                <div class="flex items-center gap-1.5">
                  <span class="text-emerald-400">✓</span>
                  <span>Unlimited Chat Bot WhatsApp tanpa kuota</span>
                </div>
              </div>
            </div>

            <!-- Action based on Auth state -->
            <div class="space-y-2">
              <!-- If logged in: direct Midtrans checkout -->
              <div v-if="authStore.isAuthenticated">
                <button
                  @click="initiateWebPayment"
                  :disabled="processing"
                  class="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-400 hover:from-emerald-400 hover:to-teal-300 text-slate-950 font-black text-xs shadow-lg shadow-emerald-500/20 transition cursor-pointer flex items-center justify-center gap-2 disabled:opacity-50 active:scale-98"
                >
                  <span v-if="processing" class="w-3.5 h-3.5 border-2 border-slate-950 border-t-transparent rounded-full animate-spin"></span>
                  <span>⚡ Bayar Instan ({{ currentPlanFormattedPrice }})</span>
                </button>
              </div>

              <!-- If visitor (not logged in): 2 convenient purchase paths -->
              <div v-else class="space-y-1.5">
                <a
                  :href="whatsappDirectPlanUrl"
                  target="_blank"
                  class="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-400 text-slate-950 font-black text-xs shadow-lg shadow-emerald-500/20 hover:scale-102 active:scale-98 transition flex items-center justify-center gap-2 cursor-pointer text-center"
                >
                  <span>💬 Langganan via WhatsApp Bot</span>
                </a>
                <button
                  type="button"
                  @click="goToLogin"
                  class="w-full py-2 px-3 rounded-xl bg-slate-950 hover:bg-slate-900 border border-slate-800 text-slate-300 hover:text-white font-semibold text-[11px] transition text-center cursor-pointer"
                >
                  <span>💳 Masuk & Bayar via Web</span>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Error Alert -->
        <div v-if="errorMessage" class="p-3 rounded-xl bg-rose-500/15 border border-rose-500/30 text-rose-300 text-xs">
          {{ errorMessage }}
        </div>

        <!-- Escape Hatch: Already a Subscriber? -->
        <div class="pt-2 text-center">
          <p class="text-xs text-slate-400">
            Sudah pernah berlangganan Pro sebelumnya?
            <button
              @click="goToLogin"
              class="text-emerald-400 hover:text-emerald-300 font-bold hover:underline cursor-pointer ml-1 inline-flex items-center gap-0.5"
            >
              <span>Masuk ke Dashboard di sini</span>
              <span>➔</span>
            </button>
          </p>
        </div>

        <!-- Trust Badges Footer -->
        <div class="pt-3 border-t border-slate-800/80 flex flex-wrap items-center justify-center gap-4 text-[10px] text-slate-500">
          <span class="flex items-center gap-1">
            <span>🔒</span>
            <span>Enkripsi AES-256</span>
          </span>
          <span>•</span>
          <span class="flex items-center gap-1">
            <span>⚡</span>
            <span>Aktivasi Instan Otomatis</span>
          </span>
          <span>•</span>
          <span class="flex items-center gap-1">
            <span>🇮🇩</span>
            <span>QRIS, e-Wallet & Virtual Account</span>
          </span>
        </div>
      </div>
    </div>

    <!-- Custom In-App Payment Modal (No Midtrans Popup) -->
    <CustomPaymentModal
      v-if="customPaymentOrderId"
      :isOpen="showCustomPayment"
      :orderId="customPaymentOrderId"
      :grossAmount="customPaymentAmount"
      :planName="customPaymentPlanName"
      :isProduction="customPaymentIsProduction"
      @close="showCustomPayment = false"
      @settled="onCustomPaymentSettled"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '../../services/api';
import { useAuthStore } from '../../stores/auth';
import { useToastStore } from '../../stores/toast';
import CustomPaymentModal from '../payment/CustomPaymentModal.vue';

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false,
  },
  botRawNumber: {
    type: String,
    default: () => (typeof window !== 'undefined' && window.__BOT_CONFIG__?.bot_number ? window.__BOT_CONFIG__.bot_number : '6285111355992'),
  },
  freeLimit: {
    type: [Number, String],
    default: () => (typeof window !== 'undefined' && window.__BOT_CONFIG__?.free_limit ? window.__BOT_CONFIG__.free_limit : 100),
  },
});

const emit = defineEmits(['close', 'upgraded']);

const router = useRouter();
const authStore = useAuthStore();
const toastStore = useToastStore();

const plans = ref([
  {
    code: 'monthly',
    name: 'Pro Bulanan',
    tagline: '30 Hari Akses',
    duration_days: 30,
    price: 25000,
    formatted_price: 'Rp 25.000',
    badge: null,
  },
  {
    code: 'annual',
    name: 'Pro 1 Tahun',
    tagline: '365 Hari Penuh',
    duration_days: 365,
    price: 199000,
    formatted_price: 'Rp 199.000',
    badge: 'HEMAT 35%',
  },
]);

async function loadPlans() {
  try {
    const res = await api.get('/plans');
    if (res.data?.success && res.data.data?.length) {
      plans.value = res.data.data;
    }
  } catch (err) {
    // fallback gracefully
  }
}

onMounted(() => {
  loadPlans();
});

const selectedPlan = ref('monthly');
const processing = ref(false);
const success = ref(false);
const errorMessage = ref('');

// Custom In-App Modal State
const showCustomPayment = ref(false);
const customPaymentOrderId = ref('');
const customPaymentAmount = ref(25000);
const customPaymentPlanName = ref('HematWoy Pro');
const customPaymentIsProduction = ref(false);

const currentPlan = computed(() => plans.value.find(p => p.code === selectedPlan.value) || plans.value[0]);
const currentPlanFormattedPrice = computed(() => currentPlan.value?.formatted_price || 'Rp 25.000');

const whatsappDirectUrl = computed(() => {
  return `https://wa.me/${props.botRawNumber}?text=Halo%20HematWoy,%20saya%20mau%20coba%20catat%20keuangan%20gratis%20di%20WhatsApp!`;
});

const whatsappDirectPlanUrl = computed(() => {
  const p = currentPlan.value;
  const periodText = (p?.duration_days >= 365) ? 'tahun' : 'bulan';
  const planText = encodeURIComponent(`${p?.name || 'Paket Pro'} (${p?.formatted_price || 'Rp 25.000'}/${periodText})`);
  return `https://wa.me/${props.botRawNumber}?text=Halo%20HematWoy,%20saya%20mau%20berlangganan%20${planText}%20untuk%20membuka%20Web%20Dashboard!`;
});

function close() {
  if (processing.value) return;
  errorMessage.value = '';
  emit('close');
}

function goToDashboard() {
  success.value = false;
  emit('upgraded');
  emit('close');
  router.push('/dashboard');
}

function goToLogin() {
  close();
  router.push('/login');
}

async function initiateWebPayment() {
  processing.value = true;
  errorMessage.value = '';
  try {
    const res = await api.post('/subscription/create-order', {
      plan: selectedPlan.value,
    });

    const data = res.data;
    customPaymentOrderId.value = data.order_id;
    customPaymentAmount.value = data.gross_amount || currentPlan.value?.price || 25000;
    customPaymentPlanName.value = data.plan_name || currentPlan.value?.name || 'HematWoy Pro';
    customPaymentIsProduction.value = !!data.is_production;
    showCustomPayment.value = true;
  } catch (err) {
    errorMessage.value = err.response?.data?.error || err.response?.data?.message || 'Gagal memulai pesanan langganan.';
  } finally {
    processing.value = false;
  }
}

async function onCustomPaymentSettled() {
  showCustomPayment.value = false;
  success.value = true;
  await authStore.fetchProfile();
  await authStore.fetchSubscription();
  toastStore.success('Pembayaran sukses! Akses Web Dashboard aktif.');
  emit('upgraded');
}
</script>
