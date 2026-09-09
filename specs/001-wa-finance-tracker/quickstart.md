# Quickstart & Verification Guide: WhatsApp Personal Finance Tracker (Laravel 11 + Baileys)

**Feature Branch**: `001-wa-finance-tracker`
**Date**: 2026-09-08

Panduan ini memungkinkan Anda menjalankan dan menguji seluruh logika bot keuangan, database SQLite, mutasi saldo, Excel export, dan REST API **secara 100% lokal di Windows** menggunakan **Laravel 11**.

---

## 1. Prerequisites (Kebutuhan Sistem Lokal)

- **PHP**: PHP 8.2+ atau PHP 8.4 (Terdeteksi terpasang: `PHP 8.4.14`)
- **Composer**: Composer 2.x (Terdeteksi terpasang: `Composer 2.8.12`)
- **Node.js**: Node.js v18+ atau v24+ (Terdeteksi terpasang: `v24.12.0`)
- **Database**: SQLite (built-in ekstensi PHP `pdo_sqlite`, zero configuration)

---

## 2. Setup & Instalasi Proyek Laravel

```bash
# 1. Pastikan file database SQLite lokal ada
touch database/database.sqlite
# (Di Windows PowerShell jika touch belum ada: New-Item database/database.sqlite -ItemType File -Force)

# 2. Instal dependensi Composer (Laravel 11 + Maatwebsite Excel + Sanctum)
composer install

# 3. Jalankan migrasi database & seed kategori default
php artisan migrate --seed

# 4. Instal dependensi WhatsApp Gateway Baileys (di folder whatsapp-gateway/)
cd whatsapp-gateway && npm install && cd ..
```

---

## 3. Opsi Pengujian 1: Test Otomatis Lokal (Pest PHP / PHPUnit)

Pengujian ini berjalan 100% offline, memverifikasi seluruh fungsi tanpa butuh internet/WA:

```bash
# Menjalankan seluruh test suite (Unit & Feature)
php artisan test

# Menguji khusus parser bahasa Indonesia (makan 25k, -15rb, gaji 1.5jt)
php artisan test tests/Unit/ParserServiceTest.php

# Menguji integritas transaksi ACID & refund saldo di database SQLite
php artisan test tests/Unit/FinanceServiceTest.php

# Menguji pembuatan file Excel multi-sheet (.xlsx) & formula SUM
php artisan test tests/Feature/ExcelExportTest.php

# Menguji endpoint REST API
php artisan test tests/Feature/Api/TransactionApiTest.php
```

---

## 4. Opsi Pengujian 2: Simulator Chat Terminal Interaktif (Tanpa HP/WA)

Laravel menyediakan Artisan Command khusus untuk mencoba chat secara langsung di terminal:

```bash
php artisan finance:simulate
```

**Contoh Interaksi di Terminal Windows**:
```text
======================================================
  SIMULATOR WHATSAPP BOT CATATAN KEUANGAN (LARAVEL 11)
======================================================
Ketik pesan transaksi Anda (atau 'exit' untuk keluar).

Pesan: keluar 25000 nasi padang
🤖 Bot:
✅ *Catatan Pengeluaran Tersimpan*
━━━━━━━━━━━━━━━━━
📝 Keterangan: Nasi padang
🏷️ Kategori: 🍜 Makanan & Minuman
💸 Nominal: Rp 25.000
━━━━━━━━━━━━━━━━━
💰 Sisa Saldo: Rp 75.000

Pesan: saldo
🤖 Bot:
💰 Saldo Anda saat ini: Rp 75.000

Pesan: export excel
🤖 Bot:
📊 File Excel berhasil dibuat: storage/app/exports/Laporan_Keuangan_SIMULATOR_20260908.xlsx
(Anda bisa langsung membuka file .xlsx tersebut di Microsoft Excel / WPS Office di komputer Anda!)

Pesan: batal
🤖 Bot:
↩️ Transaksi 'Nasi padang' (Rp 25.000) berhasil dibatalkan. Saldo kembali: Rp 100.000.
```

---

## 5. Opsi Pengujian 3: Menjalankan Server REST API Lokal

Untuk menguji API yang nantinya dipakai oleh Vue 3 Web dan React Native Expo:

```bash
php artisan serve
```
Server berjalan di `http://127.0.0.1:8000`. Endpoint yang siap dicoba via browser / Postman:
- `GET http://127.0.0.1:8000/api/v1/profile`
- `GET http://127.0.0.1:8000/api/v1/transactions`
- `GET http://127.0.0.1:8000/api/v1/analytics/summary`
- `GET http://127.0.0.1:8000/api/v1/export/excel` (langsung unduh file `.xlsx`)

---

## 6. Opsi Pengujian 4: Menjalankan Bot Live WhatsApp Nyata

Jika semua fitur lokal sudah terbukti lancar dan Anda siap menghubungkan WhatsApp sungguhan:

1. Di Terminal 1 (Laravel Server):
   ```bash
   php artisan serve
   ```
2. Di Terminal 2 (WhatsApp Baileys Gateway):
   ```bash
   cd whatsapp-gateway
   npm run dev
   ```
   Masukkan nomor WhatsApp Anda di terminal $\rightarrow$ masukkan 8 karakter kode pairing di menu WhatsApp HP (**Perangkat Tertaut**).
   Bot WhatsApp Anda resmi online dan terhubung ke backend Laravel!
