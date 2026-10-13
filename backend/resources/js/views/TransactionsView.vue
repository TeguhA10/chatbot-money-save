<template>
  <div class="space-y-6">
    <!-- Header Page -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-black text-white tracking-tight">Pusat Transaksi</h1>
        <p class="text-xs text-slate-400 mt-0.5">Kelola, cari, dan tinjau seluruh riwayat mutasi keuangan</p>
      </div>

      <button
        @click="openCreateModal"
        class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 text-slate-950 font-bold text-xs flex items-center gap-1.5 shadow-lg shadow-emerald-500/20 cursor-pointer transition active:scale-95"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        + Catat Transaksi Baru
      </button>
    </div>

    <!-- FILTER BAR & LIVE SEARCH -->
    <div class="p-4 rounded-2xl bg-slate-900/80 border border-slate-800 space-y-3">
      <!-- Date Presets & Filter Row -->
      <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-800/60 pb-3">
        <div class="flex items-center gap-1.5 flex-wrap">
          <span class="text-[11px] text-slate-400 font-medium mr-1">Rentang:</span>
          <button
            type="button"
            @click="setDatePreset('all')"
            :class="activeDatePreset === 'all' ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30' : 'bg-slate-950 text-slate-400 border-slate-800 hover:text-slate-200'"
            class="text-[11px] px-2.5 py-1 rounded-lg border font-medium transition"
          >
            Semua
          </button>
          <button
            type="button"
            @click="setDatePreset('today')"
            :class="activeDatePreset === 'today' ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30' : 'bg-slate-950 text-slate-400 border-slate-800 hover:text-slate-200'"
            class="text-[11px] px-2.5 py-1 rounded-lg border font-medium transition"
          >
            Hari Ini
          </button>
          <button
            type="button"
            @click="setDatePreset('7days')"
            :class="activeDatePreset === '7days' ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30' : 'bg-slate-950 text-slate-400 border-slate-800 hover:text-slate-200'"
            class="text-[11px] px-2.5 py-1 rounded-lg border font-medium transition"
          >
            7 Hari Terakhir
          </button>
          <button
            type="button"
            @click="setDatePreset('this_month')"
            :class="activeDatePreset === 'this_month' ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30' : 'bg-slate-950 text-slate-400 border-slate-800 hover:text-slate-200'"
            class="text-[11px] px-2.5 py-1 rounded-lg border font-medium transition"
          >
            Bulan Ini
          </button>
        </div>

        <button
          v-if="hasActiveFilters"
          @click="resetFilters"
          type="button"
          class="text-[11px] text-rose-400 hover:text-rose-300 font-medium underline transition"
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
            class="w-full pl-9 pr-4 py-2 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs placeholder:text-slate-500 focus:outline-none focus:border-emerald-500"
          />
        </div>

        <!-- Filter Type -->
        <div>
          <select
            v-model="filters.type"
            @change="applyFilters"
            class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-slate-200 text-xs focus:outline-none focus:border-emerald-500"
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
            class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-slate-200 text-xs focus:outline-none focus:border-emerald-500"
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
            class="w-1/2 px-2 py-2 rounded-xl bg-slate-950 border border-slate-800 text-slate-300 text-[11px] focus:outline-none focus:border-emerald-500"
          />
          <input
            type="date"
            v-model="filters.end_date"
            @change="onCustomDateChange"
            title="Tanggal Akhir"
            class="w-1/2 px-2 py-2 rounded-xl bg-slate-950 border border-slate-800 text-slate-300 text-[11px] focus:outline-none focus:border-emerald-500"
          />
        </div>
      </div>
    </div>

    <!-- TRANSACTIONS TABLE -->
    <div class="rounded-2xl bg-slate-900/80 border border-slate-800 overflow-hidden">
      <div v-if="financeStore.loadingTransactions" class="py-16 text-center text-slate-400 text-xs flex flex-col items-center justify-center gap-2">
        <svg class="w-6 h-6 animate-spin text-emerald-400" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
        </svg>
        <span>Memuat data transaksi...</span>
      </div>

      <!-- EMPTY STATE -->
      <div v-else-if="financeStore.transactions.length === 0" class="py-14 px-4 text-center flex flex-col items-center justify-center">
        <div class="w-14 h-14 rounded-2xl bg-slate-800/80 border border-slate-700/60 flex items-center justify-center text-2xl mb-3 shadow-inner">
          📝
        </div>
        <h3 class="text-sm font-bold text-white mb-1">
          {{ hasActiveFilters ? 'Tidak ada transaksi yang cocok' : 'Belum Ada Transaksi' }}
        </h3>
        <p class="text-xs text-slate-400 max-w-sm mb-4">
          {{ hasActiveFilters ? 'Coba ubah kata kunci atau setel ulang filter tanggal & kategori.' : 'Catat transaksi pemasukan atau pengeluaran pertama Anda untuk mulai memantau keuangan!' }}
        </p>
        <button
          v-if="hasActiveFilters"
          @click="resetFilters"
          type="button"
          class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 font-semibold text-xs transition"
        >
          Reset Filter
        </button>
        <button
          v-else
          @click="openCreateModal"
          type="button"
          class="px-4 py-2 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 text-slate-950 font-bold text-xs transition shadow-md shadow-emerald-500/20"
        >
          + Catat Transaksi Pertama
        </button>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="text-slate-400 bg-slate-950/60 border-b border-slate-800">
              <th class="py-3 px-4 font-semibold">Tanggal</th>
              <th class="py-3 px-4 font-semibold">Deskripsi</th>
              <th class="py-3 px-4 font-semibold">Kategori</th>
              <th class="py-3 px-4 font-semibold">Kanal Input</th>
              <th class="py-3 px-4 font-semibold text-right">Nominal</th>
              <th class="py-3 px-4 font-semibold text-center">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800/60">
            <tr
              v-for="t in financeStore.transactions"
              :key="t.id"
              class="hover:bg-slate-800/30 transition group"
            >
              <!-- Date -->
              <td class="py-3.5 px-4 text-slate-400 whitespace-nowrap">
                {{ formatDate(t.transaction_date) }}
              </td>

              <!-- Description & Type badge -->
              <td class="py-3.5 px-4 font-medium text-slate-200">
                <div class="flex items-center gap-2">
                  <span
                    class="w-2 h-2 rounded-full shrink-0"
                    :class="t.type === 'INCOME' ? 'bg-emerald-400' : (t.type === 'TRANSFER' ? 'bg-blue-400' : 'bg-rose-400')"
                  ></span>
                  <span>{{ t.description }}</span>
                </div>
              </td>

              <!-- Category -->
              <td class="py-3.5 px-4 text-slate-300">
                <span v-if="t.category" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-slate-800/80 border border-slate-700/60">
                  <span>{{ t.category.icon }}</span>
                  <span>{{ t.category.name }}</span>
                </span>
                <span v-else class="text-slate-500">-</span>
              </td>

              <!-- Source Channel (WhatsApp vs Web) -->
              <td class="py-3.5 px-4">
                <span
                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-medium"
                  :class="t.channel === 'WHATSAPP' || !t.channel ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-blue-500/10 text-blue-400 border border-blue-500/20'"
                >
                  <span>{{ t.channel === 'WEB' ? '💻 Web' : '📱 WhatsApp' }}</span>
                </span>
              </td>

              <!-- Amount -->
              <td
                class="py-3.5 px-4 text-right font-bold whitespace-nowrap"
                :class="vaultStore.isMasked ? 'text-slate-400' : (t.type === 'INCOME' ? 'text-emerald-400' : (t.type === 'TRANSFER' ? 'text-blue-400' : 'text-rose-400'))"
              >
                <span v-if="vaultStore.isMasked">Rp ••••••••</span>
                <span v-else>{{ t.type === 'INCOME' ? '+' : (t.type === 'TRANSFER' ? '↔ ' : '-') }}{{ formatRupiah(t.amount) }}</span>
              </td>

              <!-- Actions (Edit & Delete) -->
              <td class="py-3.5 px-4 text-center whitespace-nowrap">
                <div class="flex items-center justify-center gap-1.5 opacity-80 group-hover:opacity-100">
                  <button
                    @click="editTransaction(t)"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-emerald-400 hover:bg-slate-800 transition"
                    title="Edit"
                  >
                    ✏️
                  </button>
                  <button
                    @click="promptDelete(t)"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-rose-400 hover:bg-slate-800 transition"
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

      <!-- PAGINATION BAR -->
      <div v-if="financeStore.transactions.length > 0" class="p-4 bg-slate-950/60 border-t border-slate-800 flex items-center justify-between text-xs text-slate-400">
        <div>
          Menampilkan {{ financeStore.transactions.length }} dari {{ financeStore.pagination.total_items }} data
        </div>
        <div class="flex items-center gap-2">
          <button
            @click="changePage(financeStore.pagination.page - 1)"
            :disabled="financeStore.pagination.page <= 1"
            class="px-3 py-1.5 rounded-lg bg-slate-900 border border-slate-800 hover:bg-slate-800 text-slate-300 disabled:opacity-40 transition"
          >
            &laquo; Prev
          </button>
          <span class="px-2 font-semibold text-slate-200">
            Hal {{ financeStore.pagination.page }} / {{ financeStore.pagination.total_pages }}
          </span>
          <button
            @click="changePage(financeStore.pagination.page + 1)"
            :disabled="financeStore.pagination.page >= financeStore.pagination.total_pages"
            class="px-3 py-1.5 rounded-lg bg-slate-900 border border-slate-800 hover:bg-slate-800 text-slate-300 disabled:opacity-40 transition"
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

function applyFilters() {
  financeStore.fetchTransactions(filters.value);
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

onMounted(() => {
  financeStore.fetchCategories();
  applyFilters();
});
</script>
