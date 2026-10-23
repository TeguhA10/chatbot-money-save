<template>
  <div class="space-y-5 sm:space-y-6">
    <!-- Header Page & Direct Toolbar (No Modal Popup) -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight flex items-center gap-2.5">
          <span>Laporan & Analisis</span>
          <!-- Active sync indicator badge -->
          <span
            v-if="isSyncing"
            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30 shadow-sm animate-pulse"
          >
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-ping"></span>
            <span>Memperbarui analisis...</span>
          </span>
        </h1>
        <p class="text-xs text-slate-400 mt-0.5">Evaluasi tren, komparasi historis, dan ekspor data keuangan lengkap</p>
      </div>

      <!-- DIRECT INLINE FILTER & 1-CLICK EXPORTS (User-friendly & Standardized) -->
      <div class="flex flex-wrap sm:flex-nowrap items-center gap-2 sm:gap-2.5 w-full lg:w-auto">
        <!-- Month Picker (Custom Sleek Dark Component) -->
        <div class="relative flex-1 sm:flex-initial">
          <MonthPicker
            v-model="selectedMonth"
            @change="loadAnalytics"
            :disabled="isInitialLoading || isSyncing || isExporting"
          />
        </div>

        <!-- 1-Click Excel Direct Export -->
        <button
          @click="triggerExport('excel')"
          :disabled="isInitialLoading || isExporting"
          class="flex-1 sm:flex-initial px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-800 hover:border-emerald-500/50 hover:bg-emerald-500/10 text-emerald-400 font-bold text-xs flex items-center justify-center gap-2 shadow-sm cursor-pointer transition active:scale-95 disabled:opacity-50 whitespace-nowrap"
          title="Unduh Spreadsheet Excel Multi-Sheet Lengkap"
        >
          <svg v-if="exportingFormat === 'excel'" class="w-3.5 h-3.5 animate-spin text-emerald-400" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <span v-else>📊</span>
          <span>{{ exportingFormat === 'excel' ? 'Mengunduh...' : 'Unduh Excel' }}</span>
        </button>

        <!-- 1-Click PDF Official Statement Direct Export -->
        <button
          @click="triggerExport('pdf')"
          :disabled="isInitialLoading || isExporting"
          class="flex-1 sm:flex-initial px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-800 hover:border-rose-500/50 hover:bg-rose-500/10 text-rose-300 font-bold text-xs flex items-center justify-center gap-2 shadow-sm cursor-pointer transition active:scale-95 disabled:opacity-50 whitespace-nowrap"
          title="Unduh Dokumen Rekening Koran Resmi Standar Bank"
        >
          <svg v-if="exportingFormat === 'pdf'" class="w-3.5 h-3.5 animate-spin text-rose-400" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <span v-else>📑</span>
          <span>{{ exportingFormat === 'pdf' ? 'Mencetak...' : 'PDF Resmi' }}</span>
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
          <h3 class="text-sm font-bold text-rose-200">Gagal Memuat Analitik</h3>
          <p class="text-xs text-rose-300/90 mt-0.5 leading-relaxed">
            {{ errorMessage || 'Terjadi gangguan koneksi saat mengkalkulasi tren keuangan Anda.' }}
          </p>
        </div>
      </div>
      <button
        @click="loadAnalytics"
        class="self-start sm:self-auto px-4 py-2 rounded-xl bg-rose-500 hover:bg-rose-400 text-slate-950 font-bold text-xs flex items-center gap-1.5 transition active:scale-95 cursor-pointer shadow-md"
      >
        <span>🔄 Coba Lagi</span>
      </button>
    </div>

    <!-- SKELETON LOADER (Initial Cold Mount) -->
    <div v-if="isInitialLoading" class="space-y-5 animate-in fade-in duration-150">
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <div v-for="i in 3" :key="i" class="h-32 rounded-2xl bg-slate-900 border border-slate-800/80 animate-pulse p-6"></div>
      </div>
      <div class="h-64 rounded-2xl bg-slate-900 border border-slate-800/80 animate-pulse p-6"></div>
    </div>

    <!-- MAIN ANALYTICS CONTENT -->
    <template v-else>
      <div :class="isSyncing ? 'opacity-70 transition-opacity pointer-events-none' : 'transition-opacity'" class="space-y-5 sm:space-y-6">
        <!-- MONTH-OVER-MONTH VARIANCE HIGHLIGHT CARDS -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
          <!-- Income Card -->
          <div class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-sm">
            <span class="text-xs font-semibold text-slate-400">Total Pemasukan</span>
            <p class="text-2xl font-black text-emerald-400 mt-1">
              {{ formatRupiah(financeStore.summary?.total_income || 0) }}
            </p>
            <div class="flex items-center gap-1.5 text-xs font-semibold mt-3" :class="(financeStore.summary?.delta_vs_last_month?.income_percentage || 0) >= 0 ? 'text-emerald-400' : 'text-rose-400'">
              <span>{{ (financeStore.summary?.delta_vs_last_month?.income_percentage || 0) >= 0 ? '▲' : '▼' }}</span>
              <span>{{ Math.abs(financeStore.summary?.delta_vs_last_month?.income_percentage || 0) }}% vs bulan lalu</span>
            </div>
          </div>

          <!-- Expense Card -->
          <div class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-sm">
            <span class="text-xs font-semibold text-slate-400">Total Pengeluaran</span>
            <p class="text-2xl font-black text-rose-400 mt-1">
              {{ formatRupiah(financeStore.summary?.total_expense || 0) }}
            </p>
            <div class="flex items-center gap-1.5 text-xs font-semibold mt-3" :class="(financeStore.summary?.delta_vs_last_month?.expense_percentage || 0) <= 0 ? 'text-emerald-400' : 'text-rose-400'">
              <span>{{ (financeStore.summary?.delta_vs_last_month?.expense_percentage || 0) > 0 ? '▲' : '▼' }}</span>
              <span>{{ Math.abs(financeStore.summary?.delta_vs_last_month?.expense_percentage || 0) }}% vs bulan lalu</span>
            </div>
          </div>

          <!-- Net Surplus/Deficit -->
          <div class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-sm">
            <span class="text-xs font-semibold text-slate-400">Status Arus Kas</span>
            <p class="text-2xl font-black mt-1" :class="(financeStore.summary?.net_savings || 0) >= 0 ? 'text-teal-400' : 'text-amber-400'">
              {{ formatRupiah(financeStore.summary?.net_savings || 0) }}
            </p>
            <div class="text-xs font-bold mt-3" :class="(financeStore.summary?.net_savings || 0) >= 0 ? 'text-teal-400' : 'text-amber-400'">
              <span>{{ (financeStore.summary?.net_savings || 0) >= 0 ? '✅ Surplus Bulanan Terjaga' : '⚠️ Defisit (Pengeluaran Lebih Besar)' }}</span>
            </div>
          </div>
        </div>

        <!-- TABLE 1: 6 MONTH TREND MATRIX TABLE WITH INLINE FILTER -->
        <div class="p-5 sm:p-6 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-sm space-y-4">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-800/80 pb-3.5">
            <div>
              <h2 class="text-sm sm:text-base font-bold text-white">Riwayat Keuangan 6 Bulan Terakhir</h2>
              <p class="text-xs text-slate-400">Komparasi tren pemasukan, pengeluaran, dan tabungan bersih</p>
            </div>

            <!-- Inline Table Filter for Status (Surplus/Defisit) -->
            <div class="flex items-center gap-2">
              <span class="text-[11px] text-slate-400 font-semibold shrink-0">Filter Status:</span>
              <div class="w-44">
                <SelectDropdown
                  v-model="trendStatusFilter"
                  :options="trendStatusOptions"
                  placeholder="Semua Status"
                  icon="🎯"
                />
              </div>
            </div>
          </div>

          <div class="overflow-x-auto -mx-1">
            <table class="w-full min-w-[680px] text-left text-xs border-collapse">
              <thead>
                <tr class="text-slate-400 uppercase text-[10px] font-bold tracking-wider bg-slate-950/60 border-b border-slate-800">
                  <th class="py-3.5 px-5 font-bold">Periode Bulan</th>
                  <th class="py-3.5 px-5 font-bold text-right">Pemasukan</th>
                  <th class="py-3.5 px-5 font-bold text-right">Pengeluaran</th>
                  <th class="py-3.5 px-5 font-bold text-right">Arus Kas Bersih</th>
                  <th class="py-3.5 px-5 font-bold text-center">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-800/60">
                <tr v-if="filteredMonthlyTrend.length === 0">
                  <td colspan="5" class="py-8 text-center text-slate-500 text-xs">
                    Tidak ada data bulan yang sesuai dengan filter "{{ trendStatusFilter === 'SURPLUS' ? 'Surplus' : 'Defisit' }}".
                  </td>
                </tr>
                <tr
                  v-for="item in filteredMonthlyTrend"
                  :key="item.month"
                  class="hover:bg-slate-800/40 transition"
                >
                  <td class="py-4 px-5 font-bold text-slate-200">{{ formatMonthName(item.month) }}</td>
                  <td class="py-4 px-5 text-right font-black text-emerald-400">{{ formatRupiah(item.income) }}</td>
                  <td class="py-4 px-5 text-right font-black text-rose-400">{{ formatRupiah(item.expense) }}</td>
                  <td class="py-4 px-5 text-right font-black" :class="item.income - item.expense >= 0 ? 'text-teal-400' : 'text-amber-400'">
                    {{ formatRupiah(item.income - item.expense) }}
                  </td>
                  <td class="py-4 px-5 text-center">
                    <span
                      class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider"
                      :class="item.income - item.expense >= 0 ? 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/25' : 'bg-rose-500/15 text-rose-300 border border-rose-500/25'"
                    >
                      {{ item.income - item.expense >= 0 ? 'Surplus' : 'Defisit' }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- TABLE 2: CATEGORY BREAKDOWN WITH INLINE SEARCH & SORT FILTER -->
        <div class="p-5 sm:p-6 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-sm space-y-4">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-800/80 pb-3.5">
            <div>
              <h2 class="text-sm sm:text-base font-bold text-white">Rincian Pengeluaran per Kategori</h2>
              <p class="text-xs text-slate-400">Analisis pos pengeluaran yang paling dominan di bulan ini</p>
            </div>
            <span class="text-[10px] px-2.5 py-0.5 rounded-full bg-slate-800 text-slate-300 font-mono whitespace-nowrap self-start sm:self-auto">
              {{ filteredCategories.length }} dari {{ financeStore.categoryBreakdown.length }} Kategori
            </span>
          </div>

          <!-- Inline Category Filter Bar -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <!-- Search Input (Uniform styling) -->
            <div class="relative sm:col-span-2">
              <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs">🔍</span>
              <input
                type="text"
                v-model="categorySearch"
                placeholder="Cari nama kategori pengeluaran (misal: makanan, tagihan, hiburan)..."
                class="w-full pl-9 pr-4 py-2.5 rounded-xl bg-slate-900 border border-slate-800 hover:border-slate-700 text-slate-200 text-xs font-semibold focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/40 shadow-inner transition placeholder:text-slate-500"
              />
            </div>

            <!-- Sort dropdown -->
            <div class="w-full sm:w-52">
              <SelectDropdown
                v-model="categorySort"
                :options="categorySortOptions"
                placeholder="Urutan Kategori"
                icon="↕️"
              />
            </div>
          </div>

          <!-- Empty State (No records at all) -->
          <div v-if="financeStore.categoryBreakdown.length === 0" class="py-12 text-center text-slate-500 text-xs">
            Belum ada transaksi pengeluaran pada bulan yang dipilih.
          </div>

          <!-- Filter Result Empty -->
          <div v-else-if="filteredCategories.length === 0" class="py-10 text-center rounded-xl bg-slate-950/40 border border-slate-800/60 text-slate-400 text-xs space-y-2">
            <p>Tidak ada kategori yang cocok dengan kata kunci "{{ categorySearch }}".</p>
            <button
              @click="categorySearch = ''"
              class="text-xs text-emerald-400 hover:underline font-bold"
            >
              Reset Pencarian
            </button>
          </div>

          <!-- Category List Cards -->
          <div v-else class="space-y-3">
            <div
              v-for="cat in filteredCategories"
              :key="cat.category_name"
              class="p-4 rounded-xl bg-slate-950/60 border border-slate-800/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-slate-900/50 transition"
            >
              <div class="flex items-center gap-3 min-w-0">
                <span class="w-10 h-10 rounded-xl bg-slate-800/80 border border-slate-700/60 flex items-center justify-center text-xl shrink-0">
                  {{ cat.icon || '📌' }}
                </span>
                <div class="min-w-0">
                  <h3 class="text-xs font-bold text-white truncate">{{ cat.category_name }}</h3>
                  <p class="text-[11px] text-slate-400 mt-0.5">{{ cat.percentage }}% dari total belanja bulan ini</p>
                </div>
              </div>
              <div class="sm:text-right shrink-0">
                <p class="text-sm font-black text-rose-400">{{ formatRupiah(cat.total_amount) }}</p>
              </div>
            </div>
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
import { useToastStore } from '../stores/toast';
import { formatRupiah } from '../utils/formatters';
import { downloadExportReport } from '../utils/exportDownloader';
import MonthPicker from '../components/common/MonthPicker.vue';
import SelectDropdown from '../components/common/SelectDropdown.vue';

const financeStore = useFinanceStore();
const vaultStore = useVaultStore();
const toastStore = useToastStore();

const trendStatusOptions = [
  { value: 'ALL', label: 'Semua Status', icon: '🎯' },
  { value: 'SURPLUS', label: 'Hanya Surplus', icon: '🟢' },
  { value: 'DEFICIT', label: 'Hanya Defisit', icon: '🔴' },
];

const categorySortOptions = [
  { value: 'PERCENT_DESC', label: 'Persentase Tertinggi', icon: '📈' },
  { value: 'AMOUNT_DESC', label: 'Nominal Terbesar', icon: '💰' },
  { value: 'AMOUNT_ASC', label: 'Nominal Terkecil', icon: '📉' },
  { value: 'NAME_ASC', label: 'Nama Kategori (A-Z)', icon: '🔤' },
];

const selectedMonth = ref(new Date().toISOString().slice(0, 7));

const isInitialLoading = ref(!financeStore.summary || financeStore.monthlyTrend.length === 0);
const isSyncing = ref(false);
const isError = ref(false);
const errorMessage = ref('');

// Export states (Direct 1-click downloads)
const isExporting = ref(false);
const exportingFormat = ref(''); // 'excel' | 'pdf' | ''

// Inline table filters
const trendStatusFilter = ref('ALL');
const categorySearch = ref('');
const categorySort = ref('PERCENT_DESC');

function formatMonthName(monthStr) {
  if (!monthStr) return '-';
  const [y, m] = monthStr.split('-');
  const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
  return `${months[parseInt(m, 10) - 1]} ${y}`;
}

// Filtered Monthly Trend
const filteredMonthlyTrend = computed(() => {
  const trend = financeStore.monthlyTrend || [];
  if (trendStatusFilter.value === 'SURPLUS') {
    return trend.filter(item => (item.income - item.expense) >= 0);
  }
  if (trendStatusFilter.value === 'DEFICIT') {
    return trend.filter(item => (item.income - item.expense) < 0);
  }
  return trend;
});

// Filtered & Sorted Categories
const filteredCategories = computed(() => {
  let list = [...(financeStore.categoryBreakdown || [])];

  if (categorySearch.value.trim()) {
    const q = categorySearch.value.trim().toLowerCase();
    list = list.filter(c => (c.category_name || '').toLowerCase().includes(q));
  }

  list.sort((a, b) => {
    if (categorySort.value === 'PERCENT_DESC') {
      return (b.percentage || 0) - (a.percentage || 0);
    }
    if (categorySort.value === 'AMOUNT_DESC') {
      return (b.total_amount || 0) - (a.total_amount || 0);
    }
    if (categorySort.value === 'AMOUNT_ASC') {
      return (a.total_amount || 0) - (b.total_amount || 0);
    }
    if (categorySort.value === 'NAME_ASC') {
      return (a.category_name || '').localeCompare(b.category_name || '');
    }
    return 0;
  });

  return list;
});

async function loadAnalytics(force = false) {
  if (!financeStore.summary || financeStore.monthlyTrend.length === 0) {
    isInitialLoading.value = true;
  } else {
    isInitialLoading.value = false;
    isSyncing.value = true;
  }

  isError.value = false;
  errorMessage.value = '';

  try {
    await Promise.all([
      financeStore.fetchSummary(selectedMonth.value, force),
      financeStore.fetchCategoryBreakdown(selectedMonth.value, force),
      financeStore.fetchMonthlyTrend(force),
    ]);
  } catch (err) {
    if (!financeStore.summary) {
      isError.value = true;
      errorMessage.value = err.response?.data?.message || err.message || 'Gagal memuat analitik keuangan.';
    }
  } finally {
    isInitialLoading.value = false;
    isSyncing.value = false;
  }
}

/**
 * Direct 1-Click Export Execution without Popups
 */
async function triggerExport(format) {
  if (isExporting.value) return;
  isExporting.value = true;
  exportingFormat.value = format;

  try {
    await downloadExportReport(format, { month: selectedMonth.value }, toastStore);
  } catch (err) {
    toastStore.error(err.response?.data?.message || 'Gagal mengunduh dokumen laporan. Silakan coba kembali.');
  } finally {
    isExporting.value = false;
    exportingFormat.value = '';
  }
}

onMounted(() => {
  loadAnalytics();
});
</script>
