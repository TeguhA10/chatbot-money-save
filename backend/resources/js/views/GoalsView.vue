<template>
  <div class="space-y-5 sm:space-y-6">
    <!-- Header Page -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight flex items-center gap-2.5">
          <span>Target Tabungan</span>
          <!-- Active sync indicator badge -->
          <span
            v-if="isSyncing"
            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30 shadow-sm animate-pulse"
          >
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-ping"></span>
            <span>Memperbarui target...</span>
          </span>
        </h1>
        <p class="text-xs text-slate-400 mt-0.5">Wujudkan impian finansial Anda dengan target tabungan terukur</p>
      </div>

      <button
        @click="showGoalModal = true"
        class="self-start sm:self-auto px-4 py-2.5 rounded-xl bg-gradient-to-r from-teal-500 to-emerald-500 hover:from-teal-400 text-slate-950 font-bold text-xs flex items-center justify-center gap-2 shadow-lg shadow-teal-500/20 cursor-pointer transition active:scale-95"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
        <span>Target Baru</span>
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
          <h3 class="text-sm font-bold text-rose-200">Gagal Memuat Target</h3>
          <p class="text-xs text-rose-300/90 mt-0.5 leading-relaxed">
            {{ errorMessage || 'Terjadi gangguan koneksi saat mengambil daftar target tabungan Anda.' }}
          </p>
        </div>
      </div>
      <button
        @click="loadGoals"
        class="self-start sm:self-auto px-4 py-2 rounded-xl bg-rose-500 hover:bg-rose-400 text-slate-950 font-bold text-xs flex items-center gap-1.5 transition active:scale-95 cursor-pointer shadow-md"
      >
        <span>🔄 Coba Lagi</span>
      </button>
    </div>

    <!-- SKELETON LOADER (Initial Cold Mount) -->
    <div v-if="isInitialLoading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 animate-in fade-in duration-150">
      <div v-for="i in 3" :key="i" class="h-64 rounded-2xl bg-slate-900 border border-slate-800/80 animate-pulse p-6"></div>
    </div>

    <!-- MAIN GOALS CONTENT -->
    <template v-else>
      <div :class="isSyncing ? 'opacity-70 transition-opacity pointer-events-none' : 'transition-opacity'" class="space-y-5 sm:space-y-6">
        <!-- EMPTY STATE (Only rendered after loading completes and truly empty) -->
        <div
          v-if="financeStore.goals.length === 0"
          class="py-16 px-6 text-center rounded-2xl bg-slate-900/60 border border-slate-800 space-y-3"
        >
          <div class="w-16 h-16 rounded-2xl bg-teal-500/10 text-teal-400 flex items-center justify-center text-3xl mx-auto shadow-inner">
            🏆
          </div>
          <div class="space-y-1">
            <h3 class="text-sm sm:text-base font-bold text-white">Belum Ada Target Tabungan</h3>
            <p class="text-xs text-slate-400 max-w-sm mx-auto leading-relaxed">
              Mulai buat target impian Anda, misalnya Dana Darurat, Tabungan Liburan, atau Beli Gadget Impian.
            </p>
          </div>
          <div class="pt-2">
            <button
              @click="showGoalModal = true"
              class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-teal-500 hover:bg-teal-400 text-slate-950 font-bold text-xs transition shadow-md shadow-teal-500/20 cursor-pointer active:scale-95"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
              <span>Buat Target Pertama</span>
            </button>
          </div>
        </div>

        <!-- GOALS GRID -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
          <div
            v-for="goal in financeStore.goals"
            :key="goal.id"
            class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800 flex flex-col justify-between hover:border-teal-500/40 transition group shadow-lg"
          >
            <div>
              <!-- Title & Status -->
              <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm sm:text-base font-bold text-white flex items-center gap-2 truncate pr-2">
                  <span>🎯</span>
                  <span class="truncate">{{ goal.name }}</span>
                </h3>
                <span
                  class="px-2.5 py-0.5 rounded-full text-[10px] font-bold shrink-0"
                  :class="goal.status === 'ACHIEVED' ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-teal-500/20 text-teal-300 border border-teal-500/30'"
                >
                  {{ goal.status === 'ACHIEVED' ? 'Tercapai 🎉' : `${goal.progress_percent}%` }}
                </span>
              </div>

              <!-- Progress Bar -->
              <div class="my-4 space-y-1.5">
                <div class="w-full bg-slate-950 h-3 rounded-full overflow-hidden border border-slate-800">
                  <div
                    class="bg-gradient-to-r from-teal-500 to-emerald-400 h-full rounded-full transition-all duration-500"
                    :style="{ width: `${Math.min(goal.progress_percent, 100)}%` }"
                  ></div>
                </div>
                <div class="flex justify-between text-[11px] text-slate-400">
                  <span>Terkumpul: <b class="text-emerald-400">{{ formatRupiah(goal.current_amount) }}</b></span>
                  <span>Target: <b class="text-slate-200">{{ formatRupiah(goal.target_amount) }}</b></span>
                </div>
              </div>

              <!-- Deadline & Monthly estimate -->
              <div class="p-3.5 rounded-xl bg-slate-950/60 border border-slate-800/80 text-[11px] space-y-1.5 mt-4">
                <div class="flex justify-between text-slate-400">
                  <span>Target Tanggal:</span>
                  <span class="text-slate-200 font-semibold">{{ formatDate(goal.target_date) }}</span>
                </div>
                <div class="flex justify-between text-slate-400">
                  <span>Sisa yang Dibutuhkan:</span>
                  <span class="text-teal-300 font-bold">{{ formatRupiah(Math.max(0, goal.target_amount - goal.current_amount)) }}</span>
                </div>
              </div>
            </div>

            <!-- Action Button -->
            <div class="mt-5 pt-3 border-t border-slate-800/80">
              <button
                @click="openContribute(goal)"
                :disabled="goal.status === 'ACHIEVED'"
                class="w-full py-2.5 rounded-xl bg-slate-800 hover:bg-slate-750 text-teal-300 font-bold text-xs transition flex items-center justify-center gap-2 disabled:opacity-50 cursor-pointer"
              >
                <span>💰</span>
                <span>Setor ke Tabungan</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Modals -->
    <GoalModal
      :isOpen="showGoalModal"
      @close="showGoalModal = false"
      @saved="loadGoals"
    />

    <ContributeGoalModal
      :isOpen="showContributeModal"
      :goal="selectedGoal"
      @close="showContributeModal = false"
      @contributed="loadGoals"
    />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useFinanceStore } from '../stores/finance';
import { useVaultStore } from '../stores/vault';
import { formatRupiah as rawFormatRupiah, formatDate } from '../utils/formatters';
import GoalModal from '../components/modals/GoalModal.vue';
import ContributeGoalModal from '../components/modals/ContributeGoalModal.vue';

const financeStore = useFinanceStore();
const vaultStore = useVaultStore();

const isInitialLoading = ref(!financeStore.hasLoadedGoals);
const isSyncing = ref(false);
const isError = ref(false);
const errorMessage = ref('');

const showGoalModal = ref(false);
const showContributeModal = ref(false);
const selectedGoal = ref(null);

function formatRupiah(val) {
  if (vaultStore.isMasked) return 'Rp ••••••••';
  return rawFormatRupiah(val);
}

function openContribute(goal) {
  selectedGoal.value = goal;
  showContributeModal.value = true;
}

async function loadGoals(force = false) {
  if (!financeStore.hasLoadedGoals) {
    isInitialLoading.value = true;
  } else {
    isInitialLoading.value = false;
    isSyncing.value = true;
  }

  isError.value = false;
  errorMessage.value = '';

  try {
    await financeStore.fetchGoals(force);
  } catch (err) {
    if (!financeStore.hasLoadedGoals) {
      isError.value = true;
      errorMessage.value = err.response?.data?.message || err.message || 'Gagal memuat target tabungan.';
    }
  } finally {
    isInitialLoading.value = false;
    isSyncing.value = false;
  }
}

onMounted(() => {
  loadGoals();
});
</script>
