<template>
  <nav class="sticky top-0 z-50 w-full border-b border-slate-800/80 bg-slate-950/85 backdrop-blur-xl">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
      <!-- Logo -->
      <router-link to="/" class="flex items-center gap-3 group">
        <div class="w-10 h-10 rounded-2xl overflow-hidden bg-slate-900 border border-emerald-500/30 flex items-center justify-center p-1 shadow-lg shadow-emerald-500/20 group-hover:scale-105 group-hover:border-emerald-500/60 transition">
          <img src="/images/logo.png" alt="HematWoy Logo" class="w-full h-full object-contain" />
        </div>
        <div>
          <span class="font-black text-lg text-white tracking-tight">HematWoy</span>
          <span class="text-[10px] text-emerald-400 font-bold block -mt-1 tracking-wider uppercase">Finance Bot</span>
        </div>
      </router-link>

      <!-- Desktop Menu Links -->
      <div class="hidden md:flex items-center gap-7 text-sm font-medium text-slate-400">
        <router-link
          to="/"
          class="hover:text-emerald-400 transition"
          :class="{ 'text-emerald-400 font-semibold': activeRoute === 'landing' && !isScrolledSection }"
        >
          Beranda
        </router-link>
        <a
          href="/#fitur"
          class="hover:text-emerald-400 transition"
          @click="handleAnchorClick('/#fitur', $event)"
        >
          Fitur Utama
        </a>
        <router-link
          to="/dokumentasi"
          class="flex items-center gap-1.5 transition"
          :class="activeRoute === 'documentation' ? 'text-emerald-400 font-bold' : 'hover:text-emerald-400'"
        >
          <span>📖</span>
          <span>Dokumentasi</span>
          <span class="text-[9px] px-1.5 py-0.2 rounded-full bg-emerald-500/20 text-emerald-300 font-bold border border-emerald-500/30">
            Panduan
          </span>
        </router-link>
        <a
          href="/#harga"
          class="hover:text-emerald-400 transition"
          @click="handleAnchorClick('/#harga', $event)"
        >
          Paket Harga
        </a>
        <a
          href="/#faq"
          class="hover:text-emerald-400 transition"
          @click="handleAnchorClick('/#faq', $event)"
        >
          FAQ
        </a>
      </div>

      <!-- Action CTAs: Dashboard Direct Access & WhatsApp Button -->
      <div class="flex items-center gap-2 sm:gap-3">
        <!-- Dashboard Direct Button -->
        <button
          type="button"
          @click="handleDashboardClick"
          class="inline-flex items-center gap-1.5 sm:gap-2 px-3 py-2 sm:px-4 sm:py-2.5 rounded-xl bg-slate-900/90 hover:bg-slate-800 border border-slate-700/80 hover:border-emerald-500/50 text-slate-200 hover:text-white text-xs sm:text-sm font-bold shadow-md shadow-slate-950/40 hover:shadow-emerald-500/10 active:scale-95 transition-all cursor-pointer group"
        >
          <span class="text-emerald-400 group-hover:scale-110 transition-transform">📊</span>
          <span>Dashboard</span>
          <span
            v-if="authStore.isAuthenticated && authStore.isPro"
            class="hidden sm:inline-block text-[9px] px-1.5 py-0.5 rounded-md bg-amber-500/20 text-amber-300 font-extrabold border border-amber-500/30"
          >
            PRO
          </span>
        </button>

        <!-- WhatsApp Chat Button -->
        <a
          :href="whatsappDirectUrl"
          target="_blank"
          class="inline-flex items-center gap-1.5 sm:gap-2 px-3.5 py-2 sm:px-4 sm:py-2.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-400 text-slate-950 font-black text-xs sm:text-sm shadow-lg shadow-emerald-500/20 hover:scale-105 active:scale-95 transition cursor-pointer"
        >
          <span>💬</span>
          <span class="hidden sm:inline">Chat</span>
          <span>WhatsApp</span>
        </a>

        <!-- Mobile Menu Toggle Button -->
        <button
          type="button"
          @click="mobileMenuOpen = !mobileMenuOpen"
          class="md:hidden p-2 rounded-xl bg-slate-900 border border-slate-800 text-slate-400 hover:text-white transition"
          aria-label="Toggle Navigation Menu"
        >
          <svg v-if="!mobileMenuOpen" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
          </svg>
          <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>
    </div>

    <!-- Mobile Drawer / Menu Dropdown -->
    <div
      v-if="mobileMenuOpen"
      class="md:hidden border-b border-slate-800 bg-slate-950/95 backdrop-blur-2xl px-4 py-4 space-y-3 animate-in slide-in-from-top-2"
    >
      <router-link
        to="/"
        @click="mobileMenuOpen = false"
        class="block px-3 py-2 rounded-lg text-sm font-medium hover:bg-slate-900 transition"
        :class="activeRoute === 'landing' ? 'text-emerald-400 font-bold bg-slate-900/50' : 'text-slate-300'"
      >
        Beranda
      </router-link>
      <a
        href="/#fitur"
        @click="handleAnchorClick('/#fitur', $event); mobileMenuOpen = false"
        class="block px-3 py-2 rounded-lg text-sm font-medium text-slate-300 hover:bg-slate-900 hover:text-emerald-400 transition"
      >
        Fitur Utama
      </a>
      <router-link
        to="/dokumentasi"
        @click="mobileMenuOpen = false"
        class="flex items-center justify-between px-3 py-2 rounded-lg text-sm font-medium hover:bg-slate-900 transition"
        :class="activeRoute === 'documentation' ? 'text-emerald-400 font-bold bg-slate-900/50' : 'text-slate-300'"
      >
        <span class="flex items-center gap-2">
          <span>📖</span>
          <span>Dokumentasi Chatbot</span>
        </span>
        <span class="text-[9px] px-1.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 font-bold border border-emerald-500/30">
          Lengkap
        </span>
      </router-link>
      <a
        href="/#harga"
        @click="handleAnchorClick('/#harga', $event); mobileMenuOpen = false"
        class="block px-3 py-2 rounded-lg text-sm font-medium text-slate-300 hover:bg-slate-900 hover:text-emerald-400 transition"
      >
        Paket Harga
      </a>
      <a
        href="/#faq"
        @click="handleAnchorClick('/#faq', $event); mobileMenuOpen = false"
        class="block px-3 py-2 rounded-lg text-sm font-medium text-slate-300 hover:bg-slate-900 hover:text-emerald-400 transition"
      >
        FAQ
      </a>
    </div>

    <!-- Dashboard Paywall Modal for Guest / Free Users -->
    <DashboardPaywallModal
      :isOpen="showPaywallModal"
      :botRawNumber="botRawNumber"
      :freeLimit="freeLimit"
      @close="showPaywallModal = false"
      @upgraded="onSubscriptionUpgraded"
    />
  </nav>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import DashboardPaywallModal from '../modals/DashboardPaywallModal.vue';

const props = defineProps({
  activeRoute: {
    type: String,
    default: '',
  },
});

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();

const mobileMenuOpen = ref(false);
const showPaywallModal = ref(false);
const isScrolledSection = ref(false);

const botRawNumber = (typeof window !== 'undefined' && window.__BOT_CONFIG__?.bot_number) || import.meta.env.VITE_WHATSAPP_BOT_NUMBER || '6285111355992';
const freeLimit = ref(typeof window !== 'undefined' && window.__BOT_CONFIG__?.free_limit ? window.__BOT_CONFIG__.free_limit : 100);

const whatsappDirectUrl = computed(() => {
  return `https://wa.me/${botRawNumber}?text=Halo%20HematWoy,%20saya%20mau%20coba%20catat%20keuangan!`;
});

function handleDashboardClick() {
  if (authStore.isAuthenticated && authStore.isPro) {
    router.push('/dashboard');
    return;
  }
  showPaywallModal.value = true;
}

function onSubscriptionUpgraded() {
  if (authStore.isAuthenticated) {
    authStore.fetchProfile();
    authStore.fetchSubscription();
  }
}

function handleAnchorClick(target, event) {
  if (route.path === '/') {
    event.preventDefault();
    const hash = target.split('#')[1];
    const el = document.getElementById(hash);
    if (el) {
      el.scrollIntoView({ behavior: 'smooth' });
    }
  } else {
    // Navigating from other pages (e.g. documentation) to landing with hash
    router.push(target);
  }
}
</script>
