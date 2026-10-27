<template>
  <div class="space-y-5 sm:space-y-6">
    <!-- Header Page -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight flex items-center gap-2.5">
          <span>Pusat Transaksi</span>
          <!-- Active sync indicator badge -->
          <span
            v-if="isSyncing"
            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30 shadow-sm animate-pulse"
          >
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-ping"></span>
            <span>Memuat data...</span>
          </span>
        </h1>
        <p class="text-xs text-slate-400 mt-0.5">Kelola, cari, dan tinjau seluruh riwayat mutasi keuangan Anda</p>
      </div>

      <div class="flex items-center gap-2.5 flex-wrap self-start sm:self-auto">
        <button
          @click="openCreateModal"
          class="px-4 py-2.5 rounded-xl font-bold text-xs flex items-center gap-2 shadow-lg cursor-pointer transition active:scale-95"
          :class="authStore.isPro ? 'bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 text-slate-950 shadow-emerald-500/20' : 'bg-slate-800 text-amber-300 border border-amber-500/40 hover:bg-slate-750 shadow-amber-500/10'"
        >
          <span v-if="!authStore.isPro">🔒</span>
          <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
          <span>{{ authStore.isPro ? 'Catat Transaksi Baru' : 'Catat Transaksi Baru (Pro)' }}</span>
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
          <h3 class="text-sm font-bold text-rose-200">Gagal Memuat Transaksi</h3>
          <p class="text-xs text-rose-300/90 mt-0.5 leading-relaxed">
            {{ errorMessage || 'Terjadi gangguan koneksi saat mengambil daftar transaksi.' }}
          </p>
        </div>
      </div>
      <button
        @click="applyFilters"
        class="self-start sm:self-auto px-4 py-2 rounded-xl bg-rose-500 hover:bg-rose-400 text-slate-950 font-bold text-xs flex items-center gap-1.5 transition active:scale-95 cursor-pointer shadow-md"
      >
        <span>🔄 Coba Lagi</span>
      </button>
    </div>

    <!-- FILTER BAR & LIVE SEARCH -->
    <div class="p-4 sm:p-5 rounded-2xl bg-slate-900/90 border border-slate-800 space-y-3.5 shadow-sm">
      <!-- Date Presets & Direct 1-Click Exports Toolbar -->
      <div class="flex flex-wrap items-center justify-between gap-2.5 border-b border-slate-800/80 pb-3">
        <div class="flex items-center gap-1.5 flex-wrap">
          <span class="text-[11px] text-slate-400 font-semibold mr-1">Rentang:</span>
          <button
            type="button"
            @click="setDatePreset('all')"
            :class="activeDatePreset === 'all' ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/40 font-bold' : 'bg-slate-950 text-slate-400 border-slate-800 hover:text-slate-200'"
            class="text-[11px] px-3 py-1 rounded-xl border transition cursor-pointer"
          >
            Semua
          </button>
          <button
            type="button"
            @click="setDatePreset('today')"
            :class="activeDatePreset === 'today' ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/40 font-bold' : 'bg-slate-950 text-slate-400 border-slate-800 hover:text-slate-200'"
            class="text-[11px] px-3 py-1 rounded-xl border transition cursor-pointer"
          >
            Hari Ini
          </button>
          <button
            type="button"
            @click="setDatePreset('7days')"
            :class="activeDatePreset === '7days' ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/40 font-bold' : 'bg-slate-950 text-slate-400 border-slate-800 hover:text-slate-200'"
            class="text-[11px] px-3 py-1 rounded-xl border transition cursor-pointer"
          >
            7 Hari Terakhir
          </button>
          <button
            type="button"
            @click="setDatePreset('this_month')"
            :class="activeDatePreset === 'this_month' ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/40 font-bold' : 'bg-slate-950 text-slate-400 border-slate-800 hover:text-slate-200'"
            class="text-[11px] px-3 py-1 rounded-xl border transition cursor-pointer"
          >
            Bulan Ini
          </button>
        </div>

        <!-- 1-Click Export Buttons & Reset Filter -->
        <div class="flex items-center gap-2 flex-wrap">
          <button
            type="button"
            @click="triggerExport('excel')"
            :disabled="isExporting || isInitialLoading"
            class="px-3 py-1.5 rounded-xl bg-slate-900 border border-slate-800 hover:border-emerald-500/50 hover:bg-emerald-500/10 text-emerald-400 font-bold text-xs flex items-center gap-1.5 shadow-sm transition cursor-pointer active:scale-95 disabled:opacity-50"
            title="Unduh Spreadsheet Excel berdasarkan transaksi hasil filter"
          >
            <svg v-if="exportingFormat === 'excel'" class="w-3.5 h-3.5 animate-spin text-emerald-400" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span v-else>{{ authStore.isPro ? '📊' : '🔒' }}</span>
            <span>{{ exportingFormat === 'excel' ? 'Mengunduh...' : 'Excel' }}</span>
          </button>

          <button
            type="button"
            @click="triggerExport('pdf')"
            :disabled="isExporting || isInitialLoading"
            class="px-3 py-1.5 rounded-xl bg-slate-900 border border-slate-800 hover:border-rose-500/50 hover:bg-rose-500/10 text-rose-300 font-bold text-xs flex items-center gap-1.5 shadow-sm transition cursor-pointer active:scale-95 disabled:opacity-50"
            title="Unduh Rekening Koran PDF Resmi berdasarkan transaksi hasil filter"
          >
            <svg v-if="exportingFormat === 'pdf'" class="w-3.5 h-3.5 animate-spin text-rose-400" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span v-else>{{ authStore.isPro ? '📑' : '🔒' }}</span>
            <span>{{ exportingFormat === 'pdf' ? 'Mencetak...' : 'PDF Resmi' }}</span>
          </button>

          <button
            v-if="hasActiveFilters"
            @click="resetFilters"
            type="button"
            class="text-xs text-rose-400 hover:text-rose-300 font-bold underline transition cursor-pointer ml-1"
          >
            Reset Filter
          </button>
        </div>
      </div>

      <!-- Uniform Inputs Grid: Live Search, Type, Wallet, Target, Budget, Date Range -->
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-7 gap-3">
        <!-- Live Search -->
        <div class="relative sm:col-span-2 md:col-span-1 lg:col-span-2 xl:col-span-1">
          <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs">🔍</span>
          <input
            type="text"
            v-model="filters.search"
            @input="debounceSearch"
            placeholder="Cari deskripsi (kopi, bensin)..."
            class="w-full pl-9 pr-4 py-2.5 rounded-xl bg-slate-900 border border-slate-800 hover:border-slate-700 text-slate-200 text-xs font-semibold focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/40 shadow-inner transition placeholder:text-slate-500"
          />
        </div>

        <!-- Filter Type -->
        <div>
          <SelectDropdown
            v-model="filters.type"
            :options="typeOptions"
            placeholder="Semua Tipe"
            icon="🔄"
            @change="onFilterChange"
          />
        </div>

        <!-- Filter Wallet -->
        <div>
          <SelectDropdown
            v-model="filters.wallet_id"
            :options="walletOptions"
            placeholder="Semua Dompet"
            icon="👛"
            @change="onFilterChange"
          />
        </div>

        <!-- Filter Target Tabungan -->
        <div>
          <SelectDropdown
            v-model="filters.goal_id"
            :options="goalFilterOptions"
            placeholder="Semua Target"
            icon="🎯"
            @change="onFilterChange"
          />
        </div>

        <!-- Filter Pos Anggaran -->
        <div>
          <SelectDropdown
            v-model="filters.budget_id"
            :options="budgetFilterOptions"
            placeholder="Semua Anggaran"
            icon="📊"
            @change="onFilterChange"
          />
        </div>

        <!-- Date Range Filter: Start Date -->
        <div>
          <DatePicker
            v-model="filters.start_date"
            placeholder="Tgl Mulai"
            title="Tanggal Mulai"
            @change="onCustomDateChange"
          />
        </div>

        <!-- Date Range Filter: End Date -->
        <div>
          <DatePicker
            v-model="filters.end_date"
            placeholder="Tgl Selesai"
            title="Tanggal Selesai"
            :min="filters.start_date"
            @change="onCustomDateChange"
          />
        </div>
      </div>
    </div>

    <!-- TRANSACTIONS CONTAINER -->
    <div class="rounded-2xl bg-slate-900/90 border border-slate-800 overflow-hidden shadow-sm">
      <!-- SKELETON TABLE DURING INITIAL COLD LOAD -->
      <div v-if="isInitialLoading" class="p-5 space-y-4">
        <div class="flex items-center justify-between">
          <div class="h-4 w-40 bg-slate-800 rounded animate-pulse"></div>
          <div class="h-4 w-20 bg-slate-800 rounded animate-pulse"></div>
        </div>
        <!-- Desktop Skeleton Rows -->
        <div class="hidden md:block space-y-3">
          <div v-for="k in 5" :key="k" class="h-12 bg-slate-950/60 rounded-xl animate-pulse"></div>
        </div>
        <!-- Mobile Skeleton Cards -->
        <div class="md:hidden space-y-3">
          <div v-for="m in 4" :key="m" class="h-16 bg-slate-950/60 rounded-2xl animate-pulse"></div>
        </div>
      </div>

      <!-- EMPTY STATE (Rendered only after loading completes and genuinely 0 items found) -->
      <div
        v-else-if="financeStore.transactions.length === 0"
        class="py-16 px-4 text-center flex flex-col items-center justify-center space-y-3"
      >
        <div class="w-16 h-16 rounded-2xl bg-slate-800/80 border border-slate-700/60 flex items-center justify-center text-3xl shadow-inner">
          📝
        </div>
        <div class="space-y-1">
          <h3 class="text-sm sm:text-base font-bold text-white">
            {{ hasActiveFilters ? 'Tidak Ada Transaksi yang Cocok' : 'Belum Ada Transaksi' }}
          </h3>
          <p class="text-xs text-slate-400 max-w-sm mx-auto leading-relaxed">
            {{ hasActiveFilters ? 'Coba ubah kata kunci atau setel ulang filter tanggal & kategori untuk melihat mutasi lainnya.' : 'Catat transaksi pemasukan atau pengeluaran pertama Anda untuk mulai memantau keuangan secara otomatis!' }}
          </p>
        </div>
        <div class="pt-2">
          <button
            v-if="hasActiveFilters"
            @click="resetFilters"
            type="button"
            class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-750 text-slate-200 font-bold text-xs transition cursor-pointer"
          >
            Reset Filter
          </button>
          <button
            v-else
            @click="openCreateModal"
            type="button"
            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 text-slate-950 font-bold text-xs transition shadow-md shadow-emerald-500/20 cursor-pointer active:scale-95"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
            <span>Catat Transaksi Pertama</span>
          </button>
        </div>
      </div>

      <!-- DESKTOP SPACIOUS UNPRESSED TABLE VIEW (Hidden on Mobile) -->
      <div
        v-else
        class="hidden md:block overflow-x-auto"
        :class="isSyncing ? 'opacity-70 transition-opacity pointer-events-none' : 'transition-opacity'"
      >
        <table class="w-full text-left text-xs min-w-[780px] border-collapse">
          <thead>
            <tr class="text-slate-400 uppercase text-[10px] font-bold tracking-wider bg-slate-950/60 border-b border-slate-800">
              <th class="py-3.5 px-5 w-28 font-bold">Tanggal</th>
              <th class="py-3.5 px-5 min-w-[200px] font-bold">Deskripsi</th>
              <th class="py-3.5 px-5 w-32 font-bold">Dompet</th>
              <th class="py-3.5 px-5 w-28 text-center font-bold">Kanal</th>
              <th class="py-3.5 px-5 w-36 text-right font-bold">Nominal</th>
              <th class="py-3.5 px-5 w-20 text-center font-bold">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800/60">
            <tr
              v-for="t in financeStore.transactions"
              :key="t.id"
              class="hover:bg-slate-800/40 transition group cursor-pointer"
            >
              <!-- Date -->
              <td class="py-4 px-5 text-slate-400 whitespace-nowrap font-mono text-[11px]">
                {{ formatDate(t.transaction_date) }}
              </td>

              <!-- Description -->
              <td class="py-4 px-5 font-bold text-slate-200">
                <div class="flex items-center gap-2.5">
                  <span
                    class="w-2.5 h-2.5 rounded-full shrink-0"
                    :class="t.goal_id ? 'bg-cyan-400 ring-2 ring-cyan-400/20' : (t.budget_id ? 'bg-amber-400 ring-2 ring-amber-400/20' : (t.type === 'INCOME' ? 'bg-emerald-400' : (t.type === 'TRANSFER' ? 'bg-blue-400' : 'bg-rose-400')))"
                  ></span>
                  <div class="flex flex-col min-w-0">
                    <span class="truncate max-w-sm">{{ t.description || 'Transaksi' }}</span>
                    <div class="flex items-center gap-1.5 flex-wrap mt-0.5">
                      <span
                        v-if="t.goal_name"
                        class="inline-flex items-center gap-1 text-[10px] text-cyan-300 font-semibold px-1.5 py-0.5 rounded bg-cyan-500/10 border border-cyan-500/25"
                      >
                        <span>🎯</span>
                        <span>Target: {{ t.goal_name }}</span>
                      </span>
                      <span
                        v-if="t.budget_name"
                        class="inline-flex items-center gap-1 text-[10px] text-amber-300 font-semibold px-1.5 py-0.5 rounded bg-amber-500/10 border border-amber-500/25"
                      >
                        <span>📊</span>
                        <span>Pos: {{ t.budget_name }}</span>
                      </span>
                    </div>
                  </div>
                </div>
              </td>

              <!-- Wallet -->
              <td class="py-4 px-5 whitespace-nowrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-slate-800/80 text-slate-200 border border-slate-700/60">
                  <span>👛</span>
                  <span>{{ t.wallet_name || 'Cash' }}</span>
                </span>
              </td>

              <!-- Source Channel (WhatsApp vs Web) -->
              <td class="py-4 px-5 text-center">
                <span
                  class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold"
                  :class="t.channel === 'WHATSAPP' || !t.channel ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/25' : 'bg-blue-500/15 text-blue-400 border border-blue-500/25'"
                >
                  <span>{{ t.channel === 'WEB' ? '💻 Web' : '📱 WhatsApp' }}</span>
                </span>
              </td>

              <!-- Amount -->
              <td
                class="py-4 px-5 text-right font-black text-sm whitespace-nowrap"
                :class="vaultStore.isMasked ? 'text-slate-400' : (t.goal_id ? 'text-cyan-300' : (t.type === 'INCOME' ? 'text-emerald-400' : (t.type === 'TRANSFER' ? 'text-blue-400' : 'text-rose-400')))"
              >
                <span v-if="vaultStore.isMasked">Rp ••••••••</span>
                <span v-else>
                  <span>{{ t.type === 'INCOME' ? '+' : (t.type === 'TRANSFER' ? '↔ ' : '-') }}{{ formatRupiah(t.amount) }}</span>
                  <span v-if="t.goal_id" class="block text-[10px] font-semibold text-cyan-400/80">Tabungan</span>
                  <span v-else-if="t.budget_name && t.type === 'EXPENSE'" class="block text-[10px] font-semibold text-amber-400/80">Anggaran</span>
                </span>
              </td>

              <!-- Actions (Edit & Delete) -->
              <td class="py-4 px-5 text-center whitespace-nowrap">
                <div class="flex items-center justify-center gap-1.5">
                  <button
                    @click="editTransaction(t)"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-emerald-400 hover:bg-slate-800 transition cursor-pointer"
                    title="Edit"
                  >
                    ✏️
                  </button>
                  <button
                    @click="promptDelete(t)"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-rose-400 hover:bg-slate-800 transition cursor-pointer"
                    title="Hapus"
                  >
                    🗑️
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- MOBILE NATIVE SPACIOUS CARDS (Ergonomic mobile-first layout, not squeezed desktop) -->
      <div
        v-if="financeStore.transactions.length > 0"
        class="md:hidden divide-y divide-slate-800/80"
        :class="isSyncing ? 'opacity-70 transition-opacity pointer-events-none' : 'transition-opacity'"
      >
        <div
          v-for="t in financeStore.transactions"
          :key="'mobile-' + t.id"
          class="p-4 hover:bg-slate-800/30 transition space-y-3"
        >
          <!-- Top Row: Icon + Description + Amount -->
          <div class="flex items-start justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
              <div
                class="w-10 h-10 rounded-2xl flex items-center justify-center text-lg shrink-0 border shadow-inner"
                :class="t.goal_id ? 'bg-cyan-500/15 border-cyan-500/30 text-cyan-300' : (t.budget_id ? 'bg-amber-500/15 border-amber-500/30 text-amber-300' : (t.type === 'INCOME' ? 'bg-emerald-500/15 border-emerald-500/30 text-emerald-400' : (t.type === 'TRANSFER' ? 'bg-blue-500/15 border-blue-500/30 text-blue-400' : 'bg-rose-500/15 border-rose-500/30 text-rose-400')))"
              >
                <span>{{ t.goal_id ? '🎯' : (t.budget_id ? '📊' : (t.type === 'INCOME' ? '💰' : (t.type === 'TRANSFER' ? '⇄' : '💸'))) }}</span>
              </div>
              <div class="min-w-0">
                <p class="font-bold text-xs sm:text-sm text-white truncate">{{ t.description || 'Transaksi' }}</p>
                <p class="text-[11px] text-slate-400 font-mono mt-0.5">{{ formatDate(t.transaction_date) }}</p>
              </div>
            </div>

            <div class="text-right shrink-0">
              <p
                class="font-black text-sm"
                :class="vaultStore.isMasked ? 'text-slate-400' : (t.goal_id ? 'text-cyan-300' : (t.type === 'INCOME' ? 'text-emerald-400' : (t.type === 'TRANSFER' ? 'text-blue-400' : 'text-rose-400')))"
              >
                <span v-if="vaultStore.isMasked">Rp ••••••••</span>
                <span v-else>{{ t.type === 'INCOME' ? '+' : (t.type === 'TRANSFER' ? '↔ ' : '-') }}{{ formatRupiah(t.amount) }}</span>
              </p>
              <span v-if="t.goal_id" class="inline-block text-[10px] font-bold text-cyan-400/90">Tabungan</span>
              <span v-else-if="t.budget_name && t.type === 'EXPENSE'" class="inline-block text-[10px] font-bold text-amber-400/90">Anggaran</span>
            </div>
          </div>

          <!-- Bottom Row: Metadata chips & Action buttons -->
          <div class="flex items-center justify-between gap-2 pt-1 border-t border-slate-800/40 text-[11px]">
            <div class="flex items-center gap-1.5 flex-wrap min-w-0">
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-slate-950 border border-slate-800 text-slate-300 font-medium">
                <span>👛</span>
                <span class="truncate max-w-[100px]">{{ t.wallet_name || 'Cash' }}</span>
              </span>

              <span
                v-if="t.goal_name"
                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-cyan-500/10 border border-cyan-500/30 text-cyan-300 font-semibold"
              >
                <span>🎯</span>
                <span class="truncate max-w-[130px]">{{ t.goal_name }}</span>
              </span>

              <span
                v-if="t.budget_name"
                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-300 font-semibold"
              >
                <span>📊</span>
                <span class="truncate max-w-[130px]">{{ t.budget_name }}</span>
              </span>
            </div>

            <div class="flex items-center gap-1 shrink-0">
              <button
                @click="editTransaction(t)"
                class="p-1.5 rounded-lg text-slate-400 hover:text-emerald-400 hover:bg-slate-800 transition cursor-pointer"
                title="Edit Transaksi"
              >
                ✏️
              </button>
              <button
                @click="promptDelete(t)"
                class="p-1.5 rounded-lg text-slate-400 hover:text-rose-400 hover:bg-slate-800 transition cursor-pointer"
                title="Hapus Transaksi"
              >
                🗑️
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- PAGINATION BAR -->
      <div
        v-if="financeStore.pagination.total_items > 0"
        class="p-4 bg-slate-950/60 border-t border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs text-slate-400"
      >
        <div>
          Menampilkan <span class="font-bold text-slate-200">{{ financeStore.transactions.length }}</span> dari <span class="font-bold text-slate-200">{{ financeStore.pagination.total_items }}</span> data transaksi
        </div>
        <div class="flex items-center gap-2 self-center sm:self-auto">
          <button
            @click="changePage(financeStore.pagination.page - 1)"
            :disabled="financeStore.pagination.page <= 1"
            class="px-3 py-1.5 rounded-xl bg-slate-900 border border-slate-800 hover:bg-slate-800 text-slate-300 font-semibold disabled:opacity-40 transition cursor-pointer"
          >
            &laquo; Prev
          </button>
          <span class="px-2 font-bold text-slate-200">
            Hal {{ financeStore.pagination.page }} / {{ financeStore.pagination.total_pages || 1 }}
          </span>
          <button
            @click="changePage(financeStore.pagination.page + 1)"
            :disabled="financeStore.pagination.page >= financeStore.pagination.total_pages"
            class="px-3 py-1.5 rounded-xl bg-slate-900 border border-slate-800 hover:bg-slate-800 text-slate-300 font-semibold disabled:opacity-40 transition cursor-pointer"
          >
            Next &raquo;
          </button>
        </div>
      </div>
    </div>

    <!-- Edit/Create Modal -->
    <TransactionModal
      :isOpen="showModal"
      :transaction="selectedTransaction"
      @close="showModal = false"
      @saved="refreshList"
    />

    <!-- Delete Confirmation Modal -->
    <ConfirmModal
      :isOpen="showDeleteModal"
      title="Hapus Transaksi?"
      :message="deleteConfirmMessage"
      confirmText="Ya, Hapus Transaksi"
      :danger="true"
      :loading="deleting"
      @confirm="executeDelete"
      @cancel="showDeleteModal = false"
    />

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useFinanceStore } from '../stores/finance';
import { useVaultStore } from '../stores/vault';
import { useToastStore } from '../stores/toast';

const route = useRoute();
import { formatRupiah as rawFormatRupiah, formatDate } from '../utils/formatters';
import { downloadExportReport } from '../utils/exportDownloader';
import SelectDropdown from '../components/common/SelectDropdown.vue';
import DatePicker from '../components/common/DatePicker.vue';
import TransactionModal from '../components/modals/TransactionModal.vue';
import ConfirmModal from '../components/common/ConfirmModal.vue';

const authStore = useAuthStore();
const financeStore = useFinanceStore();
const vaultStore = useVaultStore();
const toastStore = useToastStore();

const typeOptions = [
  { value: '', label: 'Semua Tipe', icon: '🔄' },
  { value: 'EXPENSE', label: 'Pengeluaran', icon: '🔴' },
  { value: 'INCOME', label: 'Pemasukan', icon: '🟢' },
  { value: 'TRANSFER', label: 'Transfer Dompet', icon: '🔵' },
];

const walletOptions = computed(() => [
  { value: '', label: 'Semua Dompet', icon: '👛' },
  ...financeStore.wallets.map(w => ({
    value: w.id,
    label: w.name,
    icon: '👛'
  }))
]);

const goalFilterOptions = computed(() => [
  { value: '', label: 'Semua Target', icon: '🎯' },
  { value: 'none', label: 'Bukan Tabungan (Rutin)', icon: '🛒' },
  { value: 'any', label: 'Semua Tabungan Target', icon: '✨' },
  ...financeStore.goals.map(g => ({
    value: g.id,
    label: `Target: ${g.name}`,
    icon: '🏆',
  })),
]);

const budgetFilterOptions = computed(() => [
  { value: '', label: 'Semua Anggaran', icon: '📊' },
  { value: 'none', label: 'Bukan Pos Anggaran', icon: '⚪' },
  { value: 'any', label: 'Semua Pos Anggaran', icon: '📈' },
  ...financeStore.budgets.map(b => ({
    value: b.id,
    label: `Pos: ${b.description || 'Batas Belanja'}`,
    icon: '📊',
  })),
]);

// Export states (Direct 1-click downloads)
const isExporting = ref(false);
const exportingFormat = ref(''); // 'excel' | 'pdf' | ''

const isInitialLoading = ref(!financeStore.hasLoadedTransactions);
const isSyncing = ref(false);
const isError = ref(false);
const errorMessage = ref('');

function formatRupiah(val) {
  if (vaultStore.isMasked) return 'Rp ••••••••';
  return rawFormatRupiah(val);
}

const showModal = ref(false);
const selectedTransaction = ref(null);

const showDeleteModal = ref(false);
const transactionToDelete = ref(null);
const deleting = ref(false);

const activeDatePreset = ref('all');

const filters = ref({
  search: '',
  type: '',
  wallet_id: '',
  goal_id: route?.query?.goal_id ? String(route.query.goal_id) : '',
  budget_id: route?.query?.budget_id ? String(route.query.budget_id) : '',
  start_date: '',
  end_date: '',
  page: 1,
});

const hasActiveFilters = computed(() => {
  return !!(
    filters.value.search ||
    filters.value.type ||
    filters.value.wallet_id ||
    filters.value.goal_id ||
    filters.value.budget_id ||
    filters.value.start_date ||
    filters.value.end_date
  );
});

const deleteConfirmMessage = computed(() => {
  if (!transactionToDelete.value) return 'Apakah Anda yakin ingin menghapus transaksi ini?';
  const desc = transactionToDelete.value.description;
  const nom = formatRupiah(transactionToDelete.value.amount);
  return `Yakin ingin menghapus transaksi "${desc}" sebesar ${nom}? Saldo dompet Anda akan dikembalikan otomatis.`;
});

function setDatePreset(preset) {
  activeDatePreset.value = preset;
  const now = new Date();
  const formatYMD = (d) => d.toISOString().slice(0, 10);

  if (preset === 'all') {
    filters.value.start_date = '';
    filters.value.end_date = '';
  } else if (preset === 'today') {
    const today = formatYMD(now);
    filters.value.start_date = today;
    filters.value.end_date = today;
  } else if (preset === '7days') {
    const past = new Date();
    past.setDate(past.getDate() - 7);
    filters.value.start_date = formatYMD(past);
    filters.value.end_date = formatYMD(now);
  } else if (preset === 'this_month') {
    const startOfMonth = new Date(now.getFullYear(), now.getMonth(), 1);
    filters.value.start_date = formatYMD(startOfMonth);
    filters.value.end_date = formatYMD(now);
  }

  filters.value.page = 1;
  applyFilters(true);
}

function onFilterChange() {
  filters.value.page = 1;
  applyFilters(true);
}

function onCustomDateChange() {
  activeDatePreset.value = 'custom';
  filters.value.page = 1;
  applyFilters(true);
}

function resetFilters() {
  filters.value = {
    search: '',
    type: '',
    wallet_id: '',
    goal_id: '',
    budget_id: '',
    start_date: '',
    end_date: '',
    page: 1,
  };
  activeDatePreset.value = 'all';
  applyFilters(true);
}

let debounceTimer = null;
function debounceSearch() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    filters.value.page = 1;
    applyFilters(true);
  }, 300);
}

async function applyFilters(force = false) {
  if (!financeStore.hasLoadedTransactions) {
    isInitialLoading.value = true;
  } else {
    isInitialLoading.value = false;
    isSyncing.value = true;
  }

  isError.value = false;
  errorMessage.value = '';

  try {
    await financeStore.fetchTransactions(filters.value, force);
  } catch (err) {
    if (!financeStore.hasLoadedTransactions) {
      isError.value = true;
      errorMessage.value = err.response?.data?.message || err.message || 'Gagal memuat transaksi.';
    }
  } finally {
    isInitialLoading.value = false;
    isSyncing.value = false;
  }
}

/**
 * Direct 1-Click Export Execution without modal popup
 */
async function triggerExport(format) {
  if (!authStore.isPro) {
    toastStore.warning('Ekspor laporan resmi eksklusif untuk pelanggan HematWoy Pro!');
    authStore.openPricing('monthly');
    return;
  }
  if (isExporting.value) return;
  isExporting.value = true;
  exportingFormat.value = format;

  try {
    await downloadExportReport(format, filters.value, toastStore);
  } catch (err) {
    toastStore.error(err.response?.data?.message || 'Gagal mengunduh dokumen laporan. Silakan coba kembali.');
  } finally {
    isExporting.value = false;
    exportingFormat.value = '';
  }
}

function changePage(page) {
  const maxPage = financeStore.pagination.total_pages || 1;
  const targetPage = Math.max(1, Math.min(page, maxPage));
  filters.value.page = targetPage;
  applyFilters(true);
}

function openCreateModal() {
  if (!authStore.isPro) {
    toastStore.warning('Pencatatan mutasi di web terkunci. Silakan upgrade ke HematWoy Pro!');
    authStore.openPricing('monthly');
    return;
  }
  selectedTransaction.value = null;
  showModal.value = true;
}

function editTransaction(item) {
  if (!authStore.isPro) {
    toastStore.warning('Fitur edit transaksi terkunci. Silakan upgrade ke HematWoy Pro!');
    authStore.openPricing('monthly');
    return;
  }
  selectedTransaction.value = item;
  showModal.value = true;
}

function promptDelete(item) {
  if (!authStore.isPro) {
    toastStore.warning('Fitur hapus transaksi terkunci. Silakan upgrade ke HematWoy Pro!');
    authStore.openPricing('monthly');
    return;
  }
  transactionToDelete.value = item;
  showDeleteModal.value = true;
}

async function executeDelete() {
  if (!transactionToDelete.value) return;
  deleting.value = true;
  try {
    await financeStore.deleteTransaction(transactionToDelete.value.id);
    toastStore.success('Transaksi berhasil dihapus dan saldo telah disesuaikan.');
    showDeleteModal.value = false;
    applyFilters();
  } catch (err) {
    toastStore.error(err.response?.data?.message || 'Gagal menghapus transaksi.');
  } finally {
    deleting.value = false;
  }
}

function refreshList() {
  showModal.value = false;
  applyFilters();
}

onMounted(async () => {
  try {
    await Promise.allSettled([
      financeStore.fetchWallets(),
      financeStore.fetchGoals(),
      financeStore.fetchBudgets(),
    ]);
  } catch (e) {
    // Non-fatal
  }
  applyFilters();
});
</script>
