<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-950/80 backdrop-blur-md transition-all duration-300 overflow-y-auto"
    @click.self="close"
  >
    <div
      class="bg-slate-900/95 border border-slate-800 rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl relative overflow-hidden backdrop-blur-xl animate-in fade-in zoom-in-95 duration-200 my-auto max-h-[92vh] flex flex-col"
    >
      <!-- Ambient Glows -->
      <div class="absolute -top-24 -left-24 w-48 h-48 bg-emerald-500/15 rounded-full blur-3xl pointer-events-none"></div>
      <div class="absolute -bottom-24 -right-24 w-48 h-48 bg-teal-500/15 rounded-full blur-3xl pointer-events-none"></div>

      <!-- Close Button -->
      <button
        @click="close"
        class="absolute top-5 right-5 w-8 h-8 rounded-full bg-slate-800/80 hover:bg-slate-700 text-slate-400 hover:text-white flex items-center justify-center transition cursor-pointer z-10"
        title="Tutup"
      >
        ✕
      </button>

      <!-- SUCCESS / SETTLED STATE -->
      <div v-if="success" class="text-center py-4 space-y-4 overflow-y-auto pr-1 flex-1">
        <div class="w-16 h-16 rounded-3xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-3xl mx-auto shadow-lg shadow-emerald-500/20">
          🎉
        </div>
        <div class="inline-block px-3 py-1 bg-emerald-500/15 border border-emerald-500/30 rounded-full text-emerald-400 text-xs font-bold">
          PEMBAYARAN BERHASIL
        </div>
        <h3 class="text-2xl font-black text-white tracking-tight">Selamat! Akun HematWoy Pro Aktif</h3>
        <p class="text-xs sm:text-sm text-slate-300 max-w-sm mx-auto leading-relaxed">
          Seluruh batasan kuota telah diangkat. Sekarang Anda bebas mencatat transaksi, mengelola multi-dompet, dan mengekspor laporan keuangan!
        </p>

        <div class="pt-4 flex flex-col gap-2.5">
          <button
            @click="completeAndClose"
            class="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-400 text-slate-950 font-bold text-sm shadow-xl shadow-emerald-500/25 hover:opacity-95 transition cursor-pointer"
          >
            Mulai Gunakan Fitur Pro 🚀
          </button>
        </div>
      </div>

      <!-- SELECTION & CHECKOUT FORM -->
      <div v-else class="space-y-6 overflow-y-auto pr-1 flex-1">
        <!-- Header -->
        <div class="text-center space-y-1.5">
          <div class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full bg-emerald-500/15 border border-emerald-500/30 text-emerald-400 text-[11px] font-bold uppercase tracking-wider">
            ⭐ Upgrade HematWoy Pro
          </div>
          <h2 class="text-xl sm:text-2xl font-black text-white tracking-tight">
            Pencatatan Keuangan Tanpa Batas
          </h2>
          <p class="text-xs text-slate-400 max-w-sm mx-auto">
            Pilih paket langganan yang sesuai untuk memaksimalkan disiplin finansial Anda.
          </p>
        </div>

        <!-- Plan Selection Cards -->
        <div class="grid grid-cols-2 gap-3">
          <div
            v-for="plan in plans"
            :key="plan.code"
            @click="selectedPlan = plan.code"
            class="p-4 rounded-2xl border transition-all cursor-pointer relative flex flex-col justify-between"
            :class="selectedPlan === plan.code ? (plan.code === 'annual' ? 'bg-teal-950/30 border-teal-400 ring-2 ring-teal-400/30' : 'bg-emerald-950/30 border-emerald-500 ring-2 ring-emerald-500/30') : 'bg-slate-800/40 border-slate-700/60 hover:bg-slate-800/60'"
          >
            <div v-if="plan.badge" class="absolute -top-2.5 right-3 px-2 py-0.5 bg-gradient-to-r from-teal-400 to-emerald-400 text-slate-950 font-black text-[9px] uppercase tracking-wider rounded-full shadow">
              {{ plan.badge }}
            </div>
            <div>
              <span class="text-xs font-bold block" :class="plan.code === 'annual' ? 'text-white' : 'text-slate-200'">{{ plan.name }}</span>
              <span class="text-[11px] block mb-2" :class="plan.code === 'annual' ? 'text-teal-300' : 'text-slate-400'">{{ plan.tagline || (plan.duration_days + ' Hari Akses') }}</span>
              <div class="text-lg font-black" :class="plan.code === 'annual' ? 'text-teal-400' : 'text-emerald-400'">
                {{ plan.formatted_price }}
              </div>
            </div>
            <div class="mt-3 text-[10px] text-slate-400">
              {{ plan.discount_percentage > 0 ? `Hemat ${plan.discount_percentage}%!` : 'Fleksibel per periode' }}
            </div>
          </div>
        </div>

        <!-- Pro Features List -->
        <div class="bg-slate-950/70 border border-slate-800/80 rounded-2xl p-4 space-y-2 text-xs text-slate-300">
          <div v-for="(feat, idx) in currentPlanFeatures" :key="idx" class="flex items-center gap-2">
            <span class="text-emerald-400 font-bold">✓</span>
            <span>{{ feat }}</span>
          </div>
        </div>

        <!-- Error Alert -->
        <div v-if="errorMessage" class="p-3 rounded-xl bg-rose-500/15 border border-rose-500/30 text-rose-300 text-xs">
          {{ errorMessage }}
        </div>

        <!-- Action Buttons -->
        <div class="space-y-2.5">
          <button
            @click="initiatePayment"
            :disabled="processing"
            class="w-full py-3.5 px-4 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-400 hover:from-emerald-400 hover:to-teal-300 text-slate-950 font-extrabold text-sm shadow-xl shadow-emerald-500/25 transition cursor-pointer flex items-center justify-center gap-2 disabled:opacity-50"
          >
            <span v-if="processing" class="w-4 h-4 border-2 border-slate-950 border-t-transparent rounded-full animate-spin"></span>
            <span>Lanjutkan Pembayaran ({{ currentPlanFormattedPrice }})</span>
          </button>
        </div>

        <!-- Trust Badges -->
        <div class="pt-2 border-t border-slate-800/80 flex items-center justify-center gap-3 text-[10px] text-slate-500">
          <span>🔒 Enkripsi SSL 256-bit</span>
          <span>•</span>
          <span>⚡ Aktivasi Otomatis</span>
          <span>•</span>
          <span>🇮🇩 QRIS & Virtual Account Otomatis</span>
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
import { ref, computed, watch, onMounted } from 'vue';
import api from '../../services/api';
import { useAuthStore } from '../../stores/auth';
import { useToastStore } from '../../stores/toast';
import CustomPaymentModal from '../payment/CustomPaymentModal.vue';

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false,
  },
  initialPlan: {
    type: String,
    default: 'monthly',
  },
});

const emit = defineEmits(['close', 'upgraded']);

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
    discount_percentage: 0,
    features: [
      'Pencatatan transaksi tanpa kuota (Unlimited)',
      'Multi-dompet tanpa batasan (BCA, Mandiri, GoPay, OVO, Cash)',
      'Ekspor Laporan Keuangan ke format Excel (.xlsx)',
      'Peringatan Plafon Anggaran (Anti-Boncos)',
    ],
  },
  {
    code: 'annual',
    name: 'Pro 1 Tahun',
    tagline: '365 Hari Penuh',
    duration_days: 365,
    price: 199000,
    formatted_price: 'Rp 199.000',
    badge: 'HEMAT 35%',
    discount_percentage: 35,
    features: [
      'Seluruh Fitur Paket Pro',
      'Masa Aktif 365 Hari Penuh',
      'Hemat Rp 101.000 dibanding bulanan',
      'Prioritas Fitur AI Baru Mendatang',
    ],
  },
]);

async function loadPlans() {
  try {
    const res = await api.get('/plans');
    if (res.data?.success && res.data.data?.length) {
      plans.value = res.data.data;
    }
  } catch (err) {
    // fallback gracefully to initial state
  }
}

onMounted(() => {
  loadPlans();
});

const selectedPlan = ref('monthly');
const processing = ref(false);
const success = ref(false);
const errorMessage = ref('');
const lastCreatedOrderId = ref(null);

// Custom In-App Modal State
const showCustomPayment = ref(false);
const customPaymentOrderId = ref('');
const customPaymentAmount = ref(25000);
const customPaymentPlanName = ref('HematWoy Pro');
const customPaymentIsProduction = ref(false);

const currentPlan = computed(() => plans.value.find(p => p.code === selectedPlan.value) || plans.value[0]);
const currentPlanFormattedPrice = computed(() => currentPlan.value?.formatted_price || 'Rp 25.000');
const currentPlanFeatures = computed(() => currentPlan.value?.features || [
  'Pencatatan transaksi tanpa kuota (Unlimited)',
  'Multi-dompet tanpa batasan (BCA, Mandiri, GoPay, OVO, Cash)',
  'Ekspor Laporan Keuangan ke format Excel (.xlsx)',
  'Peringatan Plafon Anggaran (Anti-Boncos)',
]);

watch(
  () => props.initialPlan,
  (newVal) => {
    if (newVal === 'annual' || newVal === 'monthly') {
      selectedPlan.value = newVal;
    }
  },
  { immediate: true }
);

function close() {
  if (processing.value) return;
  errorMessage.value = '';
  emit('close');
}

function completeAndClose() {
  success.value = false;
  emit('upgraded');
  emit('close');
}

async function initiatePayment() {
  processing.value = true;
  errorMessage.value = '';
  try {
    const res = await api.post('/subscription/create-order', {
      plan: selectedPlan.value,
    });

    const data = res.data;
    lastCreatedOrderId.value = data.order_id;
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
  toastStore.success('Pembayaran sukses! Akun HematWoy Pro aktif.');
  emit('upgraded');
}
</script>
