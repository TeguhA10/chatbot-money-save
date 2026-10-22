<template>
  <div class="min-h-screen bg-slate-950 text-slate-100 font-sans selection:bg-emerald-500 selection:text-white relative overflow-hidden">
    <!-- Ambient Gradients -->
    <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[1000px] h-[500px] bg-gradient-to-b from-emerald-500/15 via-teal-500/5 to-transparent blur-3xl pointer-events-none"></div>
    <div class="absolute top-96 -left-48 w-96 h-96 bg-emerald-600/10 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute top-[1400px] -right-48 w-96 h-96 bg-teal-600/10 rounded-full blur-3xl pointer-events-none"></div>

    <!-- Reusable Shared Top Navigation Bar (Identical Layout) -->
    <PublicNavbar activeRoute="documentation" />

    <!-- HERO HEADER -->
    <header class="relative pt-12 sm:pt-16 pb-12 px-4 sm:px-6 lg:px-8 max-w-6xl mx-auto text-center">
      <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-semibold mb-5">
        <span>📖</span>
        <span>Dokumentasi Resmi & Panduan Perintah Chat</span>
      </div>

      <h1 class="text-3xl sm:text-5xl font-black text-white tracking-tight leading-tight max-w-3xl mx-auto">
        Cara Lengkap Menggunakan
        <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 via-teal-300 to-cyan-400">
          HematWoy Finance Bot
        </span>
      </h1>

      <p class="mt-4 text-sm sm:text-base text-slate-400 max-w-2xl mx-auto leading-relaxed">
        Kelola pemasukan, pengeluaran, plafon budget, mutasi multi-dompet, hingga ekspor Excel otomatis cukup dengan mengetik chat WhatsApp dalam bahasa sehari-hari.
      </p>

      <!-- Search & Quick Filter Bar -->
      <div class="mt-8 max-w-xl mx-auto relative">
        <div class="relative flex items-center">
          <span class="absolute left-4 text-slate-500 text-base">🔍</span>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Cari perintah... (misal: 'koreksi', 'bca', 'export excel', 'set pin')"
            class="w-full pl-11 pr-10 py-3.5 rounded-2xl bg-slate-900/90 border border-slate-800 text-sm text-white placeholder:text-slate-500 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition shadow-xl shadow-slate-950/50"
          />
          <button
            v-if="searchQuery"
            @click="searchQuery = ''"
            class="absolute right-3.5 text-xs text-slate-500 hover:text-slate-300 px-1 py-0.5 rounded transition"
          >
            ✕
          </button>
        </div>
      </div>

      <!-- Category Filter Pills -->
      <div class="mt-6 flex flex-wrap items-center justify-center gap-2 max-w-4xl mx-auto">
        <button
          v-for="cat in categories"
          :key="cat.id"
          @click="activeCategory = cat.id"
          class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition cursor-pointer flex items-center gap-1.5"
          :class="activeCategory === cat.id
            ? 'bg-emerald-500 text-slate-950 shadow-md shadow-emerald-500/20'
            : 'bg-slate-900/80 hover:bg-slate-800 text-slate-300 border border-slate-800'"
        >
          <span>{{ cat.icon }}</span>
          <span>{{ cat.name }}</span>
          <span
            class="text-[10px] px-1.5 py-0.2 rounded-md font-mono"
            :class="activeCategory === cat.id ? 'bg-slate-950/20 text-slate-950' : 'bg-slate-800 text-slate-400'"
          >
            {{ getCategoryCount(cat.id) }}
          </span>
        </button>
      </div>
    </header>

    <!-- SECTION: INTERACTIVE SIMULATOR (MOVED TO DOCUMENTATION AS REQUESTED) -->
    <section class="py-8 px-4 sm:px-6 lg:px-8 max-w-5xl mx-auto">
      <div class="bg-gradient-to-b from-slate-900/90 via-slate-900/60 to-slate-950/90 border border-emerald-500/30 rounded-3xl p-6 sm:p-8 shadow-2xl backdrop-blur-xl relative mb-12">
        <div class="text-center max-w-xl mx-auto mb-6">
          <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-bold mb-2">
            <span>⚡</span>
            <span>Uji Coba Interaktif Langsung</span>
          </div>
          <h2 class="text-xl sm:text-2xl font-black text-white tracking-tight">
            Coba Perintah Bot Langsung di Sini
          </h2>
          <p class="text-xs sm:text-sm text-slate-400 mt-1">
            Klik tombol <span class="text-emerald-400 font-semibold">"⚡ Coba di Simulator"</span> pada panduan di bawah, atau ketik langsung pesanmu di box ini!
          </p>
        </div>

        <!-- Embedded Interactive Simulator Component -->
        <InteractiveSimulator ref="simulatorRef" />
      </div>
    </section>

    <!-- MAIN DOCUMENTATION CONTENT -->
    <main class="py-6 px-4 sm:px-6 lg:px-8 max-w-6xl mx-auto space-y-14">
      <!-- Search Results Header (if searching) -->
      <div v-if="searchQuery" class="flex items-center justify-between pb-4 border-b border-slate-800">
        <div>
          <span class="text-xs text-slate-400">Hasil pencarian untuk:</span>
          <h3 class="text-lg font-bold text-emerald-400">"{{ searchQuery }}"</h3>
        </div>
        <button
          @click="searchQuery = ''"
          class="text-xs text-slate-400 hover:text-white underline cursor-pointer"
        >
          Tampilkan Semua Panduan
        </button>
      </div>

      <!-- No Results State -->
      <div v-if="filteredSections.length === 0" class="text-center py-16 bg-slate-900/40 rounded-3xl border border-slate-800/80">
        <span class="text-4xl block mb-3">🔍</span>
        <h3 class="text-lg font-bold text-white mb-1">Perintah tidak ditemukan</h3>
        <p class="text-xs text-slate-400 max-w-sm mx-auto mb-4">
          Tidak ada panduan perintah yang cocok dengan kata kunci "{{ searchQuery }}". Coba gunakan kata kunci lain seperti "keluar", "masuk", atau "saldo".
        </p>
        <button
          @click="searchQuery = ''; activeCategory = 'all'"
          class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-200 transition cursor-pointer"
        >
          Reset Filter Pencarian
        </button>
      </div>

      <!-- Render Sections -->
      <section
        v-for="section in filteredSections"
        :key="section.id"
        :id="section.id"
        class="space-y-6"
      >
        <!-- Section Title Header -->
        <div class="flex items-center justify-between border-b border-slate-800/80 pb-3">
          <div class="flex items-center gap-3">
            <span class="text-2xl p-2 rounded-xl bg-slate-900 border border-slate-800">{{ section.icon }}</span>
            <div>
              <h2 class="text-xl sm:text-2xl font-black text-white tracking-tight">{{ section.title }}</h2>
              <p class="text-xs text-slate-400">{{ section.description }}</p>
            </div>
          </div>
          <span class="hidden sm:inline-block text-[11px] font-mono px-2.5 py-1 rounded-full bg-slate-900 border border-slate-800 text-slate-400">
            {{ section.items.length }} perintah
          </span>
        </div>

        <!-- Section Command Cards Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
          <div
            v-for="cmd in section.items"
            :key="cmd.syntax"
            class="bg-slate-900/80 hover:bg-slate-900 border border-slate-800/90 hover:border-slate-700 rounded-3xl p-5 sm:p-6 backdrop-blur-md transition-all duration-200 flex flex-col justify-between group shadow-lg shadow-slate-950/40"
          >
            <div>
              <!-- Card Header -->
              <div class="flex items-start justify-between gap-3 mb-3">
                <div>
                  <h3 class="font-bold text-white text-base group-hover:text-emerald-400 transition">
                    {{ cmd.title }}
                  </h3>
                  <p class="text-xs text-slate-400 mt-1 leading-relaxed">
                    {{ cmd.desc }}
                  </p>
                </div>
                <span
                  v-if="cmd.badge"
                  class="shrink-0 text-[10px] px-2 py-0.5 rounded-full font-bold uppercase tracking-wider"
                  :class="cmd.badgeColor || 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30'"
                >
                  {{ cmd.badge }}
                </span>
              </div>

              <!-- Syntax Badge Box -->
              <div class="bg-slate-950 border border-slate-800 rounded-2xl p-3 mb-4 font-mono text-xs text-slate-300 flex items-center justify-between gap-2 overflow-x-auto">
                <div class="flex items-center gap-2">
                  <span class="text-slate-500 select-none">$</span>
                  <span class="text-emerald-300 font-semibold">{{ cmd.syntax }}</span>
                </div>
                <button
                  @click="copyText(cmd.syntax)"
                  class="shrink-0 text-[10px] px-2 py-1 rounded-lg bg-slate-900 hover:bg-slate-800 text-slate-400 hover:text-white transition cursor-pointer border border-slate-800"
                  :title="copiedText === cmd.syntax ? 'Tersalin!' : 'Salin Sintaks'"
                >
                  {{ copiedText === cmd.syntax ? '✓ Tersalin' : 'Salin' }}
                </button>
              </div>

              <!-- Real Example Box with Simulated WhatsApp Reply -->
              <div class="space-y-2 mb-4">
                <span class="text-[10px] uppercase font-bold text-slate-500 tracking-wider block">Contoh Percakapan:</span>
                <div class="bg-slate-950/90 rounded-2xl p-3 border border-slate-800/80 space-y-2.5 text-xs">
                  <!-- User Message -->
                  <div class="flex justify-end">
                    <div class="bg-emerald-600 text-white rounded-2xl rounded-tr-none px-3.5 py-2 max-w-[85%] text-xs leading-relaxed shadow-sm">
                      {{ cmd.example }}
                    </div>
                  </div>
                  <!-- Bot Response Preview -->
                  <div class="flex justify-start">
                    <div class="bg-slate-900 text-slate-200 border border-slate-800 rounded-2xl rounded-tl-none px-3.5 py-2 max-w-[90%] text-[11px] font-mono leading-relaxed whitespace-pre-wrap">
                      {{ cmd.botReply }}
                    </div>
                  </div>
                </div>
              </div>

              <!-- Tips / Note if exists -->
              <div v-if="cmd.note" class="flex items-start gap-2 text-[11px] text-amber-300/90 bg-amber-500/10 border border-amber-500/20 rounded-xl p-2.5 mb-4">
                <span class="shrink-0 mt-0.5">💡</span>
                <span>{{ cmd.note }}</span>
              </div>
            </div>

            <!-- Card Actions -->
            <div class="pt-3 border-t border-slate-800/70 flex items-center justify-between gap-2">
              <button
                @click="copyText(cmd.example)"
                class="inline-flex items-center gap-1.5 text-xs text-slate-400 hover:text-white font-medium transition cursor-pointer px-2 py-1 rounded-lg hover:bg-slate-800"
              >
                <span>📋</span>
                <span>{{ copiedText === cmd.example ? 'Tersalin!' : 'Salin Contoh' }}</span>
              </button>

              <button
                @click="tryInSimulator(cmd.example)"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-500/15 hover:bg-emerald-500 text-emerald-400 hover:text-slate-950 font-bold text-xs border border-emerald-500/30 hover:border-emerald-500 transition active:scale-95 cursor-pointer shadow-sm"
              >
                <span>⚡ Coba di Simulator</span>
              </button>
            </div>
          </div>
        </div>
      </section>

      <!-- CHEATSHEET SUMMARY TABLE -->
      <section class="bg-slate-900/60 border border-slate-800 rounded-3xl p-6 sm:p-8 backdrop-blur-md">
        <div class="mb-6">
          <span class="text-xs font-bold text-emerald-400 uppercase tracking-widest block mb-1">Rangkuman Cepat</span>
          <h2 class="text-xl sm:text-2xl font-black text-white">Tabel Ringkasan Perintah WhatsApp</h2>
          <p class="text-xs text-slate-400 mt-1">Cetak atau simpan tabel pintasan ini untuk referensi cepat saat menggunakan bot.</p>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="border-b border-slate-800 text-slate-400 uppercase font-bold text-[10px] tracking-wider">
                <th class="py-3 px-3">Tindakan</th>
                <th class="py-3 px-3">Format Perintah</th>
                <th class="py-3 px-3">Contoh Nyata</th>
                <th class="py-3 px-3 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60 font-mono">
              <tr
                v-for="row in cheatsheetRows"
                :key="row.cmd"
                class="hover:bg-slate-800/40 transition group"
              >
                <td class="py-3 px-3 font-sans font-semibold text-white">
                  {{ row.label }}
                </td>
                <td class="py-3 px-3 text-emerald-300">
                  {{ row.cmd }}
                </td>
                <td class="py-3 px-3 text-slate-300">
                  {{ row.example }}
                </td>
                <td class="py-3 px-3 text-right">
                  <button
                    @click="tryInSimulator(row.example)"
                    class="text-[11px] px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-emerald-500 text-slate-300 hover:text-slate-950 font-bold font-sans transition cursor-pointer"
                  >
                    Coba
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- BOTTOM CONVERSION CTA BANNER -->
      <section class="text-center py-12 px-6 rounded-3xl bg-gradient-to-r from-emerald-950/50 via-slate-900 to-teal-950/50 border border-emerald-500/30 shadow-2xl">
        <h3 class="text-2xl sm:text-3xl font-black text-white tracking-tight mb-3">
          Sudah Paham Caranya? Langsung Mulai di WhatsApp!
        </h3>
        <p class="text-xs sm:text-sm text-slate-400 max-w-xl mx-auto mb-6">
          Tanpa instal aplikasi baru, tanpa perlu registrasi rumit. Cukup klik tombol di bawah dan kirim pesan pertamamu sekarang juga.
        </p>
        <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
          <a
            :href="whatsappDirectUrl"
            target="_blank"
            class="inline-flex items-center gap-2 px-8 py-3.5 rounded-2xl bg-gradient-to-r from-emerald-500 to-teal-400 text-slate-950 font-black text-sm shadow-xl shadow-emerald-500/20 hover:scale-105 active:scale-95 transition cursor-pointer"
          >
            <span>💬 Chat Bot Sekarang (WhatsApp)</span>
          </a>
          <router-link
            to="/"
            class="inline-flex items-center gap-2 px-6 py-3.5 rounded-2xl bg-slate-900 border border-slate-800 text-slate-300 font-bold text-xs hover:text-white transition cursor-pointer"
          >
            <span>← Kembali ke Beranda</span>
          </router-link>
        </div>
      </section>
    </main>

    <!-- FOOTER (MATCHING LANDING PAGE) -->
    <footer class="border-t border-slate-900 bg-slate-950/90 py-10 px-4 text-center text-xs text-slate-500 space-y-3 mt-16">
      <div class="flex items-center justify-center gap-2 mb-1">
        <div class="w-7 h-7 rounded-lg overflow-hidden bg-slate-900 border border-emerald-500/30 p-0.5 flex items-center justify-center">
          <img src="/images/logo.png" alt="HematWoy Logo" class="w-full h-full object-contain" />
        </div>
        <span class="font-bold text-slate-300 text-sm">HematWoy</span>
      </div>
      <p class="font-medium text-slate-400">Smart WhatsApp Personal Finance Tracker</p>
      <p>&copy; {{ currentYear }} HematWoy Indonesia. Seluruh hak cipta dilindungi undang-undang.</p>
    </footer>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import PublicNavbar from '../components/layout/PublicNavbar.vue';
import InteractiveSimulator from '../components/common/InteractiveSimulator.vue';

const currentYear = new Date().getFullYear();
const botRawNumber = (typeof window !== 'undefined' && window.__BOT_CONFIG__?.bot_number) || import.meta.env.VITE_WHATSAPP_BOT_NUMBER || '6285111355992';
const whatsappDirectUrl = computed(() => {
  return `https://wa.me/${botRawNumber}?text=Halo%20HematWoy,%20saya%20mau%20coba%20catat%20keuangan!`;
});

const simulatorRef = ref(null);
const searchQuery = ref('');
const activeCategory = ref('all');
const copiedText = ref('');

function copyText(text) {
  if (navigator?.clipboard) {
    navigator.clipboard.writeText(text);
    copiedText.value = text;
    setTimeout(() => {
      if (copiedText.value === text) {
        copiedText.value = '';
      }
    }, 2000);
  }
}

function tryInSimulator(command) {
  if (simulatorRef.value) {
    simulatorRef.value.injectAndRun(command, true);
  } else {
    const el = document.getElementById('simulator');
    if (el) el.scrollIntoView({ behavior: 'smooth' });
  }
}

const categories = [
  { id: 'all', name: 'Semua Kategori', icon: '✨' },
  { id: 'pin', name: 'PIN & Keamanan', icon: '🔐' },
  { id: 'expense', name: 'Pengeluaran', icon: '💸' },
  { id: 'income', name: 'Pemasukan', icon: '💰' },
  { id: 'wallet', name: 'Multi-Dompet', icon: '👛' },
  { id: 'correction', name: 'Koreksi & Batal', icon: '✏️' },
  { id: 'budget', name: 'Plafon Budget', icon: '🎯' },
  { id: 'goals', name: 'Target Tabungan', icon: '🏆' },
  { id: 'recurring', name: 'Tagihan Rutin', icon: '⏰' },
  { id: 'report', name: 'Rekap & Excel', icon: '📊' },
  { id: 'web', name: 'Web Dashboard', icon: '🌐' },
];

const documentationSections = [
  {
    id: 'pin',
    categoryId: 'pin',
    title: 'Langkah Awal & Keamanan PIN',
    icon: '🔐',
    description: 'Lindungi kerahasiaan nominal keuanganmu dengan enkripsi Zero-Knowledge standar militer.',
    items: [
      {
        title: 'Buat Master PIN Pertama Kali',
        desc: 'Diperlukan sebelum transaksi pertama untuk mengenkripsi data keuanganmu.',
        syntax: 'set pin <pin_pilihanmu>',
        example: 'set pin 123456',
        botReply: "✅ Master PIN / Kata Sandi Aktif! 🔐\n🔑 Recovery Code Darurat: 4F9B2A10C8E39D12\n\nSimpan Recovery Code ini di tempat aman jika sewaktu-waktu kamu lupa PIN.",
        note: 'PIN bisa berupa angka atau kombinasi huruf (panjang 6-64 karakter).',
        badge: 'Wajib di Awal',
        badgeColor: 'bg-amber-500/15 text-amber-300 border border-amber-500/30',
      },
      {
        title: 'Reset PIN jika Lupa',
        desc: 'Gunakan Recovery Code yang kamu dapatkan saat setup untuk membuat PIN baru.',
        syntax: 'reset pin <recovery_code> <pin_baru>',
        example: 'reset pin 4F9B2A10C8E39D12 654321',
        botReply: "✅ PIN Berhasil Diperbarui! 🔐\nPIN baru kamu sudah aktif dan data keuanganmu terlindungi kembali.",
        badge: 'Darurat',
        badgeColor: 'bg-red-500/15 text-red-300 border border-red-500/30',
      },
    ],
  },
  {
    id: 'expense',
    categoryId: 'expense',
    title: 'Pencatatan Pengeluaran (Expense)',
    icon: '💸',
    description: 'Catat uang keluar dengan bahasa sehari-hari. Mendukung singkatan k, rb, jt, dan tag dompet.',
    items: [
      {
        title: 'Pengeluaran Cepat (Standar)',
        desc: 'Ketik keyword keluar diikuti nominal dan nama pengeluaran.',
        syntax: 'keluar <nominal> <keterangan>',
        example: 'keluar 25rb kopi kenangan',
        botReply: "✅ Catatan Pengeluaran Tersimpan\n━━━━━━━━━━━━━━━━━\n📝 Keterangan: kopi kenangan\n🏷️ Kategori: ☕ Makanan & Minuman\n👛 Dompet: Cash (Sisa: Rp75.000)\n💸 Pengeluaran: Rp25.000\n━━━━━━━━━━━━━━━━━\n💰 Total Saldo: Rp975.000",
        badge: 'Paling Sering Digunakan',
      },
      {
        title: 'Pengeluaran Spesifik Dompet / Bank',
        desc: 'Sertakan tag "via <nama_dompet>" agar saldo rekening/e-wallet langsung terpotong.',
        syntax: 'keluar <nominal> <keterangan> via <dompet>',
        example: 'keluar 150rb makan siang sushi via bca',
        botReply: "✅ Catatan Pengeluaran Tersimpan\n━━━━━━━━━━━━━━━━━\n📝 Keterangan: makan siang sushi\n🏷️ Kategori: 🍽️ Makanan & Minuman\n👛 Dompet: BCA (Sisa: Rp2.350.000)\n💸 Pengeluaran: Rp150.000\n━━━━━━━━━━━━━━━━━\n💰 Total Saldo: Rp4.850.000",
        note: 'Nama dompet bisa Cash, BCA, Mandiri, GoPay, OVO, atau nama lain yang kamu buat.',
      },
      {
        title: 'Shorthand Minus (-)',
        desc: 'Ketik awalan tanda minus (-) untuk cara mencatat super cepat.',
        syntax: '-<nominal> <keterangan>',
        example: '-15k bensin motor',
        botReply: "✅ Catatan Pengeluaran Tersimpan\n━━━━━━━━━━━━━━━━━\n📝 Keterangan: bensin motor\n🏷️ Kategori: ⛽ Transportasi\n👛 Dompet: Cash (Sisa: Rp60.000)\n💸 Pengeluaran: Rp15.000\n━━━━━━━━━━━━━━━━━\n💰 Total Saldo: Rp960.000",
      },
      {
        title: 'Gaya Alami: "Beli" atau "Bayar"',
        desc: 'Bot pintar mengenali kata seperti beli, bayar, jajan, belanja, atau tagihan.',
        syntax: 'beli <barang> <nominal> / bayar <tagihan> <nominal>',
        example: 'bayar listrik pln 250rb via mandiri',
        botReply: "✅ Catatan Pengeluaran Tersimpan\n━━━━━━━━━━━━━━━━━\n📝 Keterangan: listrik pln\n🏷️ Kategori: 💡 Tagihan & Utilitas\n👛 Dompet: Mandiri (Sisa: Rp1.250.000)\n💸 Pengeluaran: Rp250.000\n━━━━━━━━━━━━━━━━━\n💰 Total Saldo: Rp2.210.000",
      },
    ],
  },
  {
    id: 'income',
    categoryId: 'income',
    title: 'Pencatatan Pemasukan (Income)',
    icon: '💰',
    description: 'Catat gaji, omzet usaha, bonus, atau transfer masuk tanpa ribet memilih menu.',
    items: [
      {
        title: 'Pemasukan Standar',
        desc: 'Ketik kata masuk diikuti nominal dan sumber pemasukan.',
        syntax: 'masuk <nominal> <keterangan>',
        example: 'masuk 5jt gaji bulanan',
        botReply: "✅ Catatan Pemasukan Tersimpan\n━━━━━━━━━━━━━━━━━\n📝 Keterangan: gaji bulanan\n🏷️ Kategori: 💼 Gaji & Pendapatan\n👛 Dompet: Cash (Sisa: Rp5.960.000)\n💰 Pemasukan: Rp5.000.000\n━━━━━━━━━━━━━━━━━\n💰 Total Saldo: Rp5.960.000",
      },
      {
        title: 'Pemasukan ke Rekening Tertentu',
        desc: 'Gunakan keyword "ke <dompet>" atau "via <dompet>" untuk mengarahkan dana.',
        syntax: 'masuk <nominal> <keterangan> ke <dompet>',
        example: '+1.5jt freelance website ke bca',
        botReply: "✅ Catatan Pemasukan Tersimpan\n━━━━━━━━━━━━━━━━━\n📝 Keterangan: freelance website\n🏷️ Kategori: 📦 Lain-lain\n👛 Dompet: BCA (Sisa: Rp3.850.000)\n💰 Pemasukan: Rp1.500.000\n━━━━━━━━━━━━━━━━━\n💰 Total Saldo: Rp7.460.000",
      },
      {
        title: 'Shorthand Plus (+)',
        desc: 'Cukup gunakan tanda plus (+) di depan nominal angka.',
        syntax: '+<nominal> <keterangan>',
        example: '+500rb jualan online',
        botReply: "✅ Catatan Pemasukan Tersimpan\n━━━━━━━━━━━━━━━━━\n📝 Keterangan: jualan online\n🏷️ Kategori: 📦 Lain-lain\n👛 Dompet: Cash (Sisa: Rp6.460.000)\n💰 Pemasukan: Rp500.000\n━━━━━━━━━━━━━━━━━\n💰 Total Saldo: Rp7.960.000",
      },
    ],
  },
  {
    id: 'wallet',
    categoryId: 'wallet',
    title: 'Multi-Dompet & Transfer Saldo',
    icon: '👛',
    description: 'Pisahkan saldo tunai fisik, rekening bank, dan saldo dompet digital.',
    items: [
      {
        title: 'Cek Saldo Seluruh Dompet',
        desc: 'Menampilkan rincian saldo per akun dan total kekayaan bersihmu.',
        syntax: 'saldo / dompet',
        example: 'saldo',
        botReply: "💰 Saldo Anda\n\nCash           Rp6.460.000\nBCA            Rp3.850.000\nMandiri        Rp1.250.000\n-------------------------\nTotal          Rp11.560.000\n\n📅 Transaksi Hari Ini (21 Okt 2026)\n  ↑ Masuk : Rp7.000.000\n  ↓ Keluar: Rp415.000\n  📊 Total: 4 transaksi",
        badge: 'Favorit',
      },
      {
        title: 'Tambah Dompet / Rekening Baru',
        desc: 'Daftarkan dompet baru beserta saldo awalnya jika ada.',
        syntax: 'tambah dompet <nama> [saldo <nominal>]',
        example: 'tambah dompet GoPay saldo 100rb',
        botReply: "👛 Dompet Baru Ditambahkan\nNama: GoPay (Saldo Awal: Rp100.000)\n\nKetik \"saldo\" untuk melihat rincian dompet.",
      },
      {
        title: 'Transfer Saldo Antar Dompet',
        desc: 'Pindahkan dana antar dompet tanpa mengubah total saldo keseluruhan.',
        syntax: 'transfer <nominal> dari <asal> ke <tujuan>',
        example: 'transfer 500rb dari BCA ke GoPay',
        botReply: "🔄 Transfer Berhasil\nNominal: Rp500.000\nDari: BCA (Sisa: Rp3.350.000)\nKe: GoPay (Sisa: Rp600.000)\nTotal Saldo Tetap: Rp11.560.000\n\nKetik \"batal\" jika ingin membatalkan transfer ini.",
      },
    ],
  },
  {
    id: 'correction',
    categoryId: 'correction',
    title: 'Koreksi & Pembatalan Transaksi',
    icon: '✏️',
    description: 'Salah ketik nominal atau ingin membatalkan transaksi? Lakukan dalam 1 detik.',
    items: [
      {
        title: 'Koreksi Nominal Instan (Transaksi Terakhir)',
        desc: 'Ubah nominal transaksi yang baru saja dicatat tanpa perlu mengingat ID.',
        syntax: 'koreksi <nominal_baru> [keterangan_baru]',
        example: 'koreksi 30rb kopi kenangan jumbo',
        botReply: "✅ Transaksi Berhasil Diubah\n━━━━━━━━━━━━━━━━━\n📝 Keterangan: kopi kenangan jumbo\n🏷️ Kategori: ☕ Makanan & Minuman\n💵 Pengeluaran: Rp30.000\n━━━━━━━━━━━━━━━━━\n💰 Saldo Baru: Rp11.555.000",
        badge: 'Sangat Berguna',
      },
      {
        title: 'Batalkan Transaksi Terakhir (Undo)',
        desc: 'Batalkan transaksi paling akhir dan kembalikan saldo seperti semula.',
        syntax: 'batal / undo / cancel',
        example: 'batal',
        botReply: "🗑️ Transaksi Berhasil Dihapus\n━━━━━━━━━━━━━━━━━\n📝 kopi kenangan jumbo\n💵 Pengeluaran: Rp30.000\n━━━━━━━━━━━━━━━━━\n💰 Saldo Baru: Rp11.585.000",
      },
      {
        title: 'Hapus Berdasarkan Nomor List',
        desc: 'Saat melihat daftar pengeluaran, kamu bisa langsung hapus nomor urutnya.',
        syntax: 'hapus <nomor_urut>',
        example: 'hapus 2',
        botReply: "🗑️ Transaksi #2 Berhasil Dihapus\n━━━━━━━━━━━━━━━━━\n📝 bensin motor\n💵 Pengeluaran: Rp15.000\n━━━━━━━━━━━━━━━━━\n💰 Saldo Baru: Rp11.600.000",
      },
    ],
  },
  {
    id: 'budget',
    categoryId: 'budget',
    title: 'Plafon Anggaran (Budget Anti-Boncos)',
    icon: '🎯',
    description: 'Batasi pengeluaran per kategori. Bot akan memperingatkan sebelum kamu boncos.',
    items: [
      {
        title: 'Atur Batas Budget Kategori',
        desc: 'Tetapkan batas maksimal pengeluaran bulanan untuk kategori tertentu.',
        syntax: 'budget <kategori> <nominal>',
        example: 'budget makan 1.5jt',
        botReply: "🎯 Plafon Budget Diatur\nKategori: 🍽️ Makanan & Minuman\nPlafon: Rp1.500.000 / bulan\n\nSetiap kamu mencatat pengeluaran makanan, bot akan menginformasikan sisa budget kamu!",
      },
      {
        title: 'Cek Status Semua Budget',
        desc: 'Melihat progress bar pemakaian seluruh pos anggaran bulan ini.',
        syntax: 'status budget / cek budget',
        example: 'status budget',
        botReply: "🎯 Status Anggaran Bulan Ini\n\n🍽️ Makanan & Minuman\nRp450.000 / Rp1.500.000 (30%)\n[██████░░░░░░░░░░░░░░] Sisa Rp1.050.000\n\n⛽ Transportasi\nRp150.000 / Rp300.000 (50%)\n[██████████░░░░░░░░░░] Sisa Rp150.000",
      },
    ],
  },
  {
    id: 'goals',
    categoryId: 'goals',
    title: 'Target Tabungan (Financial Goals)',
    icon: '🏆',
    description: 'Wujudkan barang impian, dana darurat, atau tabungan liburan dengan tracking visual.',
    items: [
      {
        title: 'Buat Target Tabungan Baru',
        desc: 'Tentukan impian finansial dan nominal yang ingin dicapai.',
        syntax: 'buat target <nama> <nominal>',
        example: 'buat target Laptop Baru 15jt',
        botReply: "🎯 Target Finansial Dibuat\nTarget: Laptop Baru\nTujuan: Rp15.000.000\nTerkumpul: Rp0 (0%)\nSisa: Rp15.000.000\n[░░░░░░░░░░░░░░░░░░░░]",
      },
      {
        title: 'Setor / Tambah Tabungan Impian',
        desc: 'Pindahkan saldo dompet ke dalam pos tabungan impianmu.',
        syntax: 'tabung <nominal> untuk <nama_target>',
        example: 'tabung 1.5jt untuk Laptop Baru via bca',
        botReply: "🎯 Target: Laptop Baru\nTarget: Rp15.000.000\nTerkumpul: Rp1.500.000 (+Rp1.500.000)\nSisa: Rp13.500.000\n[██░░░░░░░░░░░░░░░░░░] 10%\n\nSaldo BCA berkurang Rp1.500.000",
      },
      {
        title: 'Lihat Daftar Target Tabungan',
        desc: 'Menampilkan seluruh progres tabungan yang sedang berjalan.',
        syntax: 'daftar target / list target',
        example: 'daftar target',
        botReply: "🎯 Daftar Target Finansial Anda\n\n🎯 Laptop Baru\n   Terkumpul: Rp1.500.000 / Rp15.000.000\n   [██░░░░░░░░░░░░░░░░░░] 10%\n\nKetik \"tabung <nominal> untuk <nama>\" untuk menabung.",
      },
    ],
  },
  {
    id: 'recurring',
    categoryId: 'recurring',
    title: 'Transaksi Rutin Terjadwal (Langganan)',
    icon: '⏰',
    description: 'Catat tagihan bulanan berulang seperti Wifi, kos, Netflix, atau cicilan.',
    items: [
      {
        title: 'Jadwalkan Tagihan Rutin',
        desc: 'Tentukan tanggal tagihan bulanan agar bot mengingatkanmu.',
        syntax: 'langganan <nama> <nominal> setiap tanggal <tgl>',
        example: 'langganan Wifi Indihome 350rb setiap tanggal 5',
        botReply: "⏰ Transaksi Rutin Terjadwal\nNama: Wifi Indihome\nNominal: Rp350.000\nJadwal: Tanggal 5 setiap bulan\n\nBot akan otomatis mencatat atau mengingatkanmu saat jatuh tempo!",
      },
      {
        title: 'Cek Daftar Tagihan Rutin',
        desc: 'Melihat seluruh kewajiban bulanan yang telah dijadwalkan.',
        syntax: 'daftar langganan',
        example: 'daftar langganan',
        botReply: "⏰ Jadwal Transaksi Rutin Kamu\n• Wifi Indihome: Rp350.000 (Tgl 5)\n• Netflix: Rp186.000 (Tgl 15)\n\nTotal Kewajiban Rutin: Rp536.000 / bulan",
      },
    ],
  },
  {
    id: 'report',
    categoryId: 'report',
    title: 'Rekap, Laporan & Ekspor Excel',
    icon: '📊',
    description: 'Pantau arus kas harian, bulanan, omzet usaha, dan unduh laporan spreadsheet.',
    items: [
      {
        title: 'Unduh Laporan Excel (1-Klik)',
        desc: 'Dapatkan file Excel (.xlsx) rapi multi-sheet lengkap formula SUM instan di WhatsApp.',
        syntax: 'export excel / download excel',
        example: 'export excel',
        botReply: "📊 Laporan keuangan pribadi Anda (Excel multi-sheet) sedang disiapkan...\n\nBerisi: Dashboard ringkasan + tab bulanan dengan formula SUM otomatis.\n[Dokumen: Laporan_Keuangan_20261021.xlsx]",
        badge: 'Fitur Unggulan Pro',
        badgeColor: 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40',
      },
      {
        title: 'Rekap Harian & Bulanan',
        desc: 'Melihat ringkasan total uang masuk, keluar, dan selisihnya.',
        syntax: 'rekap hari ini / rekap bulan ini',
        example: 'rekap bulan ini',
        botReply: "📊 Rekap Bulan Oktober 2026\n━━━━━━━━━━━━━━━━━\n💚 Total Masuk : Rp7.000.000\n❤️ Pengeluaran : Rp1.250.000\n📈 Tabungan Bersih: Rp5.750.000\n📝 Transaksi : 18 kali\n━━━━━━━━━━━━━━━━━\n💰 Saldo Kini : Rp11.585.000",
      },
      {
        title: 'Daftar Pengeluaran / Pemasukan Hari Ini',
        desc: 'Lihat rincian detail setiap pos transaksi hari ini.',
        syntax: 'pengeluaran hari ini / pemasukan hari ini',
        example: 'pengeluaran hari ini',
        botReply: "💸 Pengeluaran - Hari Ini\n━━━━━━━━━━━━━━━━━\n1. ☕ Makanan & Minuman\n   📝 kopi kenangan\n   💵 Rp25.000\n2. 🍽️ Makanan & Minuman\n   📝 sushi siang\n   💵 Rp150.000\n━━━━━━━━━━━━━━━━━\n💰 Saldo: Rp11.585.000",
      },
    ],
  },
  {
    id: 'web',
    categoryId: 'web',
    title: 'Web App Dashboard & Akun',
    icon: '🌐',
    description: 'Buka grafik visual, diagram donat, dan kelola dompet di layar browser laptop/PC.',
    items: [
      {
        title: 'Buka Web Dashboard Visual',
        desc: 'Dapatkan link langsung untuk membuka dashboard keuangan di browser.',
        syntax: 'web / dashboard',
        example: 'web',
        botReply: "🌐 Web App Dashboard — HematWoy\n\nPantau keuanganmu dengan grafik visual, pie chart, dan kelola dompet di browser:\n👉 http://localhost:8001\n\nCara Login:\n1. Buka link di atas\n2. Masukkan nomor HP kamu\n3. Masukkan OTP yang dikirim ke WhatsApp ini.",
      },
      {
        title: 'Cek Kuota & Status Akun',
        desc: 'Periksa sisa kuota gratis kamu dan masa aktif akun Pro.',
        syntax: 'kuota / status langganan',
        example: 'kuota',
        botReply: "📊 Status Kuota Finansial\n───────────────────────\n🏷️ Paket Saat Ini : GRATIS (Freemium)\n💬 Sisa Kuota     : 95 dari 100 pesan finansial gratis\n📈 Penggunaan     : [█░░░░░░░░░░░░░░░░░░░] 5%\n\nKetik \"langganan\" untuk upgrade akun Pro tanpa batas!",
      },
      {
        title: 'Tampilkan Menu Bantuan',
        desc: 'Tampilkan daftar panduan dan tombol pilihan cepat kapan saja.',
        syntax: 'menu / bantuan / help',
        example: 'menu',
        botReply: "👋 Halo! Berikut daftar menu cepat HematWoy 💰\n\n• Catat: \"keluar 25rb makan\" atau \"+500rb gaji\"\n• Saldo: \"saldo\"\n• Budget: \"status budget\"\n• Target: \"daftar target\"\n• Excel: \"export excel\"\n• Web: \"web\"",
      },
    ],
  },
];

const cheatsheetRows = [
  { label: 'Catat Pengeluaran', cmd: 'keluar <nominal> <ket>', example: 'keluar 25rb kopi kenangan' },
  { label: 'Pengeluaran Bank', cmd: 'keluar <nominal> <ket> via <bank>', example: 'keluar 150rb makan via bca' },
  { label: 'Shorthand Keluar', cmd: '-<nominal> <ket>', example: '-15k bensin motor' },
  { label: 'Catat Pemasukan', cmd: 'masuk <nominal> <ket>', example: 'masuk 5jt gaji bulanan' },
  { label: 'Shorthand Masuk', cmd: '+<nominal> <ket>', example: '+500rb freelance via bca' },
  { label: 'Cek Total Saldo', cmd: 'saldo / dompet', example: 'saldo' },
  { label: 'Tambah Dompet', cmd: 'tambah dompet <nama>', example: 'tambah dompet GoPay saldo 100rb' },
  { label: 'Transfer Dompet', cmd: 'transfer <nominal> dari <a> ke <b>', example: 'transfer 500rb dari BCA ke GoPay' },
  { label: 'Koreksi Terakhir', cmd: 'koreksi <nominal>', example: 'koreksi 30rb' },
  { label: 'Batalkan Transaksi', cmd: 'batal / undo', example: 'batal' },
  { label: 'Atur Anggaran', cmd: 'budget <kategori> <nominal>', example: 'budget makan 1.5jt' },
  { label: 'Cek Status Budget', cmd: 'status budget', example: 'status budget' },
  { label: 'Target Tabungan', cmd: 'buat target <nama> <nominal>', example: 'buat target Laptop 15jt' },
  { label: 'Setor Tabungan', cmd: 'tabung <nominal> untuk <nama>', example: 'tabung 1jt untuk Laptop' },
  { label: 'Ekspor File Excel', cmd: 'export excel', example: 'export excel' },
  { label: 'Rekap Bulanan', cmd: 'rekap bulan ini', example: 'rekap bulan ini' },
  { label: 'Buka Dashboard', cmd: 'web / dashboard', example: 'web' },
  { label: 'Cek Kuota Chat', cmd: 'kuota', example: 'kuota' },
  { label: 'Buat Master PIN', cmd: 'set pin <pin>', example: 'set pin 123456' },
];

function getCategoryCount(catId) {
  if (catId === 'all') {
    return documentationSections.reduce((acc, s) => acc + s.items.length, 0);
  }
  const sec = documentationSections.find(s => s.categoryId === catId);
  return sec ? sec.items.length : 0;
}

const filteredSections = computed(() => {
  const q = searchQuery.value.toLowerCase().trim();
  const cat = activeCategory.value;

  return documentationSections
    .map(section => {
      // Category filter
      if (cat !== 'all' && section.categoryId !== cat) {
        return null;
      }

      // Query filter
      if (!q) {
        return section;
      }

      const matchingItems = section.items.filter(item => {
        return (
          item.title.toLowerCase().includes(q) ||
          item.desc.toLowerCase().includes(q) ||
          item.syntax.toLowerCase().includes(q) ||
          item.example.toLowerCase().includes(q) ||
          item.botReply.toLowerCase().includes(q) ||
          (item.note && item.note.toLowerCase().includes(q))
        );
      });

      if (matchingItems.length === 0 && !section.title.toLowerCase().includes(q)) {
        return null;
      }

      return {
        ...section,
        items: matchingItems.length > 0 ? matchingItems : section.items,
      };
    })
    .filter(Boolean);
});
</script>
