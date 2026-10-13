<template>
  <div class="space-y-6">
    <!-- Header Page -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-black text-white tracking-tight">Dompet & Rekening</h1>
        <p class="text-xs text-slate-400 mt-0.5">Kelola multi-dompet & mutasi transfer dana</p>
      </div>

      <div class="flex items-center gap-3">
        <button
          @click="showTransferModal = true"
          class="px-4 py-2.5 rounded-xl bg-blue-600/20 border border-blue-500/30 hover:bg-blue-600/30 text-blue-300 font-bold text-xs flex items-center gap-1.5 cursor-pointer transition active:scale-95"
        >
          <span>⇄</span> Transfer Dana
        </button>
        <button
          @click="showWalletModal = true"
          class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 text-slate-950 font-bold text-xs flex items-center gap-1.5 shadow-lg shadow-emerald-500/20 cursor-pointer transition active:scale-95"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
          + Tambah Dompet
        </button>
      </div>
    </div>

    <!-- TOTAL WEALTH BANNER -->
    <div class="p-6 rounded-3xl bg-gradient-to-r from-slate-900 via-emerald-950/40 to-slate-900 border border-emerald-500/20 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-xl">
      <div>
        <span class="text-xs font-semibold uppercase tracking-wider text-emerald-400">Total Kekayaan Bersih</span>
        <p class="text-3xl font-black text-white mt-1 tracking-tight">
          {{ formatRupiah(financeStore.totalBalance) }}
        </p>
        <p class="text-xs text-slate-400 mt-1">Tersebar di {{ financeStore.wallets.length }} rekening & dompet digital</p>
      </div>

      <div class="flex items-center gap-2">
        <span class="px-3 py-1.5 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs font-semibold">
          🛡️ Saldo Terenkripsi
        </span>
      </div>
    </div>

    <!-- WALLET CARDS GRID -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      <div
        v-for="wallet in financeStore.wallets"
        :key="wallet.id"
        class="p-6 rounded-2xl border relative overflow-hidden flex flex-col justify-between h-48 transition hover:scale-[1.01]"
        :class="getCardStyle(wallet.type)"
      >
        <!-- Top bar: Type and Default badge -->
        <div class="flex items-center justify-between relative z-10">
          <div class="flex items-center gap-2">
            <span class="text-2xl">{{ getWalletIcon(wallet.type) }}</span>
            <span class="text-xs font-bold uppercase tracking-wider text-slate-300">{{ wallet.type }}</span>
          </div>
          <span
            v-if="wallet.is_default"
            class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40"
          >
            Utama
          </span>
        </div>

        <!-- Middle: Wallet Name -->
        <div class="relative z-10">
          <h3 class="text-lg font-bold text-white tracking-wide">{{ wallet.name }}</h3>
          <p class="text-xs text-slate-400">Rekening Terhubung</p>
        </div>

        <!-- Bottom: Balance -->
        <div class="relative z-10 pt-2 border-t border-white/10 flex items-center justify-between">
          <span class="text-[11px] text-slate-400">Saldo Saat Ini</span>
          <span class="text-lg font-black text-white tracking-tight">{{ formatRupiah(wallet.balance) }}</span>
        </div>
      </div>
    </div>

    <!-- TRANSFER HISTORY PREVIEW -->
    <div class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h2 class="text-base font-bold text-white">Riwayat Mutasi Transfer</h2>
          <p class="text-xs text-slate-400">Perpindahan dana internal antar dompet</p>
        </div>
        <RouterLink to="/transactions?type=TRANSFER" class="text-xs font-semibold text-blue-400 hover:underline">
          Lihat Semua ➔
        </RouterLink>
      </div>

      <div v-if="transferTransactions.length === 0" class="py-8 text-center text-slate-500 text-xs">
        Belum ada riwayat transfer antar dompet.
      </div>

      <div v-else class="space-y-2.5">
        <div
          v-for="item in transferTransactions"
          :key="item.id"
          class="p-3.5 rounded-xl bg-slate-950/60 border border-slate-800/80 flex items-center justify-between text-xs"
        >
          <div class="flex items-center gap-3">
            <span class="p-2 rounded-lg bg-blue-500/10 text-blue-400 font-bold">⇄</span>
            <div>
              <p class="font-semibold text-slate-200">{{ item.description }}</p>
              <p class="text-[11px] text-slate-400">{{ formatDate(item.transaction_date) }}</p>
            </div>
          </div>
          <span class="font-bold text-blue-400">{{ formatRupiah(item.amount) }}</span>
        </div>
      </div>
    </div>

    <!-- Modals -->
    <TransferModal
      :isOpen="showTransferModal"
      @close="showTransferModal = false"
      @transferred="loadWallets"
    />

    <WalletModal
      :isOpen="showWalletModal"
      @close="showWalletModal = false"
      @saved="loadWallets"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useFinanceStore } from '../stores/finance';
import { formatRupiah, formatDate } from '../utils/formatters';
import TransferModal from '../components/modals/TransferModal.vue';
import WalletModal from '../components/modals/WalletModal.vue';

const financeStore = useFinanceStore();

const showTransferModal = ref(false);
const showWalletModal = ref(false);

const transferTransactions = computed(() => {
  return financeStore.transactions.filter((t) => t.type === 'TRANSFER').slice(0, 5);
});

function getWalletIcon(type) {
  if (type === 'BANK') return '🏦';
  if (type === 'EWALLET') return '📱';
  if (type === 'CASH') return '💵';
  return '📁';
}

function getCardStyle(type) {
  if (type === 'BANK') {
    return 'bg-gradient-to-br from-slate-900 via-blue-950/30 to-slate-900 border-blue-500/30 shadow-lg shadow-blue-500/5';
  }
  if (type === 'EWALLET') {
    return 'bg-gradient-to-br from-slate-900 via-purple-950/30 to-slate-900 border-purple-500/30 shadow-lg shadow-purple-500/5';
  }
  if (type === 'CASH') {
    return 'bg-gradient-to-br from-slate-900 via-emerald-950/30 to-slate-900 border-emerald-500/30 shadow-lg shadow-emerald-500/5';
  }
  return 'bg-gradient-to-br from-slate-900 via-slate-800/40 to-slate-900 border-slate-700 shadow-lg';
}

async function loadWallets() {
  await Promise.allSettled([
    financeStore.fetchWallets(),
    financeStore.fetchTransactions({ limit: 20 }),
  ]);
}

onMounted(() => {
  loadWallets();
});
</script>
