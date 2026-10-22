<template>
  <div class="min-h-screen bg-slate-950 text-slate-100 font-sans selection:bg-emerald-500 selection:text-white relative overflow-hidden">
    <!-- Ambient Gradients -->
    <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[1000px] h-[500px] bg-gradient-to-b from-emerald-500/15 via-teal-500/5 to-transparent blur-3xl pointer-events-none"></div>
    <div class="absolute top-96 -left-48 w-96 h-96 bg-emerald-600/10 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute top-[1200px] -right-48 w-96 h-96 bg-teal-600/10 rounded-full blur-3xl pointer-events-none"></div>

    <!-- Sticky Navigation Bar (Shared Component) -->
    <PublicNavbar activeRoute="landing" />

    <!-- HERO SECTION -->
    <section class="relative pt-16 sm:pt-24 pb-20 px-4 sm:px-6 lg:px-8 max-w-6xl mx-auto text-center">
      <!-- Top Social Proof Pill -->
      <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-semibold mb-6 animate-pulse">
        <span class="flex h-2 w-2 rounded-full bg-emerald-400"></span>
        <span>Asisten Keuangan Pribadi Otomatis #1 di Indonesia</span>
      </div>

      <!-- Main Headline -->
      <h1 class="text-4xl sm:text-6xl font-black text-white tracking-tight leading-[1.15] max-w-4xl mx-auto">
        Catat Keuangan Harian Semudah
        <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 via-teal-300 to-cyan-400">
          Kirim Pesan Chat
        </span>
      </h1>

      <!-- Subheadline -->
      <p class="mt-6 text-base sm:text-lg text-slate-400 max-w-2xl mx-auto leading-relaxed">
        Bebas ribet instal aplikasi berat. Cukup ketik pengeluaranmu dalam bahasa sehari-hari, pantau mutasi saldo multi-dompet real-time, dan capai target tabungan bebas boncos.
      </p>

      <!-- Primary CTAs -->
      <div class="mt-8 sm:mt-10 max-w-lg mx-auto space-y-3">
        <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
          <a
            :href="whatsappDirectUrl"
            target="_blank"
            class="w-full sm:w-auto px-7 py-3.5 sm:px-8 sm:py-4 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-400 hover:from-emerald-400 hover:to-teal-300 text-slate-950 font-black text-sm sm:text-base shadow-xl shadow-emerald-500/25 hover:scale-102 active:scale-98 transition flex items-center justify-center gap-2 cursor-pointer"
          >
            <span>Mulai Coba Gratis</span>
            <span class="font-mono text-slate-950">➔</span>
          </a>

          <router-link
            to="/dokumentasi"
            class="w-full sm:w-auto px-6 py-3.5 sm:py-4 rounded-xl bg-slate-900 hover:bg-slate-850 border border-slate-800 hover:border-slate-700 text-slate-200 hover:text-white font-bold text-xs sm:text-sm transition flex items-center justify-center gap-2 shadow-md cursor-pointer"
          >
            <span>📖 Panduan & Dokumentasi</span>
            <span class="text-emerald-400">➔</span>
          </router-link>
        </div>

        <!-- Quota reassurance note -->
        <p class="text-xs text-slate-400 text-center font-medium">
          ✨ <span class="text-emerald-400 font-semibold">{{ freeLimit }} Chat Pertama Gratis</span> • Tanpa Registrasi Rumit • Siap Pakai
        </p>
      </div>

      <!-- Trust Badges -->
      <div class="mt-10 sm:mt-12 flex flex-wrap items-center justify-center gap-5 sm:gap-8 text-xs text-slate-500">
        <div class="flex items-center gap-1.5">
          <span class="text-emerald-400 font-bold">✓</span>
          <span>Tanpa Install Aplikasi</span>
        </div>
        <div class="flex items-center gap-1.5">
          <span class="text-emerald-400 font-bold">✓</span>
          <span>WhatsApp Resmi: {{ botNumberDisplay }}</span>
        </div>
        <div class="flex items-center gap-1.5">
          <span class="text-emerald-400 font-bold">✓</span>
          <span>Privasi Finansial Terenkripsi</span>
        </div>
        <div class="flex items-center gap-1.5">
          <span class="text-emerald-400 font-bold">✓</span>
          <span>Ekspor Excel 1-Klik</span>
        </div>
      </div>
    </section>

    <!-- SECTION 2: INTERACTIVE DOCS & PREVIEW BANNER -->
    <section class="py-12 px-4 sm:px-6 lg:px-8 max-w-5xl mx-auto">
      <div class="relative rounded-3xl p-6 sm:p-10 bg-gradient-to-r from-slate-900/95 via-slate-900 to-emerald-950/40 border border-emerald-500/30 shadow-2xl backdrop-blur-xl overflow-hidden flex flex-col md:flex-row items-center justify-between gap-6 sm:gap-8">
        <div class="max-w-xl space-y-3 text-center md:text-left">
          <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/15 border border-emerald-500/30 text-emerald-400 text-xs font-bold">
            <span>⚡</span>
            <span>Panduan Interaktif & Format Perintah</span>
          </div>
          <h2 class="text-xl sm:text-3xl font-black text-white tracking-tight">
            Ingin Coba Perintah Bot Langsung di Browser?
          </h2>
          <p class="text-xs sm:text-sm text-slate-400 leading-relaxed">
            Kunjungi halaman <span class="text-emerald-400 font-semibold">Dokumentasi</span> kami untuk mencoba format chat cerdas WhatsApp secara langsung dan melihat daftar lengkap perintah (keluar, masuk, transfer dompet, budget, hingga ekspor Excel).
          </p>
          <div class="pt-1 flex flex-wrap gap-2 justify-center md:justify-start">
            <span class="text-[11px] px-2.5 py-1 rounded-lg bg-slate-950 border border-slate-800 text-slate-400 font-mono">keluar 25rb kopi</span>
            <span class="text-[11px] px-2.5 py-1 rounded-lg bg-slate-950 border border-slate-800 text-slate-400 font-mono">+1.5jt freelance</span>
            <span class="text-[11px] px-2.5 py-1 rounded-lg bg-slate-950 border border-slate-800 text-slate-400 font-mono">saldo</span>
            <span class="text-[11px] px-2.5 py-1 rounded-lg bg-slate-950 border border-slate-800 text-slate-400 font-mono">budget makan 1jt</span>
            <span class="text-[11px] px-2.5 py-1 rounded-lg bg-slate-950 border border-slate-800 text-slate-400 font-mono">export excel</span>
          </div>
        </div>

        <div class="shrink-0 flex flex-col gap-2.5 w-full md:w-auto min-w-[230px]">
          <router-link
            to="/dokumentasi"
            class="w-full inline-flex items-center justify-center gap-2 px-6 py-3.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-400 hover:from-emerald-400 hover:to-teal-300 text-slate-950 font-black text-xs sm:text-sm shadow-xl shadow-emerald-500/20 hover:scale-102 active:scale-98 transition cursor-pointer text-center"
          >
            <span>📖 Buka Panduan & Dokumentasi</span>
            <span class="font-mono">➔</span>
          </router-link>
          <a
            :href="whatsappDirectUrl"
            target="_blank"
            class="w-full inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-slate-950 hover:bg-slate-900 border border-slate-800 hover:border-slate-700 text-slate-300 hover:text-white font-bold text-xs transition cursor-pointer text-center"
          >
            <span>Mulai Catat Transaksi</span>
          </a>
        </div>
      </div>
    </section>

    <!-- SECTION 3: FEATURES BENTO GRID -->
    <section id="fitur" class="py-20 px-4 sm:px-6 lg:px-8 max-w-6xl mx-auto">
      <div class="text-center mb-16">
        <span class="text-xs font-bold text-emerald-400 uppercase tracking-widest block mb-2">Kenapa HematWoy?</span>
        <h2 class="text-3xl sm:text-4xl font-black text-white tracking-tight">Semua yang Kamu Butuhkan untuk Finansial Bebas Pusing</h2>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <!-- Feature 1: Natural Language -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-6 sm:p-8 backdrop-blur-md hover:border-slate-700 transition">
          <div class="w-12 h-12 rounded-2xl bg-emerald-500/15 text-emerald-400 flex items-center justify-center text-2xl mb-5">
            🧠
          </div>
          <h3 class="text-lg font-bold text-white mb-2">Bahasa Manusiawi Fleksibel</h3>
          <p class="text-xs sm:text-sm text-slate-400 leading-relaxed">
            Mendukung kata gaul sehari-hari: <code class="text-emerald-400">keluar 25rb kopi</code>, <code class="text-emerald-400">+1.5jt freelance</code>, atau pemisah ribuan otomatis.
          </p>
        </div>

        <!-- Feature 2: Multi-Dompet -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-6 sm:p-8 backdrop-blur-md hover:border-slate-700 transition">
          <div class="w-12 h-12 rounded-2xl bg-teal-500/15 text-teal-400 flex items-center justify-center text-2xl mb-5">
            👛
          </div>
          <h3 class="text-lg font-bold text-white mb-2">Multi-Dompet Terintegrasi</h3>
          <p class="text-xs sm:text-sm text-slate-400 leading-relaxed">
            Pisahkan saldo rekening BCA, Mandiri, Cash, hingga e-Wallet (GoPay, OVO). Transfer antar dompet semudah kirim pesan teks.
          </p>
        </div>

        <!-- Feature 3: Anti-Boncos Budget -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-6 sm:p-8 backdrop-blur-md hover:border-slate-700 transition">
          <div class="w-12 h-12 rounded-2xl bg-cyan-500/15 text-cyan-400 flex items-center justify-center text-2xl mb-5">
            🎯
          </div>
          <h3 class="text-lg font-bold text-white mb-2">Plafon Anggaran & Peringatan</h3>
          <p class="text-xs sm:text-sm text-slate-400 leading-relaxed">
            Atur batas budget per kategori (cth: makan 1jt). Bot akan mengingatkan otomatis dengan progress bar sebelum kamu boncos!
          </p>
        </div>

        <!-- Feature 4: 1-Klik Excel -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-6 sm:p-8 backdrop-blur-md hover:border-slate-700 transition">
          <div class="w-12 h-12 rounded-2xl bg-emerald-500/15 text-emerald-400 flex items-center justify-center text-2xl mb-5">
            📊
          </div>
          <h3 class="text-lg font-bold text-white mb-2">Ekspor Excel 1-Klik</h3>
          <p class="text-xs sm:text-sm text-slate-400 leading-relaxed">
            Ketik <code class="text-emerald-400">export excel</code>, file laporan rapi multi-sheet lengkap formula SUM langsung terkirim ke WhatsApp kamu.
          </p>
        </div>

        <!-- Feature 5: AI Insights -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-6 sm:p-8 backdrop-blur-md hover:border-slate-700 transition">
          <div class="w-12 h-12 rounded-2xl bg-teal-500/15 text-teal-400 flex items-center justify-center text-2xl mb-5">
            🤖
          </div>
          <h3 class="text-lg font-bold text-white mb-2">Evaluasi & Rekap Cerdas</h3>
          <p class="text-xs sm:text-sm text-slate-400 leading-relaxed">
            Tanyakan <code class="text-emerald-400">bulan ini boros gak?</code> dan dapatkan analisis perbandingan bulan lalu vs bulan ini secara instan.
          </p>
        </div>

        <!-- Feature 6: Privacy & Security -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-6 sm:p-8 backdrop-blur-md hover:border-slate-700 transition">
          <div class="w-12 h-12 rounded-2xl bg-cyan-500/15 text-cyan-400 flex items-center justify-center text-2xl mb-5">
            🔒
          </div>
          <h3 class="text-lg font-bold text-white mb-2">Enkripsi Standar Perbankan</h3>
          <p class="text-xs sm:text-sm text-slate-400 leading-relaxed">
            Data finansial dilindungi enkripsi AES-256-GCM. Isolasi ketat berbasis nomor WhatsApp, tanpa ada pihak ketiga yang mengintip.
          </p>
        </div>
      </div>
    </section>

    <!-- SECTION 4: PRICING -->
    <section id="harga" class="py-20 px-4 sm:px-6 lg:px-8 max-w-6xl mx-auto">
      <div class="text-center mb-16">
        <span class="text-xs font-bold text-emerald-400 uppercase tracking-widest block mb-2">Pilihan Paket</span>
        <h2 class="text-3xl sm:text-4xl font-black text-white tracking-tight">Investasi Terbaik untuk Mengontrol Keuanganmu</h2>
        <p class="text-xs sm:text-sm text-slate-400 mt-2">Mulai gratis tanpa komitmen. Upgrade kapan saja saat siap lebih rapi.</p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-8 items-stretch">
        <!-- Tier 1: Free -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-6 sm:p-8 flex flex-col justify-between">
          <div>
            <h3 class="text-lg font-bold text-white mb-1">Paket Gratis</h3>
            <p class="text-xs text-slate-400 mb-6">Cocok untuk coba-coba pertama kali</p>
            <div class="text-3xl font-black text-white mb-6">
              Rp 0 <span class="text-xs font-normal text-slate-400">/ selamanya</span>
            </div>

            <ul class="space-y-3 text-xs text-slate-300">
              <li class="flex items-center gap-2">✓ {{ freeLimit }} Kuota Transaksi Chat Gratis</li>
              <li class="flex items-center gap-2">✓ 1 Dompet Kas Utama</li>
              <li class="flex items-center gap-2">✓ Rekap Pengeluaran Harian & Bulanan</li>
              <li class="flex items-center gap-2 text-slate-500">✕ Multi-Dompet Tanpa Batas</li>
              <li class="flex items-center gap-2 text-slate-500">✕ Plafon Budget Kategori</li>
            </ul>
          </div>

          <a
            :href="whatsappDirectUrl"
            target="_blank"
            class="mt-8 w-full py-3.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs text-center transition block cursor-pointer"
          >
            Mulai Coba Gratis
          </a>
        </div>

        <!-- Tier 2: Pro Bulanan (Recommended) -->
        <div class="bg-slate-900 border-2 border-emerald-500 rounded-3xl p-6 sm:p-8 flex flex-col justify-between relative shadow-2xl shadow-emerald-500/10">
          <div v-if="monthlyPlan.badge" class="absolute -top-3.5 left-1/2 -translate-x-1/2 px-3 py-1 bg-gradient-to-r from-emerald-500 to-teal-400 text-slate-950 font-black text-[10px] tracking-wider uppercase rounded-full">
            {{ monthlyPlan.badge }}
          </div>

          <div>
            <h3 class="text-lg font-bold text-white mb-1">{{ monthlyPlan.name }}</h3>
            <p class="text-xs text-slate-400 mb-6">{{ monthlyPlan.tagline || 'Untuk profesional & pencatat rutin' }}</p>
            <div class="text-3xl font-black text-emerald-400 mb-6">
              {{ monthlyPlan.formatted_price }} <span class="text-xs font-normal text-slate-400">/ {{ monthlyPlan.duration_days }} hari</span>
            </div>

            <ul class="space-y-3 text-xs text-slate-300">
              <li v-for="(feat, idx) in monthlyPlan.features" :key="idx" class="flex items-center gap-2">
                <span>⭐</span> <span>{{ feat }}</span>
              </li>
            </ul>
          </div>

          <a
            :href="whatsappDirectUrlWithPlan"
            target="_blank"
            class="mt-8 w-full py-3.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-400 text-slate-950 font-black text-xs text-center shadow-lg shadow-emerald-500/20 hover:scale-105 active:scale-95 transition block cursor-pointer"
          >
            Pilih Paket {{ monthlyPlan.name }}
          </a>
        </div>

        <!-- Tier 3: Pro Tahunan -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-6 sm:p-8 flex flex-col justify-between">
          <div>
            <div v-if="annualPlan.badge" class="inline-block px-2.5 py-0.5 rounded-full bg-teal-500/15 text-teal-400 text-[10px] font-bold mb-2">
              {{ annualPlan.badge }}
            </div>
            <h3 class="text-lg font-bold text-white mb-1">{{ annualPlan.name }}</h3>
            <p class="text-xs text-slate-400 mb-6">{{ annualPlan.tagline || 'Disiplin finansial sepanjang tahun' }}</p>
            <div class="text-3xl font-black text-white mb-6">
              {{ annualPlan.formatted_price }} <span class="text-xs font-normal text-slate-400">/ tahun</span>
            </div>

            <ul class="space-y-3 text-xs text-slate-300">
              <li v-for="(feat, idx) in annualPlan.features" :key="idx" class="flex items-center gap-2">
                <span>✓</span> <span>{{ feat }}</span>
              </li>
            </ul>
          </div>

          <a
            :href="whatsappDirectUrlWithAnnualPlan"
            target="_blank"
            class="mt-8 w-full py-3.5 rounded-xl bg-gradient-to-r from-teal-400 to-emerald-400 text-slate-950 font-black text-xs text-center shadow-lg shadow-teal-500/20 hover:scale-105 active:scale-95 transition block cursor-pointer"
          >
            Pilih Paket {{ annualPlan.name }}
          </a>
        </div>
      </div>
    </section>

    <!-- SECTION 5: FAQ -->
    <section id="faq" class="py-16 px-4 sm:px-6 lg:px-8 max-w-3xl mx-auto">
      <div class="text-center mb-12">
        <h2 class="text-2xl sm:text-3xl font-black text-white tracking-tight">Pertanyaan yang Sering Diajukan</h2>
      </div>

      <div class="space-y-4">
        <div
          v-for="(faq, i) in faqs"
          :key="i"
          class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 cursor-pointer"
          @click="faq.open = !faq.open"
        >
          <div class="flex items-center justify-between font-bold text-sm text-white">
            <span>{{ faq.q }}</span>
            <span class="text-emerald-400 text-lg">{{ faq.open ? '−' : '+' }}</span>
          </div>
          <p v-if="faq.open" class="mt-3 text-xs sm:text-sm text-slate-400 leading-relaxed border-t border-slate-800 pt-3">
            {{ typeof faq.a === 'function' ? faq.a() : faq.a }}
          </p>
        </div>
      </div>
    </section>

    <!-- FINAL CTA BANNER -->
    <section class="py-16 px-4 sm:px-6 lg:px-8 max-w-5xl mx-auto text-center">
      <div class="bg-gradient-to-r from-emerald-950/60 via-slate-900 to-teal-950/60 border border-emerald-500/30 rounded-3xl p-8 sm:p-14 shadow-2xl relative overflow-hidden">
        <h2 class="text-2xl sm:text-4xl font-black text-white tracking-tight mb-4">
          Siap Mengatur Keuangan Lebih Rapi Tanpa Beban?
        </h2>
        <p class="text-xs sm:text-sm text-slate-300 max-w-xl mx-auto mb-8">
          Mulai dalam 10 detik. Tanpa isi form panjang, tanpa perlu kartu kredit. Langsung hubungi bot resmi kami di {{ botNumberDisplay }}!
        </p>

        <a
          :href="whatsappDirectUrl"
          target="_blank"
          class="inline-flex items-center gap-2.5 px-8 py-4 rounded-2xl bg-gradient-to-r from-emerald-500 to-teal-400 text-slate-950 font-black text-base shadow-xl shadow-emerald-500/25 hover:scale-105 transition"
        >
          <span>Mulai Coba Gratis Sekarang</span>
          <span class="text-slate-950 font-bold">➔</span>
        </a>
      </div>
    </section>

    <!-- FOOTER -->
    <footer class="border-t border-slate-900 bg-slate-950/90 py-10 px-4 text-center text-xs text-slate-500 space-y-3">
      <div class="flex items-center justify-center gap-2 mb-1">
        <div class="w-7 h-7 rounded-lg overflow-hidden bg-slate-900 border border-emerald-500/30 p-0.5 flex items-center justify-center">
          <img src="/images/logo.png" alt="HematWoy Logo" class="w-full h-full object-contain" />
        </div>
        <span class="font-bold text-slate-300 text-sm">HematWoy</span>
      </div>
      <div class="flex items-center justify-center gap-4 text-xs text-slate-400">
        <router-link to="/" class="hover:text-emerald-400 transition">Beranda</router-link>
        <span>•</span>
        <router-link to="/dokumentasi" class="hover:text-emerald-400 transition">Panduan</router-link>
        <span>•</span>
        <router-link to="/privasi" class="hover:text-emerald-400 transition">Kebijakan Privasi</router-link>
      </div>
      <p class="font-medium text-slate-400">Smart WhatsApp Personal Finance Tracker</p>
      <p>&copy; {{ currentYear }} HematWoy Indonesia. Seluruh hak cipta dilindungi undang-undang.</p>
    </footer>

    <!-- Dashboard Paywall & WhatsApp Trial Modal -->
    <DashboardPaywallModal
      :isOpen="showPaywallModal"
      :botRawNumber="botRawNumber"
      :freeLimit="freeLimit"
      @close="showPaywallModal = false"
      @upgraded="onSubscriptionUpgraded"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { useAuthStore } from '../stores/auth';
import PublicNavbar from '../components/layout/PublicNavbar.vue';
import DashboardPaywallModal from '../components/modals/DashboardPaywallModal.vue';

const router = useRouter();
const authStore = useAuthStore();
const showPaywallModal = ref(false);

function handleDashboardClick() {
  if (authStore.isAuthenticated && authStore.isPro) {
    router.push('/dashboard');
    return;
  }
  showPaywallModal.value = true;
}

function onSubscriptionUpgraded() {
  if (authStore.isAuthenticated) {
    authStore.fetchProfile();
    authStore.fetchSubscription();
  }
}

const currentYear = new Date().getFullYear();

// WhatsApp Bot Number from .env with fallback
const botRawNumber = (typeof window !== 'undefined' && window.__BOT_CONFIG__?.bot_number) || import.meta.env.VITE_WHATSAPP_BOT_NUMBER || '6285111355992';

const botNumberDisplay = computed(() => {
  const clean = String(botRawNumber).replace(/\D/g, '');
  if (clean.startsWith('62')) {
    return `+62 ${clean.slice(2, 5)}-${clean.slice(5, 9)}-${clean.slice(9)}`;
  }
  if (clean.startsWith('1') && clean.length === 11) {
    return `+1 (${clean.slice(1, 4)}) ${clean.slice(4, 7)}-${clean.slice(7)}`;
  }
  return `+${clean}`;
});

const plans = ref([
  {
    code: 'monthly',
    name: 'Pro Bulanan',
    tagline: 'Untuk profesional & pencatat rutin',
    duration_days: 30,
    price: 25000,
    formatted_price: 'Rp 25.000',
    badge: 'Paling Populer',
    features: [
      'Unlimited Pencatatan Transaksi',
      'Multi-Dompet (BCA, Mandiri, Cash, e-Wallet)',
      'Plafon Anggaran & Anti-Boncos Alert',
      '1-Klik Ekspor Laporan Excel Lengkap',
      'AI Financial Spending Insights',
      'Akses Penuh Web App Dashboard',
    ],
  },
  {
    code: 'annual',
    name: 'Pro 1 Tahun',
    tagline: 'Disiplin finansial sepanjang tahun',
    duration_days: 365,
    price: 199000,
    formatted_price: 'Rp 199.000',
    badge: 'HEMAT 35%',
    features: [
      'Seluruh Fitur Paket Pro',
      'Masa Aktif 365 Hari Penuh',
      'Hemat Rp 101.000 dibanding bulanan',
      'Prioritas Fitur AI Baru Mendatang',
    ],
  },
]);

const monthlyPlan = computed(() => plans.value.find(p => p.code === 'monthly') || plans.value[0]);
const annualPlan = computed(() => plans.value.find(p => p.code === 'annual') || plans.value[1] || plans.value[0]);

const whatsappDirectUrl = computed(() => {
  return `https://wa.me/${botRawNumber}?text=Halo%20HematWoy,%20saya%20mau%20coba%20catat%20keuangan!`;
});

const whatsappDirectUrlWithPlan = computed(() => {
  const p = monthlyPlan.value;
  const planText = encodeURIComponent(`Halo HematWoy, saya mau langganan ${p.name} (${p.formatted_price})!`);
  return `https://wa.me/${botRawNumber}?text=${planText}`;
});

const whatsappDirectUrlWithAnnualPlan = computed(() => {
  const p = annualPlan.value;
  const planText = encodeURIComponent(`Halo HematWoy, saya mau langganan ${p.name} (${p.formatted_price})!`);
  return `https://wa.me/${botRawNumber}?text=${planText}`;
});

const freeLimit = ref(typeof window !== 'undefined' && window.__BOT_CONFIG__?.free_limit ? window.__BOT_CONFIG__.free_limit : 100);

async function fetchDynamicData() {
  try {
    const plansRes = await axios.get('/api/v1/plans');

    if (plansRes.status === 200 && plansRes.data?.success) {
      if (plansRes.data.data?.length) {
        plans.value = plansRes.data.data;
      }
      if (plansRes.data.free_limit) {
        freeLimit.value = plansRes.data.free_limit;
        const quotaFaq = faqs.value.find(f => f.q.includes('batas penggunaan gratis'));
        if (quotaFaq) {
          quotaFaq.a = `Di WhatsApp, kamu mendapatkan ${freeLimit.value} kuota pencatatan percakapan finansial secara gratis! Setelah itu, kamu bisa berlangganan Paket Pro mulai Rp25.000/bulan.`;
        }
      }
    }
  } catch (err) {
    // fallback gracefully
  }
}

onMounted(() => {
  fetchDynamicData();
  if (authStore.isAuthenticated) {
    authStore.fetchProfile();
    authStore.fetchSubscription();
  }
});


const faqs = ref([
  {
    q: 'Bagaimana cara mulai menggunakan bot ini?',
    a: 'Cukup klik tombol "Chat WhatsApp" di atas atau simpan nomor bot WhatsApp kami di +1 (555) 671-1016. Langsung ketik pengeluaranmu, tanpa registrasi yang rumit!',
    open: false,
  },
  {
    q: 'Apakah data keuangan saya aman?',
    a: 'Sangat aman. Setiap data nominal terenkripsi menggunakan AES-256-GCM. Kami mengisolasi database per nomor WhatsApp dan tidak pernah membagikan data kepada pihak ketiga.',
    open: false,
  },
  {
    q: 'Berapa batas penggunaan gratis di WhatsApp?',
    a: () => `Di WhatsApp, kamu mendapatkan ${freeLimit.value} kuota pencatatan percakapan finansial secara gratis! Setelah itu, kamu bisa berlangganan Paket Pro mulai Rp25.000/bulan.`,
    open: false,
  },
  {
    q: 'Metode pembayaran apa saja yang diterima untuk paket Pro?',
    a: 'Kami mendukung pembayaran instan otomatis: QRIS (GoPay, OVO, Dana, ShopeePay, BCA Mobile), Transfer Bank Virtual Account (BCA, Mandiri, BNI, BRI), dan Alfamart/Indomaret.',
    open: false,
  },
]);
</script>
