<template>
  <div class="min-h-screen bg-slate-950 text-slate-100 flex flex-col justify-between relative overflow-hidden font-sans">
    <!-- Ambient glowing backgrounds -->
    <div class="absolute -top-40 -left-40 w-96 h-96 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-teal-500/10 rounded-full blur-3xl pointer-events-none"></div>

    <!-- Header Navigation -->
    <header class="w-full border-b border-slate-800/80 bg-slate-900/50 backdrop-blur-md sticky top-0 z-50">
      <div class="max-w-4xl mx-auto px-4 py-3.5 flex items-center justify-between">
        <router-link to="/" class="flex items-center gap-2.5">
          <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-emerald-500 to-teal-400 flex items-center justify-center text-slate-950 font-black text-sm shadow-md shadow-emerald-500/20">
            HW
          </div>
          <span class="font-extrabold text-base tracking-tight text-white">HematWoy <span class="text-emerald-400 font-semibold text-xs ml-1 bg-emerald-500/15 px-2 py-0.5 rounded-full border border-emerald-500/30">Checkout</span></span>
        </router-link>

        <router-link to="/" class="text-xs text-slate-400 hover:text-white transition flex items-center gap-1.5">
          <span>&larr; Kembali ke Beranda</span>
        </router-link>
      </div>
    </header>

    <!-- Main Content Container -->
    <main class="flex-1 max-w-xl w-full mx-auto p-4 sm:p-6 my-auto relative z-10">
      <!-- Loading State -->
      <div v-if="loading" class="bg-slate-900/80 border border-slate-800 rounded-3xl p-10 text-center backdrop-blur-xl">
        <div class="inline-block w-8 h-8 border-3 border-emerald-500 border-t-transparent rounded-full animate-spin mb-4"></div>
        <p class="text-sm text-slate-300 font-medium">Memuat rincian pesanan...</p>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="bg-slate-900/90 border border-rose-500/30 rounded-3xl p-8 text-center backdrop-blur-xl">
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
      <div v-else-if="order && order.status === 'SETTLEMENT'" class="bg-slate-900/90 border border-emerald-500/40 rounded-3xl p-8 sm:p-10 text-center backdrop-blur-xl shadow-2xl shadow-emerald-500/10">
        <div class="w-16 h-16 bg-emerald-500/20 text-emerald-400 rounded-3xl flex items-center justify-center mx-auto mb-5 text-3xl shadow-lg shadow-emerald-500/20">
          🎉
        </div>
        <div class="inline-block px-3 py-1 bg-emerald-500/15 border border-emerald-500/30 rounded-full text-emerald-400 text-xs font-bold mb-3">
          PEMBAYARAN BERHASIL
        </div>
        <h2 class="text-2xl font-black text-white tracking-tight mb-2">Selamat! Akun Pro Aktif</h2>
        <p class="text-xs sm:text-sm text-slate-400 max-w-md mx-auto mb-6">
          Langganan <strong class="text-slate-200">{{ order.plan_name }}</strong> kamu telah berhasil diaktifkan. Sekarang kamu bisa mencatat keuangan tanpa batas di WhatsApp!
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
            <span class="text-slate-200 uppercase">{{ order.payment_type || 'Instant QRIS' }}</span>
          </div>
        </div>

        <div class="flex flex-col sm:flex-row gap-3">
          <a
            :href="whatsappUrl"
            target="_blank"
            class="flex-1 py-3 px-4 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-400 text-slate-950 font-bold text-xs sm:text-sm hover:opacity-95 transition shadow-lg shadow-emerald-500/20 text-center"
          >
            💬 Buka WhatsApp & Mulai Catat
          </a>
          <router-link
            to="/dashboard"
            class="flex-1 py-3 px-4 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-semibold text-xs sm:text-sm transition text-center"
          >
            Buka Web Dashboard
          </router-link>
        </div>
      </div>

      <!-- Pending Checkout Card -->
      <div v-else-if="order" class="bg-slate-900/90 border border-slate-800 rounded-3xl p-6 sm:p-8 backdrop-blur-xl shadow-2xl">
        <!-- Plan Badge & Title -->
        <div class="flex items-center justify-between pb-5 border-b border-slate-800">
          <div>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-emerald-500/15 border border-emerald-500/30 text-emerald-400 text-[11px] font-bold uppercase tracking-wider mb-1.5">
              ⭐ Langganan Resmi
            </span>
            <h1 class="text-xl font-black text-white tracking-tight">{{ order.plan_name }}</h1>
          </div>
          <div class="text-right">
            <span class="text-[10px] text-slate-400 block uppercase font-medium">Total Tagihan</span>
            <span class="text-xl sm:text-2xl font-black text-emerald-400">
              Rp {{ formatNumber(order.gross_amount) }}
            </span>
          </div>
        </div>

        <!-- Benefits checklist -->
        <div class="py-4 space-y-2.5 border-b border-slate-800/80">
          <div class="flex items-center gap-2.5 text-xs text-slate-300">
            <span class="text-emerald-400 font-bold">✓</span>
            <span>Pencatatan transaksi tanpa batas kuota (Unlimited)</span>
          </div>
          <div class="flex items-center gap-2.5 text-xs text-slate-300">
            <span class="text-emerald-400 font-bold">✓</span>
            <span>Kelola Multi-Dompet (BCA, Mandiri, Cash, GoPay, OVO)</span>
          </div>
          <div class="flex items-center gap-2.5 text-xs text-slate-300">
            <span class="text-emerald-400 font-bold">✓</span>
            <span>1-Klik Ekspor Laporan Excel Lengkap (.xlsx)</span>
          </div>
          <div class="flex items-center gap-2.5 text-xs text-slate-300">
            <span class="text-emerald-400 font-bold">✓</span>
            <span>Peringatan Plafon Anggaran Anti-Boncos Otomatis</span>
          </div>
        </div>

        <!-- Order Metadata -->
        <div class="py-4 text-xs text-slate-400 space-y-1.5">
          <div class="flex justify-between">
            <span>Nomor Pesanan:</span>
            <span class="font-mono text-slate-300 font-medium">{{ order.order_id }}</span>
          </div>
          <div class="flex justify-between">
            <span>WhatsApp Tertaut:</span>
            <span class="font-medium text-slate-300">{{ formatPhone(order.user_jid) }}</span>
          </div>
        </div>

        <!-- Checkout Buttons -->
        <div class="pt-3 space-y-3">
          <!-- Primary Midtrans Snap Button (If Snap Token Available) -->
          <button
            v-if="order.snap_token"
            @click="openSnapPopup"
            class="w-full py-3.5 px-4 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-400 hover:from-emerald-400 hover:to-teal-300 text-slate-950 font-bold text-sm shadow-xl shadow-emerald-500/25 transition cursor-pointer flex items-center justify-center gap-2"
          >
            <span>💳 Bayar Sekarang via Midtrans</span>
          </button>

          <!-- Direct Payment Redirect URL (Fallback) -->
          <a
            v-else-if="order.payment_url && order.payment_url.includes('midtrans.com')"
            :href="order.payment_url"
            class="w-full py-3.5 px-4 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-400 hover:from-emerald-400 hover:to-teal-300 text-slate-950 font-bold text-sm shadow-xl shadow-emerald-500/25 transition flex items-center justify-center gap-2"
          >
            <span>💳 Buka Halaman Pembayaran Midtrans</span>
          </a>

          <!-- Instant Simulation Button for Sandbox/Demo Testing -->
          <div class="pt-2 border-t border-slate-800">
            <div class="flex items-center justify-between mb-2">
              <span class="text-[11px] text-slate-400">Metode Pengujian / Sandbox:</span>
              <span class="text-[10px] bg-slate-800 px-2 py-0.5 rounded text-slate-300">Dev & Demo</span>
            </div>
            <button
              @click="simulatePayment"
              :disabled="simulating"
              class="w-full py-2.5 px-4 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 font-semibold text-xs transition cursor-pointer flex items-center justify-center gap-2 disabled:opacity-50"
            >
              <span v-if="simulating" class="w-3.5 h-3.5 border-2 border-slate-200 border-t-transparent rounded-full animate-spin"></span>
              <span>⚡ Konfirmasi Pembayaran Instan (Simulasi QRIS)</span>
            </button>
          </div>
        </div>

        <!-- Trust Badges -->
        <div class="mt-6 pt-4 border-t border-slate-800/80 flex items-center justify-center gap-4 text-[11px] text-slate-500">
          <span class="flex items-center gap-1">🔒 256-Bit SSL Enkripsi</span>
          <span>•</span>
          <span>⚡ Aktivasi Otomatis</span>
          <span>•</span>
          <span>🇮🇩 QRIS / VA Resmi</span>
        </div>
      </div>
    </main>

    <!-- Footer -->
    <footer class="w-full py-4 text-center text-xs text-slate-500 border-t border-slate-900">
      &copy; {{ currentYear }} HematWoy. Seluruh hak cipta dilindungi.
    </footer>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';

const route = useRoute();
const orderId = route.params.orderId;

const order = ref(null);
const loading = ref(true);
const error = ref('');
const simulating = ref(false);

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

    // If client key exists and snap token is available, load Snap JS
    if (order.value.snap_token && order.value.client_key) {
      loadSnapJs(order.value.client_key, order.value.is_production);
    }
  } catch (err) {
    error.value = err.response?.data?.error || 'Gagal memuat detail pesanan.';
  } finally {
    loading.value = false;
  }
}

function loadSnapJs(clientKey, isProduction) {
  if (document.getElementById('midtrans-snap-script')) return;

  const script = document.createElement('script');
  script.id = 'midtrans-snap-script';
  script.src = isProduction
    ? 'https://app.midtrans.com/snap/snap.js'
    : 'https://app.sandbox.midtrans.com/snap/snap.js';
  script.setAttribute('data-client-key', clientKey);
  document.head.appendChild(script);
}

function openSnapPopup() {
  if (window.snap && order.value?.snap_token) {
    window.snap.pay(order.value.snap_token, {
      onSuccess: function (result) {
        order.value.status = 'SETTLEMENT';
      },
      onPending: function (result) {
        fetchOrder();
      },
      onError: function (result) {
        alert('Pembayaran gagal atau dibatalkan.');
      },
      onClose: function () {
        fetchOrder();
      },
    });
  } else if (order.value?.payment_url) {
    window.location.href = order.value.payment_url;
  }
}

async function simulatePayment() {
  simulating.value = true;
  try {
    const res = await axios.post(`/api/v1/payment/simulate/${orderId}`);
    order.value = res.data.order;
  } catch (err) {
    alert(err.response?.data?.error || 'Gagal melakukan simulasi pembayaran.');
  } finally {
    simulating.value = false;
  }
}

onMounted(() => {
  fetchOrder();
});
</script>
