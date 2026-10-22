<template>
  <Teleport to="body" :disabled="embedded">
    <div
      v-if="embedded || isOpen"
      :class="embedded ? 'w-full' : 'fixed inset-0 z-[100] flex items-end sm:items-center justify-center p-0 sm:p-4 bg-slate-950/90 backdrop-blur-md overflow-y-auto transition-all duration-300'"
      @click.self="!embedded && handleClose()"
    >
      <!-- Modal Card container -->
      <div
        class="w-full bg-slate-900 border border-slate-800 text-slate-100 shadow-2xl relative overflow-hidden transition-all duration-200"
        :class="[
          embedded
            ? 'rounded-3xl p-4 sm:p-7'
            : 'rounded-t-3xl sm:rounded-3xl max-w-lg max-h-[92vh] sm:max-h-[90vh] flex flex-col p-4 sm:p-6 my-0 sm:my-8 animate-in fade-in slide-in-from-bottom-6 sm:zoom-in-95'
        ]"
      >
        <!-- Ambient Glows -->
        <div class="absolute -top-24 -right-24 w-56 h-56 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-24 -left-24 w-56 h-56 bg-teal-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <!-- Mobile Drag Handle Bar (only on modal mobile) -->
        <div v-if="!embedded" class="w-12 h-1.5 bg-slate-700/60 rounded-full mx-auto mb-3 sm:hidden"></div>

      <!-- Header (Only shown when opened as modal popup) -->
      <div v-if="!embedded" class="flex items-center justify-between pb-3.5 border-b border-slate-800/80 mb-4 shrink-0">
        <div class="flex items-center gap-2.5">
          <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-slate-950 border border-emerald-500/30 flex items-center justify-center p-1.5 shadow-md shadow-emerald-500/10">
            <img src="/images/logo.png" alt="HematWoy Logo" class="w-full h-full object-contain" />
          </div>
          <div>
            <h3 class="font-bold text-sm sm:text-base text-white tracking-tight flex items-center gap-1.5">
              <span>Pembayaran {{ planName }}</span>
              <span class="text-[10px] px-1.5 py-0.2 rounded bg-emerald-500/15 text-emerald-400 font-semibold border border-emerald-500/30">Resmi</span>
            </h3>
            <p class="text-[11px] text-slate-400 font-mono">Order #{{ orderId }}</p>
          </div>
        </div>

        <!-- Close button (modal mode only) -->
        <button
          @click="handleClose"
          class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white flex items-center justify-center transition text-xs sm:text-sm cursor-pointer"
          title="Tutup"
        >
          ✕
        </button>
      </div>

      <!-- Scrollable Body Content -->
      <div :class="embedded ? 'space-y-4' : 'flex-1 overflow-y-auto space-y-4 pr-0 sm:pr-1'">
        <!-- SUCCESS STATE -->
        <div v-if="isSettled" class="py-6 sm:py-8 text-center space-y-4 animate-in fade-in zoom-in-95">
          <div class="w-16 h-16 rounded-3xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-3xl mx-auto border border-emerald-500/40 shadow-xl shadow-emerald-500/20">
            🎉
          </div>
          <div>
            <span class="inline-block px-3 py-0.5 rounded-full bg-emerald-500/15 border border-emerald-500/30 text-emerald-400 text-xs font-bold mb-2">
              PEMBAYARAN TERKONFIRMASI
            </span>
            <h4 class="text-xl sm:text-2xl font-black text-white">Selamat! Akun Pro Aktif</h4>
            <p class="text-xs sm:text-sm text-slate-300 mt-1 max-w-sm mx-auto leading-relaxed">
              Langganan <strong class="text-white">{{ planName }}</strong> berhasil diaktifkan. Anda kini bebas mencatat transaksi tanpa batas!
            </p>
          </div>
          <div class="pt-3 flex flex-col sm:flex-row gap-2.5 justify-center max-w-xs mx-auto">
            <button
              @click="handleSuccessAction"
              class="w-full py-3 px-6 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-400 hover:from-emerald-400 hover:to-teal-300 text-slate-950 font-black text-xs sm:text-sm shadow-lg shadow-emerald-500/25 transition cursor-pointer"
            >
              🚀 Mulai Gunakan Sekarang
            </button>
          </div>
        </div>

        <!-- DETAILS / PAYMENT CHARGED STATE -->
        <div v-else-if="chargeResult" class="space-y-4 animate-in fade-in">
          <!-- Back button & Bill Header -->
          <div class="flex items-center justify-between p-3 rounded-2xl bg-slate-950/70 border border-slate-800">
            <button
              @click="changeMethod"
              class="text-xs text-emerald-400 hover:text-emerald-300 flex items-center gap-1 font-semibold transition cursor-pointer"
            >
              <span>←</span>
              <span>Ganti Metode</span>
            </button>
            <div class="text-right">
              <span class="text-[10px] text-slate-400 block">Total Tagihan:</span>
              <span class="text-sm sm:text-base font-black text-emerald-400">
                Rp {{ formatNumber(grossAmount) }}
              </span>
            </div>
          </div>

          <!-- QRIS VIEW -->
          <div v-if="selectedMethod === 'qris'" class="space-y-3.5">
            <!-- QR Card -->
            <div class="p-4 bg-slate-950 border border-slate-800 rounded-3xl text-center space-y-3">
              <!-- QR Image Frame with QRIS banner -->
              <div class="bg-white p-3.5 rounded-2xl inline-block shadow-xl max-w-[240px] sm:max-w-[260px] mx-auto">
                <div class="flex items-center justify-between pb-1.5 mb-1.5 border-b border-slate-200">
                  <span class="text-[10px] font-black text-slate-900 tracking-wider">QRIS STANDAR</span>
                  <span class="text-[9px] font-bold text-slate-600">GOPAY / BANK</span>
                </div>
                <img
                  v-if="chargeResult.qr_url"
                  :src="chargeResult.qr_url"
                  alt="QRIS Barcode"
                  class="w-44 h-44 sm:w-48 sm:h-48 object-contain mx-auto"
                />
                <div v-else class="w-44 h-44 flex items-center justify-center text-xs text-slate-400">
                  Memuat QR...
                </div>
                <div class="pt-1 text-[9px] text-slate-500 font-medium truncate">
                  NMID: HematWoy Pro
                </div>
              </div>

              <!-- Supported Apps Strip -->
              <div class="space-y-1">
                <div class="flex items-center justify-center gap-1.5 text-[11px] font-semibold text-emerald-400">
                  <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                  <span>Bisa di-scan oleh semua aplikasi:</span>
                </div>
                <p class="text-[10px] text-slate-400 max-w-xs mx-auto leading-normal">
                  BCA, Mandiri, BRI, BNI, GoPay, OVO, DANA, ShopeePay & LinkAja
                </p>
              </div>

              <!-- Countdown Timer & Actions -->
              <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-900/90 border border-slate-800 text-xs">
                <span class="text-slate-400 flex items-center gap-1 text-[11px]">
                  <span>⏱️</span>
                  <span>Batas Waktu:</span>
                </span>
                <span class="font-mono font-bold text-amber-400 text-sm tracking-wide">{{ countdownText }}</span>
              </div>

              <!-- Quick Helper Buttons -->
              <div class="grid grid-cols-2 gap-2 pt-1">
                <a
                  v-if="chargeResult.qr_url"
                  :href="chargeResult.qr_url"
                  download="hematwoy-qris.png"
                  target="_blank"
                  class="py-2.5 px-3 rounded-xl bg-slate-800 hover:bg-slate-750 border border-slate-700 text-slate-200 font-semibold text-xs flex items-center justify-center gap-1.5 transition text-center"
                >
                  <span>📥</span>
                  <span>Unduh QR</span>
                </a>
                <button
                  type="button"
                  @click="copyNominal"
                  class="py-2.5 px-3 rounded-xl bg-slate-800 hover:bg-slate-750 border border-slate-700 text-slate-200 font-semibold text-xs flex items-center justify-center gap-1.5 transition cursor-pointer"
                >
                  <span>{{ copiedAmount ? '✓ Tersalin' : '📋 Salin Nominal' }}</span>
                </button>
              </div>
            </div>

            <!-- Primary Action: Manual Status Check -->
            <button
              @click="checkStatus"
              :disabled="checkingStatus"
              class="w-full py-3.5 px-4 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-400 hover:from-emerald-400 hover:to-teal-300 text-slate-950 font-black text-xs sm:text-sm shadow-xl shadow-emerald-500/20 transition cursor-pointer flex items-center justify-center gap-2 disabled:opacity-50 active:scale-98"
            >
              <span v-if="checkingStatus" class="w-4 h-4 border-2 border-slate-950 border-t-transparent rounded-full animate-spin"></span>
              <span>{{ checkingStatus ? 'Memeriksa Pembayaran...' : 'Saya Sudah Bayar (Cek Status)' }}</span>
            </button>

            <!-- Step by Step Guide Accordion -->
            <div class="rounded-2xl bg-slate-950/60 border border-slate-800/80 p-3 text-xs text-slate-300 space-y-2">
              <div class="font-bold text-slate-200 flex items-center gap-1.5 text-[11px]">
                <span>💡</span>
                <span>Cara Membayar QRIS:</span>
              </div>
              <ol class="list-decimal list-inside space-y-1 text-[11px] text-slate-400 leading-relaxed">
                <li>Buka aplikasi m-Banking (BCA, Livin, BRImo) atau e-Wallet (GoPay, OVO, DANA).</li>
                <li>Pilih menu <strong>Bayar / QRIS</strong>, lalu scan barcode di atas atau unggah hasil unduhan.</li>
                <li>Periksa nominal tagihan (<strong>Rp {{ formatNumber(grossAmount) }}</strong>), lalu masukkan PIN.</li>
                <li>Setelah berhasil, klik tombol <strong>"Saya Sudah Bayar"</strong> di atas.</li>
              </ol>
            </div>
          </div>

          <!-- VIRTUAL ACCOUNT VIEW -->
          <div v-else class="space-y-3.5">
            <div class="p-4 bg-slate-950 border border-slate-800 rounded-2xl space-y-3">
              <div class="flex items-center justify-between">
                <span class="text-xs text-slate-300 font-bold uppercase tracking-wider flex items-center gap-1.5">
                  <span>🏦</span>
                  <span>{{ formatMethodName(selectedMethod) }}</span>
                </span>
                <span class="text-[10px] px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-400 font-bold border border-emerald-500/30">
                  Otomatis Terverifikasi
                </span>
              </div>

              <!-- VA Number Box with 1-Click Copy -->
              <div class="bg-slate-900 border border-slate-800/90 rounded-2xl p-3.5 space-y-2">
                <div class="flex items-center justify-between">
                  <span class="text-[10px] text-slate-400">Nomor Virtual Account / Tagihan:</span>
                  <button
                    @click="copyVaNumber"
                    class="py-1 px-2.5 rounded-lg bg-emerald-500/15 hover:bg-emerald-500/25 text-emerald-300 font-bold text-xs flex items-center gap-1 transition cursor-pointer active:scale-95"
                  >
                    <span>{{ copiedVa ? '✓ Tersalin!' : '📋 Salin VA' }}</span>
                  </button>
                </div>
                <div class="font-mono text-lg sm:text-xl font-black text-white tracking-wider break-all select-all">
                  {{ chargeResult.va_number || chargeResult.bill_key || 'Menunggu VA...' }}
                </div>
                <div v-if="chargeResult.biller_code" class="pt-1.5 border-t border-slate-800 text-[11px] text-slate-400 flex justify-between items-center">
                  <span>Kode Perusahaan (Biller):</span>
                  <strong class="font-mono text-white text-xs">{{ chargeResult.biller_code }}</strong>
                </div>
              </div>

              <!-- Total Amount Box with 1-Click Copy -->
              <div class="bg-slate-900/60 border border-slate-800/80 rounded-xl p-2.5 flex items-center justify-between text-xs">
                <div>
                  <span class="text-[10px] text-slate-400 block">Jumlah yang Harus Ditransfer:</span>
                  <span class="font-black text-emerald-400 text-sm">Rp {{ formatNumber(grossAmount) }}</span>
                </div>
                <button
                  type="button"
                  @click="copyNominal"
                  class="text-[11px] text-slate-300 hover:text-white bg-slate-800 px-2.5 py-1 rounded-lg border border-slate-700 cursor-pointer"
                >
                  {{ copiedAmount ? '✓ Tersalin' : 'Salin Jumlah' }}
                </button>
              </div>

              <!-- Countdown Timer -->
              <div class="flex items-center justify-between p-2 rounded-xl bg-slate-900/80 border border-slate-800 text-xs">
                <span class="text-slate-400 text-[11px]">⏱️ Sisa Waktu Pembayaran:</span>
                <span class="font-mono font-bold text-amber-400 text-xs">{{ countdownText }}</span>
              </div>
            </div>

            <!-- Primary Action: Manual Status Check -->
            <button
              @click="checkStatus"
              :disabled="checkingStatus"
              class="w-full py-3.5 px-4 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-400 hover:from-emerald-400 hover:to-teal-300 text-slate-950 font-black text-xs sm:text-sm shadow-xl shadow-emerald-500/20 transition cursor-pointer flex items-center justify-center gap-2 disabled:opacity-50 active:scale-98"
            >
              <span v-if="checkingStatus" class="w-4 h-4 border-2 border-slate-950 border-t-transparent rounded-full animate-spin"></span>
              <span>{{ checkingStatus ? 'Memeriksa Pembayaran...' : 'Saya Sudah Bayar (Cek Status)' }}</span>
            </button>

            <!-- Bank Specific Instruction Guide -->
            <div class="rounded-2xl bg-slate-950/60 border border-slate-800/80 p-3 text-xs text-slate-300 space-y-2">
              <div class="font-bold text-slate-200 flex items-center gap-1.5 text-[11px]">
                <span>📖</span>
                <span>Panduan Transfer {{ currentBankName }}:</span>
              </div>
              <ol class="list-decimal list-inside space-y-1 text-[11px] text-slate-400 leading-relaxed">
                <li v-for="(step, idx) in currentBankGuide" :key="idx">
                  {{ step }}
                </li>
              </ol>
            </div>
          </div>

          <!-- Status Notice Feedback (when user checks and it is still pending) -->
          <div
            v-if="checkNotice"
            class="p-3 rounded-xl bg-amber-500/15 border border-amber-500/30 text-amber-300 text-xs text-center flex items-center justify-center gap-2 animate-in fade-in"
          >
            <span>ℹ️</span>
            <span>{{ checkNotice }}</span>
          </div>

          <!-- Simulation button in Sandbox -->
          <div v-if="showSimulateButton" class="pt-2 border-t border-slate-800/60 text-center">
            <button
              @click="simulatePayment"
              :disabled="simulating"
              class="text-[11px] text-amber-400/90 hover:text-amber-300 underline font-medium cursor-pointer"
            >
              {{ simulating ? 'Memproses simulasi...' : '🧪 Klik di sini untuk simulasi bayar instan (Sandbox Mode)' }}
            </button>
          </div>
        </div>

        <!-- SELECTION STATE: CATEGORIZED & USER-FRIENDLY TABS -->
        <div v-else class="space-y-4 animate-in fade-in">
          <!-- Total Price Card (Only shown if NOT embedded in checkout page) -->
          <div v-if="!embedded" class="p-4 bg-slate-950/80 border border-slate-800 rounded-2xl flex items-center justify-between">
            <div>
              <span class="text-xs sm:text-sm font-bold text-white block">{{ planName }}</span>
              <span class="text-[11px] text-emerald-400 font-medium flex items-center gap-1.5 mt-0.5">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                <span>Aktivasi Otomatis</span>
              </span>
            </div>
            <div class="text-right">
              <span class="text-[10px] text-slate-400 block uppercase font-medium">Total Pembayaran</span>
              <span class="text-base sm:text-lg font-black text-emerald-400">Rp {{ formatNumber(grossAmount) }}</span>
            </div>
          </div>

          <!-- Loading Channels State -->
          <div v-if="loadingChannels" class="py-6 text-center space-y-2">
            <span class="w-5 h-5 border-2 border-emerald-400 border-t-transparent rounded-full animate-spin inline-block"></span>
            <p class="text-xs text-slate-400">Menyesuaikan metode pembayaran...</p>
          </div>

          <!-- Segmented Category Switcher (QRIS vs Virtual Account) - Only shown if BOTH are available -->
          <div
            v-else-if="isQrisAvailable && availableVaBanks.length > 0"
            class="grid grid-cols-2 p-1 bg-slate-950 border border-slate-800 rounded-2xl gap-1 mb-2"
          >
            <button
              type="button"
              @click="activeCategory = 'qris'"
              class="py-2.5 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer"
              :class="activeCategory === 'qris' ? 'bg-gradient-to-r from-emerald-500 to-teal-400 text-slate-950 shadow-md' : 'text-slate-400 hover:text-white'"
            >
              <span>📱 QRIS & E-Wallet</span>
            </button>
            <button
              type="button"
              @click="activeCategory = 'va'"
              class="py-2.5 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer"
              :class="activeCategory === 'va' ? 'bg-gradient-to-r from-emerald-500 to-teal-400 text-slate-950 shadow-md' : 'text-slate-400 hover:text-white'"
            >
              <span>🏦 Virtual Account</span>
            </button>
          </div>

          <!-- CATEGORY 1: QRIS HIGHLIGHT (Shown if QRIS is enabled) -->
          <div v-if="isQrisAvailable && (activeCategory === 'qris' || availableVaBanks.length === 0)" class="space-y-3">
            <div
              @click="selectPaymentMethod('qris')"
              class="p-4 rounded-2xl bg-gradient-to-b from-slate-950 to-emerald-950/20 border-2 border-emerald-500/40 hover:border-emerald-400 transition cursor-pointer group shadow-lg shadow-emerald-500/5"
            >
              <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2.5">
                  <span class="text-2xl">📱</span>
                  <div>
                    <h4 class="font-black text-sm text-white group-hover:text-emerald-300 transition">
                      QRIS Instan
                    </h4>
                    <span class="text-[11px] text-slate-400">Scan via BCA, Mandiri, BRI, GoPay, OVO, DANA, dll</span>
                  </div>
                </div>
                <span class="text-[10px] px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-400 font-bold border border-emerald-500/30">
                  Resmi
                </span>
              </div>

              <!-- Brand Logos Chips -->
              <div class="flex flex-wrap gap-1.5 pt-2.5 border-t border-slate-800/80">
                <span class="text-[10px] px-2 py-0.5 bg-slate-900 border border-slate-800 text-slate-300 rounded-md font-medium">BCA</span>
                <span class="text-[10px] px-2 py-0.5 bg-slate-900 border border-slate-800 text-slate-300 rounded-md font-medium">Livin' Mandiri</span>
                <span class="text-[10px] px-2 py-0.5 bg-slate-900 border border-slate-800 text-slate-300 rounded-md font-medium">BRImo</span>
                <span class="text-[10px] px-2 py-0.5 bg-slate-900 border border-slate-800 text-slate-300 rounded-md font-medium">BNI</span>
                <span class="text-[10px] px-2 py-0.5 bg-slate-900 border border-slate-800 text-slate-300 rounded-md font-medium">GoPay</span>
                <span class="text-[10px] px-2 py-0.5 bg-slate-900 border border-slate-800 text-slate-300 rounded-md font-medium">OVO</span>
                <span class="text-[10px] px-2 py-0.5 bg-slate-900 border border-slate-800 text-slate-300 rounded-md font-medium">DANA</span>
                <span class="text-[10px] px-2 py-0.5 bg-slate-900 border border-slate-800 text-slate-300 rounded-md font-medium">ShopeePay</span>
              </div>

              <!-- Call to Action -->
              <button
                type="button"
                :disabled="loadingMethod === 'qris'"
                class="w-full mt-3.5 py-3 px-4 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-400 hover:from-emerald-400 hover:to-teal-300 text-slate-950 font-black text-xs sm:text-sm flex items-center justify-center gap-2 transition cursor-pointer shadow-md shadow-emerald-500/20 active:scale-98"
              >
                <span v-if="loadingMethod === 'qris'" class="w-4 h-4 border-2 border-slate-950 border-t-transparent rounded-full animate-spin"></span>
                <span>{{ loadingMethod === 'qris' ? 'Menyiapkan QRIS...' : 'Bayar Sekarang via QRIS ➔' }}</span>
              </button>
            </div>
          </div>

          <!-- CATEGORY 2: VIRTUAL ACCOUNT LIST (Only displayed if verified active in Midtrans) -->
          <div v-else-if="availableVaBanks.length > 0 && (activeCategory === 'va' || !isQrisAvailable)" class="space-y-2.5">
            <span class="text-xs text-slate-300 block font-bold">Pilih Bank Transfer (Virtual Account):</span>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
              <button
                v-for="bank in availableVaBanks"
                :key="bank.id"
                @click="selectPaymentMethod(bank.id)"
                :disabled="loadingMethod === bank.id"
                class="p-3 rounded-xl bg-slate-950/70 border border-slate-800 hover:border-emerald-500/50 hover:bg-slate-900 transition text-left flex items-center justify-between cursor-pointer group"
              >
                <div class="flex items-center gap-2.5">
                  <div class="w-8 h-8 rounded-lg bg-slate-900 border border-slate-800 flex items-center justify-center text-xs font-black" :class="bank.color">
                    {{ bank.bankName }}
                  </div>
                  <div>
                    <span class="text-xs font-bold text-white group-hover:text-emerald-300 block transition">
                      {{ bank.name }}
                    </span>
                    <span class="text-[9px] text-slate-400 block mt-0.5">{{ bank.desc }}</span>
                  </div>
                </div>
                <div>
                  <span v-if="loadingMethod === bank.id" class="text-xs text-emerald-400 animate-spin">⏳</span>
                  <span v-else class="text-slate-600 group-hover:text-emerald-400 text-xs transition">→</span>
                </div>
              </button>
            </div>
          </div>

          <!-- FALLBACK STATE: If no payment methods are active in Midtrans portal yet -->
          <div v-else-if="!loadingChannels" class="p-6 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-center space-y-3">
            <div class="text-3xl">⏳</div>
            <h4 class="font-bold text-white text-sm">Metode Pembayaran Sedang Diaktivasi</h4>
            <p class="text-xs text-slate-300 leading-relaxed max-w-sm mx-auto">
              Kanal pembayaran otomatis akun Midtrans Anda saat ini sedang diverifikasi. Silakan selesaikan pembayaran manual via WhatsApp resmi kami.
            </p>
            <div class="pt-2">
              <a
                :href="whatsappDirectUrl"
                target="_blank"
                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-400 text-slate-950 font-black text-xs shadow-md cursor-pointer hover:scale-105 transition"
              >
                <span>💬 Konfirmasi Pembayaran via WhatsApp</span>
              </a>
            </div>
          </div>

          <!-- Error Alert -->
          <div v-if="errorMessage" class="p-3 rounded-xl bg-rose-500/15 border border-rose-500/30 text-rose-300 text-xs">
            {{ errorMessage }}
          </div>

          <!-- Security Footer -->
          <div class="pt-2 text-center text-[10px] text-slate-500 flex items-center justify-center gap-3">
            <span>🔒 Enkripsi SSL 256-Bit</span>
            <span>•</span>
            <span>⚡ Otomatis Terverifikasi</span>
            <span>•</span>
            <span>Midtrans</span>
          </div>
        </div>
      </div>
    </div>
  </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false,
  },
  embedded: {
    type: Boolean,
    default: false,
  },
  orderId: {
    type: String,
    required: true,
  },
  grossAmount: {
    type: Number,
    default: 25000,
  },
  planName: {
    type: String,
    default: 'HematWoy Pro',
  },
  isProduction: {
    type: Boolean,
    default: false,
  },
  enabledChannels: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(['close', 'settled']);

const activeCategory = ref('qris');
const selectedMethod = ref('');
const loadingMethod = ref('');
const chargeResult = ref(null);
const errorMessage = ref('');
const isSettled = ref(false);
const copiedVa = ref(false);
const copiedAmount = ref(false);
const checkingStatus = ref(false);
const simulating = ref(false);
const checkNotice = ref('');
const remainingSeconds = ref(900); // 15 mins default
let timerInterval = null;
let noticeTimer = null;

// Dynamic Midtrans Channels State
const midtransChannels = ref([...(props.enabledChannels || [])]);
const loadingChannels = ref(false);

const botRawNumber = (typeof window !== 'undefined' && window.__BOT_CONFIG__?.bot_number) || '6285111355992';
const whatsappDirectUrl = computed(() => `https://wa.me/${botRawNumber}?text=Halo%20Admin%20HematWoy,%20saya%20mau%20konfirmasi%20pembayaran%20Order%20${props.orderId}`);

// Master definition for supported Virtual Accounts and e-wallet mappings
const ALL_VA_BANKS = [
  {
    id: 'bca_va',
    midtransChannel: 'bca_va',
    aliases: ['bca_va', 'bca'],
    bankName: 'BCA',
    name: 'BCA Virtual Account',
    desc: 'BCA Mobile / KlikBCA / ATM',
    color: 'text-blue-400 border-blue-500/30',
    guide: [
      'Buka aplikasi BCA mobile > pilih m-Transfer > BCA Virtual Account.',
      'Masukkan nomor Virtual Account yang tertera di atas.',
      'Pastikan nama tagihan sesuai, lalu masukkan PIN m-BCA.',
      'Setelah selesai transfer, klik tombol "Saya Sudah Bayar" di atas.',
    ],
  },
  {
    id: 'mandiri_va',
    midtransChannel: 'echannel',
    aliases: ['mandiri_va', 'echannel', 'mandiri'],
    bankName: 'Mandiri',
    name: 'Mandiri Bill',
    desc: 'Livin\' by Mandiri / ATM',
    color: 'text-amber-400 border-amber-500/30',
    guide: [
      'Buka Livin\' by Mandiri > pilih menu Bayar > Pembayaran Baru > Multi Payment.',
      'Pilih Penyedia Jasa: Masukkan Kode Perusahaan (70012).',
      'Masukkan Nomor Tagihan / Virtual Account dan konfirmasi nominal.',
      'Masukkan PIN Livin\' Mandiri. Selesai! Klik "Saya Sudah Bayar".',
    ],
  },
  {
    id: 'bni_va',
    midtransChannel: 'bni_va',
    aliases: ['bni_va', 'bni'],
    bankName: 'BNI',
    name: 'BNI Virtual Account',
    desc: 'BNI Mobile Banking / ATM',
    color: 'text-orange-400 border-orange-500/30',
    guide: [
      'Buka BNI Mobile Banking > pilih menu Transfer > Virtual Account Billing.',
      'Pilih tab Input Baru > masukkan Nomor Virtual Account di atas.',
      'Konfirmasi rincian tagihan, masukkan Password Transaksi BNI.',
      'Selesai! Klik tombol "Saya Sudah Bayar" di atas.',
    ],
  },
  {
    id: 'bri_va',
    midtransChannel: 'bri_va',
    aliases: ['bri_va', 'bri'],
    bankName: 'BRI',
    name: 'BRI Virtual Account (BRIVA)',
    desc: 'BRImo / ATM BRI',
    color: 'text-cyan-400 border-cyan-500/30',
    guide: [
      'Buka aplikasi BRImo > pilih Pembayaran / Tagihan > BRIVA.',
      'Masukkan Nomor BRIVA di atas dan klik Lanjutkan.',
      'Periksa nominal dan nama penerima, lalu masukkan PIN BRImo.',
      'Selesai! Klik tombol "Saya Sudah Bayar" di atas.',
    ],
  },
  {
    id: 'permata_va',
    midtransChannel: 'permata_va',
    aliases: ['permata_va', 'permata'],
    bankName: 'Permata',
    name: 'Permata Virtual Account',
    desc: 'PermataMobile X / ATM',
    color: 'text-emerald-400 border-emerald-500/30',
    guide: [
      'Buka aplikasi PermataMobile X > pilih Bayar Tagihan > Virtual Account.',
      'Masukkan Nomor Virtual Account dan ikuti instruksi hingga selesai.',
      'Kembali ke halaman ini dan klik "Saya Sudah Bayar".',
    ],
  },
  {
    id: 'cimb_va',
    midtransChannel: 'cimb_va',
    aliases: ['cimb_va', 'cimb'],
    bankName: 'CIMB',
    name: 'CIMB Niaga Virtual Account',
    desc: 'OCTO Mobile / ATM CIMB',
    color: 'text-red-400 border-red-500/30',
    guide: [
      'Buka aplikasi OCTO Mobile > pilih Transfer > Rekening Virtual.',
      'Masukkan Nomor Virtual Account di atas.',
      'Konfirmasi rincian tagihan dan selesaikan pembayaran.',
      'Kembali ke halaman ini dan klik "Saya Sudah Bayar".',
    ],
  },
];

// Fetch active payment channels from Midtrans backend API
async function fetchMidtransChannels() {
  if (midtransChannels.value.length > 0) return;
  loadingChannels.value = true;
  try {
    const res = await axios.get('/api/v1/payment/channels');
    if (res.data?.channels && Array.isArray(res.data.channels)) {
      midtransChannels.value = res.data.channels;
    }
  } catch (err) {
    console.warn('Gagal memuat preferensi Midtrans:', err);
  } finally {
    loadingChannels.value = false;
  }
}

watch(
  () => props.enabledChannels,
  (val) => {
    if (val && Array.isArray(val) && val.length > 0) {
      midtransChannels.value = [...val];
    }
  },
  { immediate: true }
);

onMounted(() => {
  if (midtransChannels.value.length === 0) {
    fetchMidtransChannels();
  }
});

// QRIS is available if Midtrans has qris, gopay, shopeepay, dana, or ovo enabled
const isQrisAvailable = computed(() => {
  if (loadingChannels.value) return true;
  if (midtransChannels.value.length === 0) return true;
  return midtransChannels.value.some(c =>
    ['qris', 'other_qris', 'gopay', 'shopeepay', 'dana', 'ovo'].includes(String(c).toLowerCase())
  );
});

// Dynamic Virtual Accounts strictly filtered against confirmed Midtrans channels
const availableVaBanks = computed(() => {
  if (loadingChannels.value || !Array.isArray(midtransChannels.value) || midtransChannels.value.length === 0) {
    return []; // CRITICAL: NEVER assume or show fake virtual accounts if merchant has not activated them!
  }
  return ALL_VA_BANKS.filter(bank => {
    return midtransChannels.value.some(c => {
      const cl = String(c).toLowerCase();
      return cl === bank.midtransChannel || cl === bank.id || (bank.aliases && bank.aliases.includes(cl));
    });
  });
});

// Automatically select valid category based on enabled channels
watch([isQrisAvailable, availableVaBanks], () => {
  if (!isQrisAvailable.value && availableVaBanks.value.length > 0) {
    activeCategory.value = 'va';
  } else if (availableVaBanks.value.length === 0 && isQrisAvailable.value) {
    activeCategory.value = 'qris';
  }
}, { immediate: true });

// If embedded in CheckoutView and only QRIS is active, automatically load the QR code
let hasAutoLoadedQris = false;
watch([isQrisAvailable, availableVaBanks, loadingChannels], () => {
  if (
    props.embedded &&
    !hasAutoLoadedQris &&
    !loadingChannels.value &&
    isQrisAvailable.value &&
    availableVaBanks.value.length === 0 &&
    !chargeResult.value &&
    !loadingMethod.value
  ) {
    hasAutoLoadedQris = true;
    selectPaymentMethod('qris');
  }
});

const showSimulateButton = computed(() => {
  return !props.isProduction;
});

const currentBank = computed(() => {
  return ALL_VA_BANKS.find(b => b.id === selectedMethod.value || b.midtransChannel === selectedMethod.value) || null;
});

const currentBankName = computed(() => {
  return currentBank.value?.bankName || 'Bank';
});

const currentBankGuide = computed(() => {
  return currentBank.value?.guide || [
    'Buka aplikasi m-Banking atau ATM bank Anda.',
    'Pilih menu Transfer Virtual Account, masukkan nomor di atas.',
    'Pastikan nominal sesuai dan selesaikan pembayaran.',
  ];
});

const countdownText = computed(() => {
  const total = Math.max(0, remainingSeconds.value);
  const h = Math.floor(total / 3600);
  const m = Math.floor((total % 3600) / 60);
  const s = total % 60;
  if (h > 0) {
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
  }
  return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
});

function formatNumber(num) {
  return new Intl.NumberFormat('id-ID').format(num || 0);
}

function formatMethodName(id) {
  if (id === 'qris') return 'QRIS Dinamis';
  const b = vaBanks.find(x => x.id === id);
  return b ? b.name : id.toUpperCase();
}

function changeMethod() {
  stopCountdown();
  chargeResult.value = null;
  errorMessage.value = '';
  checkNotice.value = '';
}

async function selectPaymentMethod(methodId) {
  selectedMethod.value = methodId;
  loadingMethod.value = methodId;
  errorMessage.value = '';
  checkNotice.value = '';

  try {
    const res = await axios.post(`/api/v1/payment/charge/${props.orderId}`, {
      payment_method: methodId,
    });

    if (res.data.status === 'SETTLEMENT') {
      isSettled.value = true;
      stopCountdown();
      emit('settled');
      return;
    }

    if (res.data.success) {
      chargeResult.value = res.data;
      startCountdown();
    } else {
      // In sandbox mode fallback simulation
      if (res.data.fallback_simulated) {
        chargeResult.value = {
          success: true,
          qr_url: `https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=SIMULATED_ORDER_${props.orderId}`,
          va_number: '88012' + Math.floor(10000000 + Math.random() * 90000000),
          expiry_time: '15 Menit',
        };
        startCountdown();
      } else {
        errorMessage.value = res.data.message || 'Gagal menyiapkan metode pembayaran.';
      }
    }
  } catch (err) {
    errorMessage.value = err.response?.data?.message || err.response?.data?.error || 'Gagal menghubungi server pembayaran.';
  } finally {
    loadingMethod.value = '';
  }
}

async function checkStatus() {
  checkingStatus.value = true;
  checkNotice.value = '';
  if (noticeTimer) clearTimeout(noticeTimer);

  try {
    const res = await axios.get(`/api/v1/payment/order/${props.orderId}`);
    if (res.data.status === 'SETTLEMENT') {
      isSettled.value = true;
      stopCountdown();
      emit('settled');
    } else {
      checkNotice.value = 'Pembayaran belum terdeteksi. Silakan selesaikan pembayaran di aplikasi m-Banking/e-Wallet kamu lalu klik tombol ini lagi.';
      noticeTimer = setTimeout(() => {
        checkNotice.value = '';
      }, 5000);
    }
  } catch (err) {
    checkNotice.value = 'Gagal memeriksa status pembayaran. Silakan coba sesaat lagi.';
  } finally {
    checkingStatus.value = false;
  }
}

async function simulatePayment() {
  simulating.value = true;
  try {
    const res = await axios.post(`/api/v1/payment/simulate/${props.orderId}`);
    if (res.data.order?.status === 'SETTLEMENT') {
      isSettled.value = true;
      stopCountdown();
      emit('settled');
    }
  } catch (err) {
    alert('Simulasi gagal: ' + (err.response?.data?.error || err.message));
  } finally {
    simulating.value = false;
  }
}

function startCountdown() {
  stopCountdown();

  let targetEndTime = Date.now() + 15 * 60 * 1000;
  if (chargeResult.value?.expiry_time && typeof chargeResult.value.expiry_time === 'string') {
    const raw = chargeResult.value.expiry_time.trim();
    if (raw.includes('-') && (raw.includes(':') || raw.includes('T'))) {
      const normalized = raw.includes('T') ? raw : raw.replace(' ', 'T');
      const parsed = new Date(normalized).getTime();
      if (!isNaN(parsed) && parsed > Date.now()) {
        targetEndTime = parsed;
      }
    }
  }

  const update = () => {
    const diff = Math.max(0, Math.floor((targetEndTime - Date.now()) / 1000));
    remainingSeconds.value = diff;
    if (diff <= 0) {
      stopCountdown();
    }
  };

  update();
  timerInterval = setInterval(update, 1000);
}

function stopCountdown() {
  if (timerInterval) {
    clearInterval(timerInterval);
    timerInterval = null;
  }
}

function copyVaNumber() {
  const text = chargeResult.value?.va_number || chargeResult.value?.bill_key;
  if (!text) return;
  navigator.clipboard.writeText(String(text));
  copiedVa.value = true;
  setTimeout(() => {
    copiedVa.value = false;
  }, 2000);
}

function copyNominal() {
  navigator.clipboard.writeText(String(props.grossAmount));
  copiedAmount.value = true;
  setTimeout(() => {
    copiedAmount.value = false;
  }, 2000);
}

function handleClose() {
  stopCountdown();
  emit('close');
}

function handleSuccessAction() {
  emit('settled');
  handleClose();
}

watch(() => props.isOpen, (newVal) => {
  if (!newVal && !props.embedded) {
    stopCountdown();
    chargeResult.value = null;
    errorMessage.value = '';
    checkNotice.value = '';
    isSettled.value = false;
  }
});

onUnmounted(() => {
  stopCountdown();
  if (noticeTimer) clearTimeout(noticeTimer);
});
</script>
