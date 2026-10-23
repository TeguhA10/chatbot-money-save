<template>
  <div class="space-y-5 sm:space-y-6">
    <!-- Header Page -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight flex items-center gap-2.5">
          <span>Laporan & Analisis</span>
          <!-- Active sync indicator badge -->
          <span
            v-if="isSyncing"
            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30 shadow-sm animate-pulse"
          >
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-ping"></span>
            <span>Memperbarui analisis...</span>
          </span>
        </h1>
        <p class="text-xs text-slate-400 mt-0.5">Evaluasi tren, komparasi historis, dan ekspor data keuangan lengkap</p>
      </div>

      <div class="flex flex-wrap sm:flex-nowrap items-center gap-2 sm:gap-3 w-full sm:w-auto">
        <!-- Month Picker -->
        <input
          type="month"
          v-model="selectedMonth"
          @change="loadAnalytics"
          :disabled="isInitialLoading || isSyncing"
          class="flex-1 sm:flex-initial px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-800 text-slate-200 text-xs font-semibold focus:outline-none focus:border-emerald-500 shadow-inner cursor-pointer transition disabled:opacity-50"
        />

        <!-- Export Excel Button -->
        <button
          @click="downloadExcel"
          :disabled="downloading || isInitialLoading"
          class="flex-1 sm:flex-initial justify-center px-4 py-2.5 rounded-xl bg-emerald-600/20 border border-emerald-500/30 hover:bg-emerald-600/30 text-emerald-300 font-bold text-xs flex items-center gap-1.5 cursor-pointer transition disabled:opacity-50 active:scale-95 whitespace-nowrap"
        >
          <span>📊</span>
          <span>{{ downloading ? 'Mengunduh...' : 'Excel' }}</span>
        </button>

        <!-- Print/PDF Button -->
        <button
          @click="handlePrint"
          :disabled="isInitialLoading"
          class="flex-1 sm:flex-initial justify-center px-4 py-2.5 rounded-xl bg-slate-800 border border-slate-700 hover:bg-slate-700 text-slate-200 font-bold text-xs flex items-center gap-1.5 cursor-pointer transition active:scale-95 whitespace-nowrap"
        >
          <span>🖨️</span>
          <span>PDF</span>
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
          <h3 class="text-sm font-bold text-rose-200">Gagal Memuat Analitik</h3>
          <p class="text-xs text-rose-300/90 mt-0.5 leading-relaxed">
            {{ errorMessage || 'Terjadi gangguan koneksi saat mengkalkulasi tren keuangan Anda.' }}
          </p>
        </div>
      </div>
      <button
        @click="loadAnalytics"
        class="self-start sm:self-auto px-4 py-2 rounded-xl bg-rose-500 hover:bg-rose-400 text-slate-950 font-bold text-xs flex items-center gap-1.5 transition active:scale-95 cursor-pointer shadow-md"
      >
        <span>🔄 Coba Lagi</span>
      </button>
    </div>

    <!-- SKELETON LOADER (Initial Cold Mount) -->
    <div v-if="isInitialLoading" class="space-y-5 animate-in fade-in duration-150">
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <div v-for="i in 3" :key="i" class="h-32 rounded-2xl bg-slate-900 border border-slate-800/80 animate-pulse p-6"></div>
      </div>
      <div class="h-64 rounded-2xl bg-slate-900 border border-slate-800/80 animate-pulse p-6"></div>
    </div>

    <!-- MAIN ANALYTICS CONTENT -->
    <template v-else>
      <div :class="isSyncing ? 'opacity-70 transition-opacity pointer-events-none' : 'transition-opacity'" class="space-y-5 sm:space-y-6">
        <!-- MONTH-OVER-MONTH VARIANCE HIGHLIGHT CARDS -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
          <!-- Income Card -->
          <div class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-sm">
            <span class="text-xs font-semibold text-slate-400">Total Pemasukan</span>
            <p class="text-2xl font-black text-emerald-400 mt-1">
              {{ formatRupiah(financeStore.summary?.total_income || 0) }}
            </p>
            <div class="flex items-center gap-1.5 text-xs font-semibold mt-3" :class="(financeStore.summary?.delta_vs_last_month?.income_percentage || 0) >= 0 ? 'text-emerald-400' : 'text-rose-400'">
              <span>{{ (financeStore.summary?.delta_vs_last_month?.income_percentage || 0) >= 0 ? '▲' : '▼' }}</span>
              <span>{{ Math.abs(financeStore.summary?.delta_vs_last_month?.income_percentage || 0) }}% vs bulan lalu</span>
            </div>
          </div>

          <!-- Expense Card -->
          <div class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-sm">
            <span class="text-xs font-semibold text-slate-400">Total Pengeluaran</span>
            <p class="text-2xl font-black text-rose-400 mt-1">
              {{ formatRupiah(financeStore.summary?.total_expense || 0) }}
            </p>
            <div class="flex items-center gap-1.5 text-xs font-semibold mt-3" :class="(financeStore.summary?.delta_vs_last_month?.expense_percentage || 0) <= 0 ? 'text-emerald-400' : 'text-rose-400'">
              <span>{{ (financeStore.summary?.delta_vs_last_month?.expense_percentage || 0) > 0 ? '▲' : '▼' }}</span>
              <span>{{ Math.abs(financeStore.summary?.delta_vs_last_month?.expense_percentage || 0) }}% vs bulan lalu</span>
            </div>
          </div>

          <!-- Net Surplus/Deficit -->
          <div class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-sm">
            <span class="text-xs font-semibold text-slate-400">Status Arus Kas</span>
            <p class="text-2xl font-black mt-1" :class="(financeStore.summary?.net_savings || 0) >= 0 ? 'text-teal-400' : 'text-amber-400'">
              {{ formatRupiah(financeStore.summary?.net_savings || 0) }}
            </p>
            <div class="text-xs font-bold mt-3" :class="(financeStore.summary?.net_savings || 0) >= 0 ? 'text-teal-400' : 'text-amber-400'">
              <span>{{ (financeStore.summary?.net_savings || 0) >= 0 ? '✅ Surplus Bulanan Terjaga' : '⚠️ Defisit (Pengeluaran Lebih Besar)' }}</span>
            </div>
          </div>
        </div>

        <!-- 6 MONTH TREND MATRIX TABLE (UNPRESSED & SPACIOUS) -->
        <div class="p-5 sm:p-6 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-sm">
          <div class="flex items-center justify-between mb-4">
            <div>
              <h2 class="text-sm sm:text-base font-bold text-white">Riwayat Keuangan 6 Bulan Terakhir</h2>
              <p class="text-xs text-slate-400">Komparasi tren pemasukan, pengeluaran, dan tabungan bersih</p>
            </div>
          </div>

          <div class="overflow-x-auto -mx-1">
            <table class="w-full min-w-[680px] text-left text-xs border-collapse">
              <thead>
                <tr class="text-slate-400 uppercase text-[10px] font-bold tracking-wider bg-slate-950/60 border-b border-slate-800">
                  <th class="py-3.5 px-5 font-bold">Periode Bulan</th>
                  <th class="py-3.5 px-5 font-bold text-right">Pemasukan</th>
                  <th class="py-3.5 px-5 font-bold text-right">Pengeluaran</th>
                  <th class="py-3.5 px-5 font-bold text-right">Arus Kas Bersih</th>
                  <th class="py-3.5 px-5 font-bold text-center">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-800/60">
                <tr
                  v-for="item in financeStore.monthlyTrend"
                  :key="item.month"
                  class="hover:bg-slate-800/40 transition"
                >
                  <td class="py-4 px-5 font-bold text-slate-200">{{ formatMonthName(item.month) }}</td>
                  <td class="py-4 px-5 text-right font-black text-emerald-400">{{ formatRupiah(item.income) }}</td>
                  <td class="py-4 px-5 text-right font-black text-rose-400">{{ formatRupiah(item.expense) }}</td>
                  <td class="py-4 px-5 text-right font-black" :class="item.income - item.expense >= 0 ? 'text-teal-400' : 'text-amber-400'">
                    {{ formatRupiah(item.income - item.expense) }}
                  </td>
                  <td class="py-4 px-5 text-center">
                    <span
                      class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider"
                      :class="item.income - item.expense >= 0 ? 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/25' : 'bg-rose-500/15 text-rose-300 border border-rose-500/25'"
                    >
                      {{ item.income - item.expense >= 0 ? 'Surplus' : 'Defisit' }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- TOP EXPENSE CATEGORIES -->
        <div class="p-5 sm:p-6 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-sm">
          <div class="flex items-center justify-between mb-4">
            <div>
              <h2 class="text-sm sm:text-base font-bold text-white">Rincian Pengeluaran per Kategori</h2>
              <p class="text-xs text-slate-400">Analisis pos pengeluaran yang paling dominan di bulan ini</p>
            </div>
            <span class="text-[10px] px-2.5 py-0.5 rounded-full bg-slate-800 text-slate-300 font-mono whitespace-nowrap shrink-0">
              {{ financeStore.categoryBreakdown.length }} Kategori
            </span>
          </div>

          <div v-if="financeStore.categoryBreakdown.length === 0" class="py-12 text-center text-slate-500 text-xs">
            Belum ada transaksi pengeluaran pada bulan yang dipilih.
          </div>

          <div v-else class="space-y-3">
            <div
              v-for="cat in financeStore.categoryBreakdown"
              :key="cat.category_name"
              class="p-4 rounded-xl bg-slate-950/60 border border-slate-800/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-slate-900/50 transition"
            >
              <div class="flex items-center gap-3 min-w-0">
                <span class="w-10 h-10 rounded-xl bg-slate-800/80 border border-slate-700/60 flex items-center justify-center text-xl shrink-0">
                  {{ cat.icon || '📌' }}
                </span>
                <div class="min-w-0">
                  <h3 class="text-xs font-bold text-white truncate">{{ cat.category_name }}</h3>
                  <p class="text-[11px] text-slate-400 mt-0.5">{{ cat.percentage }}% dari total belanja bulan ini</p>
                </div>
              </div>
              <div class="sm:text-right shrink-0">
                <p class="text-sm font-black text-rose-400">{{ formatRupiah(cat.total_amount) }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useFinanceStore } from '../stores/finance';
import { useVaultStore } from '../stores/vault';
import { useToastStore } from '../stores/toast';
import { formatRupiah } from '../utils/formatters';
import api from '../services/api';

const financeStore = useFinanceStore();
const vaultStore = useVaultStore();
const toastStore = useToastStore();
const selectedMonth = ref(new Date().toISOString().slice(0, 7));

const isInitialLoading = ref(!financeStore.summary || financeStore.monthlyTrend.length === 0);
const isSyncing = ref(false);
const isError = ref(false);
const errorMessage = ref('');
const downloading = ref(false);

function formatMonthName(monthStr) {
  if (!monthStr) return '-';
  const [y, m] = monthStr.split('-');
  const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
  return `${months[parseInt(m, 10) - 1]} ${y}`;
}

async function loadAnalytics(force = false) {
  if (!financeStore.summary || financeStore.monthlyTrend.length === 0) {
    isInitialLoading.value = true;
  } else {
    isInitialLoading.value = false;
    isSyncing.value = true;
  }

  isError.value = false;
  errorMessage.value = '';

  try {
    await Promise.all([
      financeStore.fetchSummary(selectedMonth.value, force),
      financeStore.fetchCategoryBreakdown(selectedMonth.value, force),
      financeStore.fetchMonthlyTrend(force),
    ]);
  } catch (err) {
    if (!financeStore.summary) {
      isError.value = true;
      errorMessage.value = err.response?.data?.message || err.message || 'Gagal memuat analitik keuangan.';
    }
  } finally {
    isInitialLoading.value = false;
    isSyncing.value = false;
  }
}

async function downloadExcel() {
  downloading.value = true;
  try {
    const response = await api.get('/export/excel', {
      responseType: 'blob',
    });
    const blob = new Blob([response.data], {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `Laporan_Keuangan_HematWoy_${selectedMonth.value}.xlsx`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    toastStore.success('Laporan Excel berhasil diunduh.');
  } catch (err) {
    toastStore.error('Gagal mengunduh laporan Excel. Silakan coba kembali.');
  } finally {
    downloading.value = false;
  }
}

function handlePrint() {
  window.print();
}

onMounted(() => {
  loadAnalytics();
});
</script>
