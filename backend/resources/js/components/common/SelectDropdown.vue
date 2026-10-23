<template>
  <div class="relative inline-block w-full" ref="containerRef">
    <!-- Trigger Button -->
    <button
      type="button"
      @click="toggleDropdown"
      :disabled="disabled"
      :class="[
        isOpen ? 'border-emerald-500 ring-1 ring-emerald-500/40 bg-slate-850' : 'bg-slate-900 border-slate-800 hover:border-slate-700',
        disabled ? 'opacity-50 cursor-not-allowed pointer-events-none' : 'cursor-pointer'
      ]"
      class="w-full px-3.5 py-2.5 rounded-xl border text-slate-200 text-xs font-semibold focus:outline-none shadow-inner transition flex items-center justify-between gap-2 group select-none min-h-[42px]"
      :title="title || currentLabel"
    >
      <!-- Left side: Icon + Label -->
      <div class="flex items-center gap-2 min-w-0">
        <span
          v-if="currentIcon"
          class="shrink-0 text-xs transition-transform group-hover:scale-110 flex items-center justify-center"
        >
          {{ currentIcon }}
        </span>
        <span
          class="truncate tracking-tight"
          :class="isSelected ? 'text-white font-bold' : 'text-slate-400 font-normal'"
        >
          {{ currentLabel }}
        </span>
      </div>

      <!-- Right side: Clear button (optional) + Chevron Down Icon -->
      <div class="flex items-center gap-1.5 shrink-0 ml-1">
        <!-- Clear button (if clearable & selected) -->
        <span
          v-if="clearable && isSelected && !disabled"
          @click.stop="clearSelection"
          class="p-0.5 rounded hover:bg-slate-800 text-slate-400 hover:text-rose-400 transition cursor-pointer"
          title="Reset Pilihan"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </span>

        <!-- Chevron Down: Matches DatePicker exactly with 14px padding from border and smooth rotation -->
        <svg
          class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200"
          :class="isOpen ? 'rotate-180 text-emerald-400' : 'group-hover:text-slate-300'"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
      </div>
    </button>

    <!-- Dropdown Popover -->
    <div
      v-if="isOpen"
      :class="[
        popoverAlign === 'right' ? 'right-0' : 'left-0',
        'absolute mt-2 w-full min-w-[210px] max-w-[calc(100vw-2rem)] p-1.5 rounded-2xl bg-slate-900/95 backdrop-blur-xl border border-slate-700/80 shadow-2xl shadow-slate-950/80 z-50 animate-in fade-in zoom-in-95 duration-150 select-none space-y-1.5'
      ]"
    >
      <!-- Optional Search Bar if options > 7 -->
      <div v-if="searchable || normalizedOptions.length > 7" class="px-1 pt-1 pb-0.5">
        <div class="relative">
          <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs pointer-events-none">🔍</span>
          <input
            ref="searchInputRef"
            type="text"
            v-model="searchQuery"
            placeholder="Cari opsi..."
            class="w-full pl-7 pr-3 py-1.5 rounded-lg bg-slate-950/80 border border-slate-800 text-slate-200 text-xs focus:outline-none focus:border-emerald-500/80 transition placeholder:text-slate-600"
            @click.stop
          />
        </div>
      </div>

      <!-- Options List -->
      <div class="max-h-56 overflow-y-auto custom-scrollbar space-y-0.5 pr-0.5">
        <button
          v-for="opt in filteredOptions"
          :key="String(opt.value)"
          type="button"
          @click="selectOption(opt)"
          :class="[
            isOptionSelected(opt)
              ? 'bg-emerald-500/15 text-emerald-300 font-bold border border-emerald-500/30'
              : 'text-slate-300 hover:bg-slate-800/80 hover:text-white border border-transparent'
          ]"
          class="w-full px-3 py-2 rounded-xl text-left text-xs transition flex items-center justify-between gap-2 group/item cursor-pointer"
        >
          <div class="flex items-center gap-2 min-w-0">
            <span v-if="opt.icon" class="text-xs shrink-0 group-hover/item:scale-110 transition-transform">
              {{ opt.icon }}
            </span>
            <span class="truncate">{{ opt.label }}</span>
          </div>

          <span v-if="isOptionSelected(opt)" class="text-emerald-400 shrink-0">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
            </svg>
          </span>
        </button>

        <!-- Empty state when searching -->
        <div v-if="filteredOptions.length === 0" class="py-4 text-center text-xs text-slate-500">
          Tidak ada hasil
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'

const props = defineProps({
  modelValue: {
    type: [String, Number, Boolean],
    default: ''
  },
  options: {
    type: Array,
    default: () => []
  },
  placeholder: {
    type: String,
    default: 'Pilih Opsi'
  },
  icon: {
    type: String,
    default: ''
  },
  title: {
    type: String,
    default: ''
  },
  disabled: {
    type: Boolean,
    default: false
  },
  clearable: {
    type: Boolean,
    default: false
  },
  searchable: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:modelValue', 'change'])

const isOpen = ref(false)
const containerRef = ref(null)
const searchInputRef = ref(null)
const searchQuery = ref('')

// Normalize options to { value, label, icon }
const normalizedOptions = computed(() => {
  return props.options.map(item => {
    if (item !== null && typeof item === 'object') {
      return {
        value: item.value !== undefined ? item.value : (item.id !== undefined ? item.id : ''),
        label: item.label !== undefined ? item.label : (item.name !== undefined ? item.name : String(item.value || '')),
        icon: item.icon || ''
      }
    }
    return {
      value: item,
      label: String(item),
      icon: ''
    }
  })
})

const filteredOptions = computed(() => {
  if (!searchQuery.value.trim()) {
    return normalizedOptions.value
  }
  const q = searchQuery.value.toLowerCase()
  return normalizedOptions.value.filter(opt => opt.label.toLowerCase().includes(q))
})

const selectedOption = computed(() => {
  return normalizedOptions.value.find(opt => String(opt.value) === String(props.modelValue))
})

const isSelected = computed(() => {
  return props.modelValue !== '' && props.modelValue !== null && props.modelValue !== undefined
})

const currentLabel = computed(() => {
  if (selectedOption.value) {
    return selectedOption.value.label
  }
  return props.placeholder || 'Pilih Opsi'
})

const currentIcon = computed(() => {
  if (selectedOption.value && selectedOption.value.icon) {
    return selectedOption.value.icon
  }
  return props.icon || ''
})

function isOptionSelected(opt) {
  return String(opt.value) === String(props.modelValue)
}

const popoverAlign = ref('left')

function adjustPosition() {
  if (!containerRef.value) return
  const rect = containerRef.value.getBoundingClientRect()
  const windowWidth = window.innerWidth
  if (rect.left + 220 > windowWidth && rect.right >= 210) {
    popoverAlign.value = 'right'
  } else {
    popoverAlign.value = 'left'
  }
}

function toggleDropdown() {
  if (props.disabled) return
  adjustPosition()
  isOpen.value = !isOpen.value
  if (isOpen.value) {
    searchQuery.value = ''
    nextTick(() => {
      if (searchInputRef.value) {
        searchInputRef.value.focus()
      }
    })
  }
}

function selectOption(opt) {
  emit('update:modelValue', opt.value)
  emit('change', opt.value)
  isOpen.value = false
  searchQuery.value = ''
}

function clearSelection() {
  emit('update:modelValue', '')
  emit('change', '')
  isOpen.value = false
  searchQuery.value = ''
}

function handleClickOutside(event) {
  if (containerRef.value && !containerRef.value.contains(event.target)) {
    isOpen.value = false
  }
}

function handleKeyDown(event) {
  if (isOpen.value && event.key === 'Escape') {
    isOpen.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
  document.addEventListener('keydown', handleKeyDown)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
  document.removeEventListener('keydown', handleKeyDown)
})
</script>
