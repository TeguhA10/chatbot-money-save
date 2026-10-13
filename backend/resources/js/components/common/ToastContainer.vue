<template>
  <div class="fixed bottom-5 right-5 z-50 flex flex-col gap-2.5 max-w-sm w-full pointer-events-none px-4 sm:px-0">
    <TransitionGroup
      enter-active-class="transform ease-out duration-300 transition"
      enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
      enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
      leave-active-class="transition ease-in duration-200"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-for="toast in toastStore.toasts"
        :key="toast.id"
        class="pointer-events-auto flex items-center justify-between gap-3 p-3.5 rounded-2xl shadow-xl backdrop-blur-xl border text-xs font-medium"
        :class="getToastClasses(toast.type)"
      >
        <div class="flex items-center gap-2.5">
          <span class="text-base">{{ getToastIcon(toast.type) }}</span>
          <p class="leading-relaxed">{{ toast.message }}</p>
        </div>
        <button
          @click="toastStore.remove(toast.id)"
          class="text-slate-400 hover:text-white transition cursor-pointer p-1 rounded-lg"
        >
          ✕
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>

<script setup>
import { useToastStore } from '../../stores/toast';

const toastStore = useToastStore();

function getToastClasses(type) {
  switch (type) {
    case 'error':
      return 'bg-rose-950/90 border-rose-800/80 text-rose-200 shadow-rose-950/50';
    case 'info':
      return 'bg-sky-950/90 border-sky-800/80 text-sky-200 shadow-sky-950/50';
    case 'success':
    default:
      return 'bg-emerald-950/90 border-emerald-800/80 text-emerald-200 shadow-emerald-950/50';
  }
}

function getToastIcon(type) {
  switch (type) {
    case 'error':
      return '⚠️';
    case 'info':
      return 'ℹ️';
    case 'success':
    default:
      return '✅';
  }
}
</script>
