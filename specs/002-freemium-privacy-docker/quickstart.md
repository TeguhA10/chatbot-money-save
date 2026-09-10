# Quickstart Validation Guide: Freemium Paywall, Zero-Knowledge Encryption, and Docker Publish

**Feature Branch**: `002-freemium-privacy-docker`
**Date**: 2026-09-09

Panduan ini mendokumentasikan skenario pengujian dan verifikasi fitur kuota berbayar, integrasi Midtrans, enkripsi nominal Zero-Knowledge, dan deployment multi-container Docker secara lokal.

---

## 1. Prasyarat Pengujian Lokal
- **PHP**: 8.2+ atau 8.4+
- **Composer**: 2.x
- **Node.js**: 18+ / 22+
- **Docker Engine & Docker Compose**: Versi 24+ / Compose v2+
- **Midtrans Account (Sandbox)**: `MIDTRANS_SERVER_KEY` & `MIDTRANS_CLIENT_KEY`

---

## 2. Pengujian Skenario 1: Batas Kuota 100 & Paywall Interaktif

### Menjalankan Unit & Feature Test Kuota
```bash
cd backend
php artisan test --filter=SubscriptionQuotaTest
```

### Simulasi Interaksi via Terminal Command
```bash
php artisan finance:simulate
```
1. Kirim transaksi hingga kuota 100 habis: `keluar 10000 tes`.
2. Pada transaksi ke-101, sistem membalas dengan template paywall:
   `⛔ Batas Kuota Gratis Tercapai. Ketik 'beli pro' untuk berlangganan.`
3. Ketik `saldo` $\rightarrow$ saldo tetap dapat dicek dan tidak diblokir.

---

## 3. Pengujian Skenario 2: Zero-Knowledge Nominal Encryption

### Menjalankan Test Enkripsi Kriptografis
```bash
cd backend
php artisan test --filter=ZeroKnowledgeEncryptionTest
```

### Verifikasi Manual di Database
1. Buka database SQLite lokal menggunakan SQLite CLI atau DB Browser for SQLite:
   ```bash
   sqlite3 database/database.sqlite "SELECT id, user_jid, encrypted_amount, encrypted_balance_after FROM transactions LIMIT 5;"
   ```
2. **Kriteria Kelulusan**:
   - Kolom `encrypted_amount` dan `encrypted_balance_after` berisi string acak base64 (contoh: `dGhpcyBpcyBhbiBpdjoxMjM0NTY3OD...`).
   - Tidak ada angka numerik nominal terbaca dalam bentuk teks biasa (*plaintext*).

---

## 4. Pengujian Skenario 3: Simulasi Webhook Pembayaran Midtrans

Gunakan cURL atau Postman untuk menyimulasikan webhook callback dari Midtrans Sandbox:

```bash
curl -X POST http://127.0.0.1:8000/api/webhook/midtrans \
  -H "Content-Type: application/json" \
  -d '{
    "order_id": "SUB-6281234567890-1725800000",
    "status_code": "200",
    "gross_amount": "25000.00",
    "signature_key": "YOUR_CALCULATED_SHA512_SIGNATURE",
    "transaction_status": "settlement",
    "payment_type": "qris"
  }'
```

**Hasil yang Diharapkan**:
- Status HTTP `200 OK`.
- Status user `6281234567890@s.whatsapp.net` di tabel `users` berubah menjadi `PREMIUM`.
- `subscription_expires_at` bertambah 30 hari dari tanggal sekarang.

---

## 5. Pengujian Skenario 4: Docker Multi-Container Deployment

### Membangun dan Menjalankan Kontainer
Dari root repositori:
```bash
# Salin konfigurasi environment produksi
cp backend/.env.example backend/.env.production
cp whatsapp-gateway/.env.example whatsapp-gateway/.env.production

# Jalankan seluruh stack kontainer di background
docker compose up -d --build
```

### Memeriksa Status Kontainer & Healthcheck
```bash
docker compose ps
```
Pastikan seluruh service:
- `backend`: Status `healthy` (port 8000).
- `gateway`: Status `running` (port 3000).
- `volume`: `baileys_auth` dan `sqlite_data` terpasang persisten.

### Menautkan WhatsApp Bot di Lingkungan Docker
```bash
docker compose logs -f gateway
```
Ambil kode pairing 8-karakter dari log kontainer gateway dan tautkan ke menu WhatsApp HP Anda.
