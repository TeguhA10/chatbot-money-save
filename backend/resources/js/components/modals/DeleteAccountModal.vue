<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-950/85 backdrop-blur-md animate-fade-in overflow-y-auto">
    <div class="bg-slate-900 border border-rose-500/30 rounded-3xl w-full max-w-md p-5 sm:p-6 shadow-2xl shadow-rose-950/40 relative my-auto max-h-[92vh] flex flex-col">
      <!-- Close button -->
      <button
        @click="closeModal"
        :disabled="isDeleting"
        class="absolute top-5 right-5 text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-800 transition z-10 disabled:opacity-50"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>

      <!-- Danger Header -->
      <div class="flex items-center gap-3 mb-4 shrink-0">
        <div class="w-12 h-12 rounded-2xl bg-rose-500/15 border border-rose-500/30 flex items-center justify-center text-2xl text-rose-400 shrink-0 shadow-lg shadow-rose-500/10">
          ⚠️
        </div>
        <div>
          <h2 class="text-lg sm:text-xl font-black text-white">Zona Bahaya: Hapus Seluruh Data</h2>
          <p class="text-xs text-rose-400 font-medium">Hak Penghapusan Data Permanen (GDPR)</p>
        </div>
      </div>

      <!-- Warning Description Box -->
      <div class="space-y-3.5 text-xs text-slate-300 overflow-y-auto pr-1 flex-1">
        <div class="p-3.5 rounded-2xl bg-rose-950/30 border border-rose-500/20 text-slate-300 space-y-2 leading-relaxed">
          <p class="font-bold text-rose-300">Tindakan ini bersifat PERMANEN dan tidak dapat dibatalkan:</p>
          <ul class="list-disc list-inside space-y-1 text-slate-400 text-[11px]">
            <li>Seluruh riwayat transaksi pengeluaran & pemasukan</li>
            <li>Seluruh saldo dompet kas, bank, dan e-wallet</li>
            <li>Seluruh target tabungan & plafon anggaran</li>
            <li>Master PIN & kredensial brankas Zero-Knowledge</li>
            <li>Sesi login web akan langsung diakhiri</li>
          </ul>
        </div>

        <!-- Active Pro Subscription Warning Banner -->
        <div v-if="authStore.isPro" class="p-3.5 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-amber-200 space-y-1.5 leading-relaxed">
          <div class="flex items-center gap-2 font-bold text-amber-400 text-xs">
            <span>⭐</span>
            <span>PERINGATAN: PAKET PRO AKTIF</span>
          </div>
          <p class="text-[11px] text-amber-200/90 leading-relaxed">
            Akun Anda memiliki langganan Pro aktif<span v-if="formattedExpiry"> s/d <strong class="text-white">{{ formattedExpiry }}</strong></span>.
            Menghapus akun akan <strong>menghanguskan seluruh sisa masa aktif langganan</strong> Anda tanpa pengembalian dana (non-refundable).
          </p>
        </div>

        <p class="text-[11px] text-slate-400 leading-relaxed">
          Ketik kata <span class="font-bold text-rose-400 font-mono bg-rose-950/60 px-1.5 py-0.5 rounded border border-rose-500/30">HAPUS</span> di bawah ini untuk mengonfirmasi penghapusan:
        </p>

        <!-- Confirmation Input -->
        <div>
          <input
            v-model="confirmationInput"
            type="text"
            placeholder="Ketik HAPUS"
            :disabled="isDeleting"
            class="w-full px-4 py-3 rounded-xl bg-slate-950 border border-slate-800 text-white font-mono text-center tracking-widest text-sm focus:outline-none focus:border-rose-500 focus:ring-1 focus:ring-rose-500 transition"
          />
        </div>

        <!-- Error Message -->
        <div v-if="errorMessage" class="p-2.5 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs text-center font-medium">
          {{ errorMessage }}
        </div>

        <!-- Action Buttons -->
        <div class="pt-2 flex gap-2.5 shrink-0">
          <button
            type="button"
            @click="closeModal"
            :disabled="isDeleting"
            class="flex-1 py-2.5 px-4 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs transition cursor-pointer disabled:opacity-50"
          >
            Batal
          </button>
          <button
            type="button"
            @click="handleDeleteAccount"
            :disabled="!isConfirmed || isDeleting"
            class="flex-1 py-2.5 px-4 rounded-xl font-bold text-xs transition flex items-center justify-center gap-1.5 cursor-pointer shadow-lg active:scale-95"
            :class="isConfirmed && !isDeleting
              ? 'bg-gradient-to-r from-rose-600 to-red-600 hover:from-rose-500 hover:to-red-500 text-white shadow-rose-600/30'
              : 'bg-slate-800 text-slate-500 cursor-not-allowed border border-slate-700/50'"
          >
            <span v-if="isDeleting" class="animate-spin text-sm">⏳</span>
            <span>{{ isDeleting ? 'Memusnahkan Data...' : 'Hapus Seluruh Data Saya' }}</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import api from '../../services/api';
import { useAuthStore } from '../../stores/auth';
import { useFinanceStore } from '../../stores/finance';

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['close']);

const router = useRouter();
const authStore = useAuthStore();
const financeStore = useFinanceStore();

const confirmationInput = ref('');
const isDeleting = ref(false);
const errorMessage = ref('');

const isConfirmed = computed(() => {
  return confirmationInput.value.trim().toUpperCase() === 'HAPUS';
});

const formattedExpiry = computed(() => {
  const exp = authStore.user?.subscription_expires_at || authStore.subscription?.expires_at;
  if (!exp) return null;
  try {
    return new Date(exp).toLocaleDateString('id-ID', {
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    });
  } catch {
    return null;
  }
});

function closeModal() {
  if (isDeleting.value) return;
  confirmationInput.value = '';
  errorMessage.value = '';
  emit('close');
}

async function handleDeleteAccount() {
  if (!isConfirmed.value || isDeleting.value) return;

  isDeleting.value = true;
  errorMessage.value = '';

  try {
    const res = await api.delete('/user/account', {
      data: { confirmation: 'HAPUS' },
    });

    if (financeStore && typeof financeStore.clearCache === 'function') {
      financeStore.clearCache();
    }
    authStore.logout();

    emit('close');
    alert(res.data?.message || 'Seluruh data Anda telah berhasil dihapus permanen.');
    router.push('/');
  } catch (err) {
    errorMessage.value = err.response?.data?.message || 'Gagal menghapus data. Silakan coba lagi.';
  } finally {
    isDeleting.value = false;
  }
}
</script>
