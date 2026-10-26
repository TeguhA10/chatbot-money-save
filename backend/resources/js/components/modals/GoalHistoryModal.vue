<template>
  <div
    v-if="isOpen && goal"
    class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-950/80 backdrop-blur-sm animate-fade-in overflow-y-auto"
  >
    <div class="bg-slate-900 border border-slate-800 rounded-3xl w-full max-w-lg p-5 sm:p-6 shadow-2xl relative my-auto max-h-[92vh] flex flex-col">
      <!-- Close Button -->
      <button
        @click="$emit('close')"
        class="absolute top-5 right-5 text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-800 transition z-10 cursor-pointer"
        aria-label="Tutup"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>

      <!-- Modal Title -->
      <div class="flex items-center gap-3 mb-3 shrink-0 pr-8">
        <div class="w-10 h-10 rounded-xl bg-teal-500/20 text-teal-300 border border-teal-500/30 flex items-center justify-center text-xl shrink-0">
          📜
        </div>
        <div class="min-w-0">
          <h2 class="text-base sm:text-lg font-bold text-white truncate">Riwayat Setoran Tabungan</h2>
          <p class="text-xs text-slate-400 truncate">{{ goal.name }}</p>
        </div>
      </div>

      <!-- Goal Progress Summary Card -->
      <div class="p-3.5 rounded-2xl bg-slate-950 border border-slate-800/80 mb-4 shrink-0 space-y-2">
        <div class="flex items-center justify-between text-xs">
          <span class="text-slate-400">Progres Tabungan:</span>
          <span class="font-bold" :class="goal.status === 'ACHIEVED' ? 'text-emerald-400' : 'text-teal-300'">
            {{ goal.status === 'ACHIEVED' ? 'Target Tercapai! 🎉' : `${goal.progress_percent}%` }}
          </span>
        </div>
        <div class="w-full bg-slate-800/80 h-2.5 rounded-full overflow-hidden border border-slate-700/60">
          <div
            class="h-full rounded-full bg-gradient-to-r from-teal-500 to-emerald-400 transition-all duration-500"
            :style="{ width: `${Math.min(goal.progress_percent || 0, 100)}%` }"
          ></div>
        </div>
        <div class="flex items-center justify-between text-[11px] text-slate-400 pt-0.5">
          <span>Terkumpul: <b class="text-emerald-400">{{ formatRupiah(goal.current_amount) }}</b></span>
          <span>Target: <b class="text-slate-200">{{ formatRupiah(goal.target_amount) }}</b></span>
        </div>
      </div>

      <!-- Transaction List Section -->
      <div class="overflow-y-auto flex-1 pr-1 space-y-2.5 min-h-[160px]">
        <!-- Loading State -->
        <div v-if="loading" class="py-10 text-center space-y-2">
          <div class="w-6 h-6 border-2 border-teal-400 border-t-transparent rounded-full animate-spin mx-auto"></div>
          <p class="text-xs text-slate-400">Mengambil catatan setoran...</p>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-xs text-center space-y-2">
          <p>{{ error }}</p>
          <button
            @click="loadTransactions"
            class="px-3 py-1 rounded-lg bg-rose-500 hover:bg-rose-400 text-slate-950 font-bold transition cursor-pointer"
          >
            Coba Lagi
          </button>
        </div>

        <!-- Empty State -->
        <div
          v-else-if="transactions.length === 0"
          class="py-10 text-center space-y-2.5 rounded-2xl bg-slate-950/40 border border-dashed border-slate-800 p-4"
        >
          <div class="text-3xl">🌱</div>
          <h4 class="text-xs sm:text-sm font-bold text-slate-300">Belum Ada Setoran</h4>
          <p class="text-[11px] text-slate-400 max-w-xs mx-auto leading-relaxed">
            Target ini belum memiliki catatan setoran dana. Tabung sedikit demi sedikit untuk mewujudkan impianmu!
          </p>
        </div>

        <!-- Transaction Items -->
        <div
          v-else
          v-for="tx in transactions"
          :key="tx.id"
          class="p-3 sm:p-3.5 rounded-xl bg-slate-950/70 border border-slate-800/80 hover:border-teal-500/30 transition flex items-center justify-between gap-3"
        >
          <div class="min-w-0 flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-xl bg-teal-500/15 border border-teal-500/30 text-teal-300 flex items-center justify-center text-sm shrink-0">
              🎯
            </div>
            <div class="min-w-0">
              <p class="text-xs font-bold text-slate-200 truncate">{{ tx.description || 'Setoran Tabungan' }}</p>
              <div class="flex items-center gap-2 text-[10px] text-slate-400 mt-0.5">
                <span>{{ formatDate(tx.transaction_date) }}</span>
                <span>•</span>
                <span class="inline-flex items-center gap-1 text-slate-300">
                  <span>👛</span>
                  <span>{{ tx.wallet_name || 'Cash' }}</span>
                </span>
              </div>
            </div>
          </div>

          <div class="text-right shrink-0">
            <span class="text-xs sm:text-sm font-black text-emerald-400">
              +{{ formatRupiah(tx.amount) }}
            </span>
          </div>
        </div>
      </div>

      <!-- Footer Info -->
      <div class="mt-4 pt-3 border-t border-slate-800/80 flex items-center justify-between text-[11px] text-slate-400 shrink-0">
        <span class="flex items-center gap-1.5 text-teal-300/90">
          <span>💡</span>
          <span>Setoran tabungan tidak memotong anggaran belanja</span>
        </span>
        <button
          @click="$emit('close')"
          class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold transition cursor-pointer"
        >
          Tutup
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { useFinanceStore } from '../../stores/finance';
import { formatRupiah, formatDate } from '../../utils/formatters';

const props = defineProps({
  isOpen: Boolean,
  goal: Object,
});

defineEmits(['close']);

const financeStore = useFinanceStore();
const transactions = ref([]);
const loading = ref(false);
const error = ref(null);

async function loadTransactions() {
  if (!props.goal?.id) return;
  loading.value = true;
  error.value = null;
  try {
    const data = await financeStore.fetchGoalTransactions(props.goal.id);
    transactions.value = data.transactions || [];
  } catch (err) {
    error.value = 'Gagal memuat daftar riwayat setoran.';
  } finally {
    loading.value = false;
  }
}

watch(
  () => [props.isOpen, props.goal?.id],
  ([isOpen, goalId]) => {
    if (isOpen && goalId) {
      loadTransactions();
    } else {
      transactions.value = [];
    }
  },
  { immediate: true }
);
</script>
