import makeWASocket, {
  DisconnectReason,
  useMultiFileAuthState,
  fetchLatestBaileysVersion,
  type WASocket,
  type WACallEvent,
} from '@whiskeysockets/baileys';
import pino from 'pino';
import * as dotenv from 'dotenv';
import qrcode from 'qrcode-terminal';
import { Boom } from '@hapi/boom';
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
    msg.includes('Key used already or never filled')
  ) {
    return;
  }
  originalConsoleError(...args);
};

const WEBHOOK_URL = process.env.LARAVEL_WEBHOOK_URL || 'http://127.0.0.1:8000/api/webhook/whatsapp';
const WEBHOOK_SECRET = process.env.GATEWAY_WEBHOOK_SECRET || 'local_dev_secret_12345';
const PAIRING_PHONE = process.env.PAIRING_PHONE_NUMBER ? process.env.PAIRING_PHONE_NUMBER.replace(/\D/g, '') : '';
const AUTH_DIR = process.env.BAILEYS_AUTH_DIR || 'auth_info_baileys';
const HEALTH_PORT = Number(process.env.HEALTH_PORT || 3000);
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

async function handleIncomingMessage(msg: WAMessage): Promise<void> {
  // Ignore messages from self or status broadcasts
  if (!msg.message || msg.key.fromMe || msg.key.remoteJid === 'status@broadcast') {
    return;
  }

  const fromJid = msg.key.remoteJid;
  if (!fromJid) return;

  const pushName = msg.pushName || '';
  const messageText =
    msg.message.conversation ||
    msg.message.extendedTextMessage?.text ||
    '';

  if (!messageText.trim()) {
    return;
  }

  console.log(`[Gateway] Pesan diterima dari ${pushName || 'User'} (${fromJid}) [konten disamarkan demi privasi]`);

  // Relay to Laravel Webhook
  const response = await webhookClient.forwardMessage({
    message_id: msg.key.id || `BAILEYS_${Date.now()}`,
    from_jid: fromJid,
    push_name: pushName,
    message_text: messageText,
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

// Start gateway daemon
createServer((_request, response) => { response.writeHead(200, { 'Content-Type': 'application/json' }); response.end(JSON.stringify({ status: sock ? 'ready' : 'starting' })); }).listen(HEALTH_PORT);

startGateway().catch((err) => {
  console.error('[Gateway] Fatal initialization error:', err);
});
