<template>
  <div v-if="isOpen && goal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm animate-fade-in">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md p-6 shadow-2xl relative">
      <button
        @click="$emit('close')"
        class="absolute top-5 right-5 text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-800 transition"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>

      <div class="flex items-center gap-3 mb-1">
        <div class="w-10 h-10 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-xl">
          💰
        </div>
        <div>
          <h2 class="text-xl font-bold text-white">Setor ke Tabungan</h2>
          <p class="text-xs text-slate-400">{{ goal.name }}</p>
        </div>
      </div>

      <!-- Current Progress Summary -->
      <div class="mt-4 p-3 rounded-xl bg-slate-950 border border-slate-800/80 space-y-1.5 text-xs">
        <div class="flex justify-between text-slate-400">
          <span>Terkumpul saat ini:</span>
          <span class="text-emerald-400 font-semibold">{{ formatRupiah(goal.current_amount) }}</span>
        </div>
        <div class="flex justify-between text-slate-400">
          <span>Target total:</span>
          <span class="text-slate-200 font-semibold">{{ formatRupiah(goal.target_amount) }}</span>
        </div>
        <div class="w-full bg-slate-800 h-2 rounded-full overflow-hidden mt-1">
          <div
            class="bg-emerald-500 h-full rounded-full transition-all duration-500"
            :style="{ width: `${Math.min(goal.progress_percent, 100)}%` }"
          ></div>
        </div>
      </div>

      <form @submit.prevent="handleSubmit" class="mt-5 space-y-4">
        <!-- From Wallet -->
        <div>
          <label class="block text-xs font-medium text-slate-300 mb-1.5">Ambil dari Dompet (Opsional)</label>
          <select
            v-model="form.wallet_id"
            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-slate-200 text-sm focus:outline-none focus:border-emerald-500"
          >
            <option :value="null">Tanpa potong saldo dompet (hanya catat progres)</option>
            <option
              v-for="w in financeStore.wallets"
              :key="w.id"
              :value="w.id"
            >
              {{ w.name }} (Saldo: {{ formatRupiah(w.balance) }})
            </option>
          </select>
        </div>

        <!-- Amount -->
        <div>
          <label class="block text-xs font-medium text-slate-300 mb-1.5">Nominal Setoran (Rp)</label>
          <div class="relative">
            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm font-semibold">Rp</span>
            <input
              type="number"
              v-model.number="form.amount"
              required
              min="1000"
              step="1000"
              placeholder="0"
              class="w-full pl-11 pr-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white font-semibold text-lg focus:outline-none focus:border-emerald-500"
            />
          </div>
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
            {{ submitting ? 'Menyimpan...' : 'Simpan Setoran' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useFinanceStore } from '../../stores/finance';
import { formatRupiah } from '../../utils/formatters';

const props = defineProps({
  isOpen: Boolean,
  goal: Object,
});

const emit = defineEmits(['close', 'saved']);
const financeStore = useFinanceStore();

const submitting = ref(false);
const form = ref({
  wallet_id: null,
  amount: null,
});

async function handleSubmit() {
  if (!form.value.amount || form.value.amount <= 0) return;
  submitting.value = true;
  try {
    await financeStore.contributeGoal(props.goal.id, {
      amount: form.value.amount,
      wallet_id: form.value.wallet_id || undefined,
    });
    form.value = { wallet_id: null, amount: null };
    emit('saved');
    emit('close');
  } catch (err) {
    alert(err.response?.data?.message || 'Gagal menyetor ke target tabungan.');
  } finally {
    submitting.value = false;
  }
}
</script>
