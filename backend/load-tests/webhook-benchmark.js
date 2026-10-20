import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  stages: [
    { duration: '5s', target: 10 },  // Ramp up 10 concurrent chatters
    { duration: '15s', target: 30 }, // 30 concurrent chatters sending transactions
    { duration: '5s', target: 0 },   // Ramp down
  ],
  thresholds: {
    http_req_duration: ['p(95)<1000'], // 95% chat response under 1000ms
    http_req_failed: ['rate<0.02'],     // Error rate under 2%
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://127.0.0.1:8001';

const sampleMessages = [
  'saldo',
  'menu',
  'keluar 25000 makan siang',
  '+500000 gaji freelance',
  'beli bensin 50rb',
  'rekap hari ini',
  'rekap bulan ini',
  'cek budget',
];

export default function () {
  // Generate pseudo-random WhatsApp user and unique message ID
  const userNum = Math.floor(Math.random() * 200) + 1;
  const fromJid = `628129999${String(userNum).padStart(4, '0')}@s.whatsapp.net`;
  const messageId = `3EB0${Math.random().toString(36).substring(2, 12).toUpperCase()}`;
  const text = sampleMessages[Math.floor(Math.random() * sampleMessages.length)];

  const payload = JSON.stringify({
    message_id: messageId,
    from_jid: fromJid,
    push_name: `User ${userNum}`,
    message_text: text,
  });

  const headers = {
    'Content-Type': 'application/json',
    'X-Gateway-Secret': 'local_dev_secret_12345',
  };

  const res = http.post(`${BASE_URL}/api/webhook/whatsapp`, payload, { headers });

  check(res, {
    'Webhook status is 200 or 202': (r) => r.status === 200 || r.status === 202,
    'Action is returned': (r) => r.json('action') !== undefined,
  });

  sleep(0.2);
}
