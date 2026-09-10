# REST API Interface Contract

**Feature Branch**: `001-wa-finance-tracker`
**Date**: 2026-09-08
**Version**: `v1`
**Base URL**: `http://localhost:3000/api/v1`
**Content-Type**: `application/json`

Dokumen ini mendefinisikan kontrak antarmuka REST API antara **Backend Bot & Core Service** dengan klien **Vue 3 Web Dashboard** dan **React Native Expo Mobile App**.

---

## 1. Konvensi Umum & Keamanan

### A. Standar Format Respons
Semua endpoint menggunakan format JSON seragam:
```json
// Sukses
{
  "success": true,
  "data": { ... },
  "message": "Deskripsi sukses opsional"
}

// Gagal / Error
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR | UNAUTHORIZED | NOT_FOUND | SERVER_ERROR",
    "message": "Pesan kesalahan manusiawi",
    "details": []
  }
}
```

### B. Mekanisme Autentikasi (WhatsApp OTP Login)
Pengguna login di Web (Vue 3) atau Mobile (Expo) menggunakan nomor WhatsApp mereka.
Sistem mengirimkan 6-digit kode OTP langsung ke chat WhatsApp pengguna.
Setelah diverifikasi, klien menerima JWT Token yang disertakan pada header:
```http
Authorization: Bearer <jwt_token>
```
*Token JWT memuat payload `sub` berisi verified `user_jid` (misal: `6281234567890@s.whatsapp.net`). Seluruh query database di backend secara otomatis difilter berdasarkan `user_jid` ini (Mematuhi Konstitusi Prinsip I: Isolasi Multi-Tenant).*

---

## 2. Endpoint Autentikasi (`/auth`)

### `POST /auth/request-otp`
Meminta pengiriman 6-digit OTP ke nomor WhatsApp pengguna untuk login di Web/Expo.

**Request Body**:
```json
{
  "phone": "6281234567890"
}
```

**Response (200 OK)**:
```json
{
  "success": true,
  "message": "Kode OTP telah dikirimkan ke WhatsApp Anda."
}
```

---

### `POST /auth/verify-otp`
Memverifikasi kode OTP dan mengembalikan JWT Access Token.

**Request Body**:
```json
{
  "phone": "6281234567890",
  "otp": "839201"
}
```

**Response (200 OK)**:
```json
{
  "success": true,
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "user": {
      "jid": "6281234567890@s.whatsapp.net",
      "display_name": "Teguh AF",
      "current_balance": 1465000
    }
  }
}
```

---

## 3. Endpoint Profil & Saldo (`/profile`)

### `GET /profile`
Mengambil data ringkas profil dan saldo terkini pengguna.

**Headers**: `Authorization: Bearer <token>`

**Response (200 OK)**:
```json
{
  "success": true,
  "data": {
    "jid": "6281234567890@s.whatsapp.net",
    "display_name": "Teguh AF",
    "current_balance": 1465000,
    "created_at": "2026-09-08T10:00:00Z"
  }
}
```

---

## 4. Endpoint Transaksi Keuangan (`/transactions`)

### `GET /transactions`
Menampilkan daftar transaksi dengan filter pagination, tanggal, dan kategori (digunakan pada tabel riwayat transaksi Vue 3 & list mutasi Expo).

**Query Parameters**:
- `page` (integer, default: `1`)
- `limit` (integer, default: `20`)
- `type` (optional: `EXPENSE` | `INCOME`)
- `category_id` (optional string UUID)
- `start_date` (optional: `YYYY-MM-DD`)
- `end_date` (optional: `YYYY-MM-DD`)
- `search` (optional string keyword)

**Response (200 OK)**:
```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": "tx-9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d",
        "type": "EXPENSE",
        "amount": 35000,
        "description": "Makan siang nasi padang",
        "category": {
          "id": "cat-food-01",
          "name": "Makanan & Minuman",
          "icon": "🍜"
        },
        "balance_after": 1465000,
        "transaction_date": "2026-09-08T12:30:00Z",
        "status": "ACTIVE"
      }
    ],
    "pagination": {
      "page": 1,
      "limit": 20,
      "total_items": 45,
      "total_pages": 3
    }
  }
}
```

---

### `POST /transactions`
Menambah catatan transaksi manual langsung dari form Web atau Mobile App.

**Request Body**:
```json
{
  "type": "EXPENSE",
  "amount": 25000,
  "category_id": "cat-food-01",
  "description": "Beli kopi susu",
  "transaction_date": "2026-09-08T14:15:00Z"
}
```

**Response (201 Created)**:
```json
{
  "success": true,
  "data": {
    "id": "tx-12345",
    "type": "EXPENSE",
    "amount": 25000,
    "description": "Beli kopi susu",
    "balance_after": 1440000,
    "created_at": "2026-09-08T14:15:01Z"
  },
  "message": "Transaksi berhasil dicatat."
}
```

---

### `PUT /transactions/:id`
Memperbarui nominal amount dan deskripsi transaksi aktif, serta otomatis merekakulasi saldo berjalan (running balance) dan snapshot `balance_after` pada buku kas (ledger).

**Headers**: `Authorization: Bearer <token>`

**Request Body**:
```json
{
  "amount": 75000,
  "description": "Makan siang keluarga"
}
```

**Response (200 OK)**:
```json
{
  "success": true,
  "data": {
    "transaction": {
      "id": "tx-12345",
      "type": "EXPENSE",
      "amount": 75000,
      "description": "Makan siang keluarga",
      "category": {
        "id": "cat-food-01",
        "name": "Makanan & Minuman",
        "icon": "🍜"
      },
      "balance_after": 1390000,
      "transaction_date": "2026-09-08T14:15:00Z",
      "status": "ACTIVE"
    },
    "new_balance": 1390000
  },
  "message": "Transaksi berhasil diubah."
}
```

---

### `DELETE /transactions/:id`
Membatalkan (void) transaksi tertentu dan merekakulasi saldo mutasi secara otomatis tanpa menghapus riwayat audit trail fisik.

**Response (200 OK)**:
```json
{
  "success": true,
  "data": {
    "id": "tx-12345",
    "status": "VOIDED",
    "refunded_amount": 25000,
    "new_balance": 1465000
  },
  "message": "Transaksi berhasil dibatalkan."
}
```

---

## 5. Endpoint Kategori (`/categories`)

### `GET /categories`
Menampilkan daftar kategori bawaan (default) dan kategori kustom buatan user.

**Response (200 OK)**:
```json
{
  "success": true,
  "data": [
    {
      "id": "cat-food-01",
      "name": "Makanan & Minuman",
      "type": "EXPENSE",
      "icon": "🍜",
      "is_default": true
    },
    {
      "id": "cat-custom-99",
      "name": "Kucing",
      "type": "EXPENSE",
      "icon": "📌",
      "is_default": false
    }
  ]
}
```

---

### `POST /categories`
Menambahkan kategori baru buatan user.

**Request Body**:
```json
{
  "name": "Investasi Saham",
  "type": "EXPENSE",
  "icon": "📈"
}
```

**Response (201 Created)**:
```json
{
  "success": true,
  "data": {
    "id": "cat-uuid-generated",
    "name": "Investasi Saham",
    "type": "EXPENSE",
    "icon": "📈",
    "is_default": false
  }
}
```

---

## 6. Endpoint Analitik & Grafik Dashboard (`/analytics`)
*Sangat penting untuk merender grafik Chart.js / Recharts di Vue 3 dan Expo Charts di Mobile.*

### `GET /analytics/summary`
Mengambil ringkasan keuangan bulanan (KPI Cards).

**Query Parameters**:
- `month` (optional: `YYYY-MM`, default: bulan saat ini)

**Response (200 OK)**:
```json
{
  "success": true,
  "data": {
    "period": "2026-09",
    "total_income": 5000000,
    "total_expense": 2450000,
    "net_savings": 2550000,
    "current_balance": 2550000,
    "delta_vs_last_month": {
      "expense_percentage": -12.5,
      "income_percentage": 5.0
    }
  }
}
```

---

### `GET /analytics/category-breakdown`
Menghasilkan data distribusi pengeluaran per kategori untuk **Donut/Pie Chart**.

**Query Parameters**:
- `month` (optional: `YYYY-MM`)

**Response (200 OK)**:
```json
{
  "success": true,
  "data": [
    {
      "category_id": "cat-food-01",
      "category_name": "Makanan & Minuman",
      "icon": "🍜",
      "total_amount": 1250000,
      "percentage": 51.0
    },
    {
      "category_id": "cat-trans-02",
      "category_name": "Transportasi",
      "icon": "🛵",
      "total_amount": 450000,
      "percentage": 18.4
    }
  ]
}
```

---

### `GET /analytics/monthly-trend`
Mengambil riwayat perbandingan pengeluaran vs pemasukan 6 bulan terakhir untuk **Bar/Line Chart**.

**Response (200 OK)**:
```json
{
  "success": true,
  "data": [
    { "month": "2026-04", "income": 4000000, "expense": 2800000 },
    { "month": "2026-05", "income": 4500000, "expense": 3100000 },
    { "month": "2026-06", "income": 4200000, "expense": 2500000 },
    { "month": "2026-07", "income": 5000000, "expense": 3400000 },
    { "month": "2026-08", "income": 4800000, "expense": 2900000 },
    { "month": "2026-09", "income": 5000000, "expense": 2450000 }
  ]
}
```

---

## 7. Endpoint Ekspor Dokumen (`/export`)

### `GET /export/excel`
Mendownload file spreadsheet multi-sheet (.xlsx) langsung via HTTP response stream (untuk tombol download di Web browser atau download file di mobile app).

**Query Parameters**:
- `year` (optional, default: tahun berjalan)

**Response Headers**:
```http
Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet
Content-Disposition: attachment; filename="Laporan_Keuangan_6281234567890_20260908.xlsx"
```
*(Binary stream data workbook multi-sheet)*

---

## 8. Endpoint Webhook WhatsApp Gateway (`/webhook/whatsapp`)

Endpoint internal yang dipanggil oleh daemon Node.js Baileys (`whatsapp-gateway/`) setiap kali ada pesan masuk dari pengguna WhatsApp.

### `POST /webhook/whatsapp`

**Headers**:
- `Content-Type: application/json`
- `X-Gateway-Secret: <shared_secret>` (mencegah akses webhook yang tidak sah)

**Request Body**:
```json
{
  "message_id": "3EB0123456789ABCDEF",
  "from_jid": "6281234567890@s.whatsapp.net",
  "push_name": "Teguh AF",
  "message_text": "keluar 25000 makan siang",
  "timestamp": 1725792000
}
```

**Response (200 OK)**:
```json
{
  "action": "REPLY_TEXT",
  "reply_text": "✅ *Catatan Pengeluaran Tersimpan*\n━━━━━━━━━━━━━━━━━\n📝 *Keterangan*: Makan siang\n🏷️ *Kategori*: 🍜 Makanan & Minuman\n💸 *Nominal*: Rp 25.000\n━━━━━━━━━━━━━━━━━\n💰 *Sisa Saldo*: Rp 75.000\n_Ketik \"batal\" jika ingin membatalkan transaksi ini._"
}
```

*Atau jika perintahnya adalah `export excel`:*
```json
{
  "action": "SEND_DOCUMENT",
  "reply_text": "📊 Berikut laporan keuangan pribadi Anda dalam format Excel multi-sheet:",
  "file_url": "/api/v1/export/excel?user_jid=6281234567890@s.whatsapp.net",
  "file_name": "Laporan_Keuangan_6281234567890_20260908.xlsx",
  "mimetype": "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
}
```

