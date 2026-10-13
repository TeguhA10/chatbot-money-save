<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm animate-fade-in">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md p-6 shadow-2xl relative">
      <button
        @click="$emit('close')"
        class="absolute top-5 right-5 text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-800 transition"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>

      <h2 class="text-xl font-bold text-white mb-1">Tambah Dompet Baru</h2>
      <p class="text-xs text-slate-400 mb-6">Kelola rekening bank, e-wallet, atau uang tunai</p>

      <form @submit.prevent="handleSubmit" class="space-y-4">
        <!-- Wallet Name -->
        <div>
          <label class="block text-xs font-medium text-slate-300 mb-1.5">Nama Dompet</label>
          <input
            type="text"
            v-model="form.name"
            required
            placeholder="Contoh: BCA, Mandiri, GoPay, Tunai Dompet"
            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white text-sm focus:outline-none focus:border-emerald-500"
          />
        </div>

        <!-- Wallet Type -->
        <div>
          <label class="block text-xs font-medium text-slate-300 mb-1.5">Kategori Dompet</label>
          <select
            v-model="form.type"
            required
            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-slate-200 text-sm focus:outline-none focus:border-emerald-500"
          >
            <option value="BANK">🏦 Bank Transfer (BCA, Mandiri, BRI, dll)</option>
            <option value="EWALLET">📱 E-Wallet (GoPay, OVO, DANA, ShopeePay)</option>
            <option value="CASH">💵 Uang Tunai (Cash)</option>
            <option value="OTHER">📁 Lainnya / Investasi</option>
          </select>
        </div>

        <!-- Initial Balance -->
        <div>
          <label class="block text-xs font-medium text-slate-300 mb-1.5">Saldo Awal (Rp)</label>
          <div class="relative">
            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm font-semibold">Rp</span>
            <input
              type="number"
              v-model.number="form.initial_balance"
              min="0"
              step="1000"
              placeholder="0"
              class="w-full pl-11 pr-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white font-semibold text-lg focus:outline-none focus:border-emerald-500"
            />
          </div>
          <p class="text-[11px] text-slate-500 mt-1">Saldo saat ini yang ada di rekening/dompet Anda</p>
        </div>

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
            {{ submitting ? 'Menyimpan...' : 'Simpan Dompet' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useFinanceStore } from '../../stores/finance';

const props = defineProps({
  isOpen: Boolean,
});

const emit = defineEmits(['close', 'saved']);
const financeStore = useFinanceStore();

const submitting = ref(false);
const form = ref({
  name: '',
  type: 'BANK',
  initial_balance: 0,
});

async function handleSubmit() {
  if (!form.value.name) return;
  submitting.value = true;
  try {
    await financeStore.createWallet({
      name: form.value.name,
      type: form.value.type,
      initial_balance: form.value.initial_balance || 0,
    });
    form.value = { name: '', type: 'BANK', initial_balance: 0 };
    emit('saved');
    emit('close');
  } catch (err) {
    alert(err.response?.data?.message || 'Gagal menambahkan dompet.');
  } finally {
    submitting.value = false;
  }
}
</script>
