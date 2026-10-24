import makeWASocket, {
  DisconnectReason,
  useMultiFileAuthState,
  fetchLatestBaileysVersion,
  downloadMediaMessage,
  type WASocket,
  type WACallEvent,
} from '@whiskeysockets/baileys';
import pino from 'pino';
import * as dotenv from 'dotenv';
import qrcode from 'qrcode-terminal';
import { Boom } from '@hapi/boom';
import axios from 'axios';
import { OutboundQueue, QueuedMessage } from './queue.js';
import { WebhookClient } from './webhook-client.js';
import { createServer } from 'node:http';

type WAMessage = Parameters<Parameters<WASocket['ev']['on']>[1]>[0] extends { messages: (infer M)[] } ? M : any;

dotenv.config();

// Suppress noisy, harmless libsignal session decrypt warnings for old desynced messages
const originalConsoleError = console.error;
console.error = (...args: any[]) => {
  const msg = args.map(a => (typeof a === 'string' ? a : a?.message || '')).join(' ');
  if (
    msg.includes('Failed to decrypt message with any known session') ||
    msg.includes('MessageCounterError') ||
    msg.includes('Key used already or never filled') ||
    msg.includes('Bad MAC') ||
    msg.includes('Session error')
  ) {
    return;
  }
  originalConsoleError(...args);
};

const WEBHOOK_URL = process.env.LARAVEL_WEBHOOK_URL || 'http://127.0.0.1:8001/api/webhook/whatsapp';
const WEBHOOK_SECRET = process.env.GATEWAY_WEBHOOK_SECRET || 'local_dev_secret_12345';
const PAIRING_PHONE = process.env.PAIRING_PHONE_NUMBER ? process.env.PAIRING_PHONE_NUMBER.replace(/\D/g, '') : '';
const AUTH_DIR = process.env.BAILEYS_AUTH_DIR || 'auth_info_baileys';
const VOICE_SERVICE_URL = process.env.VOICE_SERVICE_URL || 'http://voice-service:9000/transcribe';

interface TranscriptionResult {
  text: string | null;
  provider?: string;
  errorReason?: string;
}

async function transcribeAudio(audioBuffer: Buffer): Promise<TranscriptionResult> {
  const financialPrompt = 'Pencatatan keuangan: keluar 25000 makan siang, beli bensin 15rb, transfer 500rb, gaji 5jt, bayar pulsa 50rb.';

  // 1. Groq Whisper API (Recommended: ultra-fast ~300ms, generous free tier, excellent Indonesian STT)
  const groqApiKey = process.env.GROQ_API_KEY;
  if (groqApiKey) {
    try {
      const formData = new FormData();
      const blob = new Blob([new Uint8Array(audioBuffer)], { type: 'audio/ogg' });
      formData.append('file', blob, 'voice.ogg');
      formData.append('model', process.env.GROQ_WHISPER_MODEL || 'whisper-large-v3');
      formData.append('language', 'id');
      formData.append('prompt', financialPrompt);

      const res = await axios.post<{ text?: string }>('https://api.groq.com/openai/v1/audio/transcriptions', formData, {
        headers: {
          Authorization: `Bearer ${groqApiKey}`,
          'Content-Type': 'multipart/form-data',
        },
        timeout: 15000,
      });

      const text = res.data?.text?.trim();
      if (text) {
        return { text, provider: 'Groq Whisper' };
      }
    } catch (err: any) {
      console.warn('[Gateway] Groq Whisper failed:', err?.response?.data || err?.message || err);
    }
  }

  // 2. OpenAI Whisper API
  const openaiApiKey = process.env.OPENAI_API_KEY;
  if (openaiApiKey) {
    try {
      const formData = new FormData();
      const blob = new Blob([new Uint8Array(audioBuffer)], { type: 'audio/ogg' });
      formData.append('file', blob, 'voice.ogg');
      formData.append('model', 'whisper-1');
      formData.append('language', 'id');
      formData.append('prompt', financialPrompt);

      const res = await axios.post<{ text?: string }>('https://api.openai.com/v1/audio/transcriptions', formData, {
        headers: {
          Authorization: `Bearer ${openaiApiKey}`,
          'Content-Type': 'multipart/form-data',
        },
        timeout: 20000,
      });

      const text = res.data?.text?.trim();
      if (text) {
        return { text, provider: 'OpenAI Whisper' };
      }
    } catch (err: any) {
      console.warn('[Gateway] OpenAI Whisper failed:', err?.response?.data || err?.message || err);
    }
  }

  // 3. Google Gemini API (Multimodal Audio)
  const geminiApiKey = process.env.GEMINI_API_KEY;
  if (geminiApiKey) {
    try {
      const base64Audio = audioBuffer.toString('base64');
      const geminiModel = process.env.GEMINI_MODEL || 'gemini-flash-lite-latest';
      const url = `https://generativelanguage.googleapis.com/v1beta/models/${geminiModel}:generateContent?key=${geminiApiKey}`;

      const res = await axios.post(url, {
        contents: [
          {
            parts: [
              {
                inlineData: {
                  mimeType: 'audio/ogg',
                  data: base64Audio,
                },
              },
              {
                text: 'Dengarkan rekaman suara ini dengan teliti. Transkripsikan apa yang diucapkan dalam bahasa Indonesia persis apa adanya untuk pencatatan keuangan (contoh: "keluar 25rb makan siang", "beli bensin 15000", "+500rb bonus gaji"). Tuliskan HANYA teks transkripsi ucapannya saja tanpa tanda petik, tanpa penjelasan pengantar, dan tanpa penutup.',
              },
            ],
          },
        ],
      }, {
        headers: { 'Content-Type': 'application/json' },
        timeout: 20000,
      });

      const rawText = res.data?.candidates?.[0]?.content?.parts?.[0]?.text?.trim() || '';
      const cleaned = rawText.replace(/^["'`]|["'`]$/g, '').trim();
      if (cleaned) {
        return { text: cleaned, provider: 'Google Gemini' };
      }
    } catch (err: any) {
      console.warn('[Gateway] Gemini STT failed:', err?.response?.data || err?.message || err);
    }
  }

  // 4. Local faster-whisper service fallback (check candidate URLs)
  const candidateUrls = Array.from(new Set([
    VOICE_SERVICE_URL,
    'http://127.0.0.1:9000/transcribe',
    'http://localhost:9000/transcribe',
  ]));

  for (const url of candidateUrls) {
    try {
      const formData = new FormData();
      const blob = new Blob([new Uint8Array(audioBuffer)], { type: 'audio/ogg' });
      formData.append('file', blob, 'voice.ogg');

      const res = await axios.post<{ text?: string }>(url, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
        timeout: 8000,
      });

      const text = res.data?.text?.trim();
      if (text) {
        return { text, provider: `Local Voice (${url})` };
      }
    } catch {
      // Continue to next candidate
    }
  }

  const hasAnyKey = Boolean(groqApiKey || openaiApiKey || geminiApiKey);
  return {
    text: null,
    errorReason: hasAnyKey ? 'STT_EMPTY_RESULT' : 'NO_STT_CONFIGURED',
  };
}

function resolvePort(): number {
  const argIdx = process.argv.indexOf('--port');
  if (argIdx !== -1 && process.argv[argIdx + 1]) {
    return Number(process.argv[argIdx + 1]);
  }
  return Number(process.env.HEALTH_PORT || process.env.PORT || 3001);
}
const HEALTH_PORT = resolvePort();
const AUTO_CLEAR_BOT_CHAT = process.env.AUTO_CLEAR_BOT_CHAT !== 'false';

const logger = pino({ level: process.env.LOG_LEVEL || 'info' });
const webhookClient = new WebhookClient(WEBHOOK_URL, WEBHOOK_SECRET);

let sock: WASocket;

async function deleteMessageForMe(jid: string, key: any, timestamp?: number | null): Promise<void> {
  if (!sock || !key?.id) return;

  const ts = typeof timestamp === 'number' && timestamp > 0
    ? timestamp
    : Math.floor(Date.now() / 1000);

  if (!sock.authState.creds.myAppStateKeyId) {
    console.warn(`[Gateway] ⚠️ Tidak dapat menghapus chat (${key.id}): Sesi belum memiliki "App State Key". Solusi: Hapus folder 'auth_info_baileys' dan tautkan ulang WA sekali.`);
    return;
  }

  try {
    await sock.chatModify({
      deleteForMe: {
        deleteMedia: true,
        key: {
          id: key.id,
          remoteJid: key.remoteJid || jid,
          fromMe: Boolean(key.fromMe),
          participant: key.participant,
        },
        timestamp: ts,
      },
    }, jid);
    console.log(`[Gateway] Auto-deleted chat for bot: ${key.id} (fromMe: ${Boolean(key.fromMe)})`);
  } catch (err: any) {
    console.warn(`[Gateway] Note: deleteForMe (${key.id}) failed:`, err?.message || err);
  }
}

const outboundQueue = new OutboundQueue(async (msg: QueuedMessage) => {
  if (!sock) return;

  let sent: any = null;

  if (msg.type === 'TEXT' && msg.text) {
    sent = await sock.sendMessage(msg.jid, { text: msg.text });
  } else if (msg.type === 'DOCUMENT' && msg.documentUrl) {
    sent = await sock.sendMessage(msg.jid, {
      document: { url: msg.documentUrl },
      mimetype: msg.mimetype || 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      fileName: msg.fileName || 'Laporan_Keuangan.xlsx',
      caption: msg.text || '📊 Laporan Keuangan Anda',
    });
  }

  // After reply is sent, immediately delete both bot's chat and user's chat on bot's phone only
  if (AUTO_CLEAR_BOT_CHAT) {
    setTimeout(async () => {
      // 1. Delete incoming user message on bot's phone only
      if (msg.replyToKey) {
        await deleteMessageForMe(msg.jid, msg.replyToKey, msg.replyToTimestamp);
      }
      // 2. Delete bot's outgoing reply on bot's phone only
      if (sent?.key) {
        const botTs = typeof sent.messageTimestamp === 'number'
          ? sent.messageTimestamp
          : Math.floor(Date.now() / 1000);
        await deleteMessageForMe(msg.jid, sent.key, botTs);
      }
    }, 1200);
  }
});

async function startGateway() {
  const { state, saveCreds } = await useMultiFileAuthState(AUTH_DIR);
  const { version, isLatest } = await fetchLatestBaileysVersion();

  console.log(`[Gateway] Starting Baileys Gateway using WA v${version.join('.')} (Latest: ${isLatest})`);

  if (sock) {
    try {
      sock.ev.removeAllListeners('connection.update');
      sock.ev.removeAllListeners('creds.update');
      sock.ev.removeAllListeners('messages.upsert');
      sock.ev.removeAllListeners('call');
    } catch {
      // ignore
    }
  }

  sock = makeWASocket({
    version,
    logger: pino({ level: 'silent' }),
    auth: state,
    syncFullHistory: false,
    shouldIgnoreJid: (jid) => jid === 'status@broadcast' || jid.endsWith('@broadcast'),
    browser: ['Windows', 'Chrome', '124.0.0.0'],
    generateHighQualityLinkPreview: true,
    getMessage: async () => undefined,
  });

  // Pairing code flow for phone terminal linking
  if (PAIRING_PHONE && !sock.authState.creds.registered) {
    setTimeout(async () => {
      try {
        const code = await sock.requestPairingCode(PAIRING_PHONE);
        console.log(`\n=================================================`);
        console.log(`📲 KODE PAIRING WHATSAPP: ${code}`);
        console.log(`Masukkan kode ini di WhatsApp HP kamu:`);
        console.log(`Perangkat Tertaut > Tautkan dengan nomor telepon`);
        console.log(`=================================================\n`);
      } catch (err) {
        console.error('[Gateway] Failed to request pairing code:', err);
      }
    }, 3000);
  }

  // Handle connection events
  sock.ev.on('connection.update', (update) => {
    const { connection, lastDisconnect, qr } = update;

    if (qr && !PAIRING_PHONE) {
      console.log('[Gateway] Scan QR Code di bawah dengan WhatsApp HP:');
      qrcode.generate(qr, { small: true });
    }

    if (connection === 'close') {
      const shouldReconnect = (lastDisconnect?.error as Boom)?.output?.statusCode !== DisconnectReason.loggedOut;
      console.log(`[Gateway] Connection closed. Reconnecting: ${shouldReconnect}`);

      if (shouldReconnect) {
        setTimeout(startGateway, Math.min(30000, 3000));
      } else {
        console.log('[Gateway] Device logged out. Please delete auth_info_baileys/ and re-pair.');
      }
    } else if (connection === 'open') {
      console.log('✅ [Gateway] Terhubung sukses ke WhatsApp! Bot siap menerima pesan.');
      if (sock.authState.creds.myAppStateKeyId) {
        console.log(`🔒 [Gateway] App State Key aktif (${sock.authState.creds.myAppStateKeyId}). Auto-delete chat di sisi bot siap digunakan.`);
      } else {
        console.log('⏳ [Gateway] Menunggu sinkronisasi App State Key dari WhatsApp...');
      }
    }
  });

  sock.ev.on('creds.update', async (update) => {
    await saveCreds();
    if (update.myAppStateKeyId) {
      console.log(`🔒 [Gateway] App State Key berhasil diterima (${update.myAppStateKeyId})! Auto-delete chat di sisi bot aktif.`);
    }
  });

  const rejectedCallIds = new Set<string>();

  // Auto-reject incoming calls and notify caller with explanatory chat
  sock.ev.on('call', async (calls: WACallEvent[]) => {
    for (const call of calls) {
      if (call.status === 'offer' && !rejectedCallIds.has(call.id)) {
        rejectedCallIds.add(call.id);
        setTimeout(() => rejectedCallIds.delete(call.id), 60_000);

        const callerJid = call.from || call.chatId;
        console.log(`[Gateway] Panggilan masuk otomatis ditolak dari ${callerJid} (callId: ${call.id}, video: ${Boolean(call.isVideo)})`);

        try {
          await sock.rejectCall(call.id, call.from);
        } catch (err: any) {
          console.warn('[Gateway] Gagal menolak panggilan teknis:', err?.message || err);
        }

        if (callerJid) {
          const callRejectMsg =
            `📞 *Panggilan Ditolak Otomatis*\n\n` +
            `Mohon maaf, bot ini tidak dapat menerima panggilan telepon maupun video call.\n` +
            `Layanan pencatatan dan pengelolaan keuangan hanya tersedia melalui pesan teks WhatsApp.\n\n` +
            `Ketik *bantuan* atau *halo* untuk melihat panduan fitur yang tersedia. 🙏`;

          outboundQueue.enqueue({
            type: 'TEXT',
            jid: callerJid,
            text: callRejectMsg,
          });
        }
      }
    }
  });

  // Incoming message processing
  sock.ev.on('messages.upsert', async ({ messages, type }) => {
    if (type !== 'notify') return;

    for (const msg of messages) {
      void handleIncomingMessage(msg);
    }
  });
}

const lastIncomingMessageTimes = new Map<string, number>();
const INBOUND_RATE_LIMIT_MS = 1000; // minimum 1 second interval between messages per JID

// Inbound deduplication cache: keeps message IDs for 10 minutes to prevent Baileys duplicate event processing
const processedMessageIds = new Map<string, number>();
const INBOUND_DEDUP_TTL_MS = 10 * 60 * 1000;

function isDuplicateInboundMessage(messageId: string): boolean {
  const now = Date.now();
  if (processedMessageIds.size > 2000) {
    for (const [id, time] of processedMessageIds.entries()) {
      if (now - time > INBOUND_DEDUP_TTL_MS) {
        processedMessageIds.delete(id);
      }
    }
  }

  if (processedMessageIds.has(messageId)) {
    return true;
  }
  processedMessageIds.set(messageId, now);
  return false;
}

async function handleIncomingMessage(msg: WAMessage): Promise<void> {
  // Ignore messages from self or status broadcasts
  if (!msg.message || msg.key.fromMe || msg.key.remoteJid === 'status@broadcast') {
    return;
  }

  const fromJid = msg.key.remoteJid;
  if (!fromJid) return;

  const msgId = msg.key.id;
  // Deduplicate incoming messages to prevent Baileys double-upsert processing
  if (msgId && isDuplicateInboundMessage(msgId)) {
    console.log(`[Gateway] Deduplication: Pesan ID ${msgId} dari ${fromJid} sudah diproses sebelumnya. Mengabaikan duplikat.`);
    return;
  }

  // Rate limiting per sender to prevent flood spam & DoS
  const now = Date.now();
  const lastTime = lastIncomingMessageTimes.get(fromJid) || 0;
  if (now - lastTime < INBOUND_RATE_LIMIT_MS) {
    console.warn(`[Gateway] Flood protection: Rate limit exceeded for ${fromJid}. Dropping message.`);
    return;
  }
  lastIncomingMessageTimes.set(fromJid, now);

  // Periodic cleanup of map cache
  if (lastIncomingMessageTimes.size > 5000) {
    for (const [jid, timestamp] of lastIncomingMessageTimes.entries()) {
      if (now - timestamp > 60000) {
        lastIncomingMessageTimes.delete(jid);
      }
    }
  }

  const pushName = msg.pushName || '';
  const isAudio = Boolean(msg.message.audioMessage);
  let messageText = '';
  let isVoiceNote = false;

  if (isAudio) {
    isVoiceNote = true;
    console.log(`[Gateway] 🎙️ Voice Note diterima dari ${pushName || 'User'} (${fromJid}). Mengunduh dan mentranskripsi audio...`);
    let errorReason: string | undefined;

    try {
      const audioBuffer = await downloadMediaMessage(
        msg,
        'buffer',
        {},
        {
          logger: pino({ level: 'silent' }),
          reuploadRequest: sock?.updateMediaMessage,
        }
      );

      if (audioBuffer && Buffer.isBuffer(audioBuffer)) {
        const result = await transcribeAudio(audioBuffer);
        if (result.text) {
          messageText = result.text;
          console.log(`[Gateway] 🗣️ Voice Note berhasil ditranskrip (${result.provider || 'STT'}): "${messageText}"`);
        } else {
          errorReason = result.errorReason;
        }
      }
    } catch (err: any) {
      console.error('[Gateway] Gagal mengunduh atau mentranskripsi audio:', err?.message || err);
    }

    if (!messageText.trim()) {
      const errorMsg = errorReason === 'NO_STT_CONFIGURED'
        ? `⚠️ *Layanan Voice Note Belum Aktif*\n\n` +
          `Sistem belum dapat mentranskripsi rekaman suara karena layanan Speech-to-Text belum terhubung.\n\n` +
          `💡 *Cara Aktivasi Mudah & Gratis:*\n` +
          `1. Dapatkan API Key gratis di *console.groq.com* (paling cepat & akurat untuk Bahasa Indonesia)\n` +
          `2. Masukkan ke file \`.env\` gateway/backend:\n` +
          `   \`GROQ_API_KEY=gsk_...\`\n` +
          `3. Atau jalankan voice-service lokal: \`docker compose up voice-service -d\`\n\n` +
          `Untuk saat ini, silakan ketik catatan keuanganmu lewat chat teks ya! 😊`
        : `⚠️ Maaf, rekaman suara tidak terdengar jelas atau durasinya terlalu singkat. Silakan coba rekam ulang lebih dekat atau kirim teks secara manual. 🙏`;

      outboundQueue.enqueue({
        jid: fromJid,
        type: 'TEXT',
        text: errorMsg,
        idempotencyKey: msgId ? `${msgId}:vn_error` : undefined,
      });
      return;
    }
  } else {
    messageText =
      msg.message.conversation ||
      msg.message.extendedTextMessage?.text ||
      '';
  }

  if (!messageText.trim()) {
    return;
  }

  // Length validation to protect against ReDoS / parser memory spike
  if (messageText.length > 2000) {
    console.warn(`[Gateway] Payload protection: Pesan dari ${fromJid} melebihi batas 2.000 karakter (${messageText.length} chars).`);
    outboundQueue.enqueue({
      jid: fromJid,
      type: 'TEXT',
      text: '⚠️ Pesan terlalu panjang (maksimal 2.000 karakter). Mohon kirimkan catatan transaksi yang lebih ringkas.',
    });
    return;
  }

  console.log(`[Gateway] Pesan diproses untuk ${pushName || 'User'} (${fromJid}) [Voice: ${isVoiceNote}]`);

  // Relay to Laravel Webhook
  const response = await webhookClient.forwardMessage({
    message_id: msg.key.id || `BAILEYS_${Date.now()}`,
    from_jid: fromJid,
    push_name: pushName,
    message_text: messageText,
    is_voice_note: isVoiceNote,
    timestamp: typeof msg.messageTimestamp === 'number' ? msg.messageTimestamp : Math.floor(Date.now() / 1000),
  });

  if (!response) {
    return;
  }

  const userMsgTimestamp = typeof msg.messageTimestamp === 'number'
    ? msg.messageTimestamp
    : Math.floor(Date.now() / 1000);

  if (response.action === 'REPLY_TEXT' && response.reply_text) {
    outboundQueue.enqueue({
      jid: fromJid,
      type: 'TEXT',
      text: response.reply_text,
      idempotencyKey: `${msg.key.id}:reply`,
      replyToKey: msg.key,
      replyToTimestamp: userMsgTimestamp,
    });
  } else if (response.action === 'SEND_DOCUMENT' && response.file_url) {
    outboundQueue.enqueue({
      jid: fromJid,
      type: 'DOCUMENT',
      text: response.reply_text,
      documentUrl: response.file_url,
      fileName: response.file_name,
      mimetype: response.mimetype,
      idempotencyKey: `${msg.key.id}:document`,
      replyToKey: msg.key,
      replyToTimestamp: userMsgTimestamp,
    });
  }
}

// Start gateway HTTP server with health check and outbound send API
const server = createServer((request, response) => {
  if (request.method === 'POST' && (request.url === '/send' || request.url === '/api/send')) {
    // Authenticate internal caller with X-Gateway-Secret
    const providedSecret = request.headers['x-gateway-secret'];
    if (WEBHOOK_SECRET && providedSecret !== WEBHOOK_SECRET) {
      response.writeHead(401, { 'Content-Type': 'application/json' });
      response.end(JSON.stringify({ error: 'Unauthorized: Invalid X-Gateway-Secret' }));
      return;
    }

    let body = '';
    request.on('data', (chunk) => { body += chunk; });
    request.on('end', async () => {
      try {
        const payload = JSON.parse(body || '{}');
        const { jid, text, type, documentUrl, fileName, mimetype } = payload;
        if (!jid) {
          response.writeHead(400, { 'Content-Type': 'application/json' });
          response.end(JSON.stringify({ error: 'Missing jid parameter' }));
          return;
        }

        console.log(`[Gateway] Outbound message queued via HTTP API to: ${jid}`);
        outboundQueue.enqueue({
          jid,
          type: type === 'DOCUMENT' ? 'DOCUMENT' : 'TEXT',
          text: text || '',
          documentUrl,
          fileName,
          mimetype,
        });

        let resolvedLid: string | null = null;
        if (sock && typeof (sock as any).onWhatsApp === 'function') {
          try {
            const check = await (sock as any).onWhatsApp(jid);
            if (check && check[0]?.lid) {
              resolvedLid = check[0].lid;
              console.log(`[Gateway] Resolved LID for ${jid} => ${resolvedLid}`);
            }
          } catch (err: any) {
            console.warn(`[Gateway] Could not resolve LID for ${jid}:`, err?.message || err);
          }
        }

        response.writeHead(200, { 'Content-Type': 'application/json' });
        response.end(JSON.stringify({ success: true, message: 'Message queued for delivery', lid: resolvedLid }));
      } catch (err: any) {
        response.writeHead(400, { 'Content-Type': 'application/json' });
        response.end(JSON.stringify({ error: 'Invalid JSON payload' }));
      }
    });
    return;
  }

  // Default Health check endpoint
  response.writeHead(200, { 'Content-Type': 'application/json' });
  response.end(JSON.stringify({ status: sock ? 'ready' : 'starting', timestamp: Date.now() }));
});

const HOST = process.env.HOST || '0.0.0.0';
server.listen(HEALTH_PORT, HOST, () => {
  console.log(`🚀 [Gateway] HTTP Server listening on ${HOST}:${HEALTH_PORT} (Secured & Outbound API ready)`);
});

startGateway().catch((err) => {
  console.error('[Gateway] Fatal initialization error:', err);
});
