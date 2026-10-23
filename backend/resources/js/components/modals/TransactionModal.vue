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
            class="py-2 rounded-lg text-xs font-semibold transition"
            :class="form.type === 'EXPENSE' ? 'bg-rose-500/20 text-rose-400 border border-rose-500/30' : 'text-slate-400 hover:text-slate-200'"
          >
            🔴 Pengeluaran
          </button>
          <button
            type="button"
            @click="form.type = 'INCOME'"
            class="py-2 rounded-lg text-xs font-semibold transition"
            :class="form.type === 'INCOME' ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'text-slate-400 hover:text-slate-200'"
          >
            🟢 Pemasukan
          </button>
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
              class="text-[11px] px-2.5 py-1 rounded-lg bg-slate-800/80 hover:bg-slate-750 text-slate-300 hover:text-white border border-slate-700/60 transition"
            >
              +{{ (preset / 1000).toLocaleString('id-ID') }}rb
            </button>
          </div>
        </div>

        <!-- Category (on create) -->
        <div v-if="!isEdit && form.type === 'EXPENSE'">
          <label class="block text-xs font-medium text-slate-300 mb-1.5">Kategori</label>
          <SelectDropdown
            v-model="form.category_id"
            :options="categoryOptions"
            placeholder="Pilih Kategori..."
            icon="🏷️"
            searchable
          />
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
            class="w-1/3 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-sm font-semibold transition"
          >
            Batal
          </button>
          <button
            type="submit"
            :disabled="submitting"
            class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-slate-950 font-bold text-sm transition disabled:opacity-50"
          >
            {{ submitting ? 'Menyimpan...' : (isEdit ? 'Perbarui Transaksi' : 'Simpan Transaksi') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import { useFinanceStore } from '../../stores/finance';
import { useToastStore } from '../../stores/toast';
import SelectDropdown from '../common/SelectDropdown.vue';
import DatePicker from '../common/DatePicker.vue';

const props = defineProps({
  isOpen: Boolean,
  transaction: Object,
});

const emit = defineEmits(['close', 'saved']);

const financeStore = useFinanceStore();
const toastStore = useToastStore();
const submitting = ref(false);

const categoryOptions = computed(() => {
  return financeStore.categories.map(cat => ({
    value: cat.id,
    label: cat.name,
    icon: cat.icon || '🏷️'
  }));
});

const isEdit = computed(() => !!props.transaction?.id);

const form = ref({
  type: 'EXPENSE',
  amount: null,
  category_id: null,
  description: '',
  transaction_date: new Date().toISOString().slice(0, 10),
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
  () => props.transaction,
  (val) => {
    if (val) {
      form.value = {
        type: val.type || 'EXPENSE',
        amount: val.amount || null,
        category_id: val.category_id || null,
        description: val.description || '',
        transaction_date: val.transaction_date ? val.transaction_date.slice(0, 10) : new Date().toISOString().slice(0, 10),
      };
    } else {
      form.value = {
        type: 'EXPENSE',
        amount: null,
        category_id: null,
        description: '',
        transaction_date: new Date().toISOString().slice(0, 10),
      };
    }
  },
  { immediate: true }
);

async function handleSubmit() {
  if (!form.value.amount || form.value.amount <= 0) {
    toastStore.warning('Masukkan nominal transaksi yang valid.');
    return;
  }
  submitting.value = true;
  try {
    if (isEdit.value) {
      await financeStore.updateTransaction(props.transaction.id, {
        amount: form.value.amount,
        description: form.value.description,
      });
      toastStore.success('Transaksi berhasil diperbarui.');
    } else {
      await financeStore.createTransaction({
        type: form.value.type,
        amount: form.value.amount,
        category_id: form.value.category_id,
        description: form.value.description,
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
