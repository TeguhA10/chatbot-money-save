<template>
  <div class="min-h-screen bg-slate-950 text-slate-100 flex flex-col justify-between relative overflow-hidden font-sans selection:bg-emerald-500 selection:text-slate-950">
    <!-- Ambient glowing backgrounds -->
    <div class="absolute -top-40 -left-40 w-96 h-96 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-teal-500/10 rounded-full blur-3xl pointer-events-none"></div>

    <!-- Header Navigation -->
    <header class="w-full border-b border-slate-800/80 bg-slate-900/60 backdrop-blur-md sticky top-0 z-40">
      <div class="max-w-6xl mx-auto px-4 py-3 sm:py-3.5 flex items-center justify-between">
        <router-link to="/" class="flex items-center gap-2.5">
          <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-slate-900 border border-emerald-500/40 flex items-center justify-center p-1 shadow-md shadow-emerald-500/20">
            <img src="/images/logo.png" alt="HematWoy Logo" class="w-full h-full object-contain" />
          </div>
          <span class="font-extrabold text-sm sm:text-base tracking-tight text-white flex items-center gap-1.5">
            <span>HematWoy</span>
            <span class="text-emerald-400 font-semibold text-[11px] bg-emerald-500/15 px-2 py-0.5 rounded-full border border-emerald-500/30">
              Checkout
            </span>
          </span>
        </router-link>

        <div class="flex items-center gap-3">
          <div class="hidden sm:flex items-center gap-1.5 text-slate-400 text-xs">
            <span>🔒</span>
            <span>Enkripsi SSL 256-Bit</span>
          </div>
          <router-link to="/" class="text-xs text-slate-400 hover:text-white transition flex items-center gap-1">
            <span>&larr; Beranda</span>
          </router-link>
        </div>
      </div>
    </header>

    <!-- Main Content Container -->
    <main class="flex-1 w-full max-w-6xl mx-auto p-4 sm:p-6 lg:p-8 my-auto relative z-10">
      <!-- Loading State -->
      <div v-if="loading" class="bg-slate-900/80 border border-slate-800 rounded-3xl p-10 text-center backdrop-blur-xl max-w-md mx-auto my-12">
        <div class="inline-block w-8 h-8 border-3 border-emerald-500 border-t-transparent rounded-full animate-spin mb-4"></div>
        <p class="text-sm text-slate-300 font-medium">Memuat rincian pesanan...</p>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="bg-slate-900/90 border border-rose-500/30 rounded-3xl p-8 text-center backdrop-blur-xl max-w-md mx-auto my-12">
        <div class="w-14 h-14 bg-rose-500/20 text-rose-400 rounded-2xl flex items-center justify-center mx-auto mb-4 text-2xl">
          ⚠️
        </div>
        <h2 class="text-lg font-bold text-white mb-2">Pesanan Tidak Ditemukan</h2>
        <p class="text-xs text-slate-400 mb-6">{{ error }}</p>
        <router-link to="/" class="inline-block px-5 py-2.5 rounded-xl bg-slate-800 text-white text-xs font-semibold hover:bg-slate-700 transition">
          Kembali ke Beranda
        </router-link>
      </div>

      <!-- Payment Success State -->
      <div v-else-if="order && order.status === 'SETTLEMENT'" class="bg-slate-900/90 border border-emerald-500/40 rounded-3xl p-6 sm:p-10 text-center backdrop-blur-xl shadow-2xl shadow-emerald-500/10 max-w-lg mx-auto my-6 animate-in fade-in zoom-in-95">
        <div class="w-16 h-16 bg-emerald-500/20 text-emerald-400 rounded-3xl flex items-center justify-center mx-auto mb-4 text-3xl shadow-lg shadow-emerald-500/20 border border-emerald-500/40">
          🎉
        </div>
        <div class="inline-block px-3 py-1 bg-emerald-500/15 border border-emerald-500/30 rounded-full text-emerald-400 text-xs font-bold mb-3">
          PEMBAYARAN BERHASIL
        </div>
        <h2 class="text-2xl sm:text-3xl font-black text-white tracking-tight mb-2">Selamat! Akun Pro Aktif</h2>
        <p class="text-xs sm:text-sm text-slate-300 max-w-md mx-auto mb-6 leading-relaxed">
          Langganan <strong class="text-white">{{ order.plan_name }}</strong> kamu telah berhasil diaktifkan. Sekarang kamu bisa mencatat keuangan tanpa batas kuota di WhatsApp!
        </p>

        <div class="bg-slate-950/80 border border-slate-800/80 rounded-2xl p-4 text-left space-y-2 mb-6 text-xs">
          <div class="flex justify-between text-slate-400">
            <span>Nomor Pesanan:</span>
            <span class="font-mono text-slate-200">{{ order.order_id }}</span>
          </div>
          <div class="flex justify-between text-slate-400">
            <span>Total Dibayar:</span>
            <span class="font-bold text-emerald-400">Rp {{ formatNumber(order.gross_amount) }}</span>
          </div>
          <div class="flex justify-between text-slate-400">
            <span>Metode Bayar:</span>
            <span class="text-slate-200 uppercase font-semibold">{{ order.payment_type || 'Instant QRIS' }}</span>
          </div>
        </div>

        <div class="flex flex-col sm:flex-row gap-3">
          <a
            :href="whatsappUrl"
            target="_blank"
            class="flex-1 py-3.5 px-4 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-400 text-slate-950 font-black text-xs sm:text-sm hover:opacity-95 transition shadow-lg shadow-emerald-500/20 text-center flex items-center justify-center gap-2"
          >
            <span>💬 Buka WhatsApp & Mulai Catat</span>
          </a>
          <router-link
            to="/dashboard"
            class="flex-1 py-3.5 px-4 rounded-xl bg-slate-800 hover:bg-slate-750 text-white font-bold text-xs sm:text-sm transition text-center flex items-center justify-center gap-1.5"
          >
            <span>Buka Web Dashboard</span>
          </router-link>
        </div>
      </div>

      <!-- Pending Checkout Layout: Responsive 2-Col on Desktop, Smooth Single-Col on Mobile -->
      <div v-else-if="order" class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8 items-start">
        
        <!-- LEFT COLUMN: Order Summary & Benefits (Sticky on Desktop) -->
        <div class="lg:col-span-5 space-y-4 lg:sticky lg:top-20">
          <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-5 sm:p-6 backdrop-blur-xl shadow-xl space-y-5">
            <!-- Plan Badge & Title -->
            <div class="flex items-center justify-between pb-4 border-b border-slate-800">
              <div>
                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-emerald-500/15 border border-emerald-500/30 text-emerald-400 text-[10px] font-bold uppercase tracking-wider mb-1.5">
                  ⭐ Langganan Resmi
                </span>
                <h1 class="text-lg sm:text-xl font-black text-white tracking-tight">{{ order.plan_name }}</h1>
              </div>
              <div class="text-right">
                <span class="text-[10px] text-slate-400 block uppercase font-medium">Total Tagihan</span>
                <span class="text-xl sm:text-2xl font-black text-emerald-400">
                  Rp {{ formatNumber(order.gross_amount) }}
                </span>
              </div>
            </div>

            <!-- Benefits Checklist -->
            <div class="space-y-2.5 text-xs text-slate-300">
              <div class="flex items-start gap-2.5">
                <span class="text-emerald-400 font-bold shrink-0 mt-0.5">✓</span>
                <span>Pencatatan transaksi tanpa batas kuota (Unlimited)</span>
              </div>
              <div class="flex items-start gap-2.5">
                <span class="text-emerald-400 font-bold shrink-0 mt-0.5">✓</span>
                <span>Kelola Multi-Dompet (BCA, Mandiri, Cash, GoPay, OVO)</span>
              </div>
              <div class="flex items-start gap-2.5">
                <span class="text-emerald-400 font-bold shrink-0 mt-0.5">✓</span>
                <span>1-Klik Ekspor Laporan Excel Lengkap (.xlsx)</span>
              </div>
              <div class="flex items-start gap-2.5">
                <span class="text-emerald-400 font-bold shrink-0 mt-0.5">✓</span>
                <span>Peringatan Plafon Anggaran Anti-Boncos Otomatis</span>
              </div>
            </div>

            <!-- Order Metadata -->
            <div class="p-3 bg-slate-950/70 border border-slate-800/80 rounded-2xl text-xs text-slate-400 space-y-1.5">
              <div class="flex justify-between items-center">
                <span>Nomor Pesanan:</span>
                <span class="font-mono text-slate-200 font-semibold">{{ order.order_id }}</span>
              </div>
              <div class="flex justify-between items-center">
                <span>WhatsApp Tertaut:</span>
                <span class="font-medium text-emerald-400">{{ formatPhone(order.user_jid) }}</span>
              </div>
            </div>

            <!-- Security Badges -->
            <div class="pt-2 border-t border-slate-800/80 flex items-center justify-center gap-4 text-[10px] text-slate-500">
              <span class="flex items-center gap-1">🔒 SSL 256-Bit</span>
              <span>•</span>
              <span>⚡ Aktivasi Otomatis</span>
              <span>•</span>
              <span>🇮🇩 Bank Indonesia QRIS</span>
            </div>
          </div>
        </div>

        <!-- RIGHT COLUMN: Embedded Native Payment Panel (No popup needed!) -->
        <div class="lg:col-span-7">
          <CustomPaymentModal
            :embedded="true"
            :orderId="order.order_id"
            :grossAmount="order.gross_amount"
            :planName="order.plan_name"
            :isProduction="order.is_production"
            :enabledChannels="order.enabled_channels"
            @settled="onPaymentSettled"
          />
        </div>
      </div>
    </main>

    <!-- Footer -->
    <footer class="w-full py-4 text-center text-xs text-slate-500 border-t border-slate-900 mt-auto">
      &copy; {{ currentYear }} HematWoy. Seluruh hak cipta dilindungi.
    </footer>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import CustomPaymentModal from '../components/payment/CustomPaymentModal.vue';

const route = useRoute();
const orderId = route.params.orderId;

const order = ref(null);
const loading = ref(true);
const error = ref('');

const currentYear = new Date().getFullYear();

const whatsappUrl = computed(() => {
  return 'https://wa.me/?text=Halo%20HematWoy,%20saya%20sudah%20upgrade%20ke%20Pro!';
});

function formatNumber(num) {
  return new Intl.NumberFormat('id-ID').format(num || 0);
}

function formatPhone(jid) {
  if (!jid) return '-';
  const clean = jid.replace('@s.whatsapp.net', '').replace('@lid', '');
  return clean.startsWith('62') ? '+' + clean : clean;
}

async function fetchOrder() {
  loading.value = true;
  error.value = '';
  try {
    const res = await axios.get(`/api/v1/payment/order/${orderId}`);
    order.value = res.data;
  } catch (err) {
    error.value = err.response?.data?.error || 'Gagal memuat detail pesanan.';
  } finally {
    loading.value = false;
  }
}

function onPaymentSettled() {
  if (order.value) {
    order.value.status = 'SETTLEMENT';
  }
  fetchOrder();
}

onMounted(() => {
  fetchOrder();
});
</script>
