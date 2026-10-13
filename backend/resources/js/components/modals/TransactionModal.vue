<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm animate-fade-in">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md p-6 shadow-2xl relative">
      <!-- Close Button -->
      <button
        @click="$emit('close')"
        class="absolute top-5 right-5 text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-800 transition"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>

      <h2 class="text-xl font-bold text-white mb-1">
        {{ isEdit ? 'Edit Transaksi' : 'Catat Transaksi Baru' }}
      </h2>
      <p class="text-xs text-slate-400 mb-6">
        {{ isEdit ? 'Perbarui rincian transaksi Anda' : 'Masukkan pengeluaran atau pemasukan baru' }}
      </p>

      <form @submit.prevent="handleSubmit" class="space-y-4">
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
          <label class="block text-xs font-medium text-slate-300 mb-1.5">Nominal (Rp)</label>
          <div class="relative">
            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm font-semibold">Rp</span>
            <input
              type="number"
              v-model.number="form.amount"
              required
              min="100"
              step="100"
              placeholder="0"
              class="w-full pl-11 pr-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white font-semibold text-lg focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
            />
          </div>
        </div>

        <!-- Category (on create) -->
        <div v-if="!isEdit && form.type === 'EXPENSE'">
          <label class="block text-xs font-medium text-slate-300 mb-1.5">Kategori</label>
          <select
            v-model="form.category_id"
            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-slate-200 text-sm focus:outline-none focus:border-emerald-500"
          >
            <option :value="null">Pilih Kategori...</option>
            <option v-for="cat in financeStore.categories" :key="cat.id" :value="cat.id">
              {{ cat.icon }} {{ cat.name }}
            </option>
          </select>
        </div>

        <!-- Description -->
        <div>
          <label class="block text-xs font-medium text-slate-300 mb-1.5">Deskripsi / Catatan</label>
          <input
            type="text"
            v-model="form.description"
            required
            placeholder="Contoh: Makan siang nasi padang"
            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white text-sm focus:outline-none focus:border-emerald-500"
          />
        </div>

        <!-- Transaction Date (on create) -->
        <div v-if="!isEdit">
          <label class="block text-xs font-medium text-slate-300 mb-1.5">Tanggal</label>
          <input
            type="date"
            v-model="form.transaction_date"
            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white text-sm focus:outline-none focus:border-emerald-500"
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

const props = defineProps({
  isOpen: Boolean,
  transaction: Object,
});

const emit = defineEmits(['close', 'saved']);

const financeStore = useFinanceStore();
const submitting = ref(false);

const isEdit = computed(() => !!props.transaction?.id);

const form = ref({
  type: 'EXPENSE',
  amount: null,
  category_id: null,
  description: '',
  transaction_date: new Date().toISOString().slice(0, 10),
});

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
  if (!form.value.amount || form.value.amount <= 0) return;
  submitting.value = true;
  try {
    if (isEdit.value) {
      await financeStore.updateTransaction(props.transaction.id, {
        amount: form.value.amount,
        description: form.value.description,
      });
    } else {
      await financeStore.createTransaction({
        type: form.value.type,
        amount: form.value.amount,
        category_id: form.value.category_id,
        description: form.value.description,
        transaction_date: form.value.transaction_date,
      });
    }
    emit('saved');
    emit('close');
  } catch (err) {
    alert(err.response?.data?.message || 'Gagal menyimpan transaksi.');
  } finally {
    submitting.value = false;
  }
}
</script>
