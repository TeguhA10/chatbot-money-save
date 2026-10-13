<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm animate-fade-in">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md p-6 shadow-2xl relative">
      <button
        @click="$emit('close')"
        class="absolute top-5 right-5 text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-800 transition"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>

      <h2 class="text-xl font-bold text-white mb-1">Target Tabungan Baru</h2>
      <p class="text-xs text-slate-400 mb-6">Wujudkan impian finansial Anda secara terukur</p>

      <form @submit.prevent="handleSubmit" class="space-y-4">
        <!-- Goal Name -->
        <div>
          <label class="block text-xs font-medium text-slate-300 mb-1.5">Nama Target / Impian</label>
          <input
            type="text"
            v-model="form.name"
            required
            placeholder="Contoh: Beli Mobil, Dana Darurat, Umroh"
            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white text-sm focus:outline-none focus:border-teal-500"
          />
        </div>

        <!-- Target Amount -->
        <div>
          <label class="block text-xs font-medium text-slate-300 mb-1.5">Nominal Target (Rp)</label>
          <div class="relative">
            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm font-semibold">Rp</span>
            <input
              type="number"
              v-model.number="form.target_amount"
              required
              min="50000"
              step="50000"
              placeholder="0"
              class="w-full pl-11 pr-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white font-semibold text-lg focus:outline-none focus:border-teal-500"
            />
          </div>
        </div>

        <!-- Target Date -->
        <div>
          <label class="block text-xs font-medium text-slate-300 mb-1.5">Target Tanggal Pencapaian (Opsional)</label>
          <input
            type="date"
            v-model="form.target_date"
            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white text-sm focus:outline-none focus:border-teal-500"
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
            {{ submitting ? 'Menyimpan...' : 'Buat Target' }}
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
  target_amount: null,
  target_date: '',
});

async function handleSubmit() {
  if (!form.value.name || !form.value.target_amount) return;
  submitting.value = true;
  try {
    await financeStore.createGoal({
      name: form.value.name,
      target_amount: form.value.target_amount,
      target_date: form.value.target_date || null,
    });
    form.value = { name: '', target_amount: null, target_date: '' };
    emit('saved');
    emit('close');
  } catch (err) {
    alert(err.response?.data?.message || 'Gagal membuat target tabungan.');
  } finally {
    submitting.value = false;
  }
}
</script>
