<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm"
  >
    <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-sm p-6 shadow-2xl relative">
      <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-2xl mx-auto mb-4" :class="danger ? 'bg-rose-500/15 text-rose-400 border border-rose-500/30' : 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30'">
        {{ icon || (danger ? '⚠️' : '❓') }}
      </div>

      <h3 class="text-base font-bold text-white text-center mb-1.5">
        {{ title }}
      </h3>
      <p class="text-xs text-slate-400 text-center leading-relaxed mb-6">
        {{ message }}
      </p>

      <div class="grid grid-cols-2 gap-3">
        <button
          type="button"
          @click="$emit('cancel')"
          class="py-2.5 px-4 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold text-xs transition cursor-pointer"
        >
          {{ cancelText || 'Batal' }}
        </button>
        <button
          type="button"
          @click="$emit('confirm')"
          class="py-2.5 px-4 rounded-xl font-bold text-xs shadow-lg transition cursor-pointer active:scale-95"
          :class="danger ? 'bg-rose-600 hover:bg-rose-500 text-white shadow-rose-950/50' : 'bg-emerald-500 hover:bg-emerald-400 text-slate-950 shadow-emerald-500/20'"
        >
          {{ confirmText || 'Ya, Lanjutkan' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  isOpen: {
    type: Boolean,
    default: false,
  },
  title: {
    type: String,
    required: true,
  },
  message: {
    type: String,
    required: true,
  },
  icon: {
    type: String,
    default: '',
  },
  confirmText: {
    type: String,
    default: 'Ya, Lanjutkan',
  },
  cancelText: {
    type: String,
    default: 'Batal',
  },
  danger: {
    type: Boolean,
    default: true,
  },
});

defineEmits(['confirm', 'cancel']);
</script>
