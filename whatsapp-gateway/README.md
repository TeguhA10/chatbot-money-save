# 📱 WhatsApp Gateway Sidecar (Baileys)

Layanan sidecar berbasis **Node.js & TypeScript** menggunakan pustaka `@whiskeysockets/baileys` untuk menghubungkan akun WhatsApp bot dengan backend Laravel 11.

> 📌 **Dokumentasi Lengkap Proyek**: Silakan baca panduan menyeluruh di [Root README.md](../README.md).

---

## 🚀 Quick Setup

```bash
# 1. Salin konfigurasi environment
cp .env.example .env

# 2. Instal dependensi
npm install

# 3. Jalankan gateway dalam mode development (dengan live reload)
npm run dev
```

---

## ⚙️ Konfigurasi Environment (`.env`)

```env
PORT=3000
LARAVEL_WEBHOOK_URL=http://127.0.0.1:8000/api/webhook/whatsapp
GATEWAY_WEBHOOK_SECRET=local_dev_secret_12345

# Masukkan nomor HP (format: 628xxx) untuk pairing code di terminal
# Kosongkan jika ingin scan via QR Code di terminal
PAIRING_PHONE_NUMBER=
```

---

## 🔄 Reset Sesi WhatsApp

Jika ingin menghubungkan ulang perangkat atau mengganti nomor WhatsApp bot:
```bash
rm -rf auth_info_baileys/
npm run dev
```
