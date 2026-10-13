<template>
  <div class="space-y-6">
    <!-- Header Page -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-black text-white tracking-tight">Laporan & Analisis</h1>
        <p class="text-xs text-slate-400 mt-0.5">Evaluasi tren, perbandingan bulanan, dan ekspor data keuangan</p>
      </div>

      <div class="flex items-center gap-3">
        <!-- Month Picker -->
        <input
          type="month"
          v-model="selectedMonth"
          @change="loadAnalytics"
          class="px-3.5 py-2 rounded-xl bg-slate-900 border border-slate-800 text-slate-200 text-xs font-semibold focus:outline-none focus:border-emerald-500"
        />

        <!-- Export Excel Button -->
        <button
          @click="downloadExcel"
          :disabled="downloading"
          class="px-4 py-2 rounded-xl bg-emerald-600/20 border border-emerald-500/30 hover:bg-emerald-600/30 text-emerald-300 font-bold text-xs flex items-center gap-1.5 cursor-pointer transition disabled:opacity-50"
        >
          <span>📊</span>
          <span>{{ downloading ? 'Mengunduh...' : 'Ekspor Excel' }}</span>
        </button>

        <!-- Print/PDF Button -->
        <button
          @click="handlePrint"
          class="px-4 py-2 rounded-xl bg-slate-800 border border-slate-700 hover:bg-slate-700 text-slate-200 font-bold text-xs flex items-center gap-1.5 cursor-pointer transition"
        >
          <span>🖨️</span>
          <span>Cetak PDF</span>
        </button>
      </div>
    </div>

    <!-- MONTH-OVER-MONTH VARIANCE HIGHLIGHT CARDS -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
      <!-- Income Card -->
      <div class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800">
        <span class="text-xs font-semibold text-slate-400">Total Pemasukan</span>
        <p class="text-2xl font-black text-emerald-400 mt-1">
          {{ formatRupiah(financeStore.summary?.total_income || 0) }}
        </p>
        <div class="flex items-center gap-1.5 text-xs font-medium mt-3" :class="(financeStore.summary?.delta_vs_last_month?.income_percentage || 0) >= 0 ? 'text-emerald-400' : 'text-rose-400'">
          <span>{{ (financeStore.summary?.delta_vs_last_month?.income_percentage || 0) >= 0 ? '▲' : '▼' }}</span>
          <span>{{ Math.abs(financeStore.summary?.delta_vs_last_month?.income_percentage || 0) }}% vs bulan lalu</span>
        </div>
      </div>

      <!-- Expense Card -->
      <div class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800">
        <span class="text-xs font-semibold text-slate-400">Total Pengeluaran</span>
        <p class="text-2xl font-black text-rose-400 mt-1">
          {{ formatRupiah(financeStore.summary?.total_expense || 0) }}
        </p>
        <div class="flex items-center gap-1.5 text-xs font-medium mt-3" :class="(financeStore.summary?.delta_vs_last_month?.expense_percentage || 0) <= 0 ? 'text-emerald-400' : 'text-rose-400'">
          <span>{{ (financeStore.summary?.delta_vs_last_month?.expense_percentage || 0) > 0 ? '▲' : '▼' }}</span>
          <span>{{ Math.abs(financeStore.summary?.delta_vs_last_month?.expense_percentage || 0) }}% vs bulan lalu</span>
        </div>
      </div>

      <!-- Net Surplus/Deficit -->
      <div class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800">
        <span class="text-xs font-semibold text-slate-400">Status Arus Kas</span>
        <p class="text-2xl font-black mt-1" :class="(financeStore.summary?.net_savings || 0) >= 0 ? 'text-teal-400' : 'text-amber-400'">
          {{ formatRupiah(financeStore.summary?.net_savings || 0) }}
        </p>
        <div class="text-xs font-semibold mt-3" :class="(financeStore.summary?.net_savings || 0) >= 0 ? 'text-teal-400' : 'text-amber-400'">
          <span>{{ (financeStore.summary?.net_savings || 0) >= 0 ? '✅ Surplus Bulanan Terjaga' : '⚠️ Defisit (Pengeluaran Lebih Besar)' }}</span>
        </div>
      </div>
    </div>

    <!-- 6 MONTH TREND MATRIX TABLE -->
    <div class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800">
      <div class="flex items-center justify-between mb-5">
        <div>
          <h2 class="text-base font-bold text-white">Riwayat Keuangan 6 Bulan Terakhir</h2>
          <p class="text-xs text-slate-400">Komparasi tren pemasukan, pengeluaran, dan tabungan bersih</p>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="text-slate-400 border-b border-slate-800">
              <th class="pb-3 font-semibold">Periode Bulan</th>
              <th class="pb-3 font-semibold text-right">Pemasukan</th>
              <th class="pb-3 font-semibold text-right">Pengeluaran</th>
              <th class="pb-3 font-semibold text-right">Arus Kas Bersih</th>
              <th class="pb-3 font-semibold text-center">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800/60">
            <tr
              v-for="item in financeStore.monthlyTrend"
              :key="item.month"
              class="hover:bg-slate-800/30 transition"
            >
              <td class="py-3.5 font-bold text-slate-200">{{ formatMonthName(item.month) }}</td>
              <td class="py-3.5 text-right font-bold text-emerald-400">{{ formatRupiah(item.income) }}</td>
              <td class="py-3.5 text-right font-bold text-rose-400">{{ formatRupiah(item.expense) }}</td>
              <td class="py-3.5 text-right font-bold" :class="item.income - item.expense >= 0 ? 'text-teal-400' : 'text-amber-400'">
                {{ formatRupiah(item.income - item.expense) }}
              </td>
              <td class="py-3.5 text-center">
                <span
                  class="px-2.5 py-0.5 rounded-full text-[10px] font-bold"
                  :class="item.income - item.expense >= 0 ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300'"
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
    <div class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800">
      <h2 class="text-base font-bold text-white mb-1">Rincian Pengeluaran per Kategori (Bulan Ini)</h2>
      <p class="text-xs text-slate-400 mb-5">Analisis pos pengeluaran yang paling dominan</p>

      <div v-if="financeStore.categoryBreakdown.length === 0" class="py-10 text-center text-slate-500 text-xs">
        Belum ada transaksi pengeluaran pada bulan yang dipilih.
      </div>

      <div v-else class="space-y-4">
        <div
          v-for="cat in financeStore.categoryBreakdown"
          :key="cat.category_name"
          class="p-4 rounded-xl bg-slate-950/60 border border-slate-800/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
        >
          <div class="flex items-center gap-3">
            <span class="text-2xl">{{ cat.icon || '📌' }}</span>
            <div>
              <h3 class="text-xs font-bold text-white">{{ cat.category_name }}</h3>
              <p class="text-[11px] text-slate-400">{{ cat.percentage }}% dari total pengeluaran bulan ini</p>
            </div>
          </div>
          <div class="sm:text-right">
            <p class="text-sm font-bold text-rose-400">{{ formatRupiah(cat.total_amount) }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useFinanceStore } from '../stores/finance';
import { formatRupiah } from '../utils/formatters';
import api from '../services/api';

const financeStore = useFinanceStore();
const selectedMonth = ref(new Date().toISOString().slice(0, 7));
const downloading = ref(false);

function formatMonthName(monthStr) {
  if (!monthStr) return '-';
  const [y, m] = monthStr.split('-');
  const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
  return `${months[parseInt(m, 10) - 1]} ${y}`;
}

async function loadAnalytics() {
  await Promise.allSettled([
    financeStore.fetchSummary(selectedMonth.value),
    financeStore.fetchCategoryBreakdown(selectedMonth.value),
    financeStore.fetchMonthlyTrend(),
  ]);
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
  } catch (err) {
    alert('Gagal mengunduh laporan Excel. Silakan coba kembali.');
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
