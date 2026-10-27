<template>
  <div class="min-h-screen bg-slate-950 font-sans">
    <component :is="isAppLayout ? AppLayout : 'div'" @open-transaction-modal="handleOpenTransactionModal">
      <RouterView @open-transaction-modal="handleOpenTransactionModal" />
    </component>

    <!-- Global Transaction Modal (Only for Authenticated Dashboard & Pro Users) -->
    <TransactionModal
      v-if="authStore.isAuthenticated"
      :isOpen="showTransactionModal && authStore.isPro"
      :transaction="modalTransaction"
      @close="closeTransactionModal"
      @saved="handleTransactionSaved"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import AppLayout from './components/layout/AppLayout.vue';
import TransactionModal from './components/modals/TransactionModal.vue';
import { useAuthStore } from './stores/auth';
import { useFinanceStore } from './stores/finance';

const route = useRoute();
const authStore = useAuthStore();
const financeStore = useFinanceStore();

const showTransactionModal = ref(false);
const modalTransaction = ref(null);

const isPublicPage = computed(() => {
  if (['landing', 'login', 'checkout', 'documentation'].includes(route.name) || route.meta?.isPublic || route.meta?.guestOnly) {
    return true;
  }
  const path = route.path || (typeof window !== 'undefined' ? window.location.pathname : '/');
  return path === '/' || path.startsWith('/login') || path.startsWith('/pay') || path.startsWith('/dokumentasi') || path.startsWith('/docs');
});

const isAppLayout = computed(() => {
  return !isPublicPage.value && authStore.isAuthenticated;
});

function handleOpenTransactionModal(transaction = null) {
  if (!authStore.isPro) {
    authStore.openPricing('monthly');
    return;
  }
  modalTransaction.value = transaction || null;
  showTransactionModal.value = true;
}

function closeTransactionModal() {
  showTransactionModal.value = false;
  modalTransaction.value = null;
}

function handleTransactionSaved() {
  closeTransactionModal();
}

onMounted(() => {
  if (authStore.isAuthenticated) {
    authStore.fetchProfile();
    authStore.fetchSubscription();
    financeStore.fetchCategories();
  }
});
</script>
