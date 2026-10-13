<template>
  <div class="space-y-6">
    <!-- Header Page -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-black text-white tracking-tight">Target Tabungan</h1>
        <p class="text-xs text-slate-400 mt-0.5">Wujudkan impian finansial Anda dengan target terukur</p>
      </div>

      <button
        @click="showGoalModal = true"
        class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-teal-500 to-emerald-500 hover:from-teal-400 text-slate-950 font-bold text-xs flex items-center gap-1.5 shadow-lg shadow-teal-500/20 cursor-pointer transition active:scale-95"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        + Target Baru
      </button>
    </div>

    <!-- EMPTY STATE -->
    <div v-if="financeStore.goals.length === 0" class="py-20 text-center p-8 rounded-2xl bg-slate-900/60 border border-slate-800">
      <div class="w-16 h-16 rounded-2xl bg-teal-500/10 text-teal-400 flex items-center justify-center text-3xl mx-auto mb-3">
        🏆
      </div>
      <h3 class="text-base font-bold text-white mb-1">Belum Ada Target Tabungan</h3>
      <p class="text-xs text-slate-400 max-w-sm mx-auto mb-5">
        Mulai buat target impian Anda, misalnya Beli Rumah, Dana Darurat, atau Liburan.
      </p>
      <button
        @click="showGoalModal = true"
        class="px-4 py-2 rounded-xl bg-teal-500 text-slate-950 font-bold text-xs hover:bg-teal-400 transition"
      >
        Buat Target Pertama
      </button>
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
            <h3 class="text-base font-bold text-white flex items-center gap-2">
              <span>🎯</span>
              <span>{{ goal.name }}</span>
            </h3>
            <span
              class="px-2.5 py-0.5 rounded-full text-[10px] font-bold"
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
          <div class="p-3 rounded-xl bg-slate-950/60 border border-slate-800/80 text-[11px] space-y-1 mt-4">
            <div class="flex justify-between text-slate-400">
              <span>Target Tanggal:</span>
              <span class="text-slate-200 font-medium">{{ formatDate(goal.target_date) }}</span>
            </div>
            <div class="flex justify-between text-slate-400">
              <span>Sisa yang Dibutuhkan:</span>
              <span class="text-slate-200 font-medium">{{ formatRupiah(Math.max(0, goal.target_amount - goal.current_amount)) }}</span>
            </div>
          </div>
        </div>

        <!-- Action Button -->
        <div class="mt-5 pt-3 border-t border-slate-800/80">
          <button
            @click="openContribute(goal)"
            :disabled="goal.status === 'ACHIEVED'"
            class="w-full py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-teal-300 font-semibold text-xs transition flex items-center justify-center gap-1.5 disabled:opacity-50"
          >
            <span>💰</span>
            <span>+ Setor ke Tabungan</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Modals -->
    <GoalModal
      :isOpen="showGoalModal"
      @close="showGoalModal = false"
      @saved="loadGoals"
    />

    <ContributeGoalModal
      :isOpen="showContributeModal"
      :goal="activeGoal"
      @close="showContributeModal = false"
      @saved="loadGoals"
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

function formatRupiah(val) {
  if (vaultStore.isMasked) return 'Rp ••••••••';
  return rawFormatRupiah(val);
}

const showGoalModal = ref(false);
const showContributeModal = ref(false);
const activeGoal = ref(null);

function openContribute(goal) {
  activeGoal.value = goal;
  showContributeModal.value = true;
}

async function loadGoals() {
  await financeStore.fetchGoals();
  await financeStore.fetchWallets();
}

onMounted(() => {
  loadGoals();
});
</script>
