<template>
  <div class="min-h-screen bg-slate-950 text-slate-100 flex flex-col justify-between selection:bg-emerald-500 selection:text-slate-950">
    <!-- Navbar -->
    <PublicNavbar activeRoute="privacy" />

    <main class="flex-1 max-w-4xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
      <!-- Breadcrumb & Header -->
      <div class="space-y-4 mb-10 text-center sm:text-left">
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-semibold">
          <span>🛡️ Perlindungan Privasi & Keamanan Data</span>
        </div>
        <h1 class="text-3xl sm:text-4xl font-black text-white tracking-tight">
          Kebijakan Privasi HematWoy
        </h1>
        <p class="text-sm text-slate-400 leading-relaxed max-w-2xl">
          Kami berkomitmen menjaga privasi dan integritas data finansial Anda. Dokumen ini menjelaskan bagaimana kami mengenkripsi, mengelola, dan memberikan hak penuh atas data Anda.
        </p>
        <p class="text-[11px] text-slate-500">
          Terakhir diperbarui: 22 Oktober 2026 • Berlaku efektif untuk seluruh pengguna WhatsApp Bot & Web Dashboard.
        </p>
      </div>

      <!-- Quick Summary Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-12">
        <div class="p-4 rounded-2xl bg-slate-900/80 border border-slate-800">
          <span class="text-2xl block mb-2">🔒</span>
          <h3 class="font-bold text-white text-sm mb-1">Enkripsi AES-256-GCM</h3>
          <p class="text-xs text-slate-400 leading-relaxed">
            Data nominal keuangan Anda dienkripsi secara terotentikasi di tingkat penyimpanan database.
          </p>
        </div>
        <div class="p-4 rounded-2xl bg-slate-900/80 border border-slate-800">
          <span class="text-2xl block mb-2">🚫</span>
          <h3 class="font-bold text-white text-sm mb-1">Bebas Pihak Ketiga</h3>
          <p class="text-xs text-slate-400 leading-relaxed">
            Kami tidak pernah menjual, menyewakan, atau membagikan riwayat transaksi Anda ke pihak periklanan manapun.
          </p>
        </div>
        <div class="p-4 rounded-2xl bg-slate-900/80 border border-slate-800">
          <span class="text-2xl block mb-2">🗑️</span>
          <h3 class="font-bold text-white text-sm mb-1">Hak Hapus Data Penuh</h3>
          <p class="text-xs text-slate-400 leading-relaxed">
            Anda dapat menghapus seluruh riwayat transaksi dan akun Anda secara permanen kapan saja.
          </p>
        </div>
      </div>

      <!-- Detail Policy Sections -->
      <div class="space-y-8 text-xs sm:text-sm text-slate-300 leading-relaxed">
        <!-- Section 1 -->
        <section class="p-6 rounded-3xl bg-slate-900/50 border border-slate-800/80 space-y-3">
          <h2 class="text-base sm:text-lg font-bold text-white flex items-center gap-2">
            <span>1. Informasi yang Kami Kumpulkan</span>
          </h2>
          <p>
            Untuk menjalankan fungsi pencatatan keuangan otomatis, HematWoy mengumpulkan informasi terbatas berupa:
          </p>
          <ul class="list-disc pl-5 space-y-1 text-slate-400">
            <li><strong>Nomor Ponsel (WhatsApp JID):</strong> Digunakan sebagai pengenal akun tunggal yang aman untuk otentikasi login OTP dan pengiriman rekap finansial.</li>
            <li><strong>Data Transaksi yang Anda Kirim:</strong> Nominal pengeluaran/pemasukan, kategori, nama dompet/bank, dan catatan singkat yang Anda input via pesan chat atau web.</li>
            <li><strong>Status Langganan & Transaksi Pembayaran:</strong> ID pesanan Midtrans, jenis paket langganan, dan masa berlaku aktif akun Pro Anda. Kami tidak pernah menyimpan nomor kartu kredit atau detail perbankan sensitif Anda.</li>
          </ul>
        </section>

        <!-- Section 2 -->
        <section class="p-6 rounded-3xl bg-slate-900/50 border border-slate-800/80 space-y-3">
          <h2 class="text-base sm:text-lg font-bold text-white flex items-center gap-2">
            <span>2. Bagaimana Data Finansial Dilindungi</span>
          </h2>
          <p>
            Keamanan finansial Anda adalah prioritas mutlak kami. Kami menerapkan standar perlindungan berlapis:
          </p>
          <ul class="list-disc pl-5 space-y-1 text-slate-400">
            <li><strong>Enkripsi Tingkat Server:</strong> Setiap nominal transaksi disimpan dalam bentuk terenkripsi AES-256-GCM.</li>
            <li><strong>Fitur Brankas Zero-Knowledge (Opsional):</strong> Anda dapat mengaktifkan Master PIN yang menggunakan derivasi kunci PBKDF2 (210.000 iterasi). Server tidak pernah menyimpan Master PIN Anda dalam bentuk teks mentah.</li>
            <li><strong>Isolasi Multi-Tenant:</strong> Database menerapkan penyaringan ketat per nomor ponsel terdaftar, memastikan pengguna lain mustahil mengakses riwayat finansial Anda.</li>
            <li><strong>Koneksi Terenkripsi:</strong> Seluruh komunikasi antara browser web, webhook WhatsApp, dan server dilindungi protokol HTTPS/TLS 1.3 standar industri.</li>
          </ul>
        </section>

        <!-- Section 3 -->
        <section class="p-6 rounded-3xl bg-slate-900/50 border border-slate-800/80 space-y-3">
          <h2 class="text-base sm:text-lg font-bold text-white flex items-center gap-2">
            <span>3. Hak Anda atas Data & Prosedur Penghapusan Permanen</span>
          </h2>
          <p>
            Sesuai prinsip <em>Right to be Forgotten</em> (Hak untuk Dihapus), Anda memiliki kendali penuh atas data Anda:
          </p>
          <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800 space-y-2 mt-2">
            <h4 class="font-bold text-emerald-400 text-xs sm:text-sm">Cara Menghapus Seluruh Data Finansial Anda:</h4>
            <p class="text-xs text-slate-300">
              Cukup kirimkan pesan chat berikut ke Bot WhatsApp kami:
            </p>
            <div class="p-2.5 rounded-xl bg-slate-900 font-mono text-emerald-300 text-xs inline-block border border-slate-800">
              hapus data saya
            </div>
            <p class="text-xs text-slate-400">
              Bot akan meminta konfirmasi final dengan mengetik: <code class="px-1.5 py-0.5 rounded bg-slate-900 text-rose-300 font-mono">konfirmasi hapus semua data</code>. Seketika setelah dikonfirmasi, seluruh riwayat transaksi, dompet, target tabungan, dan anggaran Anda akan <strong>dihapus permanen dari database kami</strong>.
            </p>
          </div>
        </section>

        <!-- Section 4 -->
        <section class="p-6 rounded-3xl bg-slate-900/50 border border-slate-800/80 space-y-3">
          <h2 class="text-base sm:text-lg font-bold text-white flex items-center gap-2">
            <span>4. Hubungi Kami</span>
          </h2>
          <p>
            Jika Anda memiliki pertanyaan seputar kebijakan privasi, permintaan bantuan, atau kendala akun, silakan hubungi saluran resmi kami:
          </p>
          <div class="flex flex-wrap gap-3 pt-2">
            <a
              :href="whatsappDirectUrl"
              target="_blank"
              class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-xs transition cursor-pointer"
            >
              <span>💬 Layanan Bantuan WhatsApp</span>
            </a>
            <router-link
              to="/"
              class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs transition cursor-pointer"
            >
              <span>← Kembali ke Beranda</span>
            </router-link>
          </div>
        </section>
      </div>
    </main>

    <!-- Footer -->
    <footer class="border-t border-slate-900 bg-slate-950/90 py-8 px-4 text-center text-xs text-slate-500 space-y-2">
      <div class="flex items-center justify-center gap-4 text-slate-400 text-xs">
        <router-link to="/" class="hover:text-emerald-400 transition">Beranda</router-link>
        <span>•</span>
        <router-link to="/dokumentasi" class="hover:text-emerald-400 transition">Panduan</router-link>
        <span>•</span>
        <router-link to="/privasi" class="text-emerald-400 font-bold">Kebijakan Privasi</router-link>
      </div>
      <p>© {{ new Date().getFullYear() }} HematWoy. Seluruh hak cipta dilindungi undang-undang.</p>
    </footer>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import PublicNavbar from '../components/layout/PublicNavbar.vue';

const botRawNumber = (typeof window !== 'undefined' && window.__BOT_CONFIG__?.bot_number) || import.meta.env.VITE_WHATSAPP_BOT_NUMBER || '6285111355992';

const whatsappDirectUrl = computed(() => {
  return `https://wa.me/${botRawNumber}?text=Halo%20HematWoy,%20saya%20ingin%20bertanya%20seputar%20Kebijakan%20Privasi%20dan%20Data.`;
});
</script>
