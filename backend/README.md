# 💰 WA Finance Bot — Backend Service (Laravel 11)

Layanan backend utama untuk bot WhatsApp pencatat keuangan pribadi, dibangun dengan **Laravel 11**, **SQLite**, dan **Sanctum**.

> 📌 **Dokumentasi Lengkap Proyek**: Silakan baca panduan menyeluruh di [Root README.md](../README.md).

---

## 🚀 Quick Setup (Backend)

```bash
# 1. Salin konfigurasi environment
cp .env.example .env

# 2. Instal dependensi
composer install

# 3. Generate APP_KEY
php artisan key:generate

# 4. Siapkan database SQLite & jalankan migrasi
touch database/database.sqlite
php artisan migrate --seed

# 5. Jalankan server lokal
php artisan serve
```

---

## 🛠️ Perintah Artisan Berguna

- **Simulator Chat Terminal (Uji fitur tanpa WhatsApp)**:
  ```bash
  php artisan finance:simulate
  ```
- **Automated Tests (Pest PHP)**:
  ```bash
  php artisan test
  ```
- **Clear Cache & Config**:
  ```bash
  php artisan optimize:clear
  ```

---

## 📡 Ringkasan Endpoint Utama

- **Webhook Baileys**: `POST /api/webhook/whatsapp`
- **Autentikasi OTP**: `POST /api/v1/auth/request-otp` & `POST /api/v1/auth/verify-otp`
- **Profil & Transaksi**: `GET /api/v1/profile`, `GET /api/v1/transactions`, `POST /api/v1/transactions`
- **Kategori & Analisis**: `GET /api/v1/categories`, `GET /api/v1/analytics/summary`
- **Ekspor Excel**: `GET /api/v1/export/excel`
