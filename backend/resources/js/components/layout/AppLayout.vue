<template>
  <div class="h-screen bg-slate-950 text-slate-100 flex flex-col md:flex-row overflow-hidden">
    <!-- Desktop Sidebar (Fixed viewport height, never stretches with content) -->
    <aside class="hidden md:flex flex-col w-64 h-full bg-slate-900/80 border-r border-slate-800/80 p-5 backdrop-blur-xl shrink-0 justify-between overflow-y-auto">
      <div>
        <!-- Logo & Brand -->
        <div class="flex items-center gap-3 px-2 mb-8">
          <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-emerald-500/20 to-teal-500/10 border border-emerald-500/30 flex items-center justify-center p-1.5 shadow-lg shadow-emerald-500/15">
            <img src="/images/logo.png" alt="HematWoy Logo" class="w-full h-full object-contain drop-shadow-[0_2px_6px_rgba(16,185,129,0.35)]" />
          </div>
          <div>
            <h1 class="text-lg font-bold tracking-tight text-white flex items-center gap-1.5">
              HematWoy
            </h1>
            <p class="text-xs text-slate-400">Pencatat Keuangan</p>
          </div>
        </div>

        <!-- Quick Action Button -->
        <button
          @click="openTransactionModal()"
          class="w-full mb-6 py-2.5 px-4 rounded-xl font-bold text-sm flex items-center justify-center gap-2 shadow-lg transition-all cursor-pointer active:scale-95"
          :class="isPro ? 'bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-slate-950 shadow-emerald-500/20' : 'bg-slate-800/90 hover:bg-slate-750 text-amber-300 border border-amber-500/40 shadow-amber-500/10'"
        >
          <span v-if="!isPro">🔒</span>
          <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
          <span>{{ isPro ? 'Catat Transaksi' : 'Catat Transaksi (Pro)' }}</span>
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

        <!-- Privacy Toggle Setting -->
        <div class="mt-6 pt-4 border-t border-slate-800/80">
          <div
            @click="vaultStore.toggleMask"
            class="w-full flex items-center justify-between px-3 py-2 rounded-xl hover:bg-slate-800/50 cursor-pointer transition select-none group"
            :title="vaultStore.isMasked ? 'Klik untuk membuka sensor saldo' : 'Klik untuk menyembunyikan nominal saldo'"
          >
            <div class="flex items-center gap-2.5">
              <svg v-if="vaultStore.isMasked" class="w-4 h-4 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/>
              </svg>
              <svg v-else class="w-4 h-4 text-slate-400 group-hover:text-slate-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
              </svg>
              <span class="text-xs font-medium" :class="vaultStore.isMasked ? 'text-slate-200' : 'text-slate-400 group-hover:text-slate-300'">
                Sembunyikan Saldo
              </span>
            </div>

            <!-- Sleek Switch Toggle -->
            <div
              class="w-8 h-4.5 rounded-full p-0.5 transition-colors duration-200 flex items-center shrink-0"
              :class="vaultStore.isMasked ? 'bg-emerald-500 justify-end shadow-sm shadow-emerald-500/25' : 'bg-slate-700 justify-start'"
            >
              <span class="w-3.5 h-3.5 rounded-full bg-white shadow-sm block transition-transform duration-200"></span>
            </div>
          </div>
        </div>
      </div>

      <!-- Subscription Quota & Upgrade Banner -->
      <div class="pt-4 border-t border-slate-800/80 mb-2">
        <!-- Active Pro Status -->
        <div v-if="isPro" class="p-3 rounded-2xl bg-amber-500/10 border border-amber-500/30 space-y-2.5 shadow-sm">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <span class="text-base">⭐</span>
              <div>
                <span class="text-xs font-bold text-amber-300 block">HematWoy PRO</span>
                <span class="text-[10px] text-slate-400 block">{{ expiryDisplay }}</span>
              </div>
            </div>
            <span class="text-[10px] px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300 font-bold border border-amber-500/30">AKTIF</span>
          </div>
          <button
            @click="openPricingModal('monthly')"
            class="w-full py-1.5 px-2.5 rounded-xl bg-slate-900/90 hover:bg-slate-850 border border-amber-500/30 hover:border-amber-500/60 text-amber-300 hover:text-amber-200 text-[11px] font-bold flex items-center justify-center gap-1.5 transition cursor-pointer active:scale-95 shadow-sm"
          >
            <span>⚡ Perpanjang Masa Aktif</span>
          </button>
        </div>

        <!-- Free Tier Quota & Upgrade CTA -->
        <div v-else class="p-3.5 rounded-2xl bg-slate-800/60 border border-slate-700/60 space-y-2.5 shadow-sm">
          <div class="flex items-center justify-between">
            <span class="text-[11px] font-bold text-slate-300">Paket Gratis</span>
            <span class="text-[10px] font-mono text-emerald-400 font-semibold">{{ remainingQuota }}/{{ freeLimit }} Kuota</span>
          </div>
          <div class="w-full bg-slate-900 rounded-full h-1.5 overflow-hidden">
            <div
              class="bg-gradient-to-r from-emerald-500 to-teal-400 h-1.5 rounded-full transition-all duration-500"
              :style="{ width: `${quotaPercentage}%` }"
            ></div>
          </div>
          <button
            @click="openPricingModal('monthly')"
            class="w-full py-2 px-3 rounded-xl bg-gradient-to-r from-amber-400 via-emerald-400 to-teal-400 text-slate-950 font-black text-xs hover:opacity-95 shadow-md shadow-amber-500/10 transition flex items-center justify-center gap-1.5 cursor-pointer active:scale-95"
          >
            <span>⭐ Upgrade ke Pro</span>
          </button>
        </div>
      </div>

      <!-- User Profile & Logout -->
      <div class="pt-2 border-t border-slate-800/80">
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
          <div class="flex items-center gap-1 shrink-0">
            <button
              @click="showDeleteAccountModal = true"
              title="Zona Bahaya: Hapus Seluruh Data"
              class="p-1.5 rounded-lg text-slate-500 hover:text-rose-400 hover:bg-rose-500/10 transition-colors cursor-pointer"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </button>
            <button
              @click="showLogoutModal = true"
              title="Keluar"
              class="p-1.5 rounded-lg text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 transition-colors cursor-pointer"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            </button>
          </div>
        </div>
      </div>
    </aside>

    <!-- Mobile Top Navigation Header -->
    <header class="md:hidden flex items-center justify-between px-3.5 py-2.5 bg-slate-900/95 border-b border-slate-800 sticky top-0 z-40 backdrop-blur-md">
      <div class="flex items-center gap-2">
        <div class="w-7 h-7 rounded-lg bg-gradient-to-tr from-emerald-500/20 to-teal-500/10 border border-emerald-500/30 flex items-center justify-center p-1 shadow">
          <img src="/images/logo.png" alt="HematWoy Logo" class="w-full h-full object-contain drop-shadow-[0_1px_4px_rgba(16,185,129,0.35)]" />
        </div>
        <span class="font-bold text-sm text-white">HematWoy</span>
      </div>
      <div class="flex items-center gap-1.5">
        <!-- Upgrade or Extend Button on Mobile -->
        <button
          @click="openPricingModal('monthly')"
          class="px-2 py-1 rounded-lg border text-[11px] font-bold flex items-center gap-1 shadow cursor-pointer active:scale-95 transition"
          :class="isPro ? 'bg-amber-500/15 border-amber-500/30 text-amber-300' : 'bg-gradient-to-r from-amber-400 to-emerald-400 text-slate-950'"
        >
          <span>⭐</span>
          <span>{{ isPro ? 'Pro' : 'Upgrade' }}</span>
        </button>

        <!-- Privacy Eye on Mobile -->
        <button
          @click="vaultStore.toggleMask"
          class="p-1.5 rounded-lg bg-slate-800 text-slate-300 hover:text-white border border-slate-700/60 transition cursor-pointer"
          :title="vaultStore.isMasked ? 'Buka sensor saldo' : 'Sembunyikan saldo'"
        >
          <svg v-if="vaultStore.isMasked" class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/>
          </svg>
          <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
          </svg>
        </button>
        <button
          @click="openTransactionModal()"
          class="p-1.5 rounded-lg font-bold transition cursor-pointer flex items-center justify-center"
          :class="isPro ? 'bg-emerald-500 text-slate-950 hover:bg-emerald-400' : 'bg-amber-500/20 text-amber-300 border border-amber-500/40'"
          :title="isPro ? 'Tambah Transaksi' : 'Fitur Terkunci (Khusus Pro)'"
        >
          <span v-if="!isPro" class="text-xs">🔒</span>
          <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        </button>
        <button
          @click="showDeleteAccountModal = true"
          class="p-1.5 rounded-lg bg-slate-800/90 text-slate-500 hover:text-rose-400 transition cursor-pointer"
          title="Zona Bahaya: Hapus Seluruh Data"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
        </button>
        <button
          @click="showLogoutModal = true"
          class="p-1.5 rounded-lg bg-slate-800/90 text-slate-400 hover:text-rose-400 transition cursor-pointer"
          title="Keluar"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7"/></svg>
        </button>
      </div>
    </header>

    <!-- Main Content Area -->
    <main class="flex-1 flex flex-col min-w-0 h-full pb-20 md:pb-6 overflow-y-auto">
      <div class="max-w-7xl w-full mx-auto p-3 sm:p-6 lg:p-8 space-y-4 sm:space-y-6">

        <!-- PROMO ADVERTISEMENT / UPSELL HEADER FOR FREE (READ-ONLY) USERS -->
        <div
          v-if="!isPro"
          class="relative rounded-2xl sm:rounded-3xl bg-gradient-to-r from-amber-500/15 via-emerald-500/10 to-teal-500/15 border border-amber-500/40 p-4 sm:p-5 shadow-2xl backdrop-blur-xl overflow-hidden animate-in fade-in slide-in-from-top-4 duration-300"
        >
          <!-- Ambient Glow Effect -->
          <div class="absolute -top-16 -right-16 w-40 h-40 bg-amber-400/15 rounded-full blur-2xl pointer-events-none"></div>
          <div class="absolute -bottom-16 -left-16 w-40 h-40 bg-emerald-400/15 rounded-full blur-2xl pointer-events-none"></div>

          <div class="relative z-10 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div class="flex items-start gap-3 sm:gap-4">
              <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-2xl bg-gradient-to-tr from-amber-400 to-amber-600 text-slate-950 flex items-center justify-center text-2xl sm:text-3xl font-black shrink-0 shadow-lg shadow-amber-500/30">
                👑
              </div>
              <div class="space-y-1">
                <div class="flex items-center gap-2 flex-wrap">
                  <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-amber-500/20 text-amber-300 text-[10px] sm:text-xs font-black border border-amber-500/40 tracking-wider uppercase">
                    <span>👁️</span>
                    <span>Mode Pratinjau (Lihat Saja)</span>
                  </span>
                  <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-rose-300 bg-rose-500/15 px-2 py-0.5 rounded-full border border-rose-500/30">
                    <span>🔒</span>
                    <span>Aksi Mutasi Dikunci</span>
                  </span>
                </div>
                <h2 class="text-sm sm:text-base md:text-lg font-black text-white tracking-tight">
                  Tingkatkan ke HematWoy Pro untuk Membuka Semua Fitur!
                </h2>
                <p class="text-xs sm:text-sm text-slate-300 leading-relaxed max-w-3xl">
                  Anda saat ini dapat melihat mutasi transaksi. Upgrade ke akun Pro untuk membuka pencatatan di web, kelola multi-dompet tanpa batas, atur batas anggaran belanja bulanan, target tabungan, dan ekspor spreadsheet mutasi resmi!
                </p>
              </div>
            </div>

            <div class="flex items-center gap-2 sm:gap-3 shrink-0 self-start lg:self-auto w-full lg:w-auto">
              <button
                @click="openPricingModal('monthly')"
                class="flex-1 lg:flex-initial px-5 sm:px-6 py-2.5 sm:py-3 rounded-xl bg-gradient-to-r from-amber-400 via-amber-300 to-emerald-400 hover:from-amber-300 hover:to-emerald-300 text-slate-950 font-black text-xs sm:text-sm flex items-center justify-center gap-2 shadow-xl shadow-amber-500/25 hover:scale-102 active:scale-95 transition cursor-pointer"
              >
                <span>⭐ Langganan Sekarang</span>
                <span class="hidden sm:inline text-[11px] font-bold opacity-80">(Mulai Rp15.000/bln)</span>
              </button>
            </div>
          </div>
        </div>

        <slot />
      </div>
    </main>

    <!-- Mobile Bottom Navigation Bar -->
    <nav class="md:hidden fixed bottom-0 left-0 right-0 bg-slate-900/95 border-t border-slate-800/90 py-1.5 px-1 flex justify-around items-center z-40 backdrop-blur-lg safe-bottom">
      <RouterLink
        v-for="item in navItems"
        :key="item.path"
        :to="item.path"
        class="flex flex-col items-center justify-center py-1 px-1 rounded-xl text-[9px] sm:text-[10px] font-medium transition flex-1 max-w-[62px] text-center"
        :class="isActive(item.path) ? 'text-emerald-400 font-bold bg-emerald-500/10' : 'text-slate-400 hover:text-slate-200'"
      >
        <span class="text-base leading-none mb-0.5">{{ item.icon }}</span>
        <span class="truncate w-full block text-center">{{ item.label }}</span>
      </RouterLink>
    </nav>

    <!-- Global Zero-Knowledge Security Vault Modal -->
    <VaultModal />

    <!-- Global Interactive Toast Notifications -->
    <ToastContainer />

    <!-- In-App Subscription Upgrade Modal -->
    <PricingModal
      :isOpen="authStore.showPricingModal || showPricingModal"
      :initialPlan="authStore.pricingPlan || pricingModalPlan"
      @close="authStore.closePricing(); showPricingModal = false"
      @upgraded="onSubscriptionUpgraded"
    />

    <!-- Confirmation Modal for Logout -->
    <ConfirmModal
      :isOpen="showLogoutModal"
      title="Konfirmasi Keluar"
      message="Apakah Anda yakin ingin keluar dari akun HematWoy?"
      icon="🚪"
      confirmText="Keluar"
      cancelText="Batal"
      :danger="true"
      @confirm="confirmLogout"
      @cancel="showLogoutModal = false"
    />

    <!-- Danger Zone: Permanent Account & Data Deletion Modal -->
    <DeleteAccountModal
      :isOpen="showDeleteAccountModal"
      @close="showDeleteAccountModal = false"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, provide } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../../services/api';
import { useAuthStore } from '../../stores/auth';
import { useVaultStore } from '../../stores/vault';
import { useToastStore } from '../../stores/toast';
import VaultModal from '../vault/VaultModal.vue';
import ToastContainer from '../common/ToastContainer.vue';
import ConfirmModal from '../common/ConfirmModal.vue';
import PricingModal from '../subscription/PricingModal.vue';
import DeleteAccountModal from '../modals/DeleteAccountModal.vue';

const emit = defineEmits(['open-transaction-modal']);

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const vaultStore = useVaultStore();
const toastStore = useToastStore();

const showLogoutModal = ref(false);
const showDeleteAccountModal = ref(false);
const showPricingModal = ref(false);
const pricingModalPlan = ref('monthly');
const subscription = ref(null);

async function fetchSubscriptionStatus() {
  try {
    const res = await api.get('/subscription/status');
    subscription.value = res.data.data;
  } catch (err) {
    // Graceful fallback
  }
}

const isPro = computed(() => {
  return authStore.isPro || subscription.value?.is_pro || authStore.user?.tier === 'PREMIUM';
});

const freeLimit = computed(() => {
  return subscription.value?.free_limit ?? (typeof window !== 'undefined' && window.__BOT_CONFIG__?.free_limit ? window.__BOT_CONFIG__.free_limit : 100);
});

const remainingQuota = computed(() => {
  return subscription.value?.remaining ?? (freeLimit.value - (subscription.value?.used ?? 0));
});

const quotaPercentage = computed(() => {
  const limit = freeLimit.value || 100;
  const used = subscription.value?.used ?? (limit - remainingQuota.value);
  return limit > 0 ? Math.min(100, Math.max(5, Math.round((used / limit) * 100))) : 100;
});

const expiryDisplay = computed(() => {
  const exp = subscription.value?.expires_at || authStore.user?.subscription_expires_at;
  if (!exp) return 'Aktif';
  try {
    const d = new Date(exp);
    return 's/d ' + d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
  } catch {
    return 'Aktif';
  }
});

function openPricingModal(plan = 'monthly') {
  pricingModalPlan.value = plan;
  showPricingModal.value = true;
  authStore.openPricing(plan);
}

provide('open-pricing-modal', openPricingModal);

async function onSubscriptionUpgraded() {
  await fetchSubscriptionStatus();
  await authStore.fetchProfile();
}

onMounted(() => {
  if (!authStore.isAuthenticated) return;

  vaultStore.fetchStatus();
  fetchSubscriptionStatus();

  if (route.query.upgrade === 'pro') {
    openPricingModal(route.query.plan === 'annual' ? 'annual' : 'monthly');
  }
});

function toggleVaultLock() {
  if (vaultStore.isUnlocked) {
    vaultStore.lock();
    toastStore.info('Brankas berhasil dikunci.');
  } else {
    vaultStore.openModal();
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

function confirmLogout() {
  showLogoutModal.value = false;
  toastStore.info('Mengakhiri sesi...');
  authStore.logout();
}

function openTransactionModal() {
  if (!isPro.value) {
    toastStore.warning('Pencatatan mutasi di web terkunci. Silakan upgrade ke HematWoy Pro!');
    openPricingModal('monthly');
    return;
  }
  emit('open-transaction-modal');
}
</script>
