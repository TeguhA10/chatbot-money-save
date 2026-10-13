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

        <!-- Date Range Filter Reset -->
        <div class="flex items-center gap-2">
          <input
            type="date"
            v-model="filters.start_date"
            @change="applyFilters"
            class="w-1/2 px-2 py-2 rounded-xl bg-slate-950 border border-slate-800 text-slate-300 text-[11px] focus:outline-none focus:border-emerald-500"
          />
          <input
            type="date"
            v-model="filters.end_date"
            @change="applyFilters"
            class="w-1/2 px-2 py-2 rounded-xl bg-slate-950 border border-slate-800 text-slate-300 text-[11px] focus:outline-none focus:border-emerald-500"
          />
        </div>
      </div>
    </div>

    <!-- TRANSACTIONS TABLE -->
    <div class="rounded-2xl bg-slate-900/80 border border-slate-800 overflow-hidden">
      <div v-if="financeStore.loadingTransactions" class="py-16 text-center text-slate-500 text-xs">
        Memuat data transaksi...
      </div>

      <div v-else-if="financeStore.transactions.length === 0" class="py-16 text-center text-slate-500 text-xs">
        Tidak ada transaksi yang cocok dengan filter.
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
                :class="t.type === 'INCOME' ? 'text-emerald-400' : (t.type === 'TRANSFER' ? 'text-blue-400' : 'text-rose-400')"
              >
                {{ t.type === 'INCOME' ? '+' : (t.type === 'TRANSFER' ? '↔ ' : '-') }}{{ formatRupiah(t.amount) }}
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
                    @click="deleteTransaction(t.id)"
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
      <div class="p-4 bg-slate-950/60 border-t border-slate-800 flex items-center justify-between text-xs text-slate-400">
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
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useFinanceStore } from '../stores/finance';
import { useVaultStore } from '../stores/vault';
import { formatRupiah as rawFormatRupiah, formatDate } from '../utils/formatters';
import TransactionModal from '../components/modals/TransactionModal.vue';

const financeStore = useFinanceStore();
const vaultStore = useVaultStore();

function formatRupiah(val) {
  if (vaultStore.isMasked) return 'Rp ••••••••';
  return rawFormatRupiah(val);
}

const showModal = ref(false);
const selectedTransaction = ref(null);

const filters = ref({
  search: '',
  type: '',
  category_id: '',
  start_date: '',
  end_date: '',
  page: 1,
});

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

async function deleteTransaction(id) {
  if (confirm('Apakah Anda yakin ingin menghapus transaksi ini? Saldo dompet akan disesuaikan otomatis.')) {
    try {
      await financeStore.deleteTransaction(id);
      applyFilters();
    } catch (err) {
      alert(err.response?.data?.message || 'Gagal menghapus transaksi.');
    }
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
