<template>
  <div class="space-y-5 sm:space-y-6">
    <!-- Header Page -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight flex items-center gap-2.5">
          <span>Pusat Transaksi</span>
          <!-- Active sync indicator badge -->
          <span
            v-if="isSyncing"
            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30 shadow-sm animate-pulse"
          >
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-ping"></span>
            <span>Memuat data...</span>
          </span>
        </h1>
        <p class="text-xs text-slate-400 mt-0.5">Kelola, cari, dan tinjau seluruh riwayat mutasi keuangan Anda</p>
      </div>

      <button
        @click="openCreateModal"
        class="self-start sm:self-auto px-4 py-2.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 text-slate-950 font-bold text-xs flex items-center gap-2 shadow-lg shadow-emerald-500/20 cursor-pointer transition active:scale-95"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
        <span>Catat Transaksi Baru</span>
      </button>
    </div>

    <!-- ERROR STATE ALERT WITH RETRY -->
    <div
      v-if="isError"
      class="p-4 sm:p-5 rounded-2xl bg-rose-500/15 border border-rose-500/30 text-rose-300 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-lg animate-in fade-in"
    >
      <div class="flex items-start gap-3">
        <span class="text-2xl shrink-0">⚠️</span>
        <div>
          <h3 class="text-sm font-bold text-rose-200">Gagal Memuat Transaksi</h3>
          <p class="text-xs text-rose-300/90 mt-0.5 leading-relaxed">
            {{ errorMessage || 'Terjadi gangguan koneksi saat mengambil daftar transaksi.' }}
          </p>
        </div>
      </div>
      <button
        @click="applyFilters"
        class="self-start sm:self-auto px-4 py-2 rounded-xl bg-rose-500 hover:bg-rose-400 text-slate-950 font-bold text-xs flex items-center gap-1.5 transition active:scale-95 cursor-pointer shadow-md"
      >
        <span>🔄 Coba Lagi</span>
      </button>
    </div>

    <!-- FILTER BAR & LIVE SEARCH -->
    <div class="p-4 sm:p-5 rounded-2xl bg-slate-900/90 border border-slate-800 space-y-3.5 shadow-sm">
      <!-- Date Presets & Filter Row -->
      <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-800/80 pb-3">
        <div class="flex items-center gap-1.5 flex-wrap">
          <span class="text-[11px] text-slate-400 font-semibold mr-1">Rentang:</span>
          <button
            type="button"
            @click="setDatePreset('all')"
            :class="activeDatePreset === 'all' ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/40 font-bold' : 'bg-slate-950 text-slate-400 border-slate-800 hover:text-slate-200'"
            class="text-[11px] px-3 py-1 rounded-xl border transition cursor-pointer"
          >
            Semua
          </button>
          <button
            type="button"
            @click="setDatePreset('today')"
            :class="activeDatePreset === 'today' ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/40 font-bold' : 'bg-slate-950 text-slate-400 border-slate-800 hover:text-slate-200'"
            class="text-[11px] px-3 py-1 rounded-xl border transition cursor-pointer"
          >
            Hari Ini
          </button>
          <button
            type="button"
            @click="setDatePreset('7days')"
            :class="activeDatePreset === '7days' ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/40 font-bold' : 'bg-slate-950 text-slate-400 border-slate-800 hover:text-slate-200'"
            class="text-[11px] px-3 py-1 rounded-xl border transition cursor-pointer"
          >
            7 Hari Terakhir
          </button>
          <button
            type="button"
            @click="setDatePreset('this_month')"
            :class="activeDatePreset === 'this_month' ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/40 font-bold' : 'bg-slate-950 text-slate-400 border-slate-800 hover:text-slate-200'"
            class="text-[11px] px-3 py-1 rounded-xl border transition cursor-pointer"
          >
            Bulan Ini
          </button>
        </div>

        <button
          v-if="hasActiveFilters"
          @click="resetFilters"
          type="button"
          class="text-xs text-rose-400 hover:text-rose-300 font-bold underline transition cursor-pointer"
        >
          Reset Filter
        </button>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
        <!-- Live Search -->
        <div class="relative lg:col-span-2">
          <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs">🔍</span>
          <input
            type="text"
            v-model="filters.search"
            @input="debounceSearch"
            placeholder="Cari deskripsi (misal: kopi, bensin, gaji)..."
            class="w-full pl-9 pr-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs placeholder:text-slate-500 focus:outline-none focus:border-emerald-500"
          />
        </div>

        <!-- Filter Type -->
        <div>
          <select
            v-model="filters.type"
            @change="applyFilters"
            class="w-full px-3 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-slate-200 text-xs focus:outline-none focus:border-emerald-500 cursor-pointer"
          >
            <option value="">Semua Tipe</option>
            <option value="EXPENSE">🔴 Pengeluaran</option>
            <option value="INCOME">🟢 Pemasukan</option>
            <option value="TRANSFER">🔵 Transfer Dompet</option>
          </select>
        </div>

        <!-- Filter Category -->
        <div>
          <select
            v-model="filters.category_id"
            @change="applyFilters"
            class="w-full px-3 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-slate-200 text-xs focus:outline-none focus:border-emerald-500 cursor-pointer"
          >
            <option value="">Semua Kategori</option>
            <option v-for="cat in financeStore.categories" :key="cat.id" :value="cat.id">
              {{ cat.icon }} {{ cat.name }}
            </option>
          </select>
        </div>

        <!-- Date Range Filter -->
        <div class="flex items-center gap-2">
          <input
            type="date"
            v-model="filters.start_date"
            @change="onCustomDateChange"
            title="Tanggal Mulai"
            class="w-1/2 px-2.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-slate-300 text-[11px] focus:outline-none focus:border-emerald-500"
          />
          <input
            type="date"
            v-model="filters.end_date"
            @change="onCustomDateChange"
            title="Tanggal Akhir"
            class="w-1/2 px-2.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-slate-300 text-[11px] focus:outline-none focus:border-emerald-500"
          />
        </div>
      </div>
    </div>

    <!-- TRANSACTIONS CONTAINER -->
    <div class="rounded-2xl bg-slate-900/90 border border-slate-800 overflow-hidden shadow-sm">
      <!-- SKELETON TABLE DURING INITIAL COLD LOAD -->
      <div v-if="isInitialLoading" class="p-5 space-y-4">
        <div class="flex items-center justify-between">
          <div class="h-4 w-40 bg-slate-800 rounded animate-pulse"></div>
          <div class="h-4 w-20 bg-slate-800 rounded animate-pulse"></div>
        </div>
        <!-- Desktop Skeleton Rows -->
        <div class="hidden md:block space-y-3">
          <div v-for="k in 5" :key="k" class="h-12 bg-slate-950/60 rounded-xl animate-pulse"></div>
        </div>
        <!-- Mobile Skeleton Cards -->
        <div class="md:hidden space-y-3">
          <div v-for="m in 4" :key="m" class="h-16 bg-slate-950/60 rounded-2xl animate-pulse"></div>
        </div>
      </div>

      <!-- EMPTY STATE (Rendered only after loading completes and genuinely 0 items found) -->
      <div
        v-else-if="financeStore.transactions.length === 0"
        class="py-16 px-4 text-center flex flex-col items-center justify-center space-y-3"
      >
        <div class="w-16 h-16 rounded-2xl bg-slate-800/80 border border-slate-700/60 flex items-center justify-center text-3xl shadow-inner">
          📝
        </div>
        <div class="space-y-1">
          <h3 class="text-sm sm:text-base font-bold text-white">
            {{ hasActiveFilters ? 'Tidak Ada Transaksi yang Cocok' : 'Belum Ada Transaksi' }}
          </h3>
          <p class="text-xs text-slate-400 max-w-sm mx-auto leading-relaxed">
            {{ hasActiveFilters ? 'Coba ubah kata kunci atau setel ulang filter tanggal & kategori untuk melihat mutasi lainnya.' : 'Catat transaksi pemasukan atau pengeluaran pertama Anda untuk mulai memantau keuangan secara otomatis!' }}
          </p>
        </div>
        <div class="pt-2">
          <button
            v-if="hasActiveFilters"
            @click="resetFilters"
            type="button"
            class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-750 text-slate-200 font-bold text-xs transition cursor-pointer"
          >
            Reset Filter
          </button>
          <button
            v-else
            @click="openCreateModal"
            type="button"
            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 text-slate-950 font-bold text-xs transition shadow-md shadow-emerald-500/20 cursor-pointer active:scale-95"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
            <span>Catat Transaksi Pertama</span>
          </button>
        </div>
      </div>

      <!-- DESKTOP SPACIOUS UNPRESSED TABLE VIEW (Hidden on Mobile) -->
      <div
        v-else
        class="hidden md:block overflow-x-auto"
        :class="isSyncing ? 'opacity-70 transition-opacity pointer-events-none' : 'transition-opacity'"
      >
        <table class="w-full text-left text-xs min-w-[780px] border-collapse">
          <thead>
            <tr class="text-slate-400 uppercase text-[10px] font-bold tracking-wider bg-slate-950/60 border-b border-slate-800">
              <th class="py-3.5 px-5 w-32 font-bold">Tanggal</th>
              <th class="py-3.5 px-5 min-w-[220px] font-bold">Deskripsi</th>
              <th class="py-3.5 px-5 w-44 font-bold">Kategori</th>
              <th class="py-3.5 px-5 w-32 text-center font-bold">Kanal Input</th>
              <th class="py-3.5 px-5 w-36 text-right font-bold">Nominal</th>
              <th class="py-3.5 px-5 w-24 text-center font-bold">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800/60">
            <tr
              v-for="t in financeStore.transactions"
              :key="t.id"
              class="hover:bg-slate-800/40 transition group cursor-pointer"
            >
              <!-- Date -->
              <td class="py-4 px-5 text-slate-400 whitespace-nowrap font-mono text-[11px]">
                {{ formatDate(t.transaction_date) }}
              </td>

              <!-- Description -->
              <td class="py-4 px-5 font-bold text-slate-200">
                <div class="flex items-center gap-2">
                  <span
                    class="w-2 h-2 rounded-full shrink-0"
                    :class="t.type === 'INCOME' ? 'bg-emerald-400' : (t.type === 'TRANSFER' ? 'bg-blue-400' : 'bg-rose-400')"
                  ></span>
                  <span class="truncate max-w-sm">{{ t.description || 'Transaksi' }}</span>
                </div>
              </td>

              <!-- Category Pill -->
              <td class="py-4 px-5 text-slate-300">
                <span
                  v-if="t.category"
                  class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-slate-800/90 text-slate-200 border border-slate-700/60 shadow-sm"
                >
                  <span>{{ t.category.icon }}</span>
                  <span>{{ t.category.name }}</span>
                </span>
                <span v-else class="text-slate-500 italic text-[11px]">- Umum -</span>
              </td>

              <!-- Source Channel (WhatsApp vs Web) -->
              <td class="py-4 px-5 text-center">
                <span
                  class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold"
                  :class="t.channel === 'WHATSAPP' || !t.channel ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/25' : 'bg-blue-500/15 text-blue-400 border border-blue-500/25'"
                >
                  <span>{{ t.channel === 'WEB' ? '💻 Web' : '📱 WhatsApp' }}</span>
                </span>
              </td>

              <!-- Amount -->
              <td
                class="py-4 px-5 text-right font-black text-sm whitespace-nowrap"
                :class="vaultStore.isMasked ? 'text-slate-400' : (t.type === 'INCOME' ? 'text-emerald-400' : (t.type === 'TRANSFER' ? 'text-blue-400' : 'text-rose-400'))"
              >
                <span v-if="vaultStore.isMasked">Rp ••••••••</span>
                <span v-else>{{ t.type === 'INCOME' ? '+' : (t.type === 'TRANSFER' ? '↔ ' : '-') }}{{ formatRupiah(t.amount) }}</span>
              </td>

              <!-- Actions (Edit & Delete) -->
              <td class="py-4 px-5 text-center whitespace-nowrap">
                <div class="flex items-center justify-center gap-1.5">
                  <button
                    @click="editTransaction(t)"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-emerald-400 hover:bg-slate-800 transition cursor-pointer"
                    title="Edit"
                  >
                    ✏️
                  </button>
                  <button
                    @click="promptDelete(t)"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-rose-400 hover:bg-slate-800 transition cursor-pointer"
                    title="Hapus"
                  >
                    🗑️
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- MOBILE NATIVE LIST VIEW (Shown on Mobile screens < md) -->
      <div
        v-if="financeStore.transactions.length > 0"
        class="md:hidden divide-y divide-slate-800/80"
        :class="isSyncing ? 'opacity-70 transition-opacity pointer-events-none' : 'transition-opacity'"
      >
        <div
          v-for="t in financeStore.transactions"
          :key="'mobile-' + t.id"
          class="p-4 hover:bg-slate-800/30 transition flex items-center justify-between gap-3"
        >
          <!-- Left: Category Icon & Details -->
          <div class="flex items-center gap-3 min-w-0">
            <div
              class="w-11 h-11 rounded-2xl flex items-center justify-center text-lg shrink-0 border shadow-inner"
              :class="t.type === 'INCOME' ? 'bg-emerald-500/15 border-emerald-500/30 text-emerald-400' : (t.type === 'TRANSFER' ? 'bg-blue-500/15 border-blue-500/30 text-blue-400' : 'bg-rose-500/15 border-rose-500/30 text-rose-400')"
            >
              <span>{{ t.category?.icon || (t.type === 'INCOME' ? '💰' : (t.type === 'TRANSFER' ? '⇄' : '💸')) }}</span>
            </div>
            <div class="min-w-0">
              <p class="font-bold text-xs text-white truncate">{{ t.description || 'Transaksi' }}</p>
              <div class="flex items-center gap-1.5 mt-1 text-[10px] text-slate-400">
                <span class="inline-block px-1.5 py-0.2 rounded bg-slate-800 text-slate-300 font-medium truncate max-w-[110px]">
                  {{ t.category?.name || 'Umum' }}
                </span>
                <span>•</span>
                <span class="whitespace-nowrap">{{ formatDate(t.transaction_date) }}</span>
              </div>
            </div>
          </div>

          <!-- Right: Nominal & Action Buttons -->
          <div class="text-right shrink-0">
            <p
              class="font-black text-xs"
              :class="vaultStore.isMasked ? 'text-slate-400' : (t.type === 'INCOME' ? 'text-emerald-400' : (t.type === 'TRANSFER' ? 'text-blue-400' : 'text-rose-400'))"
            >
              <span v-if="vaultStore.isMasked">Rp ••••••••</span>
              <span v-else>{{ t.type === 'INCOME' ? '+' : (t.type === 'TRANSFER' ? '↔ ' : '-') }}{{ formatRupiah(t.amount) }}</span>
            </p>
            <div class="flex items-center justify-end gap-2 mt-1.5">
              <button
                @click="editTransaction(t)"
                class="p-1 rounded-lg text-slate-400 hover:text-emerald-400 hover:bg-slate-800 transition cursor-pointer"
                title="Edit"
              >
                ✏️
              </button>
              <button
                @click="promptDelete(t)"
                class="p-1 rounded-lg text-slate-400 hover:text-rose-400 hover:bg-slate-800 transition cursor-pointer"
                title="Hapus"
              >
                🗑️
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- PAGINATION BAR -->
      <div
        v-if="financeStore.transactions.length > 0"
        class="p-4 bg-slate-950/60 border-t border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs text-slate-400"
      >
        <div>
          Menampilkan <span class="font-bold text-slate-200">{{ financeStore.transactions.length }}</span> dari <span class="font-bold text-slate-200">{{ financeStore.pagination.total_items }}</span> data transaksi
        </div>
        <div class="flex items-center gap-2 self-center sm:self-auto">
          <button
            @click="changePage(financeStore.pagination.page - 1)"
            :disabled="financeStore.pagination.page <= 1"
            class="px-3 py-1.5 rounded-xl bg-slate-900 border border-slate-800 hover:bg-slate-800 text-slate-300 font-semibold disabled:opacity-40 transition cursor-pointer"
          >
            &laquo; Prev
          </button>
          <span class="px-2 font-bold text-slate-200">
            Hal {{ financeStore.pagination.page }} / {{ financeStore.pagination.total_pages || 1 }}
          </span>
          <button
            @click="changePage(financeStore.pagination.page + 1)"
            :disabled="financeStore.pagination.page >= financeStore.pagination.total_pages"
            class="px-3 py-1.5 rounded-xl bg-slate-900 border border-slate-800 hover:bg-slate-800 text-slate-300 font-semibold disabled:opacity-40 transition cursor-pointer"
          >
            Next &raquo;
          </button>
        </div>
      </div>
    </div>

    <!-- Edit/Create Modal -->
    <TransactionModal
      :isOpen="showModal"
      :transaction="selectedTransaction"
      @close="showModal = false"
      @saved="refreshList"
    />

    <!-- Delete Confirmation Modal -->
    <ConfirmModal
      :isOpen="showDeleteModal"
      title="Hapus Transaksi?"
      :message="deleteConfirmMessage"
      confirmText="Ya, Hapus Transaksi"
      :danger="true"
      :loading="deleting"
      @confirm="executeDelete"
      @cancel="showDeleteModal = false"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useFinanceStore } from '../stores/finance';
import { useVaultStore } from '../stores/vault';
import { useToastStore } from '../stores/toast';
import { formatRupiah as rawFormatRupiah, formatDate } from '../utils/formatters';
import TransactionModal from '../components/modals/TransactionModal.vue';
import ConfirmModal from '../components/common/ConfirmModal.vue';

const financeStore = useFinanceStore();
const vaultStore = useVaultStore();
const toastStore = useToastStore();

const isInitialLoading = ref(!financeStore.hasLoadedTransactions);
const isSyncing = ref(false);
const isError = ref(false);
const errorMessage = ref('');

function formatRupiah(val) {
  if (vaultStore.isMasked) return 'Rp ••••••••';
  return rawFormatRupiah(val);
}

const showModal = ref(false);
const selectedTransaction = ref(null);

const showDeleteModal = ref(false);
const transactionToDelete = ref(null);
const deleting = ref(false);

const activeDatePreset = ref('all');

const filters = ref({
  search: '',
  type: '',
  category_id: '',
  start_date: '',
  end_date: '',
  page: 1,
});

const hasActiveFilters = computed(() => {
  return !!(filters.value.search || filters.value.type || filters.value.category_id || filters.value.start_date || filters.value.end_date);
});

const deleteConfirmMessage = computed(() => {
  if (!transactionToDelete.value) return 'Apakah Anda yakin ingin menghapus transaksi ini?';
  const desc = transactionToDelete.value.description;
  const nom = formatRupiah(transactionToDelete.value.amount);
  return `Yakin ingin menghapus transaksi "${desc}" sebesar ${nom}? Saldo dompet Anda akan dikembalikan otomatis.`;
});

function setDatePreset(preset) {
  activeDatePreset.value = preset;
  const now = new Date();
  const formatYMD = (d) => d.toISOString().slice(0, 10);

  if (preset === 'all') {
    filters.value.start_date = '';
    filters.value.end_date = '';
  } else if (preset === 'today') {
    const today = formatYMD(now);
    filters.value.start_date = today;
    filters.value.end_date = today;
  } else if (preset === '7days') {
    const past = new Date();
    past.setDate(past.getDate() - 7);
    filters.value.start_date = formatYMD(past);
    filters.value.end_date = formatYMD(now);
  } else if (preset === 'this_month') {
    const startOfMonth = new Date(now.getFullYear(), now.getMonth(), 1);
    filters.value.start_date = formatYMD(startOfMonth);
    filters.value.end_date = formatYMD(now);
  }

  filters.value.page = 1;
  applyFilters();
}

function onCustomDateChange() {
  activeDatePreset.value = 'custom';
  applyFilters();
}

function resetFilters() {
  filters.value = {
    search: '',
    type: '',
    category_id: '',
    start_date: '',
    end_date: '',
    page: 1,
  };
  activeDatePreset.value = 'all';
  applyFilters();
}

let debounceTimer = null;
function debounceSearch() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    filters.value.page = 1;
    applyFilters();
  }, 350);
}

async function applyFilters(force = false) {
  if (!financeStore.hasLoadedTransactions) {
    isInitialLoading.value = true;
  } else {
    isInitialLoading.value = false;
    isSyncing.value = true;
  }

  isError.value = false;
  errorMessage.value = '';

  try {
    await financeStore.fetchTransactions(filters.value, force);
  } catch (err) {
    if (!financeStore.hasLoadedTransactions) {
      isError.value = true;
      errorMessage.value = err.response?.data?.message || err.message || 'Gagal memuat transaksi.';
    }
  } finally {
    isInitialLoading.value = false;
    isSyncing.value = false;
  }
}

function changePage(page) {
  filters.value.page = page;
  applyFilters();
}

function openCreateModal() {
  selectedTransaction.value = null;
  showModal.value = true;
}

function editTransaction(item) {
  selectedTransaction.value = item;
  showModal.value = true;
}

function promptDelete(item) {
  transactionToDelete.value = item;
  showDeleteModal.value = true;
}

async function executeDelete() {
  if (!transactionToDelete.value) return;
  deleting.value = true;
  try {
    await financeStore.deleteTransaction(transactionToDelete.value.id);
    toastStore.success('Transaksi berhasil dihapus dan saldo telah disesuaikan.');
    showDeleteModal.value = false;
    applyFilters();
  } catch (err) {
    toastStore.error(err.response?.data?.message || 'Gagal menghapus transaksi.');
  } finally {
    deleting.value = false;
  }
}

function refreshList() {
  showModal.value = false;
  applyFilters();
}

onMounted(async () => {
  try {
    await financeStore.fetchCategories();
  } catch (e) {
    // Non-fatal if categories fail
  }
  applyFilters();
});
</script>
