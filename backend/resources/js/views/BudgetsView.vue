<template>
  <div class="space-y-6">
    <!-- Header Page -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-black text-white tracking-tight">Budget & Anggaran</h1>
        <p class="text-xs text-slate-400 mt-0.5">Kendalikan batas pengeluaran bulanan agar tidak bocor halus</p>
      </div>

      <button
        @click="showBudgetModal = true"
        class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 text-slate-950 font-bold text-xs flex items-center gap-1.5 shadow-lg shadow-amber-500/20 cursor-pointer transition active:scale-95"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        + Pasang Limit Anggaran
      </button>
    </div>

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

    <!-- BUDGET CARDS GRID -->
    <div v-if="financeStore.budgets.length === 0" class="py-20 text-center p-8 rounded-2xl bg-slate-900/60 border border-slate-800">
      <div class="w-16 h-16 rounded-2xl bg-amber-500/10 text-amber-400 flex items-center justify-center text-3xl mx-auto mb-3">
        🎯
      </div>
      <h3 class="text-base font-bold text-white mb-1">Belum Ada Anggaran yang Dibuat</h3>
      <p class="text-xs text-slate-400 max-w-sm mx-auto mb-5">
        Pasang batas anggaran untuk kategori favorit Anda seperti Makanan, Transport, atau Hiburan.
      </p>
      <button
        @click="showBudgetModal = true"
        class="px-4 py-2 rounded-xl bg-amber-500 text-slate-950 font-bold text-xs hover:bg-amber-400 transition"
      >
        Mulai Pasang Anggaran
      </button>
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      <div
        v-for="b in financeStore.budgets"
        :key="b.id"
        class="p-6 rounded-2xl bg-slate-900/80 border transition group"
        :class="b.percentage >= 90 ? 'border-rose-500/40 shadow-lg shadow-rose-500/5' : (b.percentage >= 70 ? 'border-amber-500/40' : 'border-slate-800')"
      >
        <!-- Header -->
        <div class="flex items-center justify-between mb-3">
          <div class="flex items-center gap-2.5">
            <span class="text-2xl">{{ b.category_icon }}</span>
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
            ⚠️ Overbudget! Pengeluaran melebihi plafon anggaran.
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
            <span class="font-bold" :class="b.remaining_amount < 0 ? 'text-rose-400' : 'text-emerald-400'">
              {{ formatRupiah(b.remaining_amount) }}
            </span>
          </div>
        </div>
      </div>
    </div>

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
import { formatRupiah } from '../utils/formatters';
import BudgetModal from '../components/modals/BudgetModal.vue';

const financeStore = useFinanceStore();
const showBudgetModal = ref(false);

const overallPercentage = computed(() => {
  if (!financeStore.totalBudgeted || financeStore.totalBudgeted <= 0) return 0;
  return Math.round((financeStore.totalSpent / financeStore.totalBudgeted) * 100);
});

async function loadBudgets() {
  await financeStore.fetchBudgets();
  await financeStore.fetchCategories();
}

onMounted(() => {
  loadBudgets();
});
</script>
