<template>
  <div class="space-y-6">
    <!-- Header Page & Controls -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-black text-white tracking-tight">Kondisi Keuangan</h1>
        <p class="text-xs text-slate-400 mt-0.5">Ringkasan kesehatan finansial & arus kas Anda</p>
      </div>

      <div class="flex items-center gap-2 sm:gap-3 w-full sm:w-auto">
        <!-- Month Picker -->
        <input
          type="month"
          v-model="selectedMonth"
          @change="loadDashboard"
          class="flex-1 sm:flex-initial px-3 py-2 rounded-xl bg-slate-900 border border-slate-800 text-slate-200 text-xs font-semibold focus:outline-none focus:border-emerald-500"
        />

        <button
          @click="$emit('open-transaction-modal')"
          class="flex-1 sm:flex-initial px-3.5 py-2 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 text-slate-950 font-bold text-xs flex items-center justify-center gap-1.5 shadow-lg shadow-emerald-500/20 cursor-pointer transition active:scale-95"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
          <span class="whitespace-nowrap">+ Transaksi</span>
        </button>
      </div>
    </div>

    <!-- WHATSAPP BOT QUICK BANNER -->
    <div class="p-5 rounded-2xl bg-gradient-to-r from-emerald-950/60 via-slate-900 to-teal-950/60 border border-emerald-500/30 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-xl">
      <div class="flex items-center gap-3.5">
        <div class="w-12 h-12 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-2xl shrink-0 border border-emerald-500/30">
          💬
        </div>
        <div>
          <h2 class="text-sm font-bold text-white flex items-center gap-2">
            Pencatatan Keuangan Praktis
            <span class="text-[10px] px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-400 font-semibold border border-emerald-500/30">Aktif</span>
          </h2>
          <p class="text-xs text-slate-300 mt-0.5">
            Ketik pengeluaran langsung via pesan chat, contoh: <code class="px-1.5 py-0.5 rounded bg-slate-950 text-emerald-300 text-[11px] font-mono">keluar 25rb makan</code> atau <code class="px-1.5 py-0.5 rounded bg-slate-950 text-emerald-300 text-[11px] font-mono">saldo</code>
          </p>
        </div>
      </div>
      <a
        :href="whatsappBotLink"
        target="_blank"
        rel="noopener noreferrer"
        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-xs transition shadow-lg shadow-emerald-500/20 shrink-0 cursor-pointer active:scale-95"
      >
        <span>Buka Chat Bot</span>
        <span>➔</span>
      </a>
    </div>

    <!-- 4 KPI CARDS -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <!-- Total Balance -->
      <div class="p-5 rounded-2xl bg-gradient-to-br from-slate-900 to-slate-900/60 border border-slate-800 relative overflow-hidden group hover:border-slate-700 transition">
        <div class="flex items-center justify-between mb-3">
          <span class="text-xs font-semibold text-slate-400">Total Saldo</span>
          <span class="p-2 rounded-xl bg-emerald-500/10 text-emerald-400 text-sm">👛</span>
        </div>
        <p class="text-2xl font-black text-white tracking-tight">
          {{ formatRupiah(financeStore.totalBalance) }}
        </p>
        <p class="text-[11px] text-slate-500 mt-2 flex items-center gap-1">
          <span>Dari {{ financeStore.wallets.length }} dompet aktif</span>
        </p>
      </div>

      <!-- Income This Month -->
      <div class="p-5 rounded-2xl bg-gradient-to-br from-slate-900 to-slate-900/60 border border-slate-800 relative overflow-hidden group hover:border-slate-700 transition">
        <div class="flex items-center justify-between mb-3">
          <span class="text-xs font-semibold text-slate-400">Pemasukan Bulan Ini</span>
          <span class="p-2 rounded-xl bg-emerald-500/10 text-emerald-400 text-sm">📈</span>
        </div>
        <p class="text-2xl font-black text-emerald-400 tracking-tight">
          {{ formatRupiah(financeStore.summary?.total_income || 0) }}
        </p>
        <p class="text-[11px] text-slate-500 mt-2">
          Dibanding bulan lalu: <span class="text-slate-300 font-medium">{{ financeStore.summary?.delta_vs_last_month?.income_percentage || 0 }}%</span>
        </p>
      </div>

      <!-- Expense This Month -->
      <div class="p-5 rounded-2xl bg-gradient-to-br from-slate-900 to-slate-900/60 border border-slate-800 relative overflow-hidden group hover:border-slate-700 transition">
        <div class="flex items-center justify-between mb-3">
          <span class="text-xs font-semibold text-slate-400">Pengeluaran Bulan Ini</span>
          <span class="p-2 rounded-xl bg-rose-500/10 text-rose-400 text-sm">📉</span>
        </div>
        <p class="text-2xl font-black text-rose-400 tracking-tight">
          {{ formatRupiah(financeStore.summary?.total_expense || 0) }}
        </p>
        <p class="text-[11px] text-slate-500 mt-2">
          Dibanding bulan lalu: <span class="text-slate-300 font-medium">{{ financeStore.summary?.delta_vs_last_month?.expense_percentage || 0 }}%</span>
        </p>
      </div>

      <!-- Net Savings (Cashflow) -->
      <div class="p-5 rounded-2xl bg-gradient-to-br from-slate-900 to-slate-900/60 border border-slate-800 relative overflow-hidden group hover:border-slate-700 transition">
        <div class="flex items-center justify-between mb-3">
          <span class="text-xs font-semibold text-slate-400">Sisa Saldo Bulan Ini</span>
          <span class="p-2 rounded-xl bg-blue-500/10 text-blue-400 text-sm">⚖️</span>
        </div>
        <p
          class="text-2xl font-black tracking-tight"
          :class="(financeStore.summary?.net_savings || 0) >= 0 ? 'text-teal-400' : 'text-amber-400'"
        >
          {{ formatRupiah(financeStore.summary?.net_savings || 0) }}
        </p>
        <p class="text-[11px] mt-2 font-medium" :class="(financeStore.summary?.net_savings || 0) >= 0 ? 'text-emerald-400' : 'text-amber-400'">
          {{ (financeStore.summary?.net_savings || 0) >= 0 ? 'Surplus (Tabungan Bertambah)' : 'Defisit (Pengeluaran > Pemasukan)' }}
        </p>
      </div>
    </div>

    <!-- 2 COLUMN LAYOUT: CHARTS & BREAKDOWN -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- 6 Month Cashflow Trend (2 cols) -->
      <div class="lg:col-span-2 p-6 rounded-2xl bg-slate-900/80 border border-slate-800">
        <div class="flex items-center justify-between mb-6">
          <div>
            <h2 class="text-base font-bold text-white">Tren Arus Kas 6 Bulan</h2>
            <p class="text-xs text-slate-400">Perbandingan pemasukan vs pengeluaran</p>
          </div>
          <div class="flex items-center gap-4 text-xs font-medium">
            <span class="flex items-center gap-1.5 text-emerald-400">
              <span class="w-3 h-3 rounded bg-emerald-500"></span> Pemasukan
            </span>
            <span class="flex items-center gap-1.5 text-rose-400">
              <span class="w-3 h-3 rounded bg-rose-500"></span> Pengeluaran
            </span>
          </div>
        </div>

        <!-- Custom SVG Bar Chart -->
        <div class="overflow-x-auto pb-1">
          <div class="h-64 min-w-[280px] flex items-end justify-between gap-2 sm:gap-3 pt-6 border-b border-slate-800">
            <div
              v-for="item in financeStore.monthlyTrend"
              :key="item.month"
              class="flex-1 flex flex-col items-center gap-2 h-full justify-end group"
            >
              <div class="w-full flex items-end justify-center gap-1 sm:gap-1.5 h-48">
                <!-- Income bar -->
                <div
                  class="w-3.5 sm:w-6 bg-gradient-to-t from-emerald-600 to-emerald-400 rounded-t-md transition-all duration-300 relative group-hover:brightness-110"
                  :style="{ height: `${calculateBarHeight(item.income)}%` }"
                  :title="`Pemasukan: ${formatRupiah(item.income)}`"
                ></div>
                <!-- Expense bar -->
                <div
                  class="w-3.5 sm:w-6 bg-gradient-to-t from-rose-600 to-rose-400 rounded-t-md transition-all duration-300 relative group-hover:brightness-110"
                  :style="{ height: `${calculateBarHeight(item.expense)}%` }"
                  :title="`Pengeluaran: ${formatRupiah(item.expense)}`"
                ></div>
              </div>
              <span class="text-[10px] sm:text-[11px] font-medium text-slate-400 whitespace-nowrap">{{ formatMonthLabel(item.month) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Expense Category Breakdown (1 col) -->
      <div class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800 flex flex-col justify-between">
        <div>
          <h2 class="text-base font-bold text-white mb-1">Pengeluaran per Kategori</h2>
          <p class="text-xs text-slate-400 mb-5">Distribusi belanja bulan ini</p>

          <div v-if="financeStore.categoryBreakdown.length === 0" class="py-12 text-center text-slate-500 text-xs">
            Belum ada data pengeluaran bulan ini.
          </div>

          <div v-else class="space-y-3.5">
            <div
              v-for="cat in financeStore.categoryBreakdown.slice(0, 5)"
              :key="cat.category_name"
              class="space-y-1.5"
            >
              <div class="flex items-center justify-between text-xs">
                <span class="font-medium text-slate-200 flex items-center gap-1.5">
                  <span>{{ cat.icon || '📌' }}</span>
                  <span>{{ cat.category_name }}</span>
                </span>
                <span class="font-semibold text-white">
                  {{ formatRupiah(cat.total_amount) }}
                  <span class="text-[10px] text-slate-400">({{ cat.percentage }}%)</span>
                </span>
              </div>
              <div class="w-full bg-slate-800 h-2 rounded-full overflow-hidden">
                <div
                  class="bg-gradient-to-r from-teal-500 to-emerald-400 h-full rounded-full transition-all"
                  :style="{ width: `${cat.percentage}%` }"
                ></div>
              </div>
            </div>
          </div>
        </div>

        <RouterLink
          to="/analytics"
          class="mt-6 pt-3 border-t border-slate-800 text-center text-xs font-semibold text-emerald-400 hover:text-emerald-300 transition block"
        >
          Lihat Analisis Lengkap ➔
        </RouterLink>
      </div>
    </div>

    <!-- 3 SUB WIDGETS: WALLETS, BUDGET ALERTS, & RECENT TRANSACTIONS -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <!-- Quick Wallets View -->
      <div class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-base font-bold text-white">Dompet Saya</h2>
          <RouterLink to="/wallets" class="text-xs text-emerald-400 hover:underline">Semua</RouterLink>
        </div>
        <div class="space-y-2.5">
          <div
            v-for="w in financeStore.wallets.slice(0, 4)"
            :key="w.id"
            class="p-3 rounded-xl bg-slate-950/60 border border-slate-800/80 flex items-center justify-between"
          >
            <div class="flex items-center gap-2.5">
              <span class="text-lg">{{ getWalletIcon(w.type) }}</span>
              <div>
                <p class="text-xs font-semibold text-slate-200">{{ w.name }}</p>
                <p class="text-[10px] text-slate-400 uppercase tracking-wider">{{ w.type }}</p>
              </div>
            </div>
            <span class="text-xs font-bold text-emerald-400">{{ formatRupiah(w.balance) }}</span>
          </div>
        </div>
      </div>

      <!-- Budget Approaching Limit Alert -->
      <div class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-base font-bold text-white">Status Anggaran</h2>
          <RouterLink to="/budgets" class="text-xs text-emerald-400 hover:underline">Semua</RouterLink>
        </div>
        <div v-if="financeStore.budgets.length === 0" class="py-8 text-center text-slate-500 text-xs">
          Belum ada anggaran yang diatur.
        </div>
        <div v-else class="space-y-3">
          <div
            v-for="b in financeStore.budgets.slice(0, 3)"
            :key="b.id"
            class="p-3 rounded-xl bg-slate-950/60 border border-slate-800/80 space-y-2"
          >
            <div class="flex items-center justify-between text-xs">
              <span class="font-medium text-slate-200">{{ b.category_icon }} {{ b.category_name }}</span>
              <span
                class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                :class="b.percentage >= 90 ? 'bg-rose-500/20 text-rose-400 border border-rose-500/30' : (b.percentage >= 70 ? 'bg-amber-500/20 text-amber-400' : 'bg-emerald-500/20 text-emerald-400')"
              >
                {{ b.percentage }}% Terpakai
              </span>
            </div>
            <div class="w-full bg-slate-800 h-2 rounded-full overflow-hidden">
              <div
                class="h-full rounded-full transition-all"
                :class="b.percentage >= 90 ? 'bg-rose-500' : (b.percentage >= 70 ? 'bg-amber-500' : 'bg-emerald-500')"
                :style="{ width: `${Math.min(b.percentage, 100)}%` }"
              ></div>
            </div>
            <div class="flex justify-between text-[11px] text-slate-400">
              <span>Sisa: {{ formatRupiah(b.remaining_amount) }}</span>
              <span>Batas: {{ formatRupiah(b.limit_amount) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Goals Milestones -->
      <div class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-base font-bold text-white">Target Tabungan</h2>
          <RouterLink to="/goals" class="text-xs text-emerald-400 hover:underline">Semua</RouterLink>
        </div>
        <div v-if="financeStore.goals.length === 0" class="py-8 text-center text-slate-500 text-xs">
          Belum ada target tabungan aktif.
        </div>
        <div v-else class="space-y-3">
          <div
            v-for="g in financeStore.goals.slice(0, 3)"
            :key="g.id"
            class="p-3 rounded-xl bg-slate-950/60 border border-slate-800/80 space-y-2"
          >
            <div class="flex items-center justify-between text-xs">
              <span class="font-bold text-white">🏆 {{ g.name }}</span>
              <span class="text-emerald-400 font-bold text-xs">{{ g.progress_percent }}%</span>
            </div>
            <div class="w-full bg-slate-800 h-2 rounded-full overflow-hidden">
              <div
                class="bg-gradient-to-r from-emerald-500 to-teal-400 h-full rounded-full transition-all"
                :style="{ width: `${Math.min(g.progress_percent, 100)}%` }"
              ></div>
            </div>
            <div class="flex justify-between text-[11px] text-slate-400">
              <span>Terkumpul: {{ formatRupiah(g.current_amount) }}</span>
              <span>Target: {{ formatRupiah(g.target_amount) }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- RECENT TRANSACTIONS TABLE -->
    <div class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h2 class="text-base font-bold text-white">Transaksi Terbaru</h2>
          <p class="text-xs text-slate-400">5 transaksi terakhir yang tercatat</p>
        </div>
        <RouterLink to="/transactions" class="text-xs font-semibold text-emerald-400 hover:underline">
          Lihat Semua Transaksi ➔
        </RouterLink>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="text-slate-400 border-b border-slate-800">
              <th class="pb-3 font-semibold">Tanggal</th>
              <th class="pb-3 font-semibold">Deskripsi</th>
              <th class="pb-3 font-semibold">Kategori</th>
              <th class="pb-3 font-semibold text-right">Nominal</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800/60">
            <tr v-if="financeStore.transactions.length === 0">
              <td colspan="4" class="py-8 text-center text-slate-500">
                Belum ada riwayat transaksi yang tercatat.
              </td>
            </tr>
            <tr
              v-for="t in financeStore.transactions.slice(0, 5)"
              :key="t.id"
              class="hover:bg-slate-800/30 transition"
            >
              <td class="py-3 text-slate-400 whitespace-nowrap">{{ formatDate(t.transaction_date) }}</td>
              <td class="py-3 font-medium text-slate-200">
                <div class="flex items-center gap-1.5">
                  <span>{{ t.description }}</span>
                  <span v-if="t.type === 'TRANSFER'" class="px-1.5 py-0.5 rounded text-[9px] bg-blue-500/20 text-blue-400 font-semibold">TRANSFER</span>
                </div>
              </td>
              <td class="py-3 text-slate-300">
                <span v-if="t.category">{{ t.category.icon }} {{ t.category.name }}</span>
                <span v-else class="text-slate-500">-</span>
              </td>
              <td
                class="py-3 text-right font-bold whitespace-nowrap"
                :class="vaultStore.isMasked ? 'text-slate-400' : (t.type === 'INCOME' ? 'text-emerald-400' : (t.type === 'TRANSFER' ? 'text-blue-400' : 'text-rose-400'))"
              >
                <span v-if="vaultStore.isMasked">Rp ••••••••</span>
                <span v-else>{{ t.type === 'INCOME' ? '+' : (t.type === 'TRANSFER' ? '↔ ' : '-') }}{{ formatRupiah(t.amount) }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useFinanceStore } from '../stores/finance';
import { useVaultStore } from '../stores/vault';
import { formatRupiah as rawFormatRupiah, formatDate } from '../utils/formatters';

defineEmits(['open-transaction-modal']);

const financeStore = useFinanceStore();
const vaultStore = useVaultStore();

const now = new Date();
const localMonth = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
const selectedMonth = ref(localMonth);

function formatRupiah(val) {
  if (vaultStore.isMasked) return 'Rp ••••••••';
  return rawFormatRupiah(val ?? 0);
}

function getWalletIcon(type) {
  if (type === 'BANK') return '🏦';
  if (type === 'EWALLET') return '📱';
  if (type === 'CASH') return '💵';
  return '📁';
}

function formatMonthLabel(monthStr) {
  if (!monthStr) return '';
  const [, m] = monthStr.split('-');
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
  return months[parseInt(m, 10) - 1] || monthStr;
}

function calculateBarHeight(val) {
  const max = Math.max(
    ...financeStore.monthlyTrend.map((t) => Math.max(t.income, t.expense)),
    1000000
  );
  return Math.min(Math.round((val / max) * 100), 100);
}

async function loadDashboard() {
  await financeStore.fetchDashboardFeed(selectedMonth.value);
}

onMounted(() => {
  loadDashboard();
});
</script>
