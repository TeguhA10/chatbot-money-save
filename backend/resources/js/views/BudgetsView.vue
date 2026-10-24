<template>
  <div class="space-y-5 sm:space-y-6">
    <!-- Header Page -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight flex items-center gap-2.5">
          <span>Budget & Anggaran</span>
          <!-- Active sync indicator badge -->
          <span
            v-if="isSyncing"
            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30 shadow-sm animate-pulse"
          >
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-ping"></span>
            <span>Memperbarui anggaran...</span>
          </span>
        </h1>
        <p class="text-xs text-slate-400 mt-0.5">Kendalikan batas pengeluaran bulanan agar tidak bocor halus</p>
      </div>

      <button
        @click="showBudgetModal = true"
        class="self-start sm:self-auto px-4 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 text-slate-950 font-bold text-xs flex items-center justify-center gap-2 shadow-lg shadow-amber-500/20 cursor-pointer transition active:scale-95"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
        <span>Pasang Limit Anggaran</span>
      </button>
    </div>

    <!-- ERROR STATE ALERT WITH RETRY -->
    <div
      v-if="isError"
      class="p-4 sm:p-5 rounded-2xl bg-rose-500/15 border border-rose-500/30 text-rose-300 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-lg animate-in fade-in"
    >
      <div class="flex items-start gap-3">
        <span class="text-2xl shrink-0">⚠️</span>
        <div>
          <h3 class="text-sm font-bold text-rose-200">Gagal Memuat Anggaran</h3>
          <p class="text-xs text-rose-300/90 mt-0.5 leading-relaxed">
            {{ errorMessage || 'Terjadi gangguan koneksi saat mengambil batas plafon anggaran Anda.' }}
          </p>
        </div>
      </div>
      <button
        @click="loadBudgets"
        class="self-start sm:self-auto px-4 py-2 rounded-xl bg-rose-500 hover:bg-rose-400 text-slate-950 font-bold text-xs flex items-center gap-1.5 transition active:scale-95 cursor-pointer shadow-md"
      >
        <span>🔄 Coba Lagi</span>
      </button>
    </div>

    <!-- SKELETON LOADER (Initial Cold Mount) -->
    <div v-if="isInitialLoading" class="space-y-5 animate-in fade-in duration-150">
      <div class="h-32 rounded-3xl bg-slate-900 border border-slate-800/80 animate-pulse p-6"></div>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        <div v-for="i in 3" :key="i" class="h-44 rounded-2xl bg-slate-900 border border-slate-800/80 animate-pulse p-6"></div>
      </div>
    </div>

    <!-- MAIN BUDGETS CONTENT -->
    <template v-else>
      <div :class="isSyncing ? 'opacity-70 transition-opacity pointer-events-none' : 'transition-opacity'" class="space-y-5 sm:space-y-6">
        <!-- SUMMARY PROGRESS BANNER -->
        <div class="p-6 rounded-3xl bg-slate-900 border border-slate-800 space-y-4 shadow-xl">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
              <span class="text-xs font-semibold text-slate-400">Total Plafon Anggaran Bulan Ini</span>
              <p class="text-3xl font-black text-white mt-1 tracking-tight">
                {{ formatRupiah(financeStore.totalBudgeted) }}
              </p>
            </div>
            <div class="sm:text-right">
              <span class="text-xs font-semibold text-slate-400">Total Realisasi Terpakai</span>
              <p class="text-2xl font-black text-amber-400 mt-1 tracking-tight">
                {{ formatRupiah(financeStore.totalSpent) }}
                <span class="text-xs text-slate-400 font-medium">({{ overallPercentage }}%)</span>
              </p>
            </div>
          </div>

          <!-- Overall progress bar -->
          <div class="w-full bg-slate-950 h-3 rounded-full overflow-hidden border border-slate-800">
            <div
              class="h-full rounded-full transition-all duration-500"
              :class="overallPercentage >= 90 ? 'bg-rose-500' : (overallPercentage >= 70 ? 'bg-amber-500' : 'bg-emerald-500')"
              :style="{ width: `${Math.min(overallPercentage, 100)}%` }"
            ></div>
          </div>
        </div>

        <!-- INLINE FILTER BAR FOR BUDGETS (Standardized & UI/UX Friendly) -->
        <div class="p-4 sm:p-5 rounded-2xl bg-slate-900/90 border border-slate-800 space-y-3.5 shadow-sm">
          <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-800/80 pb-3">
            <div class="flex items-center gap-2">
              <span class="text-xs font-bold text-white">Saring Anggaran:</span>
              <span class="text-[11px] px-2.5 py-0.5 rounded-full bg-slate-800 text-slate-300 font-mono">
                Menampilkan {{ filteredBudgets.length }} dari {{ financeStore.budgets.length }} Anggaran
              </span>
            </div>

            <button
              v-if="hasActiveBudgetFilters"
              @click="resetBudgetFilters"
              type="button"
              class="text-xs text-rose-400 hover:text-rose-300 font-bold underline transition cursor-pointer"
            >
              Reset Filter
            </button>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <!-- Search Input -->
            <div class="relative sm:col-span-1">
              <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs">🔍</span>
              <input
                type="text"
                v-model="budgetSearch"
                placeholder="Cari kategori anggaran..."
                class="w-full pl-9 pr-4 py-2.5 rounded-xl bg-slate-900 border border-slate-800 hover:border-slate-700 text-slate-200 text-xs font-semibold focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500/40 shadow-inner transition placeholder:text-slate-500"
              />
            </div>

            <!-- Filter Status -->
            <div>
              <SelectDropdown
                v-model="budgetStatusFilter"
                :options="budgetStatusOptions"
                placeholder="Semua Status Pemakaian"
                icon="🎯"
              />
            </div>

            <!-- Sort By -->
            <div>
              <SelectDropdown
                v-model="budgetSort"
                :options="budgetSortOptions"
                placeholder="Urutan Anggaran"
                icon="↕️"
              />
            </div>
          </div>
        </div>

        <!-- EMPTY STATE: GENUINELY NO BUDGETS -->
        <div
          v-if="financeStore.budgets.length === 0"
          class="py-16 px-6 text-center rounded-2xl bg-slate-900/60 border border-slate-800 space-y-3"
        >
          <div class="w-16 h-16 rounded-2xl bg-amber-500/10 text-amber-400 flex items-center justify-center text-3xl mx-auto shadow-inner">
            🎯
          </div>
          <div class="space-y-1">
            <h3 class="text-sm sm:text-base font-bold text-white">Belum Ada Anggaran yang Dibuat</h3>
            <p class="text-xs text-slate-400 max-w-sm mx-auto leading-relaxed">
              Pasang batas anggaran bulanan untuk mengendalikan dan membatasi total pengeluaran Anda.
            </p>
          </div>
          <div class="pt-2">
            <button
              @click="showBudgetModal = true"
              class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs transition shadow-md shadow-amber-500/20 cursor-pointer active:scale-95"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
              <span>Mulai Pasang Anggaran</span>
            </button>
          </div>
        </div>

        <!-- FILTER EMPTY STATE: 0 MATCHES -->
        <div
          v-else-if="filteredBudgets.length === 0"
          class="py-12 px-6 rounded-2xl bg-slate-900/60 border border-slate-800 text-center space-y-3"
        >
          <div class="text-3xl">🔍</div>
          <h3 class="text-sm font-bold text-white">Tidak Ada Anggaran yang Cocok</h3>
          <p class="text-xs text-slate-400">
            Tidak ditemukan plafon anggaran yang cocok dengan kriteria filter Anda.
          </p>
          <button
            @click="resetBudgetFilters"
            class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold transition"
          >
            Reset Filter
          </button>
        </div>

        <!-- BUDGET CARDS GRID -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
          <div
            v-for="b in filteredBudgets"
            :key="b.id"
            class="p-6 rounded-2xl bg-slate-900/80 border transition group shadow-sm"
            :class="b.percentage >= 90 ? 'border-rose-500/40 shadow-lg shadow-rose-500/5' : (b.percentage >= 70 ? 'border-amber-500/40' : 'border-slate-800')"
          >
            <!-- Header -->
            <div class="flex items-center justify-between mb-3">
              <div class="flex items-center gap-2.5">
                <span class="text-2xl">🎯</span>
                <div>
                  <h3 class="text-sm font-bold text-white">Batas Pengeluaran Bulanan</h3>
                  <p class="text-[11px] text-slate-400">Plafon Keseluruhan</p>
                </div>
              </div>
              <span
                class="px-2.5 py-0.5 rounded-full text-[11px] font-bold"
                :class="b.percentage >= 100 ? 'bg-rose-500/20 text-rose-400 border border-rose-500/40' : (b.percentage >= 80 ? 'bg-amber-500/20 text-amber-400 border border-amber-500/30' : 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30')"
              >
                {{ b.percentage }}%
              </span>
            </div>

            <!-- Progress Bar -->
            <div class="my-4 space-y-1.5">
              <div class="w-full bg-slate-950 h-2.5 rounded-full overflow-hidden border border-slate-800">
                <div
                  class="h-full rounded-full transition-all duration-500"
                  :class="b.percentage >= 90 ? 'bg-rose-500' : (b.percentage >= 70 ? 'bg-amber-500' : 'bg-emerald-500')"
                  :style="{ width: `${Math.min(b.percentage, 100)}%` }"
                ></div>
              </div>
              <p v-if="b.percentage >= 100" class="text-[11px] text-rose-400 font-semibold flex items-center gap-1">
                ⚠️ Overbudget! Pengeluaran melebihi plafon.
              </p>
              <p v-else-if="b.percentage >= 80" class="text-[11px] text-amber-400 font-semibold flex items-center gap-1">
                ⚠️ Peringatan: Anggaran sudah terpakai di atas 80%.
              </p>
            </div>

            <!-- Metric Details -->
            <div class="pt-3 border-t border-slate-800/80 flex items-center justify-between text-xs">
              <div>
                <span class="text-slate-400 block text-[10px]">Terpakai</span>
                <span class="font-bold text-slate-200">{{ formatRupiah(b.spent_amount) }}</span>
              </div>
              <div class="text-right">
                <span class="text-slate-400 block text-[10px]">Sisa Anggaran</span>
                <span class="font-black" :class="b.remaining_amount < 0 ? 'text-rose-400' : 'text-emerald-400'">
                  {{ formatRupiah(b.remaining_amount) }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Budget Modal -->
    <BudgetModal
      :isOpen="showBudgetModal"
      @close="showBudgetModal = false"
      @saved="loadBudgets"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useFinanceStore } from '../stores/finance';
import { useVaultStore } from '../stores/vault';
import { formatRupiah as rawFormatRupiah } from '../utils/formatters';
import BudgetModal from '../components/modals/BudgetModal.vue';
import SelectDropdown from '../components/common/SelectDropdown.vue';

const financeStore = useFinanceStore();
const vaultStore = useVaultStore();

const budgetStatusOptions = [
  { value: 'ALL', label: 'Semua Status Pemakaian', icon: '🎯' },
  { value: 'SAFE', label: 'Aman ( < 70% )', icon: '🟢' },
  { value: 'WARNING', label: 'Mendekati Batas ( 70% - 99% )', icon: '🟡' },
  { value: 'OVER', label: 'Melebihi Plafon ( ≥ 100% )', icon: '🔴' },
];

const budgetSortOptions = [
  { value: 'PERCENT_DESC', label: 'Persentase Terpakai Tertinggi', icon: '📈' },
  { value: 'PERCENT_ASC', label: 'Persentase Terpakai Terendah', icon: '📉' },
  { value: 'LIMIT_DESC', label: 'Plafon Nominal Terbesar', icon: '💰' },
];

const isInitialLoading = ref(!financeStore.hasLoadedBudgets);
const isSyncing = ref(false);
const isError = ref(false);
const errorMessage = ref('');
const showBudgetModal = ref(false);

// Budget Filter States
const budgetSearch = ref('');
const budgetStatusFilter = ref('ALL');
const budgetSort = ref('PERCENT_DESC');

function formatRupiah(val) {
  if (vaultStore.isMasked) return 'Rp ••••••••';
  return rawFormatRupiah(val);
}

const overallPercentage = computed(() => {
  if (!financeStore.totalBudgeted || financeStore.totalBudgeted <= 0) return 0;
  return Math.round((financeStore.totalSpent / financeStore.totalBudgeted) * 100);
});

const hasActiveBudgetFilters = computed(() => {
  return !!(budgetSearch.value.trim() || budgetStatusFilter.value !== 'ALL');
});

function resetBudgetFilters() {
  budgetSearch.value = '';
  budgetStatusFilter.value = 'ALL';
  budgetSort.value = 'PERCENT_DESC';
}

const filteredBudgets = computed(() => {
  let list = [...(financeStore.budgets || [])];

  if (budgetSearch.value.trim()) {
    const q = budgetSearch.value.trim().toLowerCase();
    list = list.filter(b => (b.category_name || '').toLowerCase().includes(q));
  }

  if (budgetStatusFilter.value === 'SAFE') {
    list = list.filter(b => (b.percentage || 0) < 70);
  } else if (budgetStatusFilter.value === 'WARNING') {
    list = list.filter(b => (b.percentage || 0) >= 70 && (b.percentage || 0) < 100);
  } else if (budgetStatusFilter.value === 'OVER') {
    list = list.filter(b => (b.percentage || 0) >= 100);
  }

  list.sort((a, b) => {
    if (budgetSort.value === 'PERCENT_DESC') {
      return (Number(b.percentage) || 0) - (Number(a.percentage) || 0);
    }
    if (budgetSort.value === 'PERCENT_ASC') {
      return (Number(a.percentage) || 0) - (Number(b.percentage) || 0);
    }
    if (budgetSort.value === 'LIMIT_DESC') {
      return (Number(b.limit_amount) || 0) - (Number(a.limit_amount) || 0);
    }
    return 0;
  });

  return list;
});

async function loadBudgets(force = false) {
  if (!financeStore.hasLoadedBudgets) {
    isInitialLoading.value = true;
  } else {
    isInitialLoading.value = false;
    isSyncing.value = true;
  }

  isError.value = false;
  errorMessage.value = '';

  try {
    await financeStore.fetchBudgets(force);
  } catch (err) {
    if (!financeStore.hasLoadedBudgets) {
      isError.value = true;
      errorMessage.value = err.response?.data?.message || err.message || 'Gagal memuat batas anggaran.';
    }
  } finally {
    isInitialLoading.value = false;
    isSyncing.value = false;
  }
}

onMounted(() => {
  loadBudgets();
});
</script>
