<template>
  <div id="simulator" class="w-full">
    <!-- Simulator Box -->
    <div class="max-w-2xl mx-auto">
      <!-- LOCKED STATE: Replaces chat card when 3 attempts have been reached -->
      <div
        v-if="isLocked || attempts >= 3"
        class="p-7 sm:p-10 rounded-3xl bg-gradient-to-b from-slate-900 via-slate-900 to-emerald-950/40 border-2 border-emerald-500/50 text-center shadow-2xl shadow-emerald-500/10 backdrop-blur-xl animate-fade-in"
      >
        <div class="w-16 h-16 bg-emerald-500/20 text-emerald-400 rounded-3xl flex items-center justify-center mx-auto mb-4 text-3xl shadow-lg shadow-emerald-500/20">
          💬
        </div>
        <div class="inline-block px-3 py-1 bg-emerald-500/15 border border-emerald-500/30 rounded-full text-emerald-400 text-xs font-bold mb-3 tracking-wide">
          DEMO SELESAI (3/3)
        </div>
        <h3 class="text-xl sm:text-2xl font-black text-white tracking-tight mb-2">
          Suka dengan Respon Cepat Bot?
        </h3>
        <p class="text-xs sm:text-sm text-slate-300 max-w-md mx-auto mb-6 leading-relaxed">
          Kamu telah mencoba batas demo 3x di browser. Sekarang, nikmati kemudahan mencatat keuangan sesungguhnya langsung di akun WhatsApp kamu!
        </p>

        <div class="bg-slate-950/90 border border-slate-800 rounded-2xl p-4 max-w-xs mx-auto mb-6 text-xs text-slate-300">
          <span class="text-slate-400 text-[11px] block mb-1">Nomor WhatsApp Resmi HematWoy:</span>
          <span class="font-mono font-black text-emerald-400 text-base">{{ botNumberDisplay }}</span>
        </div>

        <div class="flex flex-col sm:flex-row gap-3 justify-center items-center">
          <a
            :href="whatsappDirectUrl"
            target="_blank"
            class="inline-flex items-center justify-center gap-2.5 w-full sm:w-auto px-8 py-3.5 rounded-2xl bg-gradient-to-r from-emerald-500 to-teal-400 text-slate-950 font-black text-sm shadow-xl shadow-emerald-500/25 hover:scale-105 active:scale-98 transition cursor-pointer"
          >
            <span>💬 Pindah Catat di WhatsApp Sekarang</span>
          </a>
          <button
            type="button"
            @click="resetSimulatorForTesting"
            class="text-xs text-slate-500 hover:text-slate-300 underline transition cursor-pointer"
          >
            Reset demo browser
          </button>
        </div>
      </div>

      <!-- ACTIVE CHAT CARD: When attempts < 3 -->
      <div
        v-else
        class="bg-slate-900/90 border border-slate-800 rounded-3xl p-5 sm:p-6 shadow-2xl backdrop-blur-xl relative overflow-hidden"
      >
        <!-- Subtle Glow -->
        <div class="absolute -top-20 -right-20 w-48 h-48 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <!-- Chat Header (WhatsApp Style) -->
        <div class="flex items-center justify-between pb-4 border-b border-slate-800 mb-4">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-emerald-500 to-teal-400 flex items-center justify-center text-slate-950 font-black text-sm shadow-md shadow-emerald-500/20">
              HW
            </div>
            <div>
              <div class="font-bold text-sm text-white flex items-center gap-1.5">
                <span>HematWoy Finance Bot</span>
                <span class="text-[10px] text-emerald-400 bg-emerald-500/15 px-1.5 py-0.2 rounded font-semibold">Online</span>
              </div>
              <div class="text-[11px] text-slate-400 font-mono">{{ botNumberDisplay }}</div>
            </div>
          </div>

          <!-- Attempt Counter Badge -->
          <div class="text-right">
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-slate-800 border border-slate-700 text-slate-300">
              <span>Coba Demo:</span>
              <span class="text-emerald-400 font-mono">{{ attempts }}/3</span>
            </span>
          </div>
        </div>

        <!-- Preset Chips -->
        <div class="mb-4">
          <div class="flex items-center justify-between mb-1.5">
            <span class="text-[11px] text-slate-400 font-semibold">💡 Klik contoh perintah cepat:</span>
            <span class="text-[10px] text-slate-500">Atau pilih dari dokumentasi</span>
          </div>
          <div class="flex flex-wrap gap-1.5">
            <button
              v-for="chip in presetChips"
              :key="chip"
              @click="runPreset(chip)"
              :disabled="sending"
              class="text-[11px] px-3 py-1.5 rounded-lg bg-slate-800/80 hover:bg-slate-800 border border-slate-700/60 text-slate-300 hover:text-emerald-300 font-medium transition cursor-pointer disabled:opacity-50 active:scale-95 flex items-center gap-1"
            >
              <span>⚡</span>
              <span>{{ chip }}</span>
            </button>
          </div>
        </div>

        <!-- Chat Conversation Area -->
        <div
          ref="chatScrollArea"
          class="space-y-3 mb-4 min-h-[220px] max-h-[340px] overflow-y-auto pr-1 scrollbar-thin scrollbar-thumb-slate-800"
        >
          <div
            v-for="(msg, idx) in chatMessages"
            :key="idx"
            class="flex flex-col"
            :class="msg.sender === 'user' ? 'items-end' : 'items-start'"
          >
            <div
              class="max-w-[92%] rounded-2xl px-4 py-3 text-xs leading-relaxed"
              :class="msg.sender === 'user' ? 'bg-emerald-600 text-white rounded-tr-none shadow-md' : 'bg-slate-950 border border-slate-800 text-slate-200 rounded-tl-none font-mono whitespace-pre-wrap shadow-md'"
            >
              {{ msg.text }}
            </div>
            <span class="text-[9px] text-slate-500 mt-0.5 px-1">{{ msg.time }}</span>
          </div>

          <!-- Typing Indicator -->
          <div v-if="sending" class="flex flex-col items-start animate-pulse">
            <div class="bg-slate-950 border border-slate-800 text-slate-400 rounded-2xl rounded-tl-none px-4 py-2.5 text-xs flex items-center gap-1.5">
              <span class="w-1.5 h-1.5 bg-emerald-400 rounded-full animate-bounce"></span>
              <span class="w-1.5 h-1.5 bg-emerald-400 rounded-full animate-bounce [animation-delay:0.2s]"></span>
              <span class="w-1.5 h-1.5 bg-emerald-400 rounded-full animate-bounce [animation-delay:0.4s]"></span>
              <span class="text-[11px] ml-1">HematWoy sedang memproses...</span>
            </div>
          </div>
        </div>

        <!-- Input Bar -->
        <form @submit.prevent="sendCustomChat" class="flex gap-2">
          <input
            ref="inputField"
            v-model="inputChat"
            type="text"
            :disabled="sending"
            placeholder="Ketik: keluar 25rb kopi kenangan via bca..."
            class="flex-1 px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-xs text-white placeholder:text-slate-600 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition disabled:opacity-50"
          />
          <button
            type="submit"
            :disabled="sending || !inputChat.trim()"
            class="px-5 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-xs transition cursor-pointer disabled:opacity-50 active:scale-95 flex items-center gap-1.5"
          >
            <span v-if="sending" class="w-3 h-3 border-2 border-slate-950 border-t-transparent rounded-full animate-spin"></span>
            <span>{{ sending ? 'Proses' : 'Kirim' }}</span>
          </button>
        </form>

        <p class="text-[10px] text-slate-500 text-center mt-2.5">
          Tersedia 3 kali kesempatan coba interaktif di web. Untuk penggunaan harian tanpa batas, hubungi bot resmi di WhatsApp.
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue';
import axios from 'axios';

const botRawNumber = (typeof window !== 'undefined' && window.__BOT_CONFIG__?.bot_number) || import.meta.env.VITE_WHATSAPP_BOT_NUMBER || '6285111355992';

const botNumberDisplay = computed(() => {
  const clean = String(botRawNumber).replace(/\D/g, '');
  if (clean.startsWith('62')) {
    return `+62 ${clean.slice(2, 5)}-${clean.slice(5, 9)}-${clean.slice(9)}`;
  }
  if (clean.startsWith('1') && clean.length === 11) {
    return `+1 (${clean.slice(1, 4)}) ${clean.slice(4, 7)}-${clean.slice(7)}`;
  }
  return `+${clean}`;
});

const whatsappDirectUrl = computed(() => {
  return `https://wa.me/${botRawNumber}?text=Halo%20HematWoy,%20saya%20mau%20coba%20catat%20keuangan!`;
});

const attempts = ref(0);
const isLocked = ref(false);
const sending = ref(false);
const visitorToken = ref('');
const inputChat = ref('');
const chatScrollArea = ref(null);
const inputField = ref(null);

const presetChips = ref([
  'keluar 25rb kopi kenangan via bca',
  '+1.5jt freelance website',
  'saldo',
  'budget makan 1jt',
  'langganan',
]);

const chatMessages = ref([
  {
    sender: 'bot',
    text: '👋 Halo! Saya asisten finansial resmi HematWoy 💰\n\nCoba ketik perintah seperti "keluar 25rb kopi kenangan", "+500rb freelance via bca", atau "saldo" untuk melihat respon otomatis!',
    time: '09:00',
  },
]);

function getOrCreateVisitorToken() {
  let token = localStorage.getItem('hw_sim_visitor_token');
  if (!token) {
    token = 'tok_' + Math.random().toString(36).substring(2, 15) + Date.now().toString(36);
    localStorage.setItem('hw_sim_visitor_token', token);
  }
  return token;
}

async function checkSimulatorStatus() {
  visitorToken.value = getOrCreateVisitorToken();
  const localAttempts = parseInt(localStorage.getItem('hw_sim_attempts') || '0', 10);
  attempts.value = localAttempts;

  if (localAttempts >= 3) {
    isLocked.value = true;
    return;
  }

  try {
    const res = await axios.get(`/api/v1/simulator/status?visitor_token=${visitorToken.value}`);
    if (res.data.is_locked || res.data.attempts >= 3) {
      isLocked.value = true;
      attempts.value = 3;
      localStorage.setItem('hw_sim_attempts', '3');
    } else {
      attempts.value = Math.max(localAttempts, res.data.attempts);
      localStorage.setItem('hw_sim_attempts', String(attempts.value));
    }
  } catch (err) {
    if (localAttempts >= 3) {
      isLocked.value = true;
    }
  }
}

function scrollToBottom() {
  nextTick(() => {
    if (chatScrollArea.value) {
      chatScrollArea.value.scrollTop = chatScrollArea.value.scrollHeight;
    }
  });
}

function runPreset(text) {
  if (isLocked.value || attempts.value >= 3) return;
  inputChat.value = text;
  sendCustomChat();
}

/**
 * Public method exposed to parent components (e.g. DocumentationView)
 * so clicking "Coba Perintah" injects and sends it immediately!
 */
function injectAndRun(commandText, autoSend = true) {
  if (isLocked.value || attempts.value >= 3) {
    // If locked, scroll to locked card
    const el = document.getElementById('simulator');
    if (el) el.scrollIntoView({ behavior: 'smooth' });
    return;
  }

  inputChat.value = commandText;
  const el = document.getElementById('simulator');
  if (el) el.scrollIntoView({ behavior: 'smooth' });

  if (autoSend) {
    sendCustomChat();
  } else {
    nextTick(() => {
      inputField.value?.focus();
    });
  }
}

function resetSimulatorForTesting() {
  localStorage.removeItem('hw_sim_attempts');
  localStorage.removeItem('hw_sim_visitor_token');
  attempts.value = 0;
  isLocked.value = false;
  visitorToken.value = getOrCreateVisitorToken();
  chatMessages.value = [
    {
      sender: 'bot',
      text: '👋 Simulator direset! Silakan coba ketik perintah finansialmu kembali.',
      time: '09:00',
    },
  ];
}

async function sendCustomChat() {
  const query = inputChat.value.trim();
  if (!query || sending.value) return;

  if (isLocked.value || attempts.value >= 3) {
    isLocked.value = true;
    return;
  }

  const now = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });

  chatMessages.value.push({
    sender: 'user',
    text: query,
    time: now,
  });

  inputChat.value = '';
  sending.value = true;
  scrollToBottom();

  try {
    const res = await axios.post('/api/v1/simulator', {
      message_text: query,
      visitor_token: visitorToken.value,
    });

    const reply = res.data.reply_text;
    attempts.value = res.data.attempts;
    localStorage.setItem('hw_sim_attempts', String(attempts.value));

    chatMessages.value.push({
      sender: 'bot',
      text: reply,
      time: now,
    });
    scrollToBottom();

    if (attempts.value >= 3) {
      setTimeout(() => {
        isLocked.value = true;
      }, 1800);
    }
  } catch (err) {
    if (err.response?.status === 429 || err.response?.data?.status === 'LIMIT_REACHED') {
      isLocked.value = true;
      attempts.value = 3;
      localStorage.setItem('hw_sim_attempts', '3');
    } else {
      chatMessages.value.push({
        sender: 'bot',
        text: '⚠️ Terjadi kendala saat memproses simulator. Silakan coba lagi.',
        time: now,
      });
      scrollToBottom();
    }
  } finally {
    sending.value = false;
  }
}

onMounted(() => {
  checkSimulatorStatus();
});

defineExpose({
  injectAndRun,
});
</script>
