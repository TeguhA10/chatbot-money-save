import makeWASocket, {
  DisconnectReason,
  useMultiFileAuthState,
  fetchLatestBaileysVersion,
  type WASocket,
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

const WEBHOOK_URL = process.env.LARAVEL_WEBHOOK_URL || 'http://127.0.0.1:8000/api/webhook/whatsapp';
const WEBHOOK_SECRET = process.env.GATEWAY_WEBHOOK_SECRET || 'local_dev_secret_12345';
const PAIRING_PHONE = process.env.PAIRING_PHONE_NUMBER ? process.env.PAIRING_PHONE_NUMBER.replace(/\D/g, '') : '';
const AUTH_DIR = process.env.BAILEYS_AUTH_DIR || 'auth_info_baileys';
const HEALTH_PORT = Number(process.env.HEALTH_PORT || 3000);

const logger = pino({ level: process.env.LOG_LEVEL || 'info' });
const webhookClient = new WebhookClient(WEBHOOK_URL, WEBHOOK_SECRET);

let sock: WASocket;

const outboundQueue = new OutboundQueue(async (msg: QueuedMessage) => {
  if (!sock) return;

  if (msg.type === 'TEXT' && msg.text) {
    await sock.sendMessage(msg.jid, { text: msg.text });
  } else if (msg.type === 'DOCUMENT' && msg.documentUrl) {
    await sock.sendMessage(msg.jid, {
      document: { url: msg.documentUrl },
      mimetype: msg.mimetype || 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      fileName: msg.fileName || 'Laporan_Keuangan.xlsx',
      caption: msg.text || '📊 Laporan Keuangan Anda',
    });
  }
});

async function startGateway() {
  const { state, saveCreds } = await useMultiFileAuthState(AUTH_DIR);
  const { version, isLatest } = await fetchLatestBaileysVersion();

  console.log(`[Gateway] Starting Baileys Gateway using WA v${version.join('.')} (Latest: ${isLatest})`);

  sock = makeWASocket({
    version,
    logger: pino({ level: 'silent' }),
    auth: state,
    printQRInTerminal: !PAIRING_PHONE, // only print QR if no pairing code phone provided
    browser: ['Windows', 'Chrome', '124.0.0.0'],
    generateHighQualityLinkPreview: true,
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
    }
  });

  sock.ev.on('creds.update', saveCreds);

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

  console.log(`[Gateway] Pesan dari ${pushName} (${fromJid}): "${messageText}"`);

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

  if (response.action === 'REPLY_TEXT' && response.reply_text) {
    outboundQueue.enqueue({
      jid: fromJid,
      type: 'TEXT',
      text: response.reply_text,
      idempotencyKey: `${msg.key.id}:reply`,
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
    });
  }
}

// Start gateway daemon
createServer((_request, response) => { response.writeHead(200, { 'Content-Type': 'application/json' }); response.end(JSON.stringify({ status: sock ? 'ready' : 'starting' })); }).listen(HEALTH_PORT);

startGateway().catch((err) => {
  console.error('[Gateway] Fatal initialization error:', err);
});
