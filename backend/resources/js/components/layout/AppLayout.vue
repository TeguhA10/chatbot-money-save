<template>
  <div class="min-h-screen bg-slate-950 text-slate-100 flex flex-col md:flex-row">
    <!-- Desktop Sidebar -->
    <aside class="hidden md:flex flex-col w-64 bg-slate-900/80 border-r border-slate-800/80 p-5 backdrop-blur-xl shrink-0 justify-between">
      <div>
        <!-- Logo & Brand -->
        <div class="flex items-center gap-3 px-2 mb-8">
          <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-emerald-600 to-teal-400 flex items-center justify-center text-white shadow-lg shadow-emerald-500/20 font-bold text-xl">
            HW
          </div>
          <div>
            <h1 class="text-lg font-bold tracking-tight text-white flex items-center gap-1.5">
              HematWoy <span class="text-xs font-semibold px-1.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">AI</span>
            </h1>
            <p class="text-xs text-slate-400">Personal Finance</p>
          </div>
        </div>

        <!-- Quick Action Button -->
        <button
          @click="openTransactionModal()"
          class="w-full mb-6 py-2.5 px-4 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-slate-950 font-semibold text-sm flex items-center justify-center gap-2 shadow-lg shadow-emerald-500/20 transition-all cursor-pointer active:scale-95"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
          Catat Transaksi
        </button>

        <!-- Navigation Links -->
        <nav class="space-y-1.5">
          <RouterLink
            v-for="item in navItems"
            :key="item.path"
            :to="item.path"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-colors"
            :class="isActive(item.path) ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/20 font-semibold' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60'"
          >
            <span class="text-lg">{{ item.icon }}</span>
            <span>{{ item.label }}</span>
          </RouterLink>
        </nav>

        <!-- Security & Privacy Controls -->
        <div class="mt-6 pt-4 border-t border-slate-800/80 space-y-2">
          <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider px-2">Keamanan Zero-Knowledge</div>
          <!-- Vault Status & Instant Lock -->
          <button
            @click="toggleVaultLock"
            class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs font-medium transition-colors cursor-pointer"
            :class="vaultStore.isUnlocked ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 hover:bg-rose-500/10 hover:text-rose-400 hover:border-rose-500/30' : 'bg-rose-500/15 text-rose-400 border border-rose-500/30 font-bold'"
            :title="vaultStore.isUnlocked ? 'Klik untuk Mengunci Vault' : 'Vault Terkunci'"
          >
            <span class="flex items-center gap-2">
              <span>{{ vaultStore.isUnlocked ? '🛡️' : '🔒' }}</span>
              <span>{{ vaultStore.isUnlocked ? 'Vault Aktif' : 'Vault Terkunci' }}</span>
            </span>
            <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-800 text-slate-300">
              {{ vaultStore.isUnlocked ? 'Kunci' : 'Buka' }}
            </span>
          </button>

          <!-- Privacy Eye (Shoulder Surfing Mask) -->
          <button
            @click="vaultStore.toggleMask"
            class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs font-medium text-slate-300 bg-slate-800/50 hover:bg-slate-800 border border-slate-700/60 transition-colors cursor-pointer"
            :title="vaultStore.isMasked ? 'Tampilkan Nominal' : 'Samarkan Nominal'"
          >
            <span class="flex items-center gap-2">
              <span>{{ vaultStore.isMasked ? '👁️' : '👁️‍🗨️' }}</span>
              <span>{{ vaultStore.isMasked ? 'Nomor Disamarkan' : 'Sensor Nominal' }}</span>
            </span>
            <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-900 font-mono text-slate-400">
              {{ vaultStore.isMasked ? 'ON' : 'OFF' }}
            </span>
          </button>
        </div>
      </div>

      <!-- User Profile & Logout -->
      <div class="pt-4 border-t border-slate-800/80">
        <div class="flex items-center justify-between p-2 rounded-xl bg-slate-800/40 border border-slate-800">
          <div class="flex items-center gap-2.5 truncate">
            <div class="w-8 h-8 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center font-bold text-xs uppercase border border-emerald-500/30">
              {{ userInitial }}
            </div>
            <div class="truncate">
              <p class="text-xs font-medium text-slate-200 truncate">{{ authStore.user?.display_name || authStore.user?.name || 'Pengguna' }}</p>
              <p class="text-[11px] text-slate-400 truncate">{{ formattedPhone }}</p>
            </div>
          </div>
          <button
            @click="handleLogout"
            title="Keluar"
            class="p-1.5 rounded-lg text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 transition-colors cursor-pointer"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
          </button>
        </div>
      </div>
    </aside>

    <!-- Mobile Top Navigation Header -->
    <header class="md:hidden flex items-center justify-between p-4 bg-slate-900/90 border-b border-slate-800 sticky top-0 z-40 backdrop-blur-md">
      <div class="flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-emerald-600 to-teal-400 flex items-center justify-center text-white font-bold text-sm shadow">
          HW
        </div>
        <span class="font-bold text-base text-white">HematWoy</span>
      </div>
      <div class="flex items-center gap-2">
        <button
          @click="openTransactionModal()"
          class="p-2 rounded-lg bg-emerald-500 text-slate-950 font-bold hover:bg-emerald-400 transition cursor-pointer"
          title="Tambah Transaksi"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        </button>
        <button
          @click="handleLogout"
          class="p-2 rounded-lg bg-slate-800 text-slate-400 hover:text-rose-400"
          title="Keluar"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7"/></svg>
        </button>
      </div>
    </header>

    <!-- Main Content Area -->
    <main class="flex-1 flex flex-col min-w-0 pb-20 md:pb-6 overflow-y-auto">
      <div class="max-w-7xl w-full mx-auto p-4 sm:p-6 lg:p-8 space-y-6">
        <slot />
      </div>
    </main>

    <!-- Mobile Bottom Navigation Bar -->
    <nav class="md:hidden fixed bottom-0 left-0 right-0 bg-slate-900/95 border-t border-slate-800/90 py-2 px-3 flex justify-around items-center z-40 backdrop-blur-lg">
      <RouterLink
        v-for="item in navItems"
        :key="item.path"
        :to="item.path"
        class="flex flex-col items-center gap-1 py-1 px-2 rounded-lg text-[10px] font-medium transition"
        :class="isActive(item.path) ? 'text-emerald-400 font-bold' : 'text-slate-400 hover:text-slate-200'"
      >
        <span class="text-base">{{ item.icon }}</span>
        <span>{{ item.label }}</span>
      </RouterLink>
    </nav>

    <!-- Global Zero-Knowledge Security Vault Modal -->
    <VaultModal />

    <!-- Global Interactive Toast Notifications -->
    <ToastContainer />
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { useVaultStore } from '../../stores/vault';
import { useToastStore } from '../../stores/toast';
import VaultModal from '../vault/VaultModal.vue';
import ToastContainer from '../common/ToastContainer.vue';

const emit = defineEmits(['open-transaction-modal']);

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const vaultStore = useVaultStore();
const toastStore = useToastStore();

onMounted(() => {
  vaultStore.fetchStatus();
});

function toggleVaultLock() {
  if (vaultStore.isUnlocked) {
    vaultStore.lock();
    toastStore.info('Vault keamanan berhasil dikunci.');
  } else {
    vaultStore.fetchStatus();
  }
}

const navItems = [
  { path: '/dashboard', label: 'Dashboard', icon: '📊' },
  { path: '/transactions', label: 'Transaksi', icon: '💸' },
  { path: '/wallets', label: 'Dompet', icon: '👛' },
  { path: '/budgets', label: 'Budget', icon: '🎯' },
  { path: '/goals', label: 'Target', icon: '🏆' },
  { path: '/analytics', label: 'Laporan', icon: '📈' },
];

function isActive(path) {
  return route.path === path;
}

const userInitial = computed(() => {
  const name = authStore.user?.display_name || authStore.user?.name || authStore.user?.jid || 'U';
  return name.charAt(0).toUpperCase();
});

const formattedPhone = computed(() => {
  const raw = authStore.user?.phone || authStore.user?.jid?.replace('@s.whatsapp.net', '') || '';
  if (!raw) return '-';
  if (raw.startsWith('62')) {
    return '0' + raw.substring(2);
  }
  return raw;
});

function handleLogout() {
  if (confirm('Apakah Anda yakin ingin keluar?')) {
    toastStore.info('Mengakhiri sesi...');
    authStore.logout();
  }
}

function openTransactionModal() {
  emit('open-transaction-modal');
}
</script>
