<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-950/80 backdrop-blur-sm animate-fade-in overflow-y-auto">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl w-full max-w-md p-5 sm:p-6 shadow-2xl relative my-auto max-h-[92vh] flex flex-col">
      <button
        @click="$emit('close')"
        class="absolute top-5 right-5 text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-800 transition z-10"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>

      <div class="flex items-center gap-3 mb-4 shrink-0">
        <div class="w-9 h-9 rounded-xl bg-blue-500/20 text-blue-400 flex items-center justify-center text-lg">
          ⇄
        </div>
        <div>
          <h2 class="text-xl font-bold text-white">Transfer Antar Dompet</h2>
          <p class="text-xs text-slate-400">Pindahkan saldo tanpa menambah/mengurangi total kekayaan</p>
        </div>
      </div>

      <form @submit.prevent="handleTransfer" class="space-y-4 overflow-y-auto pr-1 flex-1">
        <!-- From Wallet -->
        <div>
          <label class="block text-xs font-medium text-slate-300 mb-1.5">Dari Dompet (Asal)</label>
          <select
            v-model="form.from_wallet_id"
            required
            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-800 hover:border-slate-700 text-slate-200 text-xs font-semibold focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/40 shadow-inner cursor-pointer transition"
          >
            <option value="" disabled>Pilih dompet asal...</option>
            <option
              v-for="w in financeStore.wallets"
              :key="w.id"
              :value="w.id"
              :disabled="w.id === form.to_wallet_id"
            >
              {{ w.name }} (Saldo: {{ formatRupiah(w.balance) }})
            </option>
          </select>
        </div>

        <!-- To Wallet -->
        <div>
          <label class="block text-xs font-medium text-slate-300 mb-1.5">Ke Dompet (Tujuan)</label>
          <select
            v-model="form.to_wallet_id"
            required
            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-800 hover:border-slate-700 text-slate-200 text-xs font-semibold focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/40 shadow-inner cursor-pointer transition"
          >
            <option value="" disabled>Pilih dompet tujuan...</option>
            <option
              v-for="w in financeStore.wallets"
              :key="w.id"
              :value="w.id"
              :disabled="w.id === form.from_wallet_id"
            >
              {{ w.name }} (Saldo: {{ formatRupiah(w.balance) }})
            </option>
          </select>
        </div>

        <!-- Amount -->
        <div>
          <div class="flex items-center justify-between mb-1.5">
            <label class="block text-xs font-medium text-slate-300">Nominal Transfer (Rp)</label>
            <span v-if="form.amount" class="text-xs font-semibold text-blue-400">
              Rp {{ Number(form.amount).toLocaleString('id-ID') }}
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
              class="w-full pl-11 pr-4 py-2.5 rounded-xl bg-slate-900 border border-slate-800 hover:border-slate-700 text-white font-semibold text-lg focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/40 shadow-inner transition"
            />
          </div>
          <!-- Quick Amount Buttons -->
          <div class="flex items-center gap-1.5 mt-2">
            <span class="text-[11px] text-slate-500 font-medium mr-1">Cepat:</span>
            <button
              v-for="preset in [50000, 100000, 250000, 500000]"
              :key="preset"
              type="button"
              @click="addPreset(preset)"
              class="text-[11px] px-2.5 py-1 rounded-lg bg-slate-800/80 hover:bg-slate-750 text-slate-300 hover:text-white border border-slate-700/60 transition"
            >
              +{{ (preset / 1000).toLocaleString('id-ID') }}rb
            </button>
          </div>
        </div>

        <!-- Live Simulation Preview -->
        <div v-if="fromWallet && toWallet && form.amount > 0" class="p-3.5 rounded-xl bg-slate-950/80 border border-slate-800 text-xs space-y-2">
          <p class="font-semibold text-slate-300">Simulasi Perubahan Saldo:</p>
          <div class="flex justify-between text-slate-400">
            <span>{{ fromWallet.name }}:</span>
            <span :class="fromWallet.balance < form.amount ? 'text-rose-400 font-bold' : 'text-slate-300'">
              {{ formatRupiah(fromWallet.balance) }} ➔ {{ formatRupiah(fromWallet.balance - form.amount) }}
            </span>
          </div>
          <div class="flex justify-between text-slate-400">
            <span>{{ toWallet.name }}:</span>
            <span class="text-emerald-400 font-semibold">
              {{ formatRupiah(toWallet.balance) }} ➔ {{ formatRupiah(toWallet.balance + form.amount) }}
            </span>
          </div>
          <p v-if="fromWallet.balance < form.amount" class="text-rose-400 font-medium text-[11px] pt-1">
            ⚠️ Saldo dompet asal tidak mencukupi untuk transfer ini!
          </p>
        </div>

        <!-- Description -->
        <div>
          <label class="block text-xs font-medium text-slate-300 mb-1.5">Catatan (Opsional)</label>
          <input
            type="text"
            v-model="form.description"
            placeholder="Contoh: Top-up saldo DANA dari BCA"
            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-800 hover:border-slate-700 text-slate-200 text-xs font-semibold focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/40 shadow-inner transition placeholder:text-slate-500"
          />
        </div>

        <!-- Actions -->
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
            :disabled="submitting || isInsufficient"
            class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-bold text-sm transition disabled:opacity-50"
          >
            {{ submitting ? 'Memproses...' : 'Kirim Transfer' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useFinanceStore } from '../../stores/finance';
import { useToastStore } from '../../stores/toast';
import { formatRupiah } from '../../utils/formatters';

const props = defineProps({
  isOpen: Boolean,
});

const emit = defineEmits(['close', 'transferred']);
const financeStore = useFinanceStore();
const toastStore = useToastStore();

const submitting = ref(false);
const form = ref({
  from_wallet_id: '',
  to_wallet_id: '',
  amount: null,
  description: '',
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

const fromWallet = computed(() => {
  return financeStore.wallets.find((w) => w.id === form.value.from_wallet_id);
});

const toWallet = computed(() => {
  return financeStore.wallets.find((w) => w.id === form.value.to_wallet_id);
});

const isInsufficient = computed(() => {
  if (!fromWallet.value || !form.value.amount) return false;
  return fromWallet.value.balance < form.value.amount;
});

async function handleTransfer() {
  if (!form.value.from_wallet_id || !form.value.to_wallet_id || !form.value.amount) {
    toastStore.warning('Pilih dompet asal, tujuan, dan masukkan nominal transfer.');
    return;
  }
  if (isInsufficient.value) {
    toastStore.error('Saldo dompet asal tidak mencukupi.');
    return;
  }

  submitting.value = true;
  try {
    await financeStore.transferFunds({
      from_wallet_id: form.value.from_wallet_id,
      to_wallet_id: form.value.to_wallet_id,
      amount: form.value.amount,
      description: form.value.description,
    });
    toastStore.success(`Transfer sebesar Rp ${Number(form.value.amount).toLocaleString('id-ID')} berhasil diproses.`);
    form.value = {
      from_wallet_id: '',
      to_wallet_id: '',
      amount: null,
      description: '',
    };
    emit('transferred');
    emit('close');
  } catch (err) {
    toastStore.error(err.response?.data?.message || 'Gagal memproses transfer dana.');
  } finally {
    submitting.value = false;
  }
}
</script>
