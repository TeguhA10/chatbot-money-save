<template>
  <div class="relative inline-block" ref="containerRef">
    <!-- Trigger Button -->
    <button
      type="button"
      @click="toggleDropdown"
      :disabled="disabled"
      class="w-full sm:w-auto px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-800 hover:border-slate-700 text-slate-200 text-xs font-semibold focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/40 shadow-inner cursor-pointer transition flex items-center justify-between gap-2.5 disabled:opacity-50 disabled:cursor-not-allowed group select-none min-h-[42px]"
      :class="isOpen ? 'border-emerald-500 ring-1 ring-emerald-500/40 bg-slate-850' : ''"
      title="Pilih Bulan Analisis"
    >
      <div class="flex items-center gap-2 min-w-0">
        <span class="text-emerald-400 group-hover:scale-110 transition-transform">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
          </svg>
        </span>
        <span class="truncate font-bold tracking-tight text-white">{{ formattedDisplay }}</span>
      </div>

      <svg
        class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200 shrink-0"
        :class="isOpen ? 'rotate-180 text-emerald-400' : ''"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
      >
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
      </svg>
    </button>

    <!-- Dropdown Calendar Popover -->
    <div
      v-if="isOpen"
      :class="[
        popoverAlign === 'right' ? 'right-0' : 'left-0',
        'absolute mt-2 w-72 max-w-[calc(100vw-2rem)] p-4 rounded-2xl bg-slate-900/95 backdrop-blur-xl border border-slate-700/80 shadow-2xl shadow-slate-950/80 z-50 animate-in fade-in zoom-in-95 duration-150 select-none space-y-3.5'
      ]"
    >
      <!-- Popover Header: Year Navigator -->
      <div class="flex items-center justify-between px-1">
        <button
          type="button"
          @click="changeYear(-1)"
          class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition active:scale-90 cursor-pointer"
          title="Tahun Sebelumnya"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
        </button>

        <div class="text-sm font-black text-white tracking-wide flex items-center gap-1.5">
          <span>{{ currentYear }}</span>
          <span v-if="currentYear === todayYear" class="w-1.5 h-1.5 rounded-full bg-emerald-400" title="Tahun Berjalan"></span>
        </div>

        <button
          type="button"
          @click="changeYear(1)"
          class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition active:scale-90 cursor-pointer"
          title="Tahun Berikutnya"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
        </button>
      </div>

      <!-- 12-Month Grid (3 cols x 4 rows) -->
      <div class="grid grid-cols-3 gap-2">
        <button
          v-for="(name, idx) in monthNames"
          :key="idx"
          type="button"
          @click="selectMonth(idx)"
          class="py-2.5 px-2 rounded-xl text-xs font-bold transition text-center relative cursor-pointer active:scale-95 flex flex-col items-center justify-center"
          :class="getMonthBtnClass(idx)"
        >
          <span>{{ name.short }}</span>
          <span
            v-if="isTodayMonth(idx)"
            class="text-[9px] font-normal leading-none mt-0.5"
            :class="isSelectedMonth(idx) ? 'text-slate-950 font-bold' : 'text-emerald-400'"
          >
            Kini
          </span>
        </button>
      </div>

      <!-- Quick Action Pills -->
      <div class="pt-2 border-t border-slate-800/80 flex items-center justify-between text-[11px]">
        <button
          type="button"
          @click="setThisMonth"
          class="px-2.5 py-1 rounded-lg text-slate-400 hover:text-emerald-400 hover:bg-emerald-500/10 font-semibold transition cursor-pointer"
        >
          Bulan Ini
        </button>
        <button
          type="button"
          @click="setLastMonth"
          class="px-2.5 py-1 rounded-lg text-slate-400 hover:text-emerald-400 hover:bg-emerald-500/10 font-semibold transition cursor-pointer"
        >
          Bulan Lalu
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['update:modelValue', 'change']);

const containerRef = ref(null);
const isOpen = ref(false);

const monthNames = [
  { short: 'Jan', full: 'Januari' },
  { short: 'Feb', full: 'Februari' },
  { short: 'Mar', full: 'Maret' },
  { short: 'Apr', full: 'April' },
  { short: 'Mei', full: 'Mei' },
  { short: 'Jun', full: 'Juni' },
  { short: 'Jul', full: 'Juli' },
  { short: 'Agu', full: 'Agustus' },
  { short: 'Sep', full: 'September' },
  { short: 'Okt', full: 'Oktober' },
  { short: 'Nov', full: 'November' },
  { short: 'Des', full: 'Desember' },
];

const now = new Date();
const todayYear = now.getFullYear();
const todayMonth = now.getMonth();

const currentYear = ref(todayYear);

// Sync currentYear with modelValue on change
watch(
  () => props.modelValue,
  (val) => {
    if (val && val.includes('-')) {
      const [y] = val.split('-');
      currentYear.value = parseInt(y, 10) || todayYear;
    }
  },
  { immediate: true }
);

const formattedDisplay = computed(() => {
  if (!props.modelValue || !props.modelValue.includes('-')) {
    return 'Pilih Bulan';
  }
  const [y, m] = props.modelValue.split('-');
  const monthIdx = parseInt(m, 10) - 1;
  const monthName = monthNames[monthIdx]?.full || m;
  return `${monthName} ${y}`;
});

const popoverAlign = ref('left');

function adjustPosition() {
  if (!containerRef.value) return;
  const rect = containerRef.value.getBoundingClientRect();
  const windowWidth = window.innerWidth;
  // If aligning left would cause popover (width ~288px) to exceed window right edge,
  // and there is enough room on the left (rect.right >= 288), align right. Otherwise align left.
  if (rect.left + 295 > windowWidth && rect.right >= 288) {
    popoverAlign.value = 'right';
  } else {
    popoverAlign.value = 'left';
  }
}

function toggleDropdown() {
  if (props.disabled) return;
  adjustPosition();
  isOpen.value = !isOpen.value;
}

function changeYear(delta) {
  currentYear.value += delta;
}

function isSelectedMonth(monthIdx) {
  if (!props.modelValue || !props.modelValue.includes('-')) return false;
  const [y, m] = props.modelValue.split('-');
  return parseInt(y, 10) === currentYear.value && parseInt(m, 10) - 1 === monthIdx;
}

function isTodayMonth(monthIdx) {
  return currentYear.value === todayYear && monthIdx === todayMonth;
}

function getMonthBtnClass(monthIdx) {
  if (isSelectedMonth(monthIdx)) {
    return 'bg-gradient-to-r from-emerald-500 to-teal-500 text-slate-950 shadow-md shadow-emerald-500/20 font-black';
  }
  if (isTodayMonth(monthIdx)) {
    return 'bg-slate-800 text-emerald-400 border border-emerald-500/30 hover:bg-slate-750';
  }
  return 'bg-slate-950/70 border border-slate-800/80 text-slate-300 hover:bg-slate-800 hover:text-white';
}

function selectMonth(monthIdx) {
  const m = String(monthIdx + 1).padStart(2, '0');
  const val = `${currentYear.value}-${m}`;
  emit('update:modelValue', val);
  emit('change', val);
  isOpen.value = false;
}

function setThisMonth() {
  currentYear.value = todayYear;
  selectMonth(todayMonth);
}

function setLastMonth() {
  const prevDate = new Date(todayYear, todayMonth - 1, 1);
  currentYear.value = prevDate.getFullYear();
  selectMonth(prevDate.getMonth());
}

function handleClickOutside(event) {
  if (containerRef.value && !containerRef.value.contains(event.target)) {
    isOpen.value = false;
  }
}

function handleKeyDown(event) {
  if (event.key === 'Escape' && isOpen.value) {
    isOpen.value = false;
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
  document.addEventListener('keydown', handleKeyDown);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickOutside);
  document.removeEventListener('keydown', handleKeyDown);
});
</script>
