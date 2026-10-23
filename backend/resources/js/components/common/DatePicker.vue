<template>
  <div class="relative inline-block w-full" ref="containerRef">
    <!-- Trigger Button -->
    <div
      role="button"
      tabindex="0"
      @click="toggleDropdown"
      @keydown.enter.space.prevent="toggleDropdown"
      :class="[
        isOpen ? 'border-emerald-500 ring-1 ring-emerald-500/40 bg-slate-850' : 'bg-slate-900 border-slate-800 hover:border-slate-700',
        disabled ? 'opacity-50 cursor-not-allowed pointer-events-none' : 'cursor-pointer'
      ]"
      class="w-full px-3.5 py-2.5 rounded-xl border text-slate-200 text-xs font-semibold focus:outline-none shadow-inner transition flex items-center justify-between gap-2 group select-none min-h-[42px]"
      :title="title || placeholder || 'Pilih Tanggal'"
    >
      <div class="flex items-center gap-2 min-w-0">
        <span class="text-emerald-400 group-hover:scale-110 transition-transform shrink-0">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
          </svg>
        </span>
        <span
          class="truncate tracking-tight"
          :class="modelValue ? 'text-white font-bold' : 'text-slate-500 font-normal'"
        >
          {{ formattedDisplay }}
        </span>
      </div>

      <div class="flex items-center gap-1 shrink-0">
        <!-- Clear button (if clearable and has value) -->
        <button
          v-if="clearable && modelValue && !disabled"
          type="button"
          @click.stop="clearDate"
          class="p-0.5 rounded hover:bg-slate-800 text-slate-400 hover:text-rose-400 transition"
          title="Hapus Tanggal"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>

        <!-- Chevron -->
        <svg
          class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200"
          :class="isOpen ? 'rotate-180 text-emerald-400' : ''"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
      </div>
    </div>

    <!-- Dropdown Calendar Popover -->
    <div
      v-if="isOpen"
      :class="[
        popoverAlign === 'right' ? 'right-0' : 'left-0',
        'absolute mt-2 w-72 max-w-[calc(100vw-2rem)] p-4 rounded-2xl bg-slate-900/95 backdrop-blur-xl border border-slate-700/80 shadow-2xl shadow-slate-950/80 z-50 animate-in fade-in zoom-in-95 duration-150 select-none space-y-3'
      ]"
    >
      <!-- Popover Header: Month & Year Navigator -->
      <div class="flex items-center justify-between px-1">
        <button
          type="button"
          @click="changeMonth(-1)"
          class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition active:scale-90 cursor-pointer"
          title="Bulan Sebelumnya"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
        </button>

        <div class="text-xs font-black text-white tracking-wide flex items-center gap-1.5">
          <span>{{ monthNames[viewMonth]?.full }} {{ viewYear }}</span>
        </div>

        <button
          type="button"
          @click="changeMonth(1)"
          class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition active:scale-90 cursor-pointer"
          title="Bulan Berikutnya"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
        </button>
      </div>

      <!-- Day Names Header -->
      <div class="grid grid-cols-7 text-center">
        <span
          v-for="day in ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab']"
          :key="day"
          class="text-[10px] font-bold text-slate-500 uppercase tracking-wider py-1"
        >
          {{ day }}
        </span>
      </div>

      <!-- Days Grid (7 columns) -->
      <div class="grid grid-cols-7 gap-1">
        <button
          v-for="cell in calendarDays"
          :key="cell.dateStr"
          type="button"
          @click="selectDate(cell)"
          :disabled="cell.disabled"
          class="h-8 rounded-xl text-xs font-semibold flex items-center justify-center transition relative cursor-pointer active:scale-95 disabled:opacity-30 disabled:cursor-not-allowed"
          :class="getDayClass(cell)"
        >
          <span>{{ cell.dayNum }}</span>
          <!-- Dot for today -->
          <span
            v-if="cell.isToday && !cell.isSelected"
            class="absolute bottom-1 w-1 h-1 rounded-full bg-emerald-400"
          ></span>
        </button>
      </div>

      <!-- Quick Shortcuts Footer -->
      <div class="pt-2.5 border-t border-slate-800/80 flex items-center justify-between text-[11px]">
        <div class="flex items-center gap-2">
          <button
            type="button"
            @click="setQuickDate('today')"
            class="text-slate-400 hover:text-emerald-400 hover:underline font-semibold transition cursor-pointer"
          >
            Hari Ini
          </button>
          <button
            type="button"
            @click="setQuickDate('yesterday')"
            class="text-slate-400 hover:text-emerald-400 hover:underline font-semibold transition cursor-pointer"
          >
            Kemarin
          </button>
        </div>

        <button
          v-if="clearable && modelValue"
          type="button"
          @click="clearDate"
          class="text-rose-400 hover:text-rose-300 hover:underline font-semibold transition cursor-pointer"
        >
          Hapus
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
  placeholder: {
    type: String,
    default: 'Pilih Tanggal',
  },
  title: {
    type: String,
    default: '',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  clearable: {
    type: Boolean,
    default: true,
  },
  min: {
    type: String,
    default: '',
  },
  max: {
    type: String,
    default: '',
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
const todayDateStr = now.toISOString().slice(0, 10);

const viewYear = ref(now.getFullYear());
const viewMonth = ref(now.getMonth()); // 0-11

// Sync view with modelValue when changed
watch(
  () => props.modelValue,
  (val) => {
    if (val && val.includes('-')) {
      const [y, m] = val.split('-');
      viewYear.value = parseInt(y, 10) || now.getFullYear();
      viewMonth.value = parseInt(m, 10) - 1 || now.getMonth();
    }
  },
  { immediate: true }
);

const formattedDisplay = computed(() => {
  if (!props.modelValue || !props.modelValue.includes('-')) {
    return props.placeholder;
  }
  const [y, m, d] = props.modelValue.split('-');
  const monthIdx = parseInt(m, 10) - 1;
  const monthShort = monthNames[monthIdx]?.short || m;
  return `${parseInt(d, 10)} ${monthShort} ${y}`;
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

function changeMonth(delta) {
  let newMonth = viewMonth.value + delta;
  if (newMonth < 0) {
    viewMonth.value = 11;
    viewYear.value -= 1;
  } else if (newMonth > 11) {
    viewMonth.value = 0;
    viewYear.value += 1;
  } else {
    viewMonth.value = newMonth;
  }
}

// Generate calendar days for current month
const calendarDays = computed(() => {
  const days = [];
  const year = viewYear.value;
  const month = viewMonth.value;

  const firstDayOfWeek = new Date(year, month, 1).getDay(); // 0 is Sunday
  const lastDate = new Date(year, month + 1, 0).getDate();
  const prevMonthLastDate = new Date(year, month, 0).getDate();

  // Previous month trailing days
  for (let i = firstDayOfWeek - 1; i >= 0; i--) {
    const dayNum = prevMonthLastDate - i;
    const prevM = month === 0 ? 12 : month;
    const prevY = month === 0 ? year - 1 : year;
    const dateStr = `${prevY}-${String(prevM).padStart(2, '0')}-${String(dayNum).padStart(2, '0')}`;
    days.push({
      dayNum,
      dateStr,
      isCurrentMonth: false,
      isToday: dateStr === todayDateStr,
      isSelected: dateStr === props.modelValue,
      disabled: checkDisabled(dateStr),
    });
  }

  // Current month days
  for (let d = 1; d <= lastDate; d++) {
    const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
    days.push({
      dayNum: d,
      dateStr,
      isCurrentMonth: true,
      isToday: dateStr === todayDateStr,
      isSelected: dateStr === props.modelValue,
      disabled: checkDisabled(dateStr),
    });
  }

  // Next month leading days to complete 35 or 42 grid cells
  const remaining = 42 - days.length;
  if (remaining < 7) {
    for (let d = 1; d <= remaining; d++) {
      const nextM = month === 11 ? 1 : month + 2;
      const nextY = month === 11 ? year + 1 : year;
      const dateStr = `${nextY}-${String(nextM).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
      days.push({
        dayNum: d,
        dateStr,
        isCurrentMonth: false,
        isToday: dateStr === todayDateStr,
        isSelected: dateStr === props.modelValue,
        disabled: checkDisabled(dateStr),
      });
    }
  }

  return days;
});

function checkDisabled(dateStr) {
  if (props.min && dateStr < props.min) return true;
  if (props.max && dateStr > props.max) return true;
  return false;
}

function getDayClass(cell) {
  if (cell.isSelected) {
    return 'bg-gradient-to-r from-emerald-500 to-teal-500 text-slate-950 font-black shadow-md shadow-emerald-500/25';
  }
  if (!cell.isCurrentMonth) {
    return 'text-slate-600 hover:text-slate-400 hover:bg-slate-800/40';
  }
  if (cell.isToday) {
    return 'text-emerald-400 font-bold bg-emerald-500/10 border border-emerald-500/25 hover:bg-emerald-500/20';
  }
  return 'text-slate-200 hover:bg-slate-800 hover:text-white';
}

function selectDate(cell) {
  if (cell.disabled) return;
  emit('update:modelValue', cell.dateStr);
  emit('change', cell.dateStr);
  isOpen.value = false;
}

function clearDate() {
  emit('update:modelValue', '');
  emit('change', '');
  isOpen.value = false;
}

function setQuickDate(type) {
  const d = new Date();
  if (type === 'yesterday') {
    d.setDate(d.getDate() - 1);
  }
  const dateStr = d.toISOString().slice(0, 10);
  emit('update:modelValue', dateStr);
  emit('change', dateStr);
  isOpen.value = false;
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
