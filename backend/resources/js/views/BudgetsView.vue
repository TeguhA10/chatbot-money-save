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

        <!-- EMPTY STATE (Only rendered after loading completes and truly empty) -->
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
              Pasang batas anggaran untuk kategori favorit Anda seperti Makanan, Transport, atau Hiburan untuk mencegah pengeluaran berlebih.
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

        <!-- BUDGET CARDS GRID -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
          <div
            v-for="b in financeStore.budgets"
            :key="b.id"
            class="p-6 rounded-2xl bg-slate-900/80 border transition group shadow-sm"
            :class="b.percentage >= 90 ? 'border-rose-500/40 shadow-lg shadow-rose-500/5' : (b.percentage >= 70 ? 'border-amber-500/40' : 'border-slate-800')"
          >
            <!-- Header -->
            <div class="flex items-center justify-between mb-3">
              <div class="flex items-center gap-2.5">
                <span class="text-2xl">{{ b.category_icon || '🏷️' }}</span>
                <div>
                  <h3 class="text-sm font-bold text-white">{{ b.category_name }}</h3>
                  <p class="text-[11px] text-slate-400">Plafon Bulanan</p>
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

const financeStore = useFinanceStore();
const vaultStore = useVaultStore();

const isInitialLoading = ref(!financeStore.hasLoadedBudgets);
const isSyncing = ref(false);
const isError = ref(false);
const errorMessage = ref('');
const showBudgetModal = ref(false);

function formatRupiah(val) {
  if (vaultStore.isMasked) return 'Rp ••••••••';
  return rawFormatRupiah(val);
}

const overallPercentage = computed(() => {
  if (!financeStore.totalBudgeted || financeStore.totalBudgeted <= 0) return 0;
  return Math.round((financeStore.totalSpent / financeStore.totalBudgeted) * 100);
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
    await Promise.all([
      financeStore.fetchBudgets(force),
      financeStore.fetchCategories(force),
    ]);
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
