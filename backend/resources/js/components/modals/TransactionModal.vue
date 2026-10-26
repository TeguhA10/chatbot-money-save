<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-950/80 backdrop-blur-sm animate-fade-in overflow-y-auto">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl w-full max-w-md p-5 sm:p-6 shadow-2xl relative my-auto max-h-[92vh] flex flex-col">
      <!-- Close Button -->
      <button
        @click="$emit('close')"
        class="absolute top-5 right-5 text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-800 transition z-10"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>

      <div class="mb-4 shrink-0">
        <h2 class="text-xl font-bold text-white mb-1">
          {{ isEdit ? 'Edit Transaksi' : 'Catat Transaksi Baru' }}
        </h2>
        <p class="text-xs text-slate-400">
          {{ isEdit ? 'Perbarui rincian transaksi Anda' : 'Masukkan pengeluaran atau pemasukan baru' }}
        </p>
      </div>

      <form @submit.prevent="handleSubmit" class="space-y-4 overflow-y-auto pr-1 flex-1">
        <!-- Type Selector (Only on create) -->
        <div v-if="!isEdit" class="grid grid-cols-2 gap-2 p-1 bg-slate-950 rounded-xl border border-slate-800">
          <button
            type="button"
            @click="form.type = 'EXPENSE'"
            class="py-2 rounded-lg text-xs font-semibold transition cursor-pointer"
            :class="form.type === 'EXPENSE' ? 'bg-rose-500/20 text-rose-400 border border-rose-500/30' : 'text-slate-400 hover:text-slate-200'"
          >
            🔴 Pengeluaran
          </button>
          <button
            type="button"
            @click="form.type = 'INCOME'"
            class="py-2 rounded-lg text-xs font-semibold transition cursor-pointer"
            :class="form.type === 'INCOME' ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'text-slate-400 hover:text-slate-200'"
          >
            🟢 Pemasukan
          </button>
        </div>

        <!-- Dompet Selection (Wajib, default Cash) -->
        <div>
          <div class="flex items-center justify-between mb-1.5">
            <label class="block text-xs font-medium text-slate-300">
              Dompet / Sumber Dana <span class="text-rose-400 font-bold">*</span>
            </label>
            <span class="text-[11px] text-slate-400 font-medium">Default: Cash</span>
          </div>
          <div class="relative">
            <select
              v-model="form.wallet_id"
              required
              class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-800 hover:border-slate-700 text-slate-200 text-xs font-semibold focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/40 shadow-inner transition cursor-pointer"
            >
              <option value="" disabled>-- Pilih Dompet (Wajib) --</option>
              <option
                v-for="w in financeStore.wallets"
                :key="w.id"
                :value="w.id"
              >
                👛 {{ w.name }} (Saldo: Rp {{ Number(w.balance || 0).toLocaleString('id-ID') }}) {{ (w.is_default || w.name.toLowerCase() === 'cash') ? '★ Default' : '' }}
              </option>
            </select>
          </div>
          <p v-if="selectedWalletBalance !== null" class="text-[11px] text-slate-500 mt-1">
            Saldo dompet terpilih: <span class="text-emerald-400 font-medium">Rp {{ Number(selectedWalletBalance).toLocaleString('id-ID') }}</span>
          </p>
        </div>

        <!-- Amount -->
        <div>
          <div class="flex items-center justify-between mb-1.5">
            <label class="block text-xs font-medium text-slate-300">Nominal (Rp)</label>
            <span v-if="form.amount" class="text-xs font-semibold text-emerald-400">
              Rp {{ (form.amount || 0).toLocaleString('id-ID') }}
            </span>
          </div>
          <div class="relative">
            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm font-semibold">Rp</span>
            <input
              type="text"
              inputmode="numeric"
              :value="formattedAmount"
              @input="handleAmountInput"
              required
              placeholder="0"
              class="w-full pl-11 pr-4 py-2.5 rounded-xl bg-slate-900 border border-slate-800 hover:border-slate-700 text-white font-semibold text-lg focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/40 shadow-inner transition"
            />
          </div>
          <!-- Quick Amount Buttons -->
          <div class="flex items-center gap-1.5 mt-2">
            <span class="text-[11px] text-slate-500 font-medium mr-1">Cepat:</span>
            <button
              v-for="preset in [10000, 20000, 50000, 100000]"
              :key="preset"
              type="button"
              @click="addPreset(preset)"
              class="text-[11px] px-2.5 py-1 rounded-lg bg-slate-800/80 hover:bg-slate-750 text-slate-300 hover:text-white border border-slate-700/60 transition cursor-pointer"
            >
              +{{ (preset / 1000).toLocaleString('id-ID') }}rb
            </button>
          </div>
        </div>

        <!-- Pilihan 1: Alokasikan ke Target Tabungan (Opsional) -->
        <div v-if="form.type === 'EXPENSE'" class="space-y-1.5">
          <div class="flex items-center justify-between">
            <label class="block text-xs font-medium text-slate-300">
              🎯 Target Tabungan (Opsional)
            </label>
            <span class="text-[10px] text-teal-400 font-semibold">Khusus Menabung</span>
          </div>

          <div v-if="financeStore.goals.length === 0" class="p-2.5 rounded-xl bg-slate-950 border border-slate-800 text-[11px] text-slate-400 flex items-center justify-between">
            <span>Belum ada target tabungan</span>
            <span class="text-teal-400 text-[10px]">Buat di menu Target</span>
          </div>

          <select
            v-else
            v-model="form.goal_id"
            @change="onGoalSelected"
            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-800 hover:border-slate-700 text-slate-200 text-xs font-semibold focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500/40 shadow-inner transition cursor-pointer"
          >
            <option :value="null">-- Bukan untuk Target Tabungan --</option>
            <option
              v-for="g in financeStore.goals"
              :key="g.id"
              :value="g.id"
            >
              🎯 {{ g.name }} (Terkumpul {{ g.progress_percent }}%)
            </option>
          </select>
        </div>

        <!-- Pilihan 2: Alokasikan ke Anggaran / Budget (Opsional) -->
        <div v-if="form.type === 'EXPENSE'" class="space-y-1.5">
          <div class="flex items-center justify-between">
            <label class="block text-xs font-medium text-slate-300">
              📊 Pos Anggaran / Budget (Opsional)
            </label>
            <span class="text-[10px] text-amber-400 font-semibold">Plafon Pengeluaran</span>
          </div>

          <div v-if="financeStore.budgets.length === 0" class="p-2.5 rounded-xl bg-slate-950 border border-slate-800 text-[11px] text-slate-400 flex items-center justify-between">
            <span>Belum ada plafon anggaran aktif</span>
            <span class="text-amber-400 text-[10px]">Atur di menu Budget</span>
          </div>

          <select
            v-else
            v-model="form.budget_id"
            @change="onBudgetSelected"
            :disabled="!!form.goal_id"
            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-800 hover:border-slate-700 text-slate-200 text-xs font-semibold focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500/40 shadow-inner transition cursor-pointer disabled:opacity-40"
          >
            <option :value="null">-- Pengeluaran Umum (Tanpa Pos Khusus) --</option>
            <option
              v-for="b in financeStore.budgets"
              :key="b.id"
              :value="b.id"
            >
              📊 {{ b.description || 'Batas Pengeluaran Bulanan' }} (Sisa: {{ formatRupiah(b.remaining_amount) }})
            </option>
          </select>
        </div>

        <!-- Live Status Dampak ke Plafon Anggaran (Budget) -->
        <div v-if="form.type === 'EXPENSE'" class="p-3.5 rounded-2xl border text-xs transition-all space-y-1" :class="form.goal_id ? 'bg-teal-500/10 border-teal-500/30 text-teal-300' : 'bg-slate-950/80 border-slate-800 text-slate-300'">
          <div class="flex items-center justify-between">
            <span class="font-bold flex items-center gap-1.5 text-[11px]">
              <span>{{ form.goal_id ? '🛡️ Bebas dari Plafon Belanja' : (form.budget_id ? '📊 Memotong Anggaran Terpilih' : '📊 Memotong Plafon Belanja Umum') }}</span>
            </span>
            <span v-if="currentBudget" class="text-[10px] text-slate-400">
              Sisa Budget: <b class="text-amber-400">{{ formatRupiah(currentBudget.remaining_amount) }}</b>
            </span>
          </div>
          <p class="text-[11px] leading-relaxed" :class="form.goal_id ? 'text-teal-300/90' : 'text-slate-400'">
            <span v-if="form.goal_id">
              Karena dialokasikan untuk <b>Target Tabungan</b>, transaksi ini dicatat sebagai aset tabungan masa depan dan <b>tidak memotong</b> jatah anggaran belanja konsumtif Anda.
            </span>
            <span v-else-if="form.budget_id">
              Transaksi belanja ini akan dicatat dan <b>memotong pos anggaran</b> yang Anda pilih di atas.
            </span>
            <span v-else>
              Pengeluaran konsumtif ini akan <b>otomatis mengurangi</b> sisa plafon anggaran belanja bulanan Anda.
            </span>
          </p>
        </div>

        <!-- Description -->
        <div>
          <label class="block text-xs font-medium text-slate-300 mb-1.5">Deskripsi / Catatan</label>
          <input
            type="text"
            v-model="form.description"
            required
            placeholder="Contoh: Makan siang nasi padang"
            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-800 hover:border-slate-700 text-slate-200 text-xs font-semibold focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/40 shadow-inner transition placeholder:text-slate-500"
          />
        </div>

        <!-- Transaction Date (on create) -->
        <div v-if="!isEdit">
          <label class="block text-xs font-medium text-slate-300 mb-1.5">Tanggal</label>
          <DatePicker
            v-model="form.transaction_date"
            placeholder="Pilih tanggal transaksi"
            :clearable="false"
          />
        </div>

        <!-- Submit Button -->
        <div class="pt-2 flex gap-3">
          <button
            type="button"
            @click="$emit('close')"
            class="w-1/3 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-sm font-semibold transition cursor-pointer"
          >
            Batal
          </button>
          <button
            type="submit"
            :disabled="submitting"
            class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-slate-950 font-bold text-sm transition cursor-pointer disabled:opacity-50"
          >
            {{ submitting ? 'Menyimpan...' : (isEdit ? 'Perbarui Transaksi' : 'Simpan Transaksi') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed, onMounted } from 'vue';
import { useFinanceStore } from '../../stores/finance';
import { useToastStore } from '../../stores/toast';
import { formatRupiah } from '../../utils/formatters';
import DatePicker from '../common/DatePicker.vue';

const props = defineProps({
  isOpen: Boolean,
  transaction: Object,
});

const emit = defineEmits(['close', 'saved']);

const financeStore = useFinanceStore();
const toastStore = useToastStore();
const submitting = ref(false);

const isEdit = computed(() => !!props.transaction?.id);
const currentBudget = computed(() => financeStore.budgets?.[0] || financeStore.budget);

function resolveDefaultWalletId() {
  if (!financeStore.wallets || financeStore.wallets.length === 0) return '';
  const defaultWallet = financeStore.wallets.find(w => w.is_default || w.name?.toLowerCase() === 'cash');
  return defaultWallet ? defaultWallet.id : (financeStore.wallets[0]?.id || '');
}

const form = ref({
  type: 'EXPENSE',
  amount: null,
  description: '',
  wallet_id: '',
  goal_id: null,
  budget_id: null,
  transaction_date: new Date().toISOString().slice(0, 10),
});

function onGoalSelected() {
  if (form.value.goal_id) {
    form.value.budget_id = null; // Target saving is an asset accumulation, not a spending budget deduction
    const g = financeStore.goals.find(x => x.id === form.value.goal_id);
    if (g && (!form.value.description || form.value.description.startsWith('Setoran Tabungan:'))) {
      form.value.description = `Setoran Tabungan: ${g.name}`;
    }
  }
}

function onBudgetSelected() {
  if (form.value.budget_id) {
    form.value.goal_id = null;
  }
}

const selectedWalletBalance = computed(() => {
  if (!form.value.wallet_id || !financeStore.wallets) return null;
  const w = financeStore.wallets.find(x => x.id === form.value.wallet_id);
  return w ? w.balance : null;
});

const formattedAmount = computed(() => {
  if (!form.value.amount && form.value.amount !== 0) return '';
  return Number(form.value.amount).toLocaleString('id-ID');
});

function handleAmountInput(e) {
  const digits = e.target.value.replace(/\D/g, '');
  form.value.amount = digits ? parseInt(digits, 10) : null;
  e.target.value = form.value.amount ? Number(form.value.amount).toLocaleString('id-ID') : '';
}

function addPreset(val) {
  form.value.amount = (form.value.amount || 0) + val;
}

watch(
  () => [props.transaction, props.isOpen],
  async ([val, isOpen]) => {
    if (isOpen) {
      if (financeStore.wallets.length === 0) financeStore.fetchWallets().catch(() => {});
      if (financeStore.goals.length === 0) financeStore.fetchGoals().catch(() => {});
      if (financeStore.budgets.length === 0) financeStore.fetchBudgets().catch(() => {});
    }

    if (val) {
      form.value = {
        type: val.type || 'EXPENSE',
        amount: val.amount || null,
        description: val.description || '',
        wallet_id: val.wallet_id || resolveDefaultWalletId(),
        goal_id: val.goal_id || null,
        budget_id: val.budget_id || null,
        transaction_date: val.transaction_date ? val.transaction_date.slice(0, 10) : new Date().toISOString().slice(0, 10),
      };
    } else {
      form.value = {
        type: 'EXPENSE',
        amount: null,
        description: '',
        wallet_id: resolveDefaultWalletId(),
        goal_id: null,
        budget_id: null,
        transaction_date: new Date().toISOString().slice(0, 10),
      };
    }
  },
  { immediate: true }
);

watch(
  () => financeStore.wallets,
  (wallets) => {
    if (wallets.length > 0 && !form.value.wallet_id) {
      form.value.wallet_id = resolveDefaultWalletId();
    }
  },
  { deep: true }
);

onMounted(async () => {
  try {
    await Promise.allSettled([
      financeStore.wallets.length === 0 ? financeStore.fetchWallets() : Promise.resolve(),
      financeStore.goals.length === 0 ? financeStore.fetchGoals() : Promise.resolve(),
      financeStore.budgets.length === 0 ? financeStore.fetchBudgets() : Promise.resolve(),
    ]);
  } catch (e) {
    // Ignored
  }
});

async function handleSubmit() {
  if (!form.value.amount || form.value.amount <= 0) {
    toastStore.warning('Masukkan nominal transaksi yang valid.');
    return;
  }
  if (!form.value.wallet_id) {
    toastStore.warning('Pilih dompet sumber dana transaksi terlebih dahulu.');
    return;
  }
  submitting.value = true;
  try {
    if (isEdit.value) {
      await financeStore.updateTransaction(props.transaction.id, {
        amount: form.value.amount,
        description: form.value.description,
        wallet_id: form.value.wallet_id,
        goal_id: form.value.goal_id || null,
        budget_id: form.value.budget_id || null,
      });
      toastStore.success('Transaksi berhasil diperbarui.');
    } else {
      await financeStore.createTransaction({
        type: form.value.type,
        amount: form.value.amount,
        description: form.value.description,
        wallet_id: form.value.wallet_id,
        goal_id: form.value.goal_id || undefined,
        budget_id: form.value.budget_id || undefined,
        transaction_date: form.value.transaction_date,
      });
      toastStore.success('Transaksi baru berhasil dicatat.');
    }
    emit('saved');
    emit('close');
  } catch (err) {
    toastStore.error(err.response?.data?.message || 'Gagal menyimpan transaksi.');
  } finally {
    submitting.value = false;
  }
}
</script>
