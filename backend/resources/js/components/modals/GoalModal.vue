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
        <h2 class="text-xl font-bold text-white mb-1">{{ isEdit ? 'Edit Target Tabungan' : 'Target Tabungan Baru' }}</h2>
        <p class="text-xs text-slate-400">{{ isEdit ? 'Ubah nama, nominal, atau target tanggal impian Anda' : 'Wujudkan impian finansial Anda secara terukur' }}</p>
      </div>

      <form @submit.prevent="handleSubmit" class="space-y-4 overflow-y-auto pr-1 flex-1">
        <!-- Goal Name -->
        <div>
          <label class="block text-xs font-medium text-slate-300 mb-1.5">Nama Target / Impian</label>
          <input
            type="text"
            v-model="form.name"
            required
            placeholder="Contoh: Beli Mobil, Dana Darurat, Umroh"
            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-800 hover:border-slate-700 text-slate-200 text-xs font-semibold focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500/40 shadow-inner transition placeholder:text-slate-500"
          />
        </div>

        <!-- Target Amount -->
        <div>
          <div class="flex items-center justify-between mb-1.5">
            <label class="block text-xs font-medium text-slate-300">Nominal Target (Rp)</label>
            <span v-if="form.target_amount" class="text-xs font-semibold text-teal-400">
              Rp {{ Number(form.target_amount).toLocaleString('id-ID') }}
            </span>
          </div>
          <div class="relative">
            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm font-semibold">Rp</span>
            <input
              type="text"
              inputmode="numeric"
              :value="formattedTargetAmount"
              @input="handleTargetAmountInput"
              required
              placeholder="0"
              class="w-full pl-11 pr-4 py-2.5 rounded-xl bg-slate-900 border border-slate-800 hover:border-slate-700 text-white font-semibold text-lg focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500/40 shadow-inner transition"
            />
          </div>
          <!-- Quick Amount Buttons -->
          <div class="flex items-center gap-1.5 mt-2">
            <span class="text-[11px] text-slate-500 font-medium mr-1">Cepat:</span>
            <button
              v-for="preset in [1000000, 5000000, 10000000, 20000000]"
              :key="preset"
              type="button"
              @click="addPreset(preset)"
              class="text-[11px] px-2.5 py-1 rounded-lg bg-slate-800/80 hover:bg-slate-750 text-slate-300 hover:text-white border border-slate-700/60 transition"
            >
              +{{ (preset / 1000000) }}jt
            </button>
          </div>
        </div>

        <!-- Target Date -->
        <div>
          <label class="block text-xs font-medium text-slate-300 mb-1.5">Target Tanggal Pencapaian (Opsional)</label>
          <DatePicker
            v-model="form.target_date"
            placeholder="Pilih target tanggal (opsional)"
            :clearable="true"
          />
          <p class="text-[11px] text-slate-500 mt-1">Membantu menghitung estimasi setoran rutin yang dibutuhkan</p>
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
            class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-teal-500 to-emerald-500 hover:from-teal-400 hover:to-emerald-400 text-slate-950 font-bold text-sm transition disabled:opacity-50"
          >
            {{ submitting ? 'Menyimpan...' : (isEdit ? 'Simpan Perubahan' : 'Buat Target') }}
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
import DatePicker from '../common/DatePicker.vue';

const props = defineProps({
  isOpen: Boolean,
  goal: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(['close', 'saved']);
const financeStore = useFinanceStore();
const toastStore = useToastStore();

const submitting = ref(false);
const isEdit = computed(() => !!props.goal);

const form = ref({
  name: '',
  target_amount: null,
  target_date: '',
});

watch(
  () => props.goal,
  (g) => {
    if (g) {
      form.value = {
        name: g.name || '',
        target_amount: g.target_amount ?? null,
        target_date: g.target_date || '',
      };
    } else {
      form.value = { name: '', target_amount: null, target_date: '' };
    }
  },
  { immediate: true }
);

const formattedTargetAmount = computed(() => {
  if (!form.value.target_amount && form.value.target_amount !== 0) return '';
  return Number(form.value.target_amount).toLocaleString('id-ID');
});

function handleTargetAmountInput(e) {
  const digits = e.target.value.replace(/\D/g, '');
  form.value.target_amount = digits ? parseInt(digits, 10) : null;
  e.target.value = form.value.target_amount ? Number(form.value.target_amount).toLocaleString('id-ID') : '';
}

function addPreset(val) {
  form.value.target_amount = (form.value.target_amount || 0) + val;
}

async function handleSubmit() {
  if (!form.value.name.trim() || !form.value.target_amount) {
    toastStore.warning('Masukkan nama target dan nominal yang valid.');
    return;
  }
  submitting.value = true;
  try {
    if (isEdit.value) {
      await financeStore.updateGoal(props.goal.id, {
        name: form.value.name.trim(),
        target_amount: form.value.target_amount,
        target_date: form.value.target_date || null,
      });
      toastStore.success(`Target tabungan "${form.value.name}" berhasil diperbarui.`);
    } else {
      await financeStore.createGoal({
        name: form.value.name.trim(),
        target_amount: form.value.target_amount,
        target_date: form.value.target_date || null,
      });
      toastStore.success(`Target tabungan "${form.value.name}" berhasil dibuat.`);
    }
    form.value = { name: '', target_amount: null, target_date: '' };
    emit('saved');
    emit('close');
  } catch (err) {
    toastStore.error(err.response?.data?.message || 'Gagal menyimpan target tabungan.');
  } finally {
    submitting.value = false;
  }
}
</script>
