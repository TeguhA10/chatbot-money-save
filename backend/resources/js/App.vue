<template>
  <div class="min-h-screen bg-slate-950 font-sans">
    <component :is="isPublicPage ? 'div' : AppLayout" @open-transaction-modal="showTransactionModal = true">
      <RouterView @open-transaction-modal="showTransactionModal = true" />
    </component>

    <!-- Global Transaction Modal -->
    <TransactionModal
      :isOpen="showTransactionModal"
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

const isPublicPage = computed(() => ['landing', 'login', 'checkout'].includes(route.name) || route.meta?.isPublic);

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
    financeStore.fetchCategories();
  }
});
</script>
