<template>
  <div class="space-y-5 sm:space-y-6">
    <!-- TOP HEADER & CONTROLS -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight flex items-center gap-2.5">
          <span>Kondisi Keuangan</span>
          <!-- Active sync indicator badge (subtle, non-disruptive) -->
          <span
            v-if="isSyncing"
            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30 shadow-sm animate-pulse"
          >
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-ping"></span>
            <span>Menyinkronkan data...</span>
          </span>
        </h1>
        <p class="text-xs text-slate-400 mt-0.5">Ringkasan arus kas & kesehatan finansial Anda</p>
      </div>

      <div class="flex items-center gap-2 sm:gap-3 w-full sm:w-auto">
        <!-- Month Picker (Custom Sleek Dark Component) -->
        <div class="relative flex-1 sm:flex-initial">
          <MonthPicker
            v-model="selectedMonth"
            @change="loadDashboard"
            :disabled="isInitialLoading || isSyncing"
          />
        </div>

        <button
          @click="$emit('open-transaction-modal')"
          class="hidden sm:inline-flex px-4 py-2.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 text-slate-950 font-bold text-xs items-center justify-center gap-2 shadow-lg shadow-emerald-500/20 cursor-pointer transition active:scale-95"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
          <span class="whitespace-nowrap">Catat Transaksi</span>
        </button>
      </div>
    </div>

    <!-- ERROR STATE ALERT WITH RETRY -->
    <div
      v-if="isError"
      class="p-4 sm:p-5 rounded-2xl bg-rose-500/15 border border-rose-500/30 text-rose-300 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-lg animate-in fade-in"
    >
      <div class="flex items-start gap-3">
        <span class="text-2xl shrink-0">⚠️</span>
        <div>
          <h3 class="text-sm font-bold text-rose-200">Gagal Menyinkronkan Data</h3>
          <p class="text-xs text-rose-300/90 mt-0.5 leading-relaxed">
            {{ errorMessage || 'Terjadi gangguan jaringan saat memuat data keuangan Anda.' }}
          </p>
        </div>
      </div>
      <button
        @click="loadDashboard"
        class="self-start sm:self-auto px-4 py-2 rounded-xl bg-rose-500 hover:bg-rose-400 text-slate-950 font-bold text-xs flex items-center gap-1.5 transition active:scale-95 cursor-pointer shadow-md"
      >
        <span>🔄 Coba Lagi</span>
      </button>
    </div>

    <!-- SKELETON LOADERS (Only on initial cold fetch to prevent annoying screen tearing) -->
    <div v-if="isInitialLoading" class="space-y-5 animate-in fade-in duration-150">
      <!-- Mobile Hero Skeleton -->
      <div class="sm:hidden p-5 rounded-3xl bg-slate-900 border border-slate-800/80 animate-pulse space-y-4">
        <div class="flex justify-between items-center">
          <div class="h-3 w-24 bg-slate-800 rounded"></div>
          <div class="h-6 w-16 bg-slate-800 rounded-lg"></div>
        </div>
        <div class="h-9 w-48 bg-slate-800 rounded-xl"></div>
        <div class="grid grid-cols-2 gap-3 pt-3 border-t border-slate-800/60">
          <div class="h-14 bg-slate-950/60 rounded-xl"></div>
          <div class="h-14 bg-slate-950/60 rounded-xl"></div>
        </div>
      </div>

      <!-- Desktop 4 KPI Skeletons -->
      <div class="hidden sm:grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div v-for="i in 4" :key="i" class="p-5 rounded-2xl bg-slate-900 border border-slate-800/80 animate-pulse space-y-3">
          <div class="flex justify-between">
            <div class="h-3 w-20 bg-slate-800 rounded"></div>
            <div class="w-7 h-7 rounded-xl bg-slate-800"></div>
          </div>
          <div class="h-7 w-32 bg-slate-800 rounded"></div>
          <div class="h-2.5 w-24 bg-slate-800/60 rounded"></div>
        </div>
      </div>

      <!-- Content Skeleton Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="lg:col-span-2 h-64 rounded-2xl bg-slate-900 border border-slate-800/80 animate-pulse p-6"></div>
        <div class="h-64 rounded-2xl bg-slate-900 border border-slate-800/80 animate-pulse p-6"></div>
      </div>

      <!-- Table Skeleton -->
      <div class="p-6 rounded-2xl bg-slate-900 border border-slate-800/80 animate-pulse space-y-4">
        <div class="h-4 w-40 bg-slate-800 rounded"></div>
        <div class="space-y-3">
          <div v-for="j in 4" :key="j" class="h-10 bg-slate-950/60 rounded-xl"></div>
        </div>
      </div>
    </div>

    <!-- MAIN DASHBOARD CONTENT (Kept active once loaded, smooth updates during month change) -->
    <template v-else>
      <div :class="isSyncing ? 'opacity-70 transition-opacity duration-200 pointer-events-none' : 'transition-opacity duration-200'" class="space-y-5 sm:space-y-6">
      <!-- MOBILE HERO FINANCIAL CARD (Visible only on mobile screens < 640px) -->
      <div class="sm:hidden p-5 rounded-3xl bg-gradient-to-br from-slate-900 via-slate-900/95 to-emerald-950/40 border border-emerald-500/25 shadow-2xl relative overflow-hidden">
        <div class="absolute -top-12 -right-12 w-36 h-36 bg-emerald-500/10 rounded-full blur-2xl pointer-events-none"></div>

        <!-- Card Top: Total Saldo & Eye Mask -->
        <div class="flex items-center justify-between mb-1.5">
          <div class="flex items-center gap-1.5 text-xs font-semibold text-slate-400">
            <span>👛 Total Saldo</span>
            <span class="text-[10px] text-slate-500 font-mono">({{ financeStore.wallets.length }} dompet)</span>
          </div>
          <button
            @click="vaultStore.toggleMask"
            class="p-1 rounded-md text-slate-400 hover:text-white hover:bg-slate-800 transition cursor-pointer"
            :title="vaultStore.isMasked ? 'Buka sensor saldo' : 'Sembunyikan saldo'"
          >
            <svg v-if="vaultStore.isMasked" class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/>
            </svg>
            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>
          </button>
        </div>

        <!-- Main Balance Figure -->
        <div class="text-3xl font-black text-white tracking-tight py-1">
          {{ formatRupiah(financeStore.totalBalance) }}
        </div>

        <!-- 2-Column Mobile Cashflow Metric Pills -->
        <div class="mt-4 pt-3.5 border-t border-slate-800/80 grid grid-cols-2 gap-2.5">
          <div class="p-2.5 rounded-2xl bg-slate-950/60 border border-slate-800/80">
            <div class="flex items-center justify-between mb-0.5">
              <span class="text-[10px] font-semibold text-emerald-400 flex items-center gap-0.5">
                <span>↓</span> Pemasukan
              </span>
              <span class="text-[9px] text-slate-400">{{ financeStore.summary?.delta_vs_last_month?.income_percentage || 0 }}%</span>
            </div>
            <p class="text-xs sm:text-sm font-black text-emerald-400 truncate">
              {{ formatRupiah(financeStore.summary?.total_income || 0) }}
            </p>
          </div>

          <div class="p-2.5 rounded-2xl bg-slate-950/60 border border-slate-800/80">
            <div class="flex items-center justify-between mb-0.5">
              <span class="text-[10px] font-semibold text-rose-400 flex items-center gap-0.5">
                <span>↑</span> Pengeluaran
              </span>
              <span class="text-[9px] text-slate-400">{{ financeStore.summary?.delta_vs_last_month?.expense_percentage || 0 }}%</span>
            </div>
            <p class="text-xs sm:text-sm font-black text-rose-400 truncate">
              {{ formatRupiah(financeStore.summary?.total_expense || 0) }}
            </p>
          </div>
        </div>

        <!-- Sisa Saldo Bulan Ini (Cashflow Status Bar) -->
        <div class="mt-2.5 px-3 py-2 rounded-xl bg-slate-950/50 border border-slate-800/60 flex items-center justify-between text-xs">
          <span class="text-slate-400 text-[11px]">Sisa Arus Kas:</span>
          <span
            class="font-black text-xs flex items-center gap-1"
            :class="(financeStore.summary?.net_savings || 0) >= 0 ? 'text-teal-400' : 'text-amber-400'"
          >
            <span>{{ (financeStore.summary?.net_savings || 0) >= 0 ? '+' : '' }}{{ formatRupiah(financeStore.summary?.net_savings || 0) }}</span>
            <span class="text-[10px] font-normal text-slate-400">({{ (financeStore.summary?.net_savings || 0) >= 0 ? 'Surplus' : 'Defisit' }})</span>
          </span>
        </div>

        <!-- Quick Action Buttons on Mobile Card -->
        <div class="mt-4 pt-3 border-t border-slate-800/80 grid grid-cols-2 gap-2">
          <button
            @click="$emit('open-transaction-modal')"
            class="py-2.5 px-3 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-400 text-slate-950 font-bold text-xs flex items-center justify-center gap-1.5 shadow-md shadow-emerald-500/20 active:scale-95 transition cursor-pointer"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
            <span>Catat Transaksi</span>
          </button>
          <RouterLink
            to="/analytics"
            class="py-2.5 px-3 rounded-xl bg-slate-800 hover:bg-slate-750 border border-slate-700/80 text-white font-bold text-xs flex items-center justify-center gap-1.5 active:scale-95 transition text-center"
          >
            <span>📊</span>
            <span>Lihat Analisis</span>
          </RouterLink>
        </div>
      </div>

      <!-- DESKTOP 4 KPI CARDS (Hidden on mobile < 640px, visible on sm:grid) -->
      <div class="hidden sm:grid grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Total Balance -->
        <div class="p-5 rounded-2xl bg-gradient-to-br from-slate-900 to-slate-900/60 border border-slate-800 relative overflow-hidden group hover:border-slate-700 transition shadow-sm">
          <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold text-slate-400">Total Saldo</span>
            <span class="p-2 rounded-xl bg-emerald-500/10 text-emerald-400 text-sm">👛</span>
          </div>
          <p class="text-2xl font-black text-white tracking-tight">
            {{ formatRupiah(financeStore.totalBalance) }}
          </p>
          <p class="text-[11px] text-slate-500 mt-2 flex items-center gap-1">
            <span>Dari {{ financeStore.wallets.length }} dompet aktif</span>
          </p>
        </div>

        <!-- Income This Month -->
        <div class="p-5 rounded-2xl bg-gradient-to-br from-slate-900 to-slate-900/60 border border-slate-800 relative overflow-hidden group hover:border-slate-700 transition shadow-sm">
          <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold text-slate-400">Pemasukan Bulan Ini</span>
            <span class="p-2 rounded-xl bg-emerald-500/10 text-emerald-400 text-sm">📈</span>
          </div>
          <p class="text-2xl font-black text-emerald-400 tracking-tight">
            {{ formatRupiah(financeStore.summary?.total_income || 0) }}
          </p>
          <p class="text-[11px] text-slate-500 mt-2">
            Dibanding bulan lalu: <span class="text-slate-300 font-medium">{{ financeStore.summary?.delta_vs_last_month?.income_percentage || 0 }}%</span>
          </p>
        </div>

        <!-- Expense This Month -->
        <div class="p-5 rounded-2xl bg-gradient-to-br from-slate-900 to-slate-900/60 border border-slate-800 relative overflow-hidden group hover:border-slate-700 transition shadow-sm">
          <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold text-slate-400">Pengeluaran Bulan Ini</span>
            <span class="p-2 rounded-xl bg-rose-500/10 text-rose-400 text-sm">📉</span>
          </div>
          <p class="text-2xl font-black text-rose-400 tracking-tight">
            {{ formatRupiah(financeStore.summary?.total_expense || 0) }}
          </p>
          <p class="text-[11px] text-slate-500 mt-2">
            Dibanding bulan lalu: <span class="text-slate-300 font-medium">{{ financeStore.summary?.delta_vs_last_month?.expense_percentage || 0 }}%</span>
          </p>
        </div>

        <!-- Net Savings (Cashflow) -->
        <div class="p-5 rounded-2xl bg-gradient-to-br from-slate-900 to-slate-900/60 border border-slate-800 relative overflow-hidden group hover:border-slate-700 transition shadow-sm">
          <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold text-slate-400">Sisa Saldo Bulan Ini</span>
            <span class="p-2 rounded-xl bg-blue-500/10 text-blue-400 text-sm">⚖️</span>
          </div>
          <p
            class="text-2xl font-black tracking-tight"
            :class="(financeStore.summary?.net_savings || 0) >= 0 ? 'text-teal-400' : 'text-amber-400'"
          >
            {{ formatRupiah(financeStore.summary?.net_savings || 0) }}
          </p>
          <p class="text-[11px] mt-2 font-medium" :class="(financeStore.summary?.net_savings || 0) >= 0 ? 'text-emerald-400' : 'text-amber-400'">
            {{ (financeStore.summary?.net_savings || 0) >= 0 ? 'Surplus (Tabungan Bertambah)' : 'Defisit (Pengeluaran > Pemasukan)' }}
          </p>
        </div>
      </div>

      <!-- WHATSAPP BOT COMPACT ASSISTANT STRIP -->
      <div class="p-3.5 sm:p-4 rounded-2xl bg-gradient-to-r from-emerald-950/50 via-slate-900 to-teal-950/40 border border-emerald-500/25 flex items-center justify-between gap-3 shadow-md">
        <div class="flex items-center gap-3 min-w-0">
          <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-emerald-500/15 text-emerald-400 flex items-center justify-center text-lg sm:text-xl shrink-0 border border-emerald-500/25">
            💬
          </div>
          <div class="min-w-0">
            <h2 class="text-xs sm:text-sm font-bold text-white flex items-center gap-1.5 truncate">
              <span>WhatsApp</span>
              <span class="text-[9px] px-1.5 py-0.2 rounded-full bg-emerald-500/20 text-emerald-400 font-semibold border border-emerald-500/30">Aktif</span>
            </h2>
            <p class="text-[11px] text-slate-400 truncate mt-0.5 hidden xs:block">
              Contoh: <code class="px-1 py-0.5 rounded bg-slate-950 text-emerald-300 font-mono text-[10px]">keluar 25rb makan</code>
            </p>
          </div>
        </div>
        <a
          :href="whatsappBotLink"
          target="_blank"
          rel="noopener noreferrer"
          class="inline-flex items-center justify-center gap-1.5 px-3 sm:px-3.5 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-xs transition shadow-md shadow-emerald-500/20 shrink-0 cursor-pointer active:scale-95"
        >
          <span>Buka Bot</span>
          <span>➔</span>
        </a>
      </div>

      <!-- WALLETS HORIZONTAL SNAP CAROUSEL ON MOBILE / LIST ON DESKTOP -->
      <div class="space-y-3">
        <div class="flex items-center justify-between">
          <h2 class="text-sm sm:text-base font-bold text-white flex items-center gap-2">
            <span>Dompet Saya</span>
            <span class="text-xs px-2 py-0.5 rounded-full bg-slate-800 text-slate-300 font-normal">{{ financeStore.wallets.length }}</span>
          </h2>
          <RouterLink to="/wallets" class="text-xs font-semibold text-emerald-400 hover:underline">
            Kelola Dompet ➔
          </RouterLink>
        </div>

        <div v-if="financeStore.wallets.length === 0" class="p-5 rounded-2xl bg-slate-900/60 border border-slate-800 text-center text-xs text-slate-500">
          Belum ada dompet terdaftar. Dompet Cash utama akan dibuat otomatis.
        </div>

        <!-- Horizontal Cards Carousel with smooth snap -->
        <div v-else class="flex gap-3 overflow-x-auto pb-1.5 -mx-1 px-1 scroll-smooth snap-x snap-mandatory focus:outline-none">
          <div
            v-for="w in financeStore.wallets"
            :key="w.id"
            class="min-w-[160px] sm:min-w-[200px] p-3.5 rounded-2xl bg-gradient-to-br from-slate-900 to-slate-950 border border-slate-800 hover:border-slate-700 transition shrink-0 snap-start flex flex-col justify-between shadow-sm"
          >
            <div class="flex items-center justify-between mb-3">
              <span class="w-8 h-8 rounded-xl bg-slate-800/80 flex items-center justify-center text-base border border-slate-700/50">
                {{ getWalletIcon(w.type) }}
              </span>
              <span class="text-[9px] uppercase tracking-wider px-1.5 py-0.5 rounded bg-slate-800 text-slate-400 font-mono">
                {{ w.type }}
              </span>
            </div>
            <div>
              <p class="text-xs font-bold text-slate-200 truncate">{{ w.name }}</p>
              <p class="text-sm font-black text-emerald-400 mt-1">
                {{ formatRupiah(w.balance) }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- ANALYTICS SECTION: CHARTS & BREAKDOWN -->
      <div class="space-y-3">
        <!-- Mobile Segmented Tabs for Analytics (Chart vs Snapshot) -->
        <div class="sm:hidden flex rounded-xl bg-slate-900 p-1 border border-slate-800">
          <button
            @click="activeAnalyticsTab = 'trend'"
            class="flex-1 py-1.5 text-xs font-bold rounded-lg transition cursor-pointer"
            :class="activeAnalyticsTab === 'trend' ? 'bg-emerald-500 text-slate-950 shadow' : 'text-slate-400 hover:text-white'"
          >
            📊 Tren 6 Bulan
          </button>
          <button
            @click="activeAnalyticsTab = 'snapshot'"
            class="flex-1 py-1.5 text-xs font-bold rounded-lg transition cursor-pointer"
            :class="activeAnalyticsTab === 'snapshot' ? 'bg-emerald-500 text-slate-950 shadow' : 'text-slate-400 hover:text-white'"
          >
            📋 Ringkasan
          </button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
          <!-- 6 Month Cashflow Trend -->
          <div
            class="lg:col-span-2 p-4 sm:p-6 rounded-2xl bg-slate-900/80 border border-slate-800"
            :class="activeAnalyticsTab === 'trend' ? 'block' : 'hidden sm:block'"
          >
            <div class="flex flex-col xs:flex-row xs:items-center justify-between gap-2 mb-5">
              <div>
                <h2 class="text-sm sm:text-base font-bold text-white">Tren Arus Kas 6 Bulan</h2>
                <p class="text-[11px] sm:text-xs text-slate-400">Pemasukan vs pengeluaran historis</p>
              </div>
              <div class="flex items-center gap-3 text-[11px] font-medium">
                <span class="flex items-center gap-1.5 text-emerald-400">
                  <span class="w-2.5 h-2.5 rounded bg-emerald-500"></span> Masuk
                </span>
                <span class="flex items-center gap-1.5 text-rose-400">
                  <span class="w-2.5 h-2.5 rounded bg-rose-500"></span> Keluar
                </span>
              </div>
            </div>

            <!-- SVG Responsive Bar Chart -->
            <div class="overflow-x-auto pb-1">
              <div class="h-56 sm:h-64 min-w-[260px] flex items-end justify-between gap-2 sm:gap-3 pt-6 border-b border-slate-800">
                <div
                  v-for="item in financeStore.monthlyTrend"
                  :key="item.month"
                  class="flex-1 flex flex-col items-center gap-2 h-full justify-end group cursor-pointer"
                >
                  <div class="w-full flex items-end justify-center gap-1 sm:gap-1.5 h-40 sm:h-48">
                    <!-- Income bar -->
                    <div
                      class="w-3 sm:w-5 bg-gradient-to-t from-emerald-600 to-emerald-400 rounded-t-md transition-all duration-300 relative group-hover:brightness-110"
                      :style="{ height: `${calculateBarHeight(item.income)}%` }"
                      :title="`Pemasukan: ${formatRupiah(item.income)}`"
                    ></div>
                    <!-- Expense bar -->
                    <div
                      class="w-3 sm:w-5 bg-gradient-to-t from-rose-600 to-rose-400 rounded-t-md transition-all duration-300 relative group-hover:brightness-110"
                      :style="{ height: `${calculateBarHeight(item.expense)}%` }"
                      :title="`Pengeluaran: ${formatRupiah(item.expense)}`"
                    ></div>
                  </div>
                  <span class="text-[9px] sm:text-[11px] font-medium text-slate-400 whitespace-nowrap">{{ formatMonthLabel(item.month) }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Monthly Snapshot Card -->
          <div
            class="p-4 sm:p-6 rounded-2xl bg-slate-900/80 border border-slate-800 flex flex-col justify-between"
            :class="activeAnalyticsTab === 'snapshot' ? 'block' : 'hidden sm:flex'"
          >
            <div>
              <div class="flex items-center justify-between mb-4">
                <div>
                  <h2 class="text-sm sm:text-base font-bold text-white">Ringkasan Bulan Ini</h2>
                  <p class="text-[11px] sm:text-xs text-slate-400">Ikhtisar mutasi & tabungan</p>
                </div>
                <span class="text-[10px] px-2 py-0.5 rounded-full bg-slate-800 text-slate-300 font-mono">
                  {{ financeStore.selectedMonth }}
                </span>
              </div>

              <div class="space-y-3">
                <div class="p-3 rounded-xl bg-slate-950/60 border border-slate-800/80 space-y-1">
                  <div class="flex items-center justify-between text-xs">
                    <span class="text-slate-400">Total Pemasukan</span>
                    <span class="font-bold text-emerald-400">+{{ formatRupiah(financeStore.summary?.total_income || 0) }}</span>
                  </div>
                </div>

                <div class="p-3 rounded-xl bg-slate-950/60 border border-slate-800/80 space-y-1">
                  <div class="flex items-center justify-between text-xs">
                    <span class="text-slate-400">Total Pengeluaran</span>
                    <span class="font-bold text-rose-400">-{{ formatRupiah(financeStore.summary?.total_expense || 0) }}</span>
                  </div>
                </div>

                <div class="p-3 rounded-xl bg-slate-950/60 border border-slate-800/80 space-y-1">
                  <div class="flex items-center justify-between text-xs">
                    <span class="text-slate-400">Tabungan Bersih</span>
                    <span class="font-black" :class="(financeStore.summary?.net_savings || 0) >= 0 ? 'text-emerald-400' : 'text-rose-400'">
                      {{ (financeStore.summary?.net_savings || 0) >= 0 ? '+' : '' }}{{ formatRupiah(financeStore.summary?.net_savings || 0) }}
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <RouterLink
              to="/analytics"
              class="mt-5 pt-3 border-t border-slate-800 text-center text-xs font-semibold text-emerald-400 hover:text-emerald-300 transition block"
            >
              Lihat Analisis Lengkap ➔
            </RouterLink>
          </div>
        </div>
      </div>

      <!-- 2 SUB-CARDS: BUDGETS & FINANCIAL GOALS -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <!-- Status Anggaran -->
        <div class="p-4 sm:p-6 rounded-2xl bg-slate-900/80 border border-slate-800">
          <div class="flex items-center justify-between mb-3.5">
            <div>
              <h2 class="text-sm sm:text-base font-bold text-white">Status Anggaran</h2>
              <p class="text-[11px] text-slate-400">Plafon batas pengeluaran bulan ini</p>
            </div>
            <RouterLink to="/budgets" class="text-xs text-emerald-400 hover:underline font-semibold">Semua ➔</RouterLink>
          </div>

          <div v-if="financeStore.budgets.length === 0" class="py-8 text-center text-slate-500 text-xs">
            Belum ada anggaran yang diatur.
          </div>
          <div v-else class="space-y-2.5">
            <div
              v-for="b in financeStore.budgets.slice(0, 3)"
              :key="b.id"
              class="p-3 rounded-xl bg-slate-950/60 border border-slate-800/80 space-y-1.5"
            >
              <div class="flex items-center justify-between text-xs">
                <span class="font-medium text-slate-200 truncate pr-2">🎯 {{ b.description || 'Batas Pengeluaran Bulanan' }}</span>
                <span
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                  :class="b.percentage >= 90 ? 'bg-rose-500/20 text-rose-400 border border-rose-500/30' : (b.percentage >= 70 ? 'bg-amber-500/20 text-amber-400' : 'bg-emerald-500/20 text-emerald-400')"
                >
                  {{ b.percentage }}% Terpakai
                </span>
              </div>
              <div class="w-full bg-slate-800 h-1.5 rounded-full overflow-hidden">
                <div
                  class="h-full rounded-full transition-all"
                  :class="b.percentage >= 90 ? 'bg-rose-500' : (b.percentage >= 70 ? 'bg-amber-500' : 'bg-emerald-500')"
                  :style="{ width: `${Math.min(b.percentage, 100)}%` }"
                ></div>
              </div>
              <div class="flex justify-between text-[10px] text-slate-400">
                <span>Sisa: {{ formatRupiah(b.remaining_amount) }}</span>
                <span>Batas: {{ formatRupiah(b.limit_amount) }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Target Tabungan -->
        <div class="p-4 sm:p-6 rounded-2xl bg-slate-900/80 border border-slate-800">
          <div class="flex items-center justify-between mb-3.5">
            <div>
              <h2 class="text-sm sm:text-base font-bold text-white">Target Tabungan</h2>
              <p class="text-[11px] text-slate-400">Progres pencapaian impian finansial</p>
            </div>
            <RouterLink to="/goals" class="text-xs text-emerald-400 hover:underline font-semibold">Semua ➔</RouterLink>
          </div>

          <div v-if="financeStore.goals.length === 0" class="py-8 text-center text-slate-500 text-xs">
            Belum ada target tabungan aktif.
          </div>
          <div v-else class="space-y-2.5">
            <div
              v-for="g in financeStore.goals.slice(0, 3)"
              :key="g.id"
              class="p-3 rounded-xl bg-slate-950/60 border border-slate-800/80 space-y-1.5"
            >
              <div class="flex items-center justify-between text-xs">
                <span class="font-bold text-white truncate pr-2">🏆 {{ g.name }}</span>
                <span class="text-emerald-400 font-bold text-xs shrink-0">{{ g.progress_percent }}%</span>
              </div>
              <div class="w-full bg-slate-800 h-1.5 rounded-full overflow-hidden">
                <div
                  class="bg-gradient-to-r from-emerald-500 to-teal-400 h-full rounded-full transition-all"
                  :style="{ width: `${Math.min(g.progress_percent, 100)}%` }"
                ></div>
              </div>
              <div class="flex justify-between text-[10px] text-slate-400">
                <span>Terkumpul: {{ formatRupiah(g.current_amount) }}</span>
                <span>Target: {{ formatRupiah(g.target_amount) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- RECENT TRANSACTIONS (Unpressed Ledger Table & Native Mobile Cards) -->
      <div class="p-4 sm:p-6 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-sm">
        <div class="flex items-center justify-between mb-4">
          <div>
            <h2 class="text-sm sm:text-base font-bold text-white flex items-center gap-2">
              <span>Transaksi Terbaru</span>
              <span v-if="dashboardTransactions.length > 0" class="text-[10px] px-2 py-0.5 rounded-full bg-slate-800 text-slate-300 font-mono">
                {{ dashboardTransactions.length }}
              </span>
            </h2>
            <p class="text-[11px] sm:text-xs text-slate-400">Catatan mutasi kas terkini</p>
          </div>
          <RouterLink to="/transactions" class="text-xs font-semibold text-emerald-400 hover:underline">
            Lihat Semua ➔
          </RouterLink>
        </div>

        <!-- FRIENDLY EMPTY STATE WITH CTA (When genuinely no transactions) -->
        <div
          v-if="dashboardTransactions.length === 0"
          class="py-12 px-4 text-center rounded-2xl bg-slate-950/40 border border-slate-800/60 space-y-3"
        >
          <div class="w-14 h-14 rounded-2xl bg-slate-800/80 text-emerald-400 flex items-center justify-center text-2xl mx-auto shadow-inner">
            📝
          </div>
          <div class="space-y-1">
            <h3 class="text-sm font-bold text-white">Belum Ada Transaksi di Bulan Ini</h3>
            <p class="text-xs text-slate-400 max-w-sm mx-auto leading-relaxed">
              Catat pemasukan atau pengeluaran pertama Anda untuk mulai memantau analitik keuangan secara akurat.
            </p>
          </div>
          <div class="pt-2">
            <button
              @click="$emit('open-transaction-modal')"
              class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 text-slate-950 font-bold text-xs shadow-lg shadow-emerald-500/20 active:scale-95 transition cursor-pointer"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
              <span>Catat Transaksi Sekarang</span>
            </button>
          </div>
        </div>

        <!-- MOBILE NATIVE LIST VIEW (< sm:hidden) -->
        <div v-else class="sm:hidden space-y-2">
          <div
            v-for="t in dashboardTransactions.slice(0, 5)"
            :key="t.id"
            class="p-3.5 rounded-2xl bg-slate-950/60 hover:bg-slate-800/50 border border-slate-800/80 flex items-center justify-between gap-3 active:scale-98 transition shadow-sm"
          >
            <div class="flex items-center gap-3 min-w-0">
              <div
                class="w-11 h-11 rounded-2xl flex items-center justify-center shrink-0 text-lg shadow-inner"
                :class="t.type === 'INCOME' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/25' : (t.type === 'TRANSFER' ? 'bg-blue-500/10 text-blue-400 border border-blue-500/25' : 'bg-rose-500/10 text-rose-400 border border-rose-500/25')"
              >
                <span>{{ t.type === 'INCOME' ? '💰' : (t.type === 'TRANSFER' ? '↔' : '💸') }}</span>
              </div>
              <div class="min-w-0">
                <p class="text-xs font-bold text-white truncate">{{ t.description || 'Transaksi Tanpa Keterangan' }}</p>
                <div class="flex items-center gap-2 text-[10px] text-slate-400 mt-1 flex-wrap">
                  <span class="whitespace-nowrap">{{ formatDate(t.transaction_date) }}</span>
                  <span>•</span>
                  <span class="text-slate-300 font-semibold">👛 {{ t.wallet_name || 'Cash' }}</span>
                </div>
              </div>
            </div>
            <div class="text-right shrink-0">
              <p
                class="text-xs sm:text-sm font-black whitespace-nowrap"
                :class="vaultStore.isMasked ? 'text-slate-400' : (t.type === 'INCOME' ? 'text-emerald-400' : (t.type === 'TRANSFER' ? 'text-blue-400' : 'text-rose-400'))"
              >
                <span v-if="vaultStore.isMasked">Rp ••••••••</span>
                <span v-else>{{ t.type === 'INCOME' ? '+' : (t.type === 'TRANSFER' ? '↔ ' : '-') }}{{ formatRupiah(t.amount) }}</span>
              </p>
              <span
                v-if="t.type === 'TRANSFER'"
                class="text-[9px] px-1.5 py-0.2 rounded bg-blue-500/20 text-blue-400 font-semibold uppercase mt-0.5 inline-block"
              >
                Transfer
              </span>
            </div>
          </div>
        </div>

        <!-- DESKTOP SPACIOUS UNPRESSED TABLE VIEW (hidden sm:block) -->
        <div v-if="dashboardTransactions.length > 0" class="hidden sm:block overflow-x-auto -mx-1">
          <table class="w-full text-left text-xs min-w-[700px] border-collapse">
            <thead>
              <tr class="text-slate-400 uppercase text-[10px] font-bold tracking-wider border-b border-slate-800/80 bg-slate-950/40">
                <th class="py-3.5 px-5 w-28 font-bold">Tanggal</th>
                <th class="py-3.5 px-5 min-w-[200px] font-bold">Deskripsi</th>
                <th class="py-3.5 px-5 w-32 font-bold">Dompet</th>
                <th class="py-3.5 px-5 w-24 text-center font-bold">Tipe</th>
                <th class="py-3.5 px-5 w-36 text-right font-bold">Nominal</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
              <tr
                v-for="t in dashboardTransactions.slice(0, 5)"
                :key="t.id"
                class="hover:bg-slate-800/40 transition group cursor-pointer"
              >
                <!-- Date -->
                <td class="py-4 px-5 text-slate-400 whitespace-nowrap font-mono text-[11px]">
                  {{ formatDate(t.transaction_date) }}
                </td>

                <!-- Description -->
                <td class="py-4 px-5 font-bold text-slate-200">
                  <div class="flex items-center gap-2">
                    <span class="truncate max-w-sm">{{ t.description || 'Transaksi' }}</span>
                  </div>
                </td>

                <!-- Wallet -->
                <td class="py-4 px-5 whitespace-nowrap">
                  <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-slate-800/80 text-slate-200 border border-slate-700/60">
                    <span>👛</span>
                    <span>{{ t.wallet_name || 'Cash' }}</span>
                  </span>
                </td>

                <!-- Type Badge -->
                <td class="py-4 px-5 text-center">
                  <span
                    class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider"
                    :class="t.type === 'INCOME' ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/25' : (t.type === 'TRANSFER' ? 'bg-blue-500/15 text-blue-400 border border-blue-500/25' : 'bg-rose-500/15 text-rose-400 border border-rose-500/25')"
                  >
                    {{ t.type }}
                  </span>
                </td>

                <!-- Amount -->
                <td
                  class="py-4 px-5 text-right font-black text-sm whitespace-nowrap"
                  :class="vaultStore.isMasked ? 'text-slate-400' : (t.type === 'INCOME' ? 'text-emerald-400' : (t.type === 'TRANSFER' ? 'text-blue-400' : 'text-rose-400'))"
                >
                  <span v-if="vaultStore.isMasked">Rp ••••••••</span>
                  <span v-else>{{ t.type === 'INCOME' ? '+' : (t.type === 'TRANSFER' ? '↔ ' : '-') }}{{ formatRupiah(t.amount) }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useFinanceStore } from '../stores/finance';
import { useVaultStore } from '../stores/vault';
import { formatRupiah as rawFormatRupiah, formatDate } from '../utils/formatters';
import MonthPicker from '../components/common/MonthPicker.vue';

defineEmits(['open-transaction-modal']);

const financeStore = useFinanceStore();
const vaultStore = useVaultStore();

const isInitialLoading = ref(!financeStore.hasLoadedFeed);
const isSyncing = ref(false);
const isError = ref(false);
const errorMessage = ref('');

const activeAnalyticsTab = ref('trend'); // 'trend' | 'breakdown' for mobile switcher

const now = new Date();
const localMonth = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
const selectedMonth = ref(localMonth);

const dashboardTransactions = computed(() => {
  if (financeStore.recentTransactions && financeStore.recentTransactions.length > 0) {
    return financeStore.recentTransactions;
  }
  return financeStore.transactions;
});

const botRawNumber = computed(() => {
  return typeof window !== 'undefined' && window.__BOT_CONFIG__?.bot_number
    ? window.__BOT_CONFIG__.bot_number
    : '6285111355992';
});

const whatsappBotLink = computed(() => {
  return `https://wa.me/${botRawNumber.value}?text=Halo%20HematWoy,%20saya%20mau%20catat%20keuangan!`;
});

function formatRupiah(val) {
  if (vaultStore.isMasked) return 'Rp ••••••••';
  return rawFormatRupiah(val ?? 0);
}

function getWalletIcon(type) {
  if (type === 'BANK') return '🏦';
  if (type === 'EWALLET') return '📱';
  if (type === 'CASH') return '💵';
  return '📁';
}

function formatMonthLabel(monthStr) {
  if (!monthStr) return '';
  const [, m] = monthStr.split('-');
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
  return months[parseInt(m, 10) - 1] || monthStr;
}

function calculateBarHeight(val) {
  const max = Math.max(
    ...financeStore.monthlyTrend.map((t) => Math.max(t.income, t.expense)),
    1000000
  );
  return Math.min(Math.round((val / max) * 100), 100);
}

async function loadDashboard(force = false) {
  if (!financeStore.hasLoadedFeed) {
    isInitialLoading.value = true;
  } else {
    isInitialLoading.value = false;
    isSyncing.value = true;
  }

  isError.value = false;
  errorMessage.value = '';

  try {
    const data = await financeStore.fetchDashboardFeed(selectedMonth.value, force);
    if (!data) {
      isError.value = true;
      errorMessage.value = 'Tidak dapat menghubungi server. Silakan coba kembali.';
    }
  } catch (err) {
    // Only show error screen if we have no cached data at all
    if (!financeStore.hasLoadedFeed) {
      isError.value = true;
      errorMessage.value = err.response?.data?.message || err.message || 'Terjadi kesalahan saat mengambil data.';
    }
  } finally {
    isInitialLoading.value = false;
    isSyncing.value = false;
  }
}

onMounted(() => {
  loadDashboard();
});
</script>
