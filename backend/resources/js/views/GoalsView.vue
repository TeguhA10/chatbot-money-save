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
        @click="openCreateGoal"
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
        <!-- INLINE FILTER BAR FOR GOALS (Standardized & UI/UX Friendly) -->
        <div class="p-4 sm:p-5 rounded-2xl bg-slate-900/90 border border-slate-800 space-y-3.5 shadow-sm">
          <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-800/80 pb-3">
            <div class="flex items-center gap-2">
              <span class="text-xs font-bold text-white">Saring Target:</span>
              <span class="text-[11px] px-2.5 py-0.5 rounded-full bg-slate-800 text-slate-300 font-mono">
                Menampilkan {{ filteredGoals.length }} dari {{ financeStore.goals.length }} Target
              </span>
            </div>

            <button
              v-if="hasActiveGoalFilters"
              @click="resetGoalFilters"
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
                v-model="goalSearch"
                placeholder="Cari nama target tabungan..."
                class="w-full pl-9 pr-4 py-2.5 rounded-xl bg-slate-900 border border-slate-800 hover:border-slate-700 text-slate-200 text-xs font-semibold focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500/40 shadow-inner transition placeholder:text-slate-500"
              />
            </div>

            <!-- Filter Status -->
            <div>
              <SelectDropdown
                v-model="goalStatusFilter"
                :options="goalStatusOptions"
                placeholder="Semua Status Target"
                icon="🎯"
              />
            </div>

            <!-- Sort By -->
            <div>
              <SelectDropdown
                v-model="goalSort"
                :options="goalSortOptions"
                placeholder="Urutan Target"
                icon="↕️"
              />
            </div>
          </div>
        </div>

        <!-- EMPTY STATE: GENUINELY NO GOALS -->
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

        <!-- FILTER EMPTY STATE: 0 MATCHES -->
        <div
          v-else-if="filteredGoals.length === 0"
          class="py-12 px-6 rounded-2xl bg-slate-900/60 border border-slate-800 text-center space-y-3"
        >
          <div class="text-3xl">🔍</div>
          <h3 class="text-sm font-bold text-white">Tidak Ada Target yang Cocok</h3>
          <p class="text-xs text-slate-400">
            Tidak ditemukan target tabungan yang cocok dengan kriteria filter Anda.
          </p>
          <button
            @click="resetGoalFilters"
            class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold transition"
          >
            Reset Filter
          </button>
        </div>

        <!-- GOALS GRID -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
          <div
            v-for="goal in filteredGoals"
            :key="goal.id"
            class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800 flex flex-col justify-between hover:border-teal-500/40 transition group shadow-lg"
          >
            <div>
              <!-- Title & Status & Actions -->
              <div class="flex items-center justify-between mb-3 gap-2">
                <h3 class="text-sm sm:text-base font-bold text-white flex items-center gap-2 truncate pr-1">
                  <span>🎯</span>
                  <span class="truncate">{{ goal.name }}</span>
                </h3>
                <div class="flex items-center gap-1.5 shrink-0">
                  <span
                    class="px-2.5 py-0.5 rounded-full text-[10px] font-bold"
                    :class="goal.status === 'ACHIEVED' ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-teal-500/20 text-teal-300 border border-teal-500/30'"
                  >
                    {{ goal.status === 'ACHIEVED' ? 'Tercapai 🎉' : `${goal.progress_percent}%` }}
                  </span>
                  <button
                    @click.stop="openEditGoal(goal)"
                    title="Edit Target"
                    class="p-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white transition cursor-pointer"
                  >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                  </button>
                  <button
                    @click.stop="confirmDeleteGoal(goal)"
                    title="Hapus Target"
                    class="p-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500/25 text-rose-400 hover:text-rose-300 transition cursor-pointer"
                  >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                  </button>
                </div>
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
      :goal="selectedGoalForEdit"
      @close="showGoalModal = false; selectedGoalForEdit = null"
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
import { ref, computed, onMounted } from 'vue';
import { useFinanceStore } from '../stores/finance';
import { useVaultStore } from '../stores/vault';
import { useToastStore } from '../stores/toast';
import { formatRupiah as rawFormatRupiah, formatDate } from '../utils/formatters';
import GoalModal from '../components/modals/GoalModal.vue';
import ContributeGoalModal from '../components/modals/ContributeGoalModal.vue';
import SelectDropdown from '../components/common/SelectDropdown.vue';

const financeStore = useFinanceStore();
const vaultStore = useVaultStore();
const toastStore = useToastStore();
const selectedGoalForEdit = ref(null);

function openCreateGoal() {
  selectedGoalForEdit.value = null;
  showGoalModal.value = true;
}

function openEditGoal(goal) {
  selectedGoalForEdit.value = goal;
  showGoalModal.value = true;
}

async function confirmDeleteGoal(goal) {
  let msg = `Apakah Anda yakin ingin membatalkan/menghapus target "${goal.name}"?`;
  if (goal.current_amount > 0) {
    msg += ` Dana yang telah terkumpul (${formatRupiah(goal.current_amount)}) akan otomatis dikembalikan ke dompet utama Anda.`;
  }
  const confirmed = window.confirm(msg);
  if (!confirmed) return;

  try {
    await financeStore.deleteGoal(goal.id, { refund: true });
    toastStore.success(`Target "${goal.name}" berhasil dihapus.`);
    await loadGoals(true);
  } catch (err) {
    toastStore.error(err.response?.data?.message || 'Gagal menghapus target.');
  }
}

const goalStatusOptions = [
  { value: 'ALL', label: 'Semua Status Target', icon: '🎯' },
  { value: 'ACTIVE', label: 'Dalam Proses Menabung', icon: '⏳' },
  { value: 'ACHIEVED', label: 'Sudah Tercapai', icon: '🎉' },
];

const goalSortOptions = [
  { value: 'PROGRESS_DESC', label: 'Progres Terbesar', icon: '📈' },
  { value: 'PROGRESS_ASC', label: 'Progres Terendah', icon: '📉' },
  { value: 'TARGET_DESC', label: 'Nominal Target Terbesar', icon: '💰' },
  { value: 'NAME_ASC', label: 'Nama Target (A-Z)', icon: '🔤' },
];

const isInitialLoading = ref(!financeStore.hasLoadedGoals);
const isSyncing = ref(false);
const isError = ref(false);
const errorMessage = ref('');

const showGoalModal = ref(false);
const showContributeModal = ref(false);
const selectedGoal = ref(null);

// Goal Filter States
const goalSearch = ref('');
const goalStatusFilter = ref('ALL');
const goalSort = ref('PROGRESS_DESC');

function formatRupiah(val) {
  if (vaultStore.isMasked) return 'Rp ••••••••';
  return rawFormatRupiah(val);
}

function openContribute(goal) {
  selectedGoal.value = goal;
  showContributeModal.value = true;
}

const hasActiveGoalFilters = computed(() => {
  return !!(goalSearch.value.trim() || goalStatusFilter.value !== 'ALL');
});

function resetGoalFilters() {
  goalSearch.value = '';
  goalStatusFilter.value = 'ALL';
  goalSort.value = 'PROGRESS_DESC';
}

const filteredGoals = computed(() => {
  let list = [...(financeStore.goals || [])];

  if (goalSearch.value.trim()) {
    const q = goalSearch.value.trim().toLowerCase();
    list = list.filter(g => (g.name || '').toLowerCase().includes(q));
  }

  if (goalStatusFilter.value === 'ACHIEVED') {
    list = list.filter(g => g.status === 'ACHIEVED' || g.status === 'COMPLETED' || (Number(g.progress_percent) || 0) >= 100);
  } else if (goalStatusFilter.value === 'ACTIVE') {
    list = list.filter(g => (g.status === 'ACTIVE' || !g.status) && (Number(g.progress_percent) || 0) < 100);
  }

  list.sort((a, b) => {
    if (goalSort.value === 'PROGRESS_DESC') {
      return (Number(b.progress_percent) || 0) - (Number(a.progress_percent) || 0);
    }
    if (goalSort.value === 'PROGRESS_ASC') {
      return (Number(a.progress_percent) || 0) - (Number(b.progress_percent) || 0);
    }
    if (goalSort.value === 'TARGET_DESC') {
      return (Number(b.target_amount) || 0) - (Number(a.target_amount) || 0);
    }
    if (goalSort.value === 'NAME_ASC') {
      return (a.name || '').localeCompare(b.name || '');
    }
    return 0;
  });

  return list;
});

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
