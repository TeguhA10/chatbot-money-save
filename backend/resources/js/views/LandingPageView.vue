<template>
  <div class="min-h-screen bg-slate-950 text-slate-100 font-sans selection:bg-emerald-500 selection:text-white relative overflow-hidden">
    <!-- Ambient Gradients -->
    <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[1100px] h-[550px] bg-gradient-to-b from-emerald-500/15 via-teal-500/10 to-transparent blur-3xl pointer-events-none"></div>
    <div class="absolute top-96 -left-48 w-96 h-96 bg-emerald-600/10 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute top-[1200px] -right-48 w-96 h-96 bg-teal-600/10 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute top-[2200px] left-1/3 w-80 h-80 bg-cyan-600/10 rounded-full blur-3xl pointer-events-none"></div>

    <!-- Sticky Navigation Bar (Shared Component) -->
    <PublicNavbar activeRoute="landing" />

    <!-- HERO SECTION -->
    <section class="relative pt-14 sm:pt-20 pb-16 sm:pb-20 px-4 sm:px-6 lg:px-8 max-w-6xl mx-auto text-center">
      <!-- Top Social Proof Pill -->
      <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-semibold mb-6 shadow-sm shadow-emerald-500/10">
        <span class="flex h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
        <span>✨ Didukung Voice Note WhatsApp & Asisten AI Finansial Viona</span>
      </div>

      <!-- Main Headline -->
      <h1 class="text-3xl sm:text-5xl lg:text-6xl font-black text-white tracking-tight leading-[1.18] max-w-4xl mx-auto">
        Catat Keuangan Harian Semudah
        <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 via-teal-300 to-cyan-400">
          Kirim Chat & Voice Note
        </span>
      </h1>

      <!-- Subheadline -->
      <p class="mt-4 sm:mt-6 text-sm sm:text-lg text-slate-300 max-w-2xl mx-auto leading-relaxed">
        Bebas ribet instal aplikasi berat. Cukup ketik pesan atau kirim rekaman suara (VN), asisten cerdas <strong class="text-white">Viona</strong> otomatis mencatat mutasi, mengelola multi-dompet real-time, dan mengawal target tabungan bebas boncos.
      </p>

      <!-- Primary CTAs -->
      <div class="mt-7 sm:mt-10 max-w-lg mx-auto space-y-3">
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
            <span>📖 Panduan & Format Chat</span>
            <span class="text-emerald-400">➔</span>
          </router-link>
        </div>

        <!-- Quota reassurance note -->
        <p class="text-xs text-slate-400 text-center font-medium">
          ✨ <span class="text-emerald-400 font-semibold">{{ freeLimit }} Percakapan Pertama Gratis</span> • Tanpa Kartu Kredit • Siap Pakai di WhatsApp
        </p>
      </div>

      <!-- Trust Badges -->
      <div class="mt-10 sm:mt-12 flex flex-wrap items-center justify-center gap-3 sm:gap-4 text-xs text-slate-400">
        <div class="flex items-center gap-1.5 bg-slate-900/80 border border-slate-800 px-3.5 py-1.5 rounded-full">
          <span class="text-emerald-400 font-bold">🎙️</span>
          <span>Voice Note AI</span>
        </div>
        <div class="flex items-center gap-1.5 bg-slate-900/80 border border-slate-800 px-3.5 py-1.5 rounded-full">
          <span class="text-teal-400 font-bold">✨</span>
          <span>Asisten AI Viona</span>
        </div>
        <div class="flex items-center gap-1.5 bg-slate-900/80 border border-slate-800 px-3.5 py-1.5 rounded-full">
          <span class="text-cyan-400 font-bold">🏆</span>
          <span>Target Tabungan</span>
        </div>
        <div class="flex items-center gap-1.5 bg-slate-900/80 border border-slate-800 px-3.5 py-1.5 rounded-full">
          <span class="text-emerald-400 font-bold">📑</span>
          <span>Excel & Rekening Koran PDF</span>
        </div>
        <div class="flex items-center gap-1.5 bg-slate-900/80 border border-slate-800 px-3.5 py-1.5 rounded-full">
          <span class="text-emerald-400 font-bold">🔒</span>
          <span>Enkripsi Standar Bank</span>
        </div>
      </div>
    </section>

    <!-- SECTION 2: INTERACTIVE DOCS & PREVIEW BANNER -->
    <section class="py-10 px-4 sm:px-6 lg:px-8 max-w-5xl mx-auto">
      <div class="relative rounded-3xl p-6 sm:p-10 bg-gradient-to-r from-slate-900/95 via-slate-900 to-emerald-950/40 border border-emerald-500/30 shadow-2xl backdrop-blur-xl overflow-hidden flex flex-col md:flex-row items-center justify-between gap-6 sm:gap-8">
        <div class="max-w-xl space-y-3 text-center md:text-left">
          <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/15 border border-emerald-500/30 text-emerald-400 text-xs font-bold">
            <span>⚡</span>
            <span>Panduan Interaktif & Format Perintah Lengkap</span>
          </div>
          <h2 class="text-xl sm:text-3xl font-black text-white tracking-tight">
            Ingin Coba Perintah Bot Langsung di Browser?
          </h2>
          <p class="text-xs sm:text-sm text-slate-400 leading-relaxed">
            Kunjungi halaman <span class="text-emerald-400 font-semibold">Dokumentasi</span> kami untuk mencoba format chat cerdas WhatsApp secara langsung di simulator browser dan melihat daftar lengkap perintah bot.
          </p>
          <div class="pt-1 flex flex-wrap gap-2 justify-center md:justify-start">
            <span class="text-[11px] px-2.5 py-1 rounded-lg bg-emerald-950/80 border border-emerald-500/40 text-emerald-300 font-mono">🎙️ [Kirim Voice Note VN]</span>
            <span class="text-[11px] px-2.5 py-1 rounded-lg bg-teal-950/80 border border-teal-500/40 text-teal-300 font-mono">"Halo Viona, sisa budget berapa?"</span>
            <span class="text-[11px] px-2.5 py-1 rounded-lg bg-slate-950 border border-slate-800 text-slate-400 font-mono">buat target Liburan 10jt</span>
            <span class="text-[11px] px-2.5 py-1 rounded-lg bg-slate-950 border border-slate-800 text-slate-400 font-mono">keluar 25rb kopi</span>
            <span class="text-[11px] px-2.5 py-1 rounded-lg bg-slate-950 border border-slate-800 text-slate-400 font-mono">+1.5jt freelance</span>
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
            <span>Mulai Chat di WhatsApp</span>
          </a>
        </div>
      </div>
    </section>

    <!-- SECTION 3: FEATURES BENTO GRID (UPGRADED WITH ALL NEW FEATURES) -->
    <section id="fitur" class="py-20 px-4 sm:px-6 lg:px-8 max-w-6xl mx-auto">
      <div class="text-center mb-16">
        <span class="text-xs font-bold text-emerald-400 uppercase tracking-widest block mb-2">Fitur Unggulan HematWoy</span>
        <h2 class="text-3xl sm:text-4xl font-black text-white tracking-tight">Semua yang Kamu Butuhkan untuk Finansial Rapi & Terkendali</h2>
        <p class="text-xs sm:text-sm text-slate-400 max-w-xl mx-auto mt-2">
          Dari pencatatan kilat via suara hingga visual analytics di web, semua dirancang agar kamu bebas boncos tanpa pusing.
        </p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        <!-- Feature 1: Voice Note WhatsApp (FEATURED 2-COL ON MD) -->
        <div class="md:col-span-2 bg-gradient-to-br from-slate-900/90 via-slate-900/70 to-emerald-950/30 border border-emerald-500/40 rounded-3xl p-5 sm:p-8 backdrop-blur-md relative overflow-hidden group hover:border-emerald-400/60 transition shadow-xl">
          <div class="flex items-center justify-between gap-3 mb-4">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/20 border border-emerald-500/30 text-emerald-300 text-xs font-bold">
              <span>🎙️ Fitur Favorit Baru</span>
            </div>
            <span class="text-[11px] text-slate-500 font-mono">Whisper AI STT</span>
          </div>

          <h3 class="text-xl sm:text-2xl font-black text-white mb-2">Pencatatan Super Kilat via Voice Note WhatsApp</h3>
          <p class="text-xs sm:text-sm text-slate-400 leading-relaxed max-w-xl mb-5">
            Lagi nyetir motor, belanja di pasar, atau malas mengetik? Cukup tekan tombol rekam suara (VN) di WhatsApp dan bicara santai. AI mentranskripsi suara bahasa Indonesia dan langsung mencatatnya ke dompet yang pas!
          </p>

          <!-- Interactive Voice Note Audio Mockup (Responsive & Mobile-Friendly) -->
          <div class="bg-slate-950/90 border border-slate-800/90 rounded-2xl p-3.5 sm:p-4 space-y-3">
            <!-- Audio Player Bar -->
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-emerald-500/20 border border-emerald-500/30 text-emerald-400 flex items-center justify-center shrink-0 text-xs sm:text-sm shadow-sm shadow-emerald-500/10">
                ▶
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between text-[11px] text-slate-400 mb-1">
                  <span class="flex items-center gap-1 font-medium text-slate-300">
                    <span>🎤</span> Pesan Suara
                  </span>
                  <span class="font-mono text-emerald-400 text-[10px]">0:07</span>
                </div>
                <!-- Waveform Bars -->
                <div class="flex items-center gap-1 h-4 sm:h-5 overflow-hidden">
                  <div class="w-1 h-2.5 bg-emerald-400 rounded-full"></div>
                  <div class="w-1 h-4 bg-emerald-400 rounded-full"></div>
                  <div class="w-1 h-2 bg-emerald-400 rounded-full"></div>
                  <div class="w-1 h-4.5 bg-emerald-400 rounded-full"></div>
                  <div class="w-1 h-3 bg-emerald-400 rounded-full"></div>
                  <div class="w-1 h-4 bg-emerald-400/80 rounded-full"></div>
                  <div class="w-1 h-2 bg-emerald-400/70 rounded-full"></div>
                  <div class="w-1 h-4.5 bg-emerald-400/70 rounded-full"></div>
                  <div class="w-1 h-3 bg-emerald-400/50 rounded-full"></div>
                  <div class="w-1 h-2 bg-emerald-400/40 rounded-full"></div>
                  <div class="w-1 h-3.5 bg-emerald-400/30 rounded-full"></div>
                  <div class="w-1 h-2 bg-emerald-400/20 rounded-full"></div>
                  <div class="w-1 h-1.5 bg-emerald-400/20 rounded-full"></div>
                </div>
              </div>
            </div>

            <!-- Transcription & Bot Confirmation (Stacked cleanly on mobile, inline on tablet/desktop) -->
            <div class="pt-2 border-t border-slate-900/90 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
              <div class="flex items-start gap-1.5 min-w-0">
                <span class="text-xs text-slate-500">💬</span>
                <p class="text-[11px] sm:text-xs text-slate-200 font-mono italic leading-relaxed">
                  "Viona, catat makan sate 35 ribu pakai Cash"
                </p>
              </div>
              <div class="inline-flex items-center gap-1 self-start sm:self-center px-2.5 py-1 rounded-lg bg-emerald-500/10 border border-emerald-500/25 text-emerald-300 text-[10px] sm:text-[11px] font-semibold shrink-0">
                <span>✓</span>
                <span>Tercatat di Dompet Cash</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Feature 2: Asisten AI Finansial "Viona" -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-5 sm:p-7 backdrop-blur-md hover:border-slate-700 transition flex flex-col justify-between">
          <div>
            <div class="w-12 h-12 rounded-2xl bg-teal-500/15 text-teal-400 flex items-center justify-center text-2xl mb-4">
              🤖
            </div>
            <div class="inline-block px-2.5 py-0.5 rounded-full bg-teal-500/10 border border-teal-500/30 text-teal-400 text-[10px] font-bold mb-2">
              ✨ Gemini AI Agent
            </div>
            <h3 class="text-lg font-bold text-white mb-2">Asisten Keuangan Pribadi "Viona"</h3>
            <p class="text-xs sm:text-sm text-slate-400 leading-relaxed mb-4">
              Ngobrol santai seputar keuangan. Sapa <code class="text-teal-400">"Halo Viona"</code> untuk tanya sisa anggaran, minta saran hemat, atau koreksi transaksi secara natural tanpa sintaks kaku.
            </p>
          </div>
          <div class="p-3 rounded-xl bg-slate-950/70 border border-slate-800 text-[11px] text-slate-300 italic">
            "Halo Alfaruqi! Bulan ini pengeluaranmu hemat 15% dibanding bulan lalu lho, pertahankan ya! 😊"
          </div>
        </div>

        <!-- Feature 3: Target Tabungan (Financial Goals) -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-5 sm:p-7 backdrop-blur-md hover:border-slate-700 transition flex flex-col justify-between">
          <div>
            <div class="w-12 h-12 rounded-2xl bg-cyan-500/15 text-cyan-400 flex items-center justify-center text-2xl mb-4">
              🏆
            </div>
            <div class="inline-block px-2.5 py-0.5 rounded-full bg-cyan-500/10 border border-cyan-500/30 text-cyan-400 text-[10px] font-bold mb-2">
              🎯 Anti-Wacana
            </div>
            <h3 class="text-lg font-bold text-white mb-2">Target Tabungan & Pos Impian</h3>
            <p class="text-xs sm:text-sm text-slate-400 leading-relaxed mb-4">
              Wujudkan beli gadget, liburan, atau dana darurat. Ketik <code class="text-cyan-400">buat target Laptop 15jt</code>, setor dana berkala, dan pantau progress bar pencapaian impianmu.
            </p>
          </div>
          <div class="p-3 rounded-xl bg-slate-950/70 border border-slate-800 space-y-1.5">
            <div class="flex justify-between text-[11px] text-slate-300 font-semibold">
              <span>🎯 Laptop Idaman</span>
              <span class="text-cyan-400">80%</span>
            </div>
            <div class="w-full bg-slate-800 h-2 rounded-full overflow-hidden">
              <div class="bg-gradient-to-r from-cyan-500 to-teal-400 h-full w-[80%] rounded-full"></div>
            </div>
            <div class="text-[10px] text-slate-500 flex justify-between">
              <span>Terkumpul: Rp12.000.000</span>
              <span>Target: Rp15jt</span>
            </div>
          </div>
        </div>

        <!-- Feature 4: Multi-Dompet & Transfer -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-5 sm:p-7 backdrop-blur-md hover:border-slate-700 transition flex flex-col justify-between">
          <div>
            <div class="w-12 h-12 rounded-2xl bg-emerald-500/15 text-emerald-400 flex items-center justify-center text-2xl mb-4">
              👛
            </div>
            <h3 class="text-lg font-bold text-white mb-2">Multi-Dompet & Transfer Instan</h3>
            <p class="text-xs sm:text-sm text-slate-400 leading-relaxed mb-4">
              Kelola saldo terpisah untuk BCA, Mandiri, SeaBank, Cash, GoPay, dan OVO. Pindah dana semudah ketik: <code class="text-emerald-400">transfer 200rb dari bca ke gopay</code>.
            </p>
          </div>
          <div class="flex flex-wrap gap-1.5 pt-2">
            <span class="text-[10px] px-2 py-0.5 rounded bg-slate-950 border border-slate-800 text-slate-400">BCA</span>
            <span class="text-[10px] px-2 py-0.5 rounded bg-slate-950 border border-slate-800 text-slate-400">Mandiri</span>
            <span class="text-[10px] px-2 py-0.5 rounded bg-slate-950 border border-slate-800 text-slate-400">SeaBank</span>
            <span class="text-[10px] px-2 py-0.5 rounded bg-slate-950 border border-slate-800 text-slate-400">GoPay</span>
            <span class="text-[10px] px-2 py-0.5 rounded bg-slate-950 border border-slate-800 text-slate-400">Cash</span>
          </div>
        </div>

        <!-- Feature 5: Plafon Anggaran Anti-Boncos -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-5 sm:p-7 backdrop-blur-md hover:border-slate-700 transition flex flex-col justify-between">
          <div>
            <div class="w-12 h-12 rounded-2xl bg-teal-500/15 text-teal-400 flex items-center justify-center text-2xl mb-4">
              🎯
            </div>
            <h3 class="text-lg font-bold text-white mb-2">Plafon Anggaran Anti-Boncos</h3>
            <p class="text-xs sm:text-sm text-slate-400 leading-relaxed mb-4">
              Pasang limit belanja bulanan (<code class="text-teal-400">budget 2jt</code>). Sistem otomatis memperingatkan saat pengeluaran mendekati 80% sebelum kantongmu kebobolan.
            </p>
          </div>
          <div class="p-2.5 rounded-xl bg-slate-950/70 border border-amber-500/30 text-[11px] text-amber-300 flex items-center gap-2">
            <span>⚠️</span>
            <span>Budget bulanan sudah terpakai 78%, jaga pengeluaran ya!</span>
          </div>
        </div>

        <!-- Feature 6: Web Dashboard & Visual Analytics (FEATURED ON LG) -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-5 sm:p-7 backdrop-blur-md hover:border-slate-700 transition flex flex-col justify-between">
          <div>
            <div class="w-12 h-12 rounded-2xl bg-cyan-500/15 text-cyan-400 flex items-center justify-center text-2xl mb-4">
              📊
            </div>
            <div class="inline-block px-2.5 py-0.5 rounded-full bg-cyan-500/10 border border-cyan-500/30 text-cyan-400 text-[10px] font-bold mb-2">
              🌐 Web App
            </div>
            <h3 class="text-lg font-bold text-white mb-2">Web Dashboard & Visual Analytics</h3>
            <p class="text-xs sm:text-sm text-slate-400 leading-relaxed mb-4">
              WhatsApp untuk catat cepat harian (maks 1 bulan agar rapi). Buka <strong class="text-white">Web Dashboard</strong> untuk grafik interaktif, tren arus kas, dan riwayat mutasi lengkap tanpa batasan.
            </p>
          </div>
          <button
            @click="handleDashboardClick"
            class="w-full py-2.5 rounded-xl bg-slate-800 hover:bg-slate-750 text-slate-200 text-xs font-semibold flex items-center justify-center gap-1.5 transition cursor-pointer"
          >
            <span>Buka Dashboard Web</span>
            <span>➔</span>
          </button>
        </div>

        <!-- Feature 7: Ekspor Laporan Excel & Rekening Koran PDF -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-5 sm:p-7 backdrop-blur-md hover:border-slate-700 transition flex flex-col justify-between">
          <div>
            <div class="w-12 h-12 rounded-2xl bg-emerald-500/15 text-emerald-400 flex items-center justify-center text-2xl mb-4">
              📑
            </div>
            <h3 class="text-lg font-bold text-white mb-2">Ekspor Excel & Rekening Koran PDF</h3>
            <p class="text-xs sm:text-sm text-slate-400 leading-relaxed mb-4">
              Ketik <code class="text-emerald-400">export excel</code> atau <code class="text-emerald-400">export pdf</code>, laporan keuangan formal lengkap formula SUM atau Rekening Koran resmi ber-kop langsung terkirim ke WhatsApp.
            </p>
          </div>
          <div class="text-[11px] text-slate-400 flex items-center gap-2">
            <span>✓ Multi-Sheet XLSX</span>
            <span>•</span>
            <span>✓ PDF Siap Cetak</span>
          </div>
        </div>

        <!-- Feature 8: Keamanan & Enkripsi Standar Perbankan -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-5 sm:p-7 backdrop-blur-md hover:border-slate-700 transition flex flex-col justify-between">
          <div>
            <div class="w-12 h-12 rounded-2xl bg-teal-500/15 text-teal-400 flex items-center justify-center text-2xl mb-4">
              🔒
            </div>
            <h3 class="text-lg font-bold text-white mb-2">Keamanan Enkripsi Bank Grade</h3>
            <p class="text-xs sm:text-sm text-slate-400 leading-relaxed mb-4">
              Privasi nominal uangmu dilindungi enkripsi AES-256-GCM dan sistem PIN rahasia. Data terisolasi ketat per nomor WhatsApp tanpa ada pihak ketiga yang bisa mengintip.
            </p>
          </div>
          <div class="text-[11px] text-emerald-400 font-semibold flex items-center gap-1">
            <span>🛡️ Zero-Knowledge Architecture</span>
          </div>
        </div>
      </div>
    </section>

    <!-- SECTION 4: PRICING -->
    <section id="harga" class="py-20 px-4 sm:px-6 lg:px-8 max-w-6xl mx-auto">
      <div class="text-center mb-16">
        <span class="text-xs font-bold text-emerald-400 uppercase tracking-widest block mb-2">Pilihan Paket Investasi</span>
        <h2 class="text-3xl sm:text-4xl font-black text-white tracking-tight">Investasi Murah untuk Mengontrol Keuanganmu</h2>
        <p class="text-xs sm:text-sm text-slate-400 mt-2">Mulai gratis tanpa komitmen. Upgrade kapan saja saat siap lebih rapi.</p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-8 items-stretch">
        <!-- Tier 1: Free -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-6 sm:p-8 flex flex-col justify-between">
          <div>
            <h3 class="text-lg font-bold text-white mb-1">Paket Gratis</h3>
            <p class="text-xs text-slate-400 mb-6">Cocok untuk mencoba pengalaman pertama</p>
            <div class="text-3xl font-black text-white mb-6">
              Rp 0 <span class="text-xs font-normal text-slate-400">/ selamanya</span>
            </div>

            <ul class="space-y-3 text-xs text-slate-300">
              <li class="flex items-center gap-2">✓ {{ freeLimit }} Kuota Transaksi Chat & Voice Note</li>
              <li class="flex items-center gap-2">✓ 1 Dompet Kas Utama</li>
              <li class="flex items-center gap-2">✓ Rekap Mutasi Harian & Bulanan</li>
              <li class="flex items-center gap-2 text-slate-500">✕ Multi-Dompet & Transfer Antar Bank</li>
              <li class="flex items-center gap-2 text-slate-500">✕ Target Tabungan (Goals Tracking)</li>
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
        <p class="text-xs sm:text-sm text-slate-400 mt-1">Semua jawaban yang perlu kamu ketahui sebelum mulai menggunakan HematWoy.</p>
      </div>

      <div class="space-y-4">
        <div
          v-for="(faq, i) in faqs"
          :key="i"
          class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 cursor-pointer hover:border-slate-700 transition"
          @click="faq.open = !faq.open"
        >
          <div class="flex items-center justify-between font-bold text-sm text-white">
            <span>{{ faq.q }}</span>
            <span class="text-emerald-400 text-lg ml-3">{{ faq.open ? '−' : '+' }}</span>
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
        <p class="text-xs sm:text-sm text-slate-300 max-w-xl mx-auto mb-8 leading-relaxed">
          Mulai dalam 10 detik. Tanpa isi form panjang, tanpa perlu kartu kredit. Langsung hubungi asisten bot resmi kami di {{ botNumberDisplay }}!
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
      <p class="font-medium text-slate-400">Smart WhatsApp Personal Finance Tracker & AI Assistant</p>
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
      'Unlimited Catat Transaksi via Teks & Voice Note 🎙️',
      'Asisten AI Viona & Smart Insights ✨',
      'Multi-Dompet (BCA, Mandiri, SeaBank, Cash, e-Wallet)',
      'Target Tabungan (Financial Goals Tracking) 🏆',
      'Plafon Anggaran & Anti-Boncos Alert 🎯',
      'Ekspor Laporan Excel & Rekening Koran PDF 📑',
      'Akses Penuh Web App Dashboard & Visual Analytics 📊',
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
      'Seluruh Fitur Paket Pro Tanpa Batas',
      'Masa Aktif 365 Hari Penuh',
      'Voice Note AI Whisper Unlimited 🎙️',
      'Target Tabungan & Pos Impian Unlimited 🏆',
      'Hemat Rp 101.000 dibanding bulanan',
      'Prioritas Akses Fitur AI Baru Mendatang',
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
    a: () => `Cukup klik tombol "Mulai Coba Gratis" di atas atau simpan nomor bot WhatsApp resmi kami di ${botNumberDisplay.value}. Kamu bisa langsung kirim pesan teks atau rekaman suara (Voice Note), tanpa perlu registrasi yang rumit!`,
    open: false,
  },
  {
    q: 'Apakah saya bisa mencatat keuangan menggunakan rekaman suara (Voice Note)?',
    a: 'Bisa banget! Kamu cukup tekan dan tahan tombol rekam suara (VN) di WhatsApp, lalu bicara santai misalnya: "Viona, catat makan sate padang 35 ribu pakai Cash". AI kami langsung mentranskripsi suara dan mencatat transaksi ke dompetmu secara instan.',
    open: false,
  },
  {
    q: 'Siapa itu Asisten AI "Viona" dan apa keunggulannya?',
    a: 'Viona adalah asisten AI perencana keuangan pribadi yang ramah dan cerdas (ditenagai Google Gemini). Kamu bisa mengobrol santai seputar tips hemat, menanyakan sisa anggaran bulan ini, atau meminta evaluasi pengeluaran tanpa perlu mengingat format sintaks kaku.',
    open: false,
  },
  {
    q: 'Mengapa di chat WhatsApp riwayat transaksi dibatasi 1 bulan terakhir?',
    a: 'Layanan chat WhatsApp dioptimalkan untuk pencatatan cepat harian agar layar percakapan tetap rapi, bersih, dan nyaman dibaca. Untuk riwayat transaksi lengkap lebih dari 1 bulan dan grafik visual interaktif, kamu bisa mengaksesnya langsung di Web App Dashboard HematWoy.',
    open: false,
  },
  {
    q: 'Apakah data keuangan saya aman?',
    a: 'Sangat aman. Setiap data nominal terenkripsi rapat menggunakan standar perbankan AES-256-GCM. Kami mengisolasi database secara ketat per nomor WhatsApp dan tidak pernah membagikan data kepada pihak ketiga.',
    open: false,
  },
  {
    q: 'Berapa batas penggunaan gratis di WhatsApp?',
    a: () => `Di WhatsApp, kamu mendapatkan ${freeLimit.value} kuota percakapan pencatatan keuangan gratis! Setelah kuota habis, kamu bisa bebas melanjutkan dengan berlangganan Paket Pro mulai Rp25.000/bulan.`,
    open: false,
  },
  {
    q: 'Metode pembayaran apa saja yang diterima untuk paket Pro?',
    a: 'Kami mendukung pembayaran instan otomatis: QRIS (GoPay, OVO, Dana, ShopeePay, BCA Mobile), Transfer Bank Virtual Account (BCA, Mandiri, BNI, BRI), dan Alfamart/Indomaret.',
    open: false,
  },
]);
</script>
