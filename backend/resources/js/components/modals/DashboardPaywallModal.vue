<template>
  <Teleport to="body">
    <div
      v-if="isOpen"
      class="fixed inset-0 z-[100] flex items-end sm:items-center justify-center p-0 sm:p-4 bg-slate-950/90 backdrop-blur-md overflow-y-auto"
      @click.self="close"
    >
      <div
        class="bg-slate-900 border border-slate-800 rounded-t-3xl sm:rounded-3xl max-w-3xl w-full p-5 sm:p-7 shadow-2xl relative overflow-hidden text-slate-100 max-h-[92vh] sm:max-h-[90vh] flex flex-col animate-in fade-in slide-in-from-bottom-6 sm:zoom-in-95 duration-200"
      >
        <!-- Ambient Decorative Gradients -->
        <div class="absolute -top-32 -left-32 w-64 h-64 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-32 -right-32 w-64 h-64 bg-teal-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <!-- Mobile Drag Pill -->
        <div class="w-12 h-1.5 bg-slate-700/60 rounded-full mx-auto mb-3 sm:hidden shrink-0"></div>

        <!-- Close Button -->
        <button
          @click="close"
          class="absolute top-4 right-4 sm:top-5 sm:right-5 w-8 h-8 rounded-full bg-slate-800/90 hover:bg-slate-700 text-slate-400 hover:text-white flex items-center justify-center transition cursor-pointer z-20 text-xs sm:text-sm"
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
        <div v-else class="space-y-5 overflow-y-auto pr-1 flex-1">
          <!-- Header -->
          <div class="text-center space-y-2 pr-8 sm:pr-0">
            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500/15 border border-emerald-500/30 text-emerald-400 text-[11px] font-bold uppercase tracking-wider">
              <span>✨</span>
              <span>Akses Eksklusif Web Dashboard</span>
            </div>
            <h2 class="text-xl sm:text-2xl font-black text-white tracking-tight leading-snug">
              Pantau Finansialmu dalam
              <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 via-teal-300 to-cyan-400">
                Satu Dashboard
              </span>
            </h2>
            <p class="text-xs text-slate-400 max-w-md mx-auto leading-relaxed">
              Lihat analitik mutasi arus kas, grafik kategori, kelola multi-dompet, dan unduh laporan Excel resmi dengan mudah.
            </p>
          </div>

          <!-- Feature Badges Strip -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-left">
            <div class="p-2.5 rounded-xl bg-slate-950/70 border border-slate-800">
              <span class="text-base block mb-0.5">📈</span>
              <span class="text-xs font-bold text-white block">Visual Grafik</span>
              <span class="text-[10px] text-slate-400 block">Arus kas bulanan</span>
            </div>
            <div class="p-2.5 rounded-xl bg-slate-950/70 border border-slate-800">
              <span class="text-base block mb-0.5">👛</span>
              <span class="text-xs font-bold text-white block">Multi-Dompet</span>
              <span class="text-[10px] text-slate-400 block">Bank & E-Wallet</span>
            </div>
            <div class="p-2.5 rounded-xl bg-slate-950/70 border border-slate-800">
              <span class="text-base block mb-0.5">🎯</span>
              <span class="text-xs font-bold text-white block">Plafon Budget</span>
              <span class="text-[10px] text-slate-400 block">Anti-boncos</span>
            </div>
            <div class="p-2.5 rounded-xl bg-slate-950/70 border border-slate-800">
              <span class="text-base block mb-0.5">📑</span>
              <span class="text-xs font-bold text-white block">Ekspor Excel</span>
              <span class="text-[10px] text-slate-400 block">Format .xlsx resmi</span>
            </div>
          </div>

          <!-- DUAL CONVERSION TRACKS -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5 items-stretch">
            <!-- TRACK 1: GRATIS DI WHATSAPP -->
            <div class="p-4 rounded-2xl bg-slate-950/80 border border-slate-800 hover:border-slate-700 transition flex flex-col justify-between">
              <div>
                <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-emerald-500/15 text-emerald-400 text-[10px] font-bold mb-2 border border-emerald-500/30">
                  <span>⭐ Coba Gratis</span>
                </div>
                <h3 class="text-sm sm:text-base font-bold text-white mb-1">Uji Coba Gratis</h3>
                <p class="text-xs text-slate-400 mb-3 leading-relaxed">
                  Coba catat keuangan langsung lewat pesan chat. Bebas ribet tanpa registrasi awal.
                </p>

                <ul class="space-y-1.5 text-xs text-slate-300 mb-4">
                  <li class="flex items-center gap-2">
                    <span class="text-emerald-400 font-bold">✓</span>
                    <span><strong>{{ freeLimit }} Kuota Chat Pertama Gratis</strong></span>
                  </li>
                  <li class="flex items-center gap-2">
                    <span class="text-emerald-400 font-bold">✓</span>
                    <span>Tanpa perlu kartu kredit</span>
                  </li>
                  <li class="flex items-center gap-2">
                    <span class="text-emerald-400 font-bold">✓</span>
                    <span>Bebas coba mutasi kas harian</span>
                  </li>
                </ul>
              </div>

              <a
                :href="whatsappDirectUrl"
                target="_blank"
                class="w-full py-2.5 px-4 rounded-xl bg-slate-800 hover:bg-slate-750 border border-slate-700 text-slate-100 font-bold text-xs text-center flex items-center justify-center gap-1.5 transition active:scale-98"
              >
                <span>Coba Gratis Sekarang ➔</span>
              </a>
            </div>

            <!-- TRACK 2: BERLANGGANAN PRO -->
            <div class="p-4 rounded-2xl bg-gradient-to-b from-slate-950 to-emerald-950/30 border border-emerald-500/40 shadow-lg shadow-emerald-500/10 flex flex-col justify-between">
              <div>
                <div class="flex items-center justify-between mb-2">
                  <h3 class="text-sm sm:text-base font-bold text-white">Langganan Pro</h3>
                  <span class="text-[10px] px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-400 font-bold border border-emerald-500/30">
                    Akses Penuh
                  </span>
                </div>
                <p class="text-xs text-slate-400 mb-3">
                  Akses web dashboard & catat transaksi sepuasnya tanpa batas kuota.
                </p>

                <!-- Plan Selector -->
                <div class="grid grid-cols-2 gap-2 mb-3">
                  <button
                    v-for="plan in plans"
                    :key="plan.code"
                    type="button"
                    @click="selectedPlan = plan.code"
                    class="p-2 rounded-xl border text-left transition cursor-pointer relative"
                    :class="selectedPlan === plan.code ? 'bg-emerald-500/15 border-emerald-400 ring-1 ring-emerald-400/30' : 'bg-slate-900 border-slate-800 hover:border-slate-750'"
                  >
                    <div class="text-[11px] font-bold text-white flex items-center justify-between">
                      <span>{{ plan.name }}</span>
                      <span v-if="plan.badge" class="text-[8px] px-1 py-0.2 bg-teal-400 text-slate-950 font-black rounded">{{ plan.badge }}</span>
                    </div>
                    <div class="text-xs font-black text-emerald-400 mt-0.5">{{ plan.formatted_price }}</div>
                    <div class="text-[9px] text-slate-400">/ {{ plan.duration_days >= 365 ? '1 tahun' : '30 hari' }}</div>
                  </button>
                </div>

                <div class="space-y-1 text-[11px] text-slate-300 mb-3 bg-slate-950/70 p-2 rounded-xl border border-slate-800">
                  <div class="flex items-center gap-1.5">
                    <span class="text-emerald-400 font-bold">✓</span>
                    <span>Dashboard Web Lengkap & Ekspor Excel</span>
                  </div>
                  <div class="flex items-center gap-1.5">
                    <span class="text-emerald-400 font-bold">✓</span>
                    <span>Pencatatan keuangan tanpa batas kuota</span>
                  </div>
                </div>
              </div>

              <!-- Action button -->
              <div class="space-y-1.5">
                <div v-if="authStore.isAuthenticated">
                  <button
                    @click="initiateWebPayment"
                    :disabled="processing"
                    class="w-full py-2.5 px-4 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-400 hover:from-emerald-400 hover:to-teal-300 text-slate-950 font-bold text-xs shadow-md shadow-emerald-500/20 transition cursor-pointer flex items-center justify-center gap-1.5 disabled:opacity-50 active:scale-98"
                  >
                    <span v-if="processing" class="w-3.5 h-3.5 border-2 border-slate-950 border-t-transparent rounded-full animate-spin"></span>
                    <span>⚡ Lanjutkan Pembayaran ({{ currentPlanFormattedPrice }})</span>
                  </button>
                </div>
                <div v-else class="space-y-2">
                  <a
                    :href="whatsappDirectPlanUrl"
                    target="_blank"
                    class="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-400 text-slate-950 font-bold text-xs shadow-md shadow-emerald-500/20 hover:scale-102 active:scale-98 transition flex items-center justify-center gap-1.5 cursor-pointer text-center"
                  >
                    <span>⚡ Berlangganan Sekarang</span>
                  </a>
                </div>
              </div>
            </div>
          </div>

          <!-- Error Alert -->
          <div v-if="errorMessage" class="p-3 rounded-xl bg-rose-500/15 border border-rose-500/30 text-rose-300 text-xs">
            {{ errorMessage }}
          </div>

          <!-- Existing Subscriber Prompt Card -->
          <div class="p-3.5 rounded-2xl bg-slate-950/90 border border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs shadow-inner">
            <div class="flex items-center gap-2.5 text-slate-300 text-center sm:text-left">
              <span class="text-lg">🔑</span>
              <span>Sudah pernah berlangganan paket Pro di WhatsApp?</span>
            </div>
            <button
              @click="goToLogin"
              class="w-full sm:w-auto px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-emerald-400 hover:text-emerald-300 font-bold transition flex items-center justify-center gap-1.5 cursor-pointer shrink-0 active:scale-95"
            >
              <span>Masuk ke Dashboard</span>
              <span>➔</span>
            </button>
          </div>

          <!-- Trust Badges Footer -->
          <div class="pt-2 border-t border-slate-800/80 flex flex-wrap items-center justify-center gap-3 text-[10px] text-slate-500">
            <span>🔒 Enkripsi SSL 256-Bit</span>
            <span>•</span>
            <span>⚡ Aktivasi Otomatis</span>
            <span>•</span>
            <span>QRIS & E-Wallet Resmi</span>
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
  </Teleport>
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
