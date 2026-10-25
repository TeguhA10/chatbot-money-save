<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-950/80 backdrop-blur-sm animate-fade-in overflow-y-auto">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl w-full max-w-md p-5 sm:p-6 shadow-2xl relative my-auto max-h-[92vh] flex flex-col">
      <button
        @click="$emit('close')"
        class="absolute top-5 right-5 text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-800 transition z-10 cursor-pointer"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>

      <div class="mb-4 shrink-0">
        <h2 class="text-xl font-bold text-white mb-1">Pasang Limit Anggaran</h2>
        <p class="text-xs text-slate-400">Tentukan nama dan batas plafon total pengeluaran bulanan Anda</p>
      </div>

      <form @submit.prevent="handleSubmit" class="space-y-4 overflow-y-auto pr-1 flex-1">
        <!-- Description (Mandatory) -->
        <div>
          <label class="block text-xs font-medium text-slate-300 mb-1.5">
            Deskripsi / Nama Anggaran <span class="text-rose-400 font-bold">*</span>
          </label>
          <input
            type="text"
            v-model="form.description"
            required
            placeholder="Contoh: Batas Belanja & Pengeluaran Bulanan"
            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-800 hover:border-slate-700 text-slate-200 text-xs font-semibold focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500/40 shadow-inner transition placeholder:text-slate-500"
          />
        </div>

        <!-- Limit Amount -->
        <div>
          <div class="flex items-center justify-between mb-1.5">
            <label class="block text-xs font-medium text-slate-300">
              Batas Pengeluaran Bulanan (Rp) <span class="text-rose-400 font-bold">*</span>
            </label>
            <span v-if="form.limit_amount" class="text-xs font-semibold text-amber-400">
              Rp {{ Number(form.limit_amount).toLocaleString('id-ID') }}
            </span>
          </div>
          <div class="relative">
            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm font-semibold">Rp</span>
            <input
              type="text"
              inputmode="numeric"
              :value="formattedLimitAmount"
              @input="handleLimitAmountInput"
              required
              placeholder="0"
              class="w-full pl-11 pr-4 py-2.5 rounded-xl bg-slate-900 border border-slate-800 hover:border-slate-700 text-white font-semibold text-lg focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500/40 shadow-inner transition"
            />
          </div>
          <!-- Quick Amount Buttons -->
          <div class="flex items-center gap-1.5 mt-2">
            <span class="text-[11px] text-slate-500 font-medium mr-1">Cepat:</span>
            <button
              v-for="preset in [500000, 1000000, 2000000, 5000000]"
              :key="preset"
              type="button"
              @click="addPreset(preset)"
              class="text-[11px] px-2.5 py-1 rounded-lg bg-slate-800/80 hover:bg-slate-750 text-slate-300 hover:text-white border border-slate-700/60 transition cursor-pointer"
            >
              +{{ (preset / 1000000) >= 1 ? (preset / 1000000) + 'jt' : (preset / 1000) + 'rb' }}
            </button>
          </div>
          <p class="text-[11px] text-slate-500 mt-1">Sistem akan memberi alert saat pemakaian mencapai 80%</p>
        </div>

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
            class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-slate-950 font-bold text-sm transition cursor-pointer disabled:opacity-50"
          >
            {{ submitting ? 'Menyimpan...' : 'Simpan Anggaran' }}
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
});

const emit = defineEmits(['close', 'saved']);
const financeStore = useFinanceStore();
const toastStore = useToastStore();

const submitting = ref(false);
const form = ref({
  description: '',
  limit_amount: null,
});

watch(
  () => [props.isOpen, financeStore.budgets],
  ([isOpen]) => {
    if (isOpen) {
      const existing = financeStore.budgets && financeStore.budgets.length > 0 ? financeStore.budgets[0] : null;
      if (existing) {
        form.value = {
          description: existing.description || 'Batas Pengeluaran Bulanan',
          limit_amount: existing.limit_amount || null,
        };
      } else {
        form.value = {
          description: '',
          limit_amount: null,
        };
      }
    }
  },
  { immediate: true }
);

const formattedLimitAmount = computed(() => {
  if (!form.value.limit_amount && form.value.limit_amount !== 0) return '';
  return Number(form.value.limit_amount).toLocaleString('id-ID');
});

function handleLimitAmountInput(e) {
  const digits = e.target.value.replace(/\D/g, '');
  form.value.limit_amount = digits ? parseInt(digits, 10) : null;
  e.target.value = form.value.limit_amount ? Number(form.value.limit_amount).toLocaleString('id-ID') : '';
}

function addPreset(val) {
  form.value.limit_amount = (form.value.limit_amount || 0) + val;
}

async function handleSubmit() {
  if (!form.value.description || !form.value.description.trim()) {
    toastStore.warning('Deskripsi anggaran wajib diisi.');
    return;
  }
  if (!form.value.limit_amount || form.value.limit_amount <= 0) {
    toastStore.warning('Tentukan batas anggaran bulanan yang valid.');
    return;
  }
  submitting.value = true;
  try {
    await financeStore.createBudget({
      description: form.value.description.trim(),
      limit_amount: form.value.limit_amount,
    });
    toastStore.success('Batas anggaran berhasil dipasang.');
    emit('saved');
    emit('close');
  } catch (err) {
    toastStore.error(err.response?.data?.message || 'Gagal menyimpan anggaran.');
  } finally {
    submitting.value = false;
  }
}
</script>
