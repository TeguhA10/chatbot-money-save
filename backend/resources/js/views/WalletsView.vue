<template>
  <div class="space-y-5 sm:space-y-6">
    <!-- Header Page -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight flex items-center gap-2.5">
          <span>Dompet & Rekening</span>
          <!-- Active sync indicator badge -->
          <span
            v-if="isSyncing"
            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30 shadow-sm animate-pulse"
          >
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-ping"></span>
            <span>Memperbarui dompet...</span>
          </span>
        </h1>
        <p class="text-xs text-slate-400 mt-0.5">Kelola multi-dompet, rekening bank, & mutasi transfer internal</p>
      </div>

      <div class="flex items-center gap-2 sm:gap-3 w-full sm:w-auto">
        <button
          @click="openTransfer"
          class="flex-1 sm:flex-initial justify-center px-3.5 py-2.5 rounded-xl bg-blue-600/20 border border-blue-500/30 hover:bg-blue-600/30 text-blue-300 font-bold text-xs flex items-center gap-2 cursor-pointer transition active:scale-95"
        >
          <span>{{ authStore.isPro ? '⇄' : '🔒' }}</span>
          <span>Transfer Dana</span>
        </button>
        <button
          @click="openCreateWallet"
          class="flex-1 sm:flex-initial justify-center px-3.5 py-2.5 rounded-xl font-bold text-xs flex items-center gap-2 shadow-lg cursor-pointer transition active:scale-95"
          :class="authStore.isPro ? 'bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 text-slate-950 shadow-emerald-500/20' : 'bg-slate-800 text-amber-300 border border-amber-500/40 hover:bg-slate-750 shadow-amber-500/10'"
        >
          <span v-if="!authStore.isPro">🔒</span>
          <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
          <span class="whitespace-nowrap">{{ authStore.isPro ? 'Dompet Baru' : 'Dompet Baru (Pro)' }}</span>
        </button>
      </div>
    </div>

    <!-- ERROR STATE ALERT WITH RETRY -->
    <div
      v-if="isError"
      class="p-4 sm:p-5 rounded-2xl bg-rose-500/15 border border-rose-500/30 text-rose-300 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-lg animate-in fade-in"
    >
      <div class="flex items-start gap-3">
        <span class="text-2xl shrink-0">⚠️</span>
        <div>
          <h3 class="text-sm font-bold text-rose-200">Gagal Memuat Dompet</h3>
          <p class="text-xs text-rose-300/90 mt-0.5 leading-relaxed">
            {{ errorMessage || 'Terjadi gangguan koneksi saat mengambil daftar rekening dompet Anda.' }}
          </p>
        </div>
      </div>
      <button
        @click="loadWallets"
        class="self-start sm:self-auto px-4 py-2 rounded-xl bg-rose-500 hover:bg-rose-400 text-slate-950 font-bold text-xs flex items-center gap-1.5 transition active:scale-95 cursor-pointer shadow-md"
      >
        <span>🔄 Coba Lagi</span>
      </button>
    </div>

    <!-- SKELETON LOADER (Initial Cold Mount) -->
    <div v-if="isInitialLoading" class="space-y-5 animate-in fade-in duration-150">
      <div class="h-32 rounded-3xl bg-slate-900 border border-slate-800/80 animate-pulse p-6"></div>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        <div v-for="i in 3" :key="i" class="h-48 rounded-2xl bg-slate-900 border border-slate-800/80 animate-pulse p-6"></div>
      </div>
    </div>

    <!-- MAIN WALLETS CONTENT -->
    <template v-else>
      <div :class="isSyncing ? 'opacity-70 transition-opacity pointer-events-none' : 'transition-opacity'" class="space-y-5 sm:space-y-6">
        <!-- TOTAL WEALTH BANNER -->
        <div class="p-6 rounded-3xl bg-gradient-to-r from-slate-900 via-emerald-950/40 to-slate-900 border border-emerald-500/25 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-xl">
          <div>
            <span class="text-xs font-semibold uppercase tracking-wider text-emerald-400">Total Saldo Dompet</span>
            <p class="text-3xl font-black text-white mt-1 tracking-tight">
              {{ formatRupiah(financeStore.totalBalance) }}
            </p>
            <p class="text-xs text-slate-400 mt-1">Tersebar di {{ financeStore.wallets.length }} rekening & dompet digital</p>
          </div>

          <div class="flex items-center gap-2">
            <span class="px-3.5 py-1.5 rounded-xl bg-emerald-500/10 border border-emerald-500/25 text-emerald-300 text-xs font-semibold flex items-center gap-1.5 shadow-sm">
              <span>🛡️</span>
              <span>Saldo Aman & Terenkripsi</span>
            </span>
          </div>
        </div>

        <!-- INLINE FILTER BAR FOR WALLETS (Standardized & UI/UX Friendly) -->
        <div class="p-4 sm:p-5 rounded-2xl bg-slate-900/90 border border-slate-800 space-y-3.5 shadow-sm">
          <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-800/80 pb-3">
            <div class="flex items-center gap-2">
              <span class="text-xs font-bold text-white">Saring Dompet:</span>
              <span class="text-[11px] px-2.5 py-0.5 rounded-full bg-slate-800 text-slate-300 font-mono">
                Menampilkan {{ filteredWallets.length }} dari {{ financeStore.wallets.length }} Dompet
              </span>
            </div>

            <button
              v-if="hasActiveWalletFilters"
              @click="resetWalletFilters"
              type="button"
              class="text-xs text-rose-400 hover:text-rose-300 font-bold underline transition cursor-pointer"
            >
              Reset Filter
            </button>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <!-- Search Input -->
            <div class="relative sm:col-span-1">
              <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs">🔍</span>
              <input
                type="text"
                v-model="walletSearch"
                placeholder="Cari nama dompet (BCA, GoPay, Tunai)..."
                class="w-full pl-9 pr-4 py-2.5 rounded-xl bg-slate-900 border border-slate-800 hover:border-slate-700 text-slate-200 text-xs font-semibold focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/40 shadow-inner transition placeholder:text-slate-500"
              />
            </div>

            <!-- Filter Type -->
            <div>
              <SelectDropdown
                v-model="walletTypeFilter"
                :options="walletTypeOptions"
                placeholder="Semua Tipe Dompet"
                icon="💳"
              />
            </div>

            <!-- Sort By -->
            <div>
              <SelectDropdown
                v-model="walletSort"
                :options="walletSortOptions"
                placeholder="Urutan Dompet"
                icon="↕️"
              />
            </div>
          </div>
        </div>

        <!-- EMPTY STATE: GENUINELY NO WALLETS -->
        <div
          v-if="financeStore.wallets.length === 0"
          class="py-16 px-6 rounded-2xl bg-slate-900/60 border border-slate-800 text-center flex flex-col items-center justify-center space-y-3"
        >
          <div class="w-16 h-16 rounded-2xl bg-slate-800/80 border border-slate-700/60 flex items-center justify-center text-3xl shadow-inner">
            💳
          </div>
          <div class="space-y-1">
            <h3 class="text-sm sm:text-base font-bold text-white">Belum Ada Dompet Terdaftar</h3>
            <p class="text-xs text-slate-400 max-w-sm mx-auto leading-relaxed">
              Tambahkan rekening bank, dompet tunai (Cash), atau e-wallet (GoPay, OVO, ShopeePay) untuk mulai mengelola saldo dan transaksi.
            </p>
          </div>
          <div class="pt-2">
            <button
              @click="showWalletModal = true"
              type="button"
              class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 text-slate-950 font-bold text-xs transition shadow-md shadow-emerald-500/20 cursor-pointer active:scale-95"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
              <span>Tambah Dompet Pertama</span>
            </button>
          </div>
        </div>

        <!-- FILTER EMPTY STATE: FILTER RETURNED 0 MATCHES -->
        <div
          v-else-if="filteredWallets.length === 0"
          class="py-12 px-6 rounded-2xl bg-slate-900/60 border border-slate-800 text-center space-y-3"
        >
          <div class="text-3xl">🔍</div>
          <h3 class="text-sm font-bold text-white">Tidak Ada Dompet yang Cocok</h3>
          <p class="text-xs text-slate-400">
            Tidak ditemukan dompet yang sesuai dengan filter atau kata kunci pencarian Anda.
          </p>
          <button
            @click="resetWalletFilters"
            class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold transition"
          >
            Reset Filter
          </button>
        </div>

        <!-- WALLET CARDS GRID -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
          <div
            v-for="wallet in filteredWallets"
            :key="wallet.id"
            class="p-6 rounded-2xl border relative overflow-hidden flex flex-col justify-between h-48 transition hover:scale-[1.01] shadow-md"
            :class="getCardStyle(wallet.type)"
          >
            <!-- Top bar: Type, Default badge, and Actions -->
            <div class="flex items-center justify-between relative z-10">
              <div class="flex items-center gap-2">
                <span class="text-2xl">{{ getWalletIcon(wallet.type) }}</span>
                <span class="text-xs font-bold uppercase tracking-wider text-slate-300">{{ wallet.type }}</span>
                <span
                  v-if="wallet.is_default"
                  class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40"
                >
                  Utama
                </span>
              </div>
              <div class="flex items-center gap-1.5">
                <button
                  @click.stop="openEditWallet(wallet)"
                  title="Edit Dompet"
                  class="p-1.5 rounded-lg bg-slate-800/80 hover:bg-slate-700 text-slate-300 hover:text-white transition cursor-pointer"
                >
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                </button>
                <button
                  v-if="!wallet.is_default && financeStore.wallets.length > 1"
                  @click.stop="confirmDeleteWallet(wallet)"
                  title="Hapus Dompet"
                  class="p-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500/25 text-rose-400 hover:text-rose-300 transition cursor-pointer"
                >
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
              </div>
            </div>

            <!-- Middle: Wallet Name -->
            <div class="relative z-10">
              <h3 class="text-lg font-bold text-white tracking-wide truncate">{{ wallet.name }}</h3>
              <p class="text-xs text-slate-400 mt-0.5">Rekening Terhubung</p>
            </div>

            <!-- Bottom: Balance -->
            <div class="relative z-10 pt-2 border-t border-white/10 flex items-center justify-between">
              <span class="text-[11px] text-slate-400">Saldo Saat Ini</span>
              <span class="text-lg font-black text-white tracking-tight">{{ formatRupiah(wallet.balance) }}</span>
            </div>
          </div>
        </div>

        <!-- TRANSFER HISTORY PREVIEW WITH INLINE SEARCH FILTER -->
        <div class="p-5 sm:p-6 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-sm space-y-4">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-800/80 pb-3.5">
            <div>
              <h2 class="text-sm sm:text-base font-bold text-white">Riwayat Mutasi Transfer</h2>
              <p class="text-xs text-slate-400">Perpindahan dana internal antar dompet</p>
            </div>
            <RouterLink to="/transactions?type=TRANSFER" class="text-xs font-semibold text-blue-400 hover:underline">
              Lihat Semua di Pusat Transaksi ➔
            </RouterLink>
          </div>

          <!-- Filter for Transfer History -->
          <div class="relative">
            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs">🔍</span>
            <input
              type="text"
              v-model="transferSearch"
              placeholder="Cari catatan mutasi transfer..."
              class="w-full pl-9 pr-4 py-2 rounded-xl bg-slate-900 border border-slate-800 hover:border-slate-700 text-slate-200 text-xs font-semibold focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/40 shadow-inner transition placeholder:text-slate-500"
            />
          </div>

          <div v-if="filteredTransferTransactions.length === 0" class="py-10 text-center text-slate-500 text-xs">
            {{ transferSearch ? 'Tidak ada riwayat transfer yang cocok dengan pencarian.' : 'Belum ada riwayat transfer antar dompet.' }}
          </div>

          <div v-else class="space-y-2.5">
            <div
              v-for="item in filteredTransferTransactions"
              :key="item.id"
              class="p-4 rounded-xl bg-slate-950/60 border border-slate-800/80 flex items-center justify-between text-xs hover:bg-slate-900/50 transition"
            >
              <div class="flex items-center gap-3 min-w-0">
                <span class="w-9 h-9 rounded-xl bg-blue-500/10 text-blue-400 font-bold flex items-center justify-center shrink-0 border border-blue-500/20">
                  ⇄
                </span>
                <div class="min-w-0">
                  <p class="font-bold text-slate-200 truncate">{{ item.description }}</p>
                  <p class="text-[11px] text-slate-400 mt-0.5">{{ formatDate(item.transaction_date) }}</p>
                </div>
              </div>
              <div class="text-right shrink-0">
                <p class="font-black text-blue-400">{{ formatRupiah(item.amount) }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Modals -->
    <TransferModal
      :isOpen="showTransferModal"
      @close="showTransferModal = false"
      @transferred="loadWallets(true)"
    />

    <WalletModal
      :isOpen="showWalletModal"
      :wallet="selectedWalletForEdit"
      @close="showWalletModal = false; selectedWalletForEdit = null"
      @saved="loadWallets(true)"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useAuthStore } from '../stores/auth';
import { useFinanceStore } from '../stores/finance';
import { useVaultStore } from '../stores/vault';
import { useToastStore } from '../stores/toast';
import { formatRupiah as rawFormatRupiah, formatDate } from '../utils/formatters';
import TransferModal from '../components/modals/TransferModal.vue';
import WalletModal from '../components/modals/WalletModal.vue';
import SelectDropdown from '../components/common/SelectDropdown.vue';

const authStore = useAuthStore();
const financeStore = useFinanceStore();
const vaultStore = useVaultStore();
const toastStore = useToastStore();
const selectedWalletForEdit = ref(null);

function openTransfer() {
  if (!authStore.isPro) {
    toastStore.warning('Fitur transfer saldo multi-dompet eksklusif untuk HematWoy Pro!');
    authStore.openPricing('monthly');
    return;
  }
  showTransferModal.value = true;
}

function openCreateWallet() {
  if (!authStore.isPro) {
    toastStore.warning('Penambahan multi-dompet eksklusif untuk HematWoy Pro!');
    authStore.openPricing('monthly');
    return;
  }
  selectedWalletForEdit.value = null;
  showWalletModal.value = true;
}

function openEditWallet(wallet) {
  if (!authStore.isPro) {
    toastStore.warning('Fitur edit dompet eksklusif untuk HematWoy Pro!');
    authStore.openPricing('monthly');
    return;
  }
  selectedWalletForEdit.value = wallet;
  showWalletModal.value = true;
}

async function confirmDeleteWallet(wallet) {
  if (!authStore.isPro) {
    toastStore.warning('Fitur hapus dompet eksklusif untuk HematWoy Pro!');
    authStore.openPricing('monthly');
    return;
  }
  if (wallet.is_default) {
    toastStore.warning('Dompet utama tidak dapat dihapus.');
    return;
  }
  const confirmed = window.confirm(`Apakah Anda yakin ingin menghapus dompet "${wallet.name}"? Sisa saldo akan otomatis diamankan ke dompet utama.`);
  if (!confirmed) return;

  try {
    await financeStore.deleteWallet(wallet.id);
    toastStore.success(`Dompet "${wallet.name}" berhasil dihapus.`);
    await loadWallets(true);
  } catch (err) {
    toastStore.error(err.response?.data?.message || 'Gagal menghapus dompet.');
  }
}

const walletTypeOptions = [
  { value: 'ALL', label: 'Semua Tipe Dompet', icon: '💳' },
  { value: 'BANK', label: 'Rekening Bank (BCA, Mandiri, dll)', icon: '🏦' },
  { value: 'EWALLET', label: 'E-Wallet (GoPay, OVO, ShopeePay)', icon: '📱' },
  { value: 'CASH', label: 'Tunai / Kas Fisik', icon: '💵' },
  { value: 'OTHER', label: 'Lainnya / Rekening Khusus', icon: '📁' },
];

const walletSortOptions = [
  { value: 'BALANCE_DESC', label: 'Saldo Tertinggi', icon: '📈' },
  { value: 'BALANCE_ASC', label: 'Saldo Terendah', icon: '📉' },
  { value: 'NAME_ASC', label: 'Nama Dompet (A-Z)', icon: '🔤' },
];

const isInitialLoading = ref(!financeStore.hasLoadedWallets);
const isSyncing = ref(false);
const isError = ref(false);
const errorMessage = ref('');

// Wallet Filter States
const walletSearch = ref('');
const walletTypeFilter = ref('ALL');
const walletSort = ref('BALANCE_DESC');

// Transfer Filter State
const transferSearch = ref('');

function formatRupiah(val) {
  if (vaultStore.isMasked) return 'Rp ••••••••';
  return rawFormatRupiah(val);
}

const showTransferModal = ref(false);
const showWalletModal = ref(false);

const hasActiveWalletFilters = computed(() => {
  return !!(walletSearch.value.trim() || walletTypeFilter.value !== 'ALL');
});

function resetWalletFilters() {
  walletSearch.value = '';
  walletTypeFilter.value = 'ALL';
  walletSort.value = 'BALANCE_DESC';
}

const filteredWallets = computed(() => {
  let list = [...(financeStore.wallets || [])];

  if (walletSearch.value.trim()) {
    const q = walletSearch.value.trim().toLowerCase();
    list = list.filter(w => (w.name || '').toLowerCase().includes(q));
  }

  if (walletTypeFilter.value !== 'ALL') {
    list = list.filter(w => w.type === walletTypeFilter.value);
  }

  list.sort((a, b) => {
    if (walletSort.value === 'BALANCE_DESC') {
      return (Number(b.balance) || 0) - (Number(a.balance) || 0);
    }
    if (walletSort.value === 'BALANCE_ASC') {
      return (Number(a.balance) || 0) - (Number(b.balance) || 0);
    }
    if (walletSort.value === 'NAME_ASC') {
      return (a.name || '').localeCompare(b.name || '');
    }
    return 0;
  });

  return list;
});

const filteredTransferTransactions = computed(() => {
  const transfers = financeStore.transactions.filter((t) => t.type === 'TRANSFER');
  if (!transferSearch.value.trim()) {
    return transfers.slice(0, 5);
  }
  const q = transferSearch.value.trim().toLowerCase();
  return transfers.filter(t => (t.description || '').toLowerCase().includes(q)).slice(0, 5);
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

async function loadWallets(force = false) {
  if (!financeStore.hasLoadedWallets) {
    isInitialLoading.value = true;
  } else {
    isInitialLoading.value = false;
    isSyncing.value = true;
  }

  isError.value = false;
  errorMessage.value = '';

  try {
    await Promise.all([
      financeStore.fetchWallets(force),
      financeStore.fetchTransactions({ limit: 20 }, force),
    ]);
  } catch (err) {
    if (!financeStore.hasLoadedWallets) {
      isError.value = true;
      errorMessage.value = err.response?.data?.message || err.message || 'Gagal mengambil data dompet.';
    }
  } finally {
    isInitialLoading.value = false;
    isSyncing.value = false;
  }
}

onMounted(() => {
  loadWallets();
});
</script>
