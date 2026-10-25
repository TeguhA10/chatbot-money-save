<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-950/80 backdrop-blur-sm animate-fade-in overflow-y-auto">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl w-full max-w-md p-5 sm:p-6 shadow-2xl relative my-auto max-h-[92vh] flex flex-col">
      <button
        @click="$emit('close')"
        class="absolute top-5 right-5 text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-800 transition z-10"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>

      <div class="mb-4 shrink-0">
        <h2 class="text-xl font-bold text-white mb-1">{{ isEdit ? 'Edit Dompet' : 'Tambah Dompet Baru' }}</h2>
        <p class="text-xs text-slate-400">{{ isEdit ? 'Sesuaikan nama, kategori, atau saldo dompet Anda' : 'Kelola rekening bank, e-wallet, atau uang tunai' }}</p>
      </div>

      <form @submit.prevent="handleSubmit" class="space-y-4 overflow-y-auto pr-1 flex-1">
        <!-- Wallet Name -->
        <div>
          <label class="block text-xs font-medium text-slate-300 mb-1.5">Nama Dompet</label>
          <input
            type="text"
            v-model="form.name"
            required
            placeholder="Contoh: BCA, Mandiri, GoPay, Tunai Dompet"
            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-800 hover:border-slate-700 text-slate-200 text-xs font-semibold focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/40 shadow-inner transition placeholder:text-slate-500"
          />
        </div>

        <!-- Wallet Type -->
        <div>
          <label class="block text-xs font-medium text-slate-300 mb-1.5">Kategori Dompet</label>
          <select
            v-model="form.type"
            required
            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-800 hover:border-slate-700 text-slate-200 text-xs font-semibold focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/40 shadow-inner cursor-pointer transition"
          >
            <option value="BANK">🏦 Bank Transfer (BCA, Mandiri, BRI, dll)</option>
            <option value="EWALLET">📱 E-Wallet (GoPay, OVO, DANA, ShopeePay)</option>
            <option value="CASH">💵 Uang Tunai (Cash)</option>
            <option value="OTHER">📁 Lainnya / Investasi</option>
          </select>
        </div>

        <!-- Balance -->
        <div>
          <div class="flex items-center justify-between mb-1.5">
            <label class="block text-xs font-medium text-slate-300">{{ isEdit ? 'Saldo Terkini (Rp)' : 'Saldo Awal (Rp)' }}</label>
            <span v-if="form.initial_balance" class="text-xs font-semibold text-emerald-400">
              Rp {{ Number(form.initial_balance).toLocaleString('id-ID') }}
            </span>
          </div>
          <div class="relative">
            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm font-semibold">Rp</span>
            <input
              type="text"
              inputmode="numeric"
              :value="formattedInitialBalance"
              @input="handleInitialBalanceInput"
              placeholder="0"
              class="w-full pl-11 pr-4 py-2.5 rounded-xl bg-slate-900 border border-slate-800 hover:border-slate-700 text-white font-semibold text-lg focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/40 shadow-inner transition"
            />
          </div>
          <p class="text-[11px] text-slate-500 mt-1">{{ isEdit ? 'Menyesuaikan saldo dompet ini akan otomatis memperbarui total kekayaan Anda' : 'Saldo saat ini yang ada di rekening/dompet Anda' }}</p>
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
            {{ submitting ? 'Menyimpan...' : (isEdit ? 'Simpan Perubahan' : 'Simpan Dompet') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { useFinanceStore } from '../../stores/finance';
import { useToastStore } from '../../stores/toast';

const props = defineProps({
  isOpen: Boolean,
  wallet: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(['close', 'saved']);
const financeStore = useFinanceStore();
const toastStore = useToastStore();

const submitting = ref(false);
const isEdit = computed(() => !!props.wallet);

const form = ref({
  name: '',
  type: 'BANK',
  initial_balance: 0,
});

watch(
  () => props.wallet,
  (w) => {
    if (w) {
      form.value = {
        name: w.name || '',
        type: w.type || 'BANK',
        initial_balance: w.balance ?? 0,
      };
    } else {
      form.value = { name: '', type: 'BANK', initial_balance: 0 };
    }
  },
  { immediate: true }
);

const formattedInitialBalance = computed(() => {
  if (!form.value.initial_balance && form.value.initial_balance !== 0) return '';
  return Number(form.value.initial_balance).toLocaleString('id-ID');
});

function handleInitialBalanceInput(e) {
  const digits = e.target.value.replace(/\D/g, '');
  form.value.initial_balance = digits ? parseInt(digits, 10) : 0;
  e.target.value = form.value.initial_balance ? Number(form.value.initial_balance).toLocaleString('id-ID') : '0';
}

async function handleSubmit() {
  if (!form.value.name.trim()) {
    toastStore.warning('Nama dompet tidak boleh kosong.');
    return;
  }
  submitting.value = true;
  try {
    if (isEdit.value) {
      await financeStore.updateWallet(props.wallet.id, {
        name: form.value.name.trim(),
        type: form.value.type,
        balance: form.value.initial_balance || 0,
      });
      toastStore.success(`Dompet "${form.value.name}" berhasil diperbarui.`);
    } else {
      await financeStore.createWallet({
        name: form.value.name.trim(),
        type: form.value.type,
        initial_balance: form.value.initial_balance || 0,
      });
      toastStore.success(`Dompet "${form.value.name}" berhasil ditambahkan.`);
    }
    form.value = { name: '', type: 'BANK', initial_balance: 0 };
    emit('saved');
    emit('close');
  } catch (err) {
    toastStore.error(err.response?.data?.message || 'Gagal menyimpan dompet.');
  } finally {
    submitting.value = false;
  }
}
</script>
