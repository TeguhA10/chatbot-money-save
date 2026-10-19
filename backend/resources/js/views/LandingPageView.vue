<template>
  <div class="min-h-screen bg-slate-950 text-slate-100 font-sans selection:bg-emerald-500 selection:text-white relative overflow-hidden">
    <!-- Ambient Gradients -->
    <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[1000px] h-[500px] bg-gradient-to-b from-emerald-500/15 via-teal-500/5 to-transparent blur-3xl pointer-events-none"></div>
    <div class="absolute top-96 -left-48 w-96 h-96 bg-emerald-600/10 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute top-[1200px] -right-48 w-96 h-96 bg-teal-600/10 rounded-full blur-3xl pointer-events-none"></div>

    <!-- Sticky Navigation Bar -->
    <nav class="sticky top-0 z-50 w-full border-b border-slate-800/80 bg-slate-950/80 backdrop-blur-xl">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
        <!-- Logo -->
        <router-link to="/" class="flex items-center gap-3 group">
          <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-emerald-500 to-teal-400 flex items-center justify-center text-slate-950 font-black text-base shadow-lg shadow-emerald-500/20 group-hover:scale-105 transition">
            HW
          </div>
          <div>
            <span class="font-black text-lg text-white tracking-tight">HematWoy</span>
            <span class="text-[10px] text-emerald-400 font-bold block -mt-1 tracking-wider uppercase">Finance Bot</span>
          </div>
        </router-link>

        <!-- Desktop Menu Links -->
        <div class="hidden md:flex items-center gap-8 text-sm font-medium text-slate-400">
          <a href="#fitur" class="hover:text-emerald-400 transition">Fitur Utama</a>
          <a href="#simulator" class="hover:text-emerald-400 transition">Coba Simulator</a>
          <a href="#harga" class="hover:text-emerald-400 transition">Paket Harga</a>
          <a href="#faq" class="hover:text-emerald-400 transition">FAQ</a>
        </div>

        <!-- Action CTAs -->
        <div class="flex items-center gap-3">
          <!-- Auth Dynamic Button: Login if guest, Dashboard if authenticated -->
          <router-link
            v-if="!authStore.isAuthenticated"
            to="/login"
            class="px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold text-slate-300 hover:text-white hover:bg-slate-900 transition"
          >
            Login
          </router-link>

          <router-link
            v-else
            to="/dashboard"
            class="px-4 py-2 rounded-xl text-xs sm:text-sm font-bold text-emerald-400 bg-emerald-500/15 border border-emerald-500/30 hover:bg-emerald-500/25 transition flex items-center gap-1.5"
          >
            <span>Dashboard</span>
            <span>&rarr;</span>
          </router-link>

          <a
            :href="whatsappDirectUrl"
            target="_blank"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-400 text-slate-950 font-bold text-xs sm:text-sm shadow-lg shadow-emerald-500/20 hover:opacity-95 transition cursor-pointer"
          >
            <span>💬 Chat WhatsApp</span>
          </a>
        </div>
      </div>
    </nav>

    <!-- HERO SECTION -->
    <section class="relative pt-16 sm:pt-24 pb-20 px-4 sm:px-6 lg:px-8 max-w-6xl mx-auto text-center">
      <!-- Top Social Proof Pill -->
      <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-semibold mb-6 animate-pulse">
        <span class="flex h-2 w-2 rounded-full bg-emerald-400"></span>
        <span>Asisten Finansial WhatsApp Otomatis #1 di Indonesia</span>
      </div>

      <!-- Main Headline -->
      <h1 class="text-4xl sm:text-6xl font-black text-white tracking-tight leading-[1.15] max-w-4xl mx-auto">
        Catat Keuangan Harian Semudah
        <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 via-teal-300 to-cyan-400">
          Kirim Chat WhatsApp
        </span>
      </h1>

      <!-- Subheadline -->
      <p class="mt-6 text-base sm:text-lg text-slate-400 max-w-2xl mx-auto leading-relaxed">
        Bebas ribet instal aplikasi berat. Cukup ketik pengeluaranmu dalam bahasa sehari-hari, pantau mutasi saldo multi-dompet real-time, dan capai target tabungan bebas boncos.
      </p>

      <!-- Primary CTAs -->
      <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
        <a
          :href="whatsappDirectUrl"
          target="_blank"
          class="w-full sm:w-auto px-8 py-4 rounded-2xl bg-gradient-to-r from-emerald-500 to-teal-400 text-slate-950 font-extrabold text-base shadow-2xl shadow-emerald-500/30 hover:scale-105 active:scale-98 transition flex items-center justify-center gap-2.5 cursor-pointer"
        >
          <span>🚀 Mulai Gratis di WhatsApp</span>
          <span class="text-xs font-normal opacity-80">(100 Chat Pertama Free)</span>
        </a>

        <a
          href="#simulator"
          class="w-full sm:w-auto px-6 py-4 rounded-2xl bg-slate-900 border border-slate-800 hover:border-slate-700 text-slate-200 font-bold text-sm transition flex items-center justify-center gap-2"
        >
          <span>👀 Coba Demo Interaktif</span>
        </a>
      </div>

      <!-- Trust Badges -->
      <div class="mt-12 flex flex-wrap items-center justify-center gap-6 sm:gap-10 text-xs text-slate-500">
        <div class="flex items-center gap-2">
          <span class="text-emerald-400 text-base">✓</span>
          <span>Tanpa Install Aplikasi</span>
        </div>
        <div class="flex items-center gap-2">
          <span class="text-emerald-400 text-base">✓</span>
          <span>Nomor WhatsApp: {{ botNumberDisplay }}</span>
        </div>
        <div class="flex items-center gap-2">
          <span class="text-emerald-400 text-base">✓</span>
          <span>Enkripsi Privasi Finansial</span>
        </div>
        <div class="flex items-center gap-2">
          <span class="text-emerald-400 text-base">✓</span>
          <span>Ekspor Excel 1-Klik</span>
        </div>
      </div>
    </section>

    <!-- SECTION 2: LIVE SIMULATOR -->
    <section id="simulator" class="py-16 px-4 sm:px-6 lg:px-8 max-w-5xl mx-auto">
      <div class="text-center mb-8">
        <span class="text-xs font-bold text-emerald-400 uppercase tracking-widest block mb-2">Simulasi Interaktif Real-Time</span>
        <h2 class="text-2xl sm:text-3xl font-black text-white tracking-tight">Coba Langsung Respon Asli Bot WhatsApp</h2>
        <p class="text-xs sm:text-sm text-slate-400 mt-2">
          Simulator ini memproses input menggunakan engine parser resmi WhatsApp HematWoy.
        </p>
      </div>

      <!-- Simulator Box -->
      <div class="max-w-xl mx-auto">
        <!-- LOCKED STATE: Replaces chat card when 3 attempts have been reached -->
        <div
          v-if="isLocked || attempts >= 3"
          class="p-7 sm:p-10 rounded-3xl bg-gradient-to-b from-slate-900 via-slate-900 to-emerald-950/40 border-2 border-emerald-500/50 text-center shadow-2xl shadow-emerald-500/10 backdrop-blur-xl animate-fade-in"
        >
          <div class="w-16 h-16 bg-emerald-500/20 text-emerald-400 rounded-3xl flex items-center justify-center mx-auto mb-4 text-3xl shadow-lg shadow-emerald-500/20">
            💬
          </div>
          <div class="inline-block px-3 py-1 bg-emerald-500/15 border border-emerald-500/30 rounded-full text-emerald-400 text-xs font-bold mb-3 tracking-wide">
            KUOTA COBA DEMO SELESAI (3/3)
          </div>
          <h3 class="text-xl sm:text-2xl font-black text-white tracking-tight mb-2">
            Suka dengan Respon Cepat Bot?
          </h3>
          <p class="text-xs sm:text-sm text-slate-300 max-w-md mx-auto mb-6 leading-relaxed">
            Kamu telah mencoba 3 kali simulasi di website. Sekarang, saatnya merasakan kemudahan mencatat keuangan pribadi secara nyata langsung di aplikasi WhatsApp kamu!
          </p>

          <div class="bg-slate-950/90 border border-slate-800 rounded-2xl p-4 max-w-xs mx-auto mb-6 text-xs text-slate-300">
            <span class="text-slate-400 text-[11px] block mb-1">Nomor WhatsApp Resmi HematWoy:</span>
            <span class="font-mono font-black text-emerald-400 text-base">{{ botNumberDisplay }}</span>
          </div>

          <a
            :href="whatsappDirectUrl"
            target="_blank"
            class="inline-flex items-center justify-center gap-2.5 w-full sm:w-auto px-8 py-4 rounded-2xl bg-gradient-to-r from-emerald-500 to-teal-400 text-slate-950 font-black text-sm shadow-xl shadow-emerald-500/25 hover:scale-105 active:scale-98 transition cursor-pointer"
          >
            <span>💬 Pindah Catat di WhatsApp Sekarang</span>
          </a>
        </div>

        <!-- ACTIVE CHAT CARD: When attempts < 3 -->
        <div
          v-else
          class="bg-slate-900/90 border border-slate-800 rounded-3xl p-5 sm:p-6 shadow-2xl backdrop-blur-xl"
        >
          <!-- Chat header (WhatsApp style) -->
          <div class="flex items-center justify-between pb-4 border-b border-slate-800 mb-4">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-emerald-500 to-teal-400 flex items-center justify-center text-slate-950 font-bold text-sm">
                HW
              </div>
              <div>
                <div class="font-bold text-sm text-white flex items-center gap-1.5">
                  <span>HematWoy Finance Bot</span>
                  <span class="text-[10px] text-emerald-400 bg-emerald-500/15 px-1.5 py-0.2 rounded font-semibold">Online</span>
                </div>
                <div class="text-[11px] text-slate-400 font-mono">{{ botNumberDisplay }}</div>
              </div>
            </div>

            <!-- Attempt Counter Badge -->
            <div class="text-right">
              <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-slate-800 border border-slate-700 text-slate-300">
                <span>Coba Web:</span>
                <span class="text-emerald-400 font-mono">{{ attempts }}/3</span>
              </span>
            </div>
          </div>

          <!-- Preset Chips -->
          <div class="mb-4">
            <span class="text-[10px] text-slate-400 font-medium block mb-1.5">Klik contoh cepat di bawah:</span>
            <div class="flex flex-wrap gap-1.5">
              <button
                v-for="chip in presetChips"
                :key="chip"
                @click="runPreset(chip)"
                :disabled="sending"
                class="text-[11px] px-3 py-1.5 rounded-lg bg-slate-800/80 hover:bg-slate-800 border border-slate-700/60 text-slate-300 font-medium transition cursor-pointer disabled:opacity-50"
              >
                {{ chip }}
              </button>
            </div>
          </div>

          <!-- Chat Conversation Area -->
          <div class="space-y-3 mb-4 min-h-[220px] max-h-[320px] overflow-y-auto pr-1">
            <div
              v-for="(msg, idx) in chatMessages"
              :key="idx"
              class="flex flex-col"
              :class="msg.sender === 'user' ? 'items-end' : 'items-start'"
            >
              <div
                class="max-w-[90%] rounded-2xl px-4 py-3 text-xs leading-relaxed"
                :class="msg.sender === 'user' ? 'bg-emerald-600 text-white rounded-tr-none' : 'bg-slate-950 border border-slate-800 text-slate-200 rounded-tl-none font-mono whitespace-pre-wrap'"
              >
                {{ msg.text }}
              </div>
              <span class="text-[9px] text-slate-500 mt-0.5 px-1">{{ msg.time }}</span>
            </div>

            <!-- Typing indicator -->
            <div v-if="sending" class="flex flex-col items-start">
              <div class="bg-slate-950 border border-slate-800 text-slate-400 rounded-2xl rounded-tl-none px-4 py-2.5 text-xs flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 bg-emerald-400 rounded-full animate-bounce"></span>
                <span class="w-1.5 h-1.5 bg-emerald-400 rounded-full animate-bounce [animation-delay:0.2s]"></span>
                <span class="w-1.5 h-1.5 bg-emerald-400 rounded-full animate-bounce [animation-delay:0.4s]"></span>
                <span class="text-[11px] ml-1">HematWoy sedang memproses...</span>
              </div>
            </div>
          </div>

          <!-- Input Bar -->
          <form @submit.prevent="sendCustomChat" class="flex gap-2">
            <input
              v-model="inputChat"
              type="text"
              :disabled="sending"
              placeholder="Ketik: keluar 25rb kopi kenangan via bca..."
              class="flex-1 px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-xs text-white focus:outline-none focus:border-emerald-500 transition disabled:opacity-50"
            />
            <button
              type="submit"
              :disabled="sending || !inputChat.trim()"
              class="px-5 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-xs transition cursor-pointer disabled:opacity-50"
            >
              {{ sending ? 'Mengirim...' : 'Kirim' }}
            </button>
          </form>

          <p class="text-[10px] text-slate-500 text-center mt-2.5">
            Maksimal 3 kali simulasi di browser. Setelah itu, kamu bisa lanjut sepuasnya di WhatsApp!
          </p>
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
              <li class="flex items-center gap-2">✓ 100 Kuota Transaksi Chat Gratis</li>
              <li class="flex items-center gap-2">✓ 1 Dompet Kas Utama</li>
              <li class="flex items-center gap-2">✓ Rekap Pengeluaran Harian & Bulanan</li>
              <li class="flex items-center gap-2 text-slate-500">✕ Multi-Dompet Tanpa Batas</li>
              <li class="flex items-center gap-2 text-slate-500">✕ Plafon Budget Kategori</li>
            </ul>
          </div>

          <a
            :href="whatsappDirectUrl"
            target="_blank"
            class="mt-8 w-full py-3 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs text-center transition block"
          >
            Mulai Gratis
          </a>
        </div>

        <!-- Tier 2: Pro Bulanan (Recommended) -->
        <div class="bg-slate-900 border-2 border-emerald-500 rounded-3xl p-6 sm:p-8 flex flex-col justify-between relative shadow-2xl shadow-emerald-500/10">
          <div class="absolute -top-3.5 left-1/2 -translate-x-1/2 px-3 py-1 bg-gradient-to-r from-emerald-500 to-teal-400 text-slate-950 font-black text-[10px] tracking-wider uppercase rounded-full">
            Paling Populer
          </div>

          <div>
            <h3 class="text-lg font-bold text-white mb-1">Pro Bulanan</h3>
            <p class="text-xs text-slate-400 mb-6">Untuk profesional & pencatat rutin</p>
            <div class="text-3xl font-black text-emerald-400 mb-6">
              Rp 25.000 <span class="text-xs font-normal text-slate-400">/ 30 hari</span>
            </div>

            <ul class="space-y-3 text-xs text-slate-300">
              <li class="flex items-center gap-2">⭐ <strong>Unlimited</strong> Pencatatan Transaksi</li>
              <li class="flex items-center gap-2">⭐ Multi-Dompet (BCA, Mandiri, Cash, e-Wallet)</li>
              <li class="flex items-center gap-2">⭐ Plafon Anggaran & Anti-Boncos Alert</li>
              <li class="flex items-center gap-2">⭐ 1-Klik Ekspor Laporan Excel Lengkap</li>
              <li class="flex items-center gap-2">⭐ AI Financial Spending Insights</li>
              <li class="flex items-center gap-2">⭐ Akses Penuh Web App Dashboard</li>
            </ul>
          </div>

          <a
            :href="whatsappDirectUrlWithPlan"
            target="_blank"
            class="mt-8 w-full py-3.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-400 text-slate-950 font-black text-xs text-center shadow-lg shadow-emerald-500/20 hover:opacity-95 transition block"
          >
            Daftar Pro Sekarang
          </a>
        </div>

        <!-- Tier 3: Pro Tahunan -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-6 sm:p-8 flex flex-col justify-between">
          <div>
            <div class="inline-block px-2.5 py-0.5 rounded-full bg-teal-500/15 text-teal-400 text-[10px] font-bold mb-2">
              HEMAT 35%
            </div>
            <h3 class="text-lg font-bold text-white mb-1">Pro 1 Tahun</h3>
            <p class="text-xs text-slate-400 mb-6">Disiplin finansial sepanjang tahun</p>
            <div class="text-3xl font-black text-white mb-6">
              Rp 199.000 <span class="text-xs font-normal text-slate-400">/ tahun</span>
            </div>

            <ul class="space-y-3 text-xs text-slate-300">
              <li class="flex items-center gap-2">✓ Seluruh Fitur Paket Pro</li>
              <li class="flex items-center gap-2">✓ Masa Aktif 365 Hari Penuh</li>
              <li class="flex items-center gap-2">✓ Hemat Rp 101.000 dibanding bulanan</li>
              <li class="flex items-center gap-2">✓ Prioritas Fitur AI Baru Mendatang</li>
            </ul>
          </div>

          <a
            :href="whatsappDirectUrlWithAnnualPlan"
            target="_blank"
            class="mt-8 w-full py-3 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs text-center transition block"
          >
            Pilih Paket Tahunan
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
            {{ faq.a }}
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
          Mulai dalam 10 detik. Tanpa isi form panjang, tanpa perlu kartu kredit. Langsung chat nomor WhatsApp resmi kami di {{ botNumberDisplay }}!
        </p>

        <a
          :href="whatsappDirectUrl"
          target="_blank"
          class="inline-flex items-center gap-2.5 px-8 py-4 rounded-2xl bg-gradient-to-r from-emerald-500 to-teal-400 text-slate-950 font-black text-base shadow-xl shadow-emerald-500/25 hover:scale-105 transition"
        >
          <span>💬 Coba Gratis di WhatsApp ({{ botNumberDisplay }})</span>
        </a>
      </div>
    </section>

    <!-- FOOTER -->
    <footer class="border-t border-slate-900 bg-slate-950/90 py-10 px-4 text-center text-xs text-slate-500 space-y-2">
      <p class="font-bold text-slate-400">HematWoy — Smart WhatsApp Personal Finance Tracker</p>
      <p>&copy; {{ currentYear }} HematWoy Indonesia. Seluruh hak cipta dilindungi undang-undang.</p>
    </footer>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { useAuthStore } from '../stores/auth';

const authStore = useAuthStore();
const currentYear = new Date().getFullYear();

// WhatsApp Bot Number from .env with fallback
const botRawNumber = import.meta.env.VITE_WHATSAPP_BOT_NUMBER || '15556711016';
const botNumberDisplay = '+1 (555) 671-1016';

const whatsappDirectUrl = computed(() => {
  return `https://wa.me/${botRawNumber}?text=Halo%20HematWoy,%20saya%20mau%20coba%20catat%20keuangan!`;
});

const whatsappDirectUrlWithPlan = computed(() => {
  return `https://wa.me/${botRawNumber}?text=Halo%20HematWoy,%20saya%20mau%20langganan%20Paket%20Pro!`;
});

const whatsappDirectUrlWithAnnualPlan = computed(() => {
  return `https://wa.me/${botRawNumber}?text=Halo%20HematWoy,%20saya%20mau%20langganan%20Paket%20Tahunan!`;
});

// Simulator state & anti-refresh attempts management
const attempts = ref(0);
const isLocked = ref(false);
const sending = ref(false);
const visitorToken = ref('');

const presetChips = [
  'keluar 25rb kopi kenangan via bca',
  '+1.5jt freelance website',
  'saldo',
  'budget makan 1jt',
  'langganan',
];

const chatMessages = ref([
  {
    sender: 'bot',
    text: '👋 Halo! Saya asisten finansial resmi HematWoy 💰\nCoba ketik pengeluaranmu (misal: "keluar 25rb kopi kenangan via bca") atau klik tombol di atas!',
    time: '09:00',
  },
]);

const inputChat = ref('');

function getOrCreateVisitorToken() {
  let token = localStorage.getItem('hw_sim_visitor_token');
  if (!token) {
    token = 'tok_' + Math.random().toString(36).substring(2, 15) + Date.now().toString(36);
    localStorage.setItem('hw_sim_visitor_token', token);
  }
  return token;
}

async function checkSimulatorStatus() {
  visitorToken.value = getOrCreateVisitorToken();
  const localAttempts = parseInt(localStorage.getItem('hw_sim_attempts') || '0', 10);
  attempts.value = localAttempts;

  if (localAttempts >= 3) {
    isLocked.value = true;
    return;
  }

  try {
    const res = await axios.get(`/api/v1/simulator/status?visitor_token=${visitorToken.value}`);
    if (res.data.is_locked || res.data.attempts >= 3) {
      isLocked.value = true;
      attempts.value = 3;
      localStorage.setItem('hw_sim_attempts', '3');
    } else {
      attempts.value = Math.max(localAttempts, res.data.attempts);
      localStorage.setItem('hw_sim_attempts', String(attempts.value));
    }
  } catch (err) {
    // If offline or network issue, rely on localStorage
    if (localAttempts >= 3) {
      isLocked.value = true;
    }
  }
}

function runPreset(text) {
  if (isLocked.value || attempts.value >= 3) return;
  inputChat.value = text;
  sendCustomChat();
}

async function sendCustomChat() {
  const query = inputChat.value.trim();
  if (!query || sending.value) return;

  if (isLocked.value || attempts.value >= 3) {
    isLocked.value = true;
    return;
  }

  const now = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });

  chatMessages.value.push({
    sender: 'user',
    text: query,
    time: now,
  });

  inputChat.value = '';
  sending.value = true;

  try {
    const res = await axios.post('/api/v1/simulator', {
      message_text: query,
      visitor_token: visitorToken.value,
    });

    const reply = res.data.reply_text;
    attempts.value = res.data.attempts;
    localStorage.setItem('hw_sim_attempts', String(attempts.value));

    chatMessages.value.push({
      sender: 'bot',
      text: reply,
      time: now,
    });

    if (attempts.value >= 3) {
      setTimeout(() => {
        isLocked.value = true;
      }, 1800);
    }
  } catch (err) {
    if (err.response?.status === 429 || err.response?.data?.status === 'LIMIT_REACHED') {
      isLocked.value = true;
      attempts.value = 3;
      localStorage.setItem('hw_sim_attempts', '3');
    } else {
      chatMessages.value.push({
        sender: 'bot',
        text: '⚠️ Terjadi kendala teknis saat memproses simulator. Silakan coba lagi.',
        time: now,
      });
    }
  } finally {
    sending.value = false;
  }
}

onMounted(() => {
  checkSimulatorStatus();
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
    a: 'Di WhatsApp, kamu mendapatkan 100 kuota pencatatan percakapan finansial secara gratis! Setelah itu, kamu bisa berlangganan Paket Pro mulai Rp25.000/bulan.',
    open: false,
  },
  {
    q: 'Metode pembayaran apa saja yang diterima untuk paket Pro?',
    a: 'Kami mendukung pembayaran instan via Midtrans: QRIS (GoPay, OVO, Dana, ShopeePay, BCA Mobile), Transfer Bank (BCA, Mandiri, BNI, BRI), dan Alfamart/Indomaret.',
    open: false,
  },
]);
</script>
