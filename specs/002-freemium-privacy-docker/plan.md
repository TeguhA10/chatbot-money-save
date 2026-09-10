# Implementation Plan: Freemium Paywall, Zero-Knowledge Nominal Encryption, and Production Dockerization

**Branch**: `002-freemium-privacy-docker` | **Date**: 2026-09-09 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/002-freemium-privacy-docker/spec.md`

---

## Summary

Implementasi model bisnis freemium dengan batas kuota maksimal 100 transaksi gratis dan otomatisasi langganan berbayar via Midtrans (Snap/QRIS). Sistem dilengkapi dengan perlindungan privasi tingkat tinggi berupa **Zero-Knowledge User-Keyed Encryption (AES-256-GCM)** sehingga seluruh nominal transaksi dan saldo tersimpan dalam bentuk *ciphertext* acak yang tidak dapat dibaca oleh pihak pengembang maupun administrator basis data tanpa PIN rahasia pengguna. Seluruh ekosistem dikemas ke dalam arsitektur kontainer **Docker multi-layanan** siap publikasi dengan volume persisten untuk sesi WhatsApp dan basis data.

> **Revised architecture (2026-09-10)**: Strict zero knowledge is delivered by a companion PWA, not by a PIN sent through WhatsApp. Web Crypto encryption/decryption and all balance/report arithmetic run in the browser. Laravel, the gateway, workers, Redis, logs, and database receive only opaque encrypted ledger envelopes. WhatsApp becomes a linking, subscription, payment, and notification channel.

---

## Technical Context

**Language/Version**: PHP 8.4+ (Backend Laravel 11), TypeScript 5.x / Node.js 22+ (WhatsApp Baileys Gateway), browser Web Crypto API / PWA  
**Primary Dependencies**: 
- Backend: Laravel Framework 11.x, `midtrans/midtrans-php`, `maatwebsite/excel`, Laravel Sanctum, PHP OpenSSL extension (`aes-256-gcm`, `pbkdf2`)
- Gateway: `@whiskeysockets/baileys`, `pino`, `axios`, `qrcode-terminal`  
**Storage**: SQLite 3 (Database persisten ACID) dengan opsi migrasi mudah ke PostgreSQL / MySQL via Docker Compose  
**Testing**: Pest PHP (PHPUnit test suite)  
**Target Platform**: Linux Server / Cloud VPS (Ubuntu 22.04/24.04), Docker Engine 24+, Docker Compose v2+  
**Project Type**: Multi-service Web API Backend + Real-time WhatsApp Socket Gateway Sidecar  
**Performance Goals**: Latensi pengakuan pesan WhatsApp < 1.5 detik, dekripsi nominal in-memory < 5ms per transaksi, waktu build kontainer Docker < 3 menit  
**Constraints**: Strict zero-knowledge privacy (PIN, raw encryption key, plaintext nominal, and plaintext balance never reach the server), zero session loss pada restart kontainer, proteksi anti-ban laju pesan keluar (throttled queue)  
**Scale/Scope**: 1.000+ pengguna aktif, 100 transaksi gratis per pengguna sebelum paywall, masa aktif langganan bulanan 30 hari  

---

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Konstitusi | Prinsip | Status Evaluasi Desain |
| :--- | :--- | :--- |
| **Prinsip I** | *Strict Multi-Tenant Isolation & Zero Data Leakage* | **LULUS (PASS)**: Semua tabel `subscriptions`, `payment_orders`, dan `transactions` dipartisi mutlak oleh `user_jid`. Kunci enkripsi diturunkan secara independen per pengguna sehingga dekripsi silang antar-pengguna mustahil secara kriptografis. |
| **Prinsip II** | *Non-Blocking Event Loop & Asynchronous Processing* | **LULUS (PASS)**: Gateway Baileys hanya bertugas me-relay pesan ke backend Laravel via HTTP webhook. Pembuatan invoice Midtrans dan ekspor dokumen Excel berjalan tanpa menghalangi event loop socket. |
| **Prinsip III** | *Financial Data Integrity & Atomic Transactions* | **LULUS (PASS)**: Mutasi kuota, verifikasi webhook Midtrans, dan pembaruan saldo terenkripsi dibungkus dalam `DB::transaction` atomik dengan rekalkulasi ledger berurutan. |
| **Prinsip IV** | *Outbound Rate Limiting & Anti-Ban Safeguards* | **LULUS (PASS)**: Antrean pesan keluar (*outbound queue*) pada sidecar Baileys memberlakukan jeda waktu aman dan deduplikasi pesan masuk via tabel `processed_messages`. |
| **Prinsip V** | *Test-First (TDD) for Financial Calculation & Parsing* | **LULUS (PASS)**: Skenario pengujian kuota, dekripsi kriptografis, dan verifikasi tanda tangan webhook Midtrans dirancang terlebih dahulu dan divalidasi via Pest test suite. |

---

## Project Structure

### Documentation (this feature)

```text
specs/002-freemium-privacy-docker/
├── spec.md              # Spesifikasi kebutuhan fitur & fungsional
├── plan.md              # Dokumen perencanaan teknis & arsitektur (berkas ini)
├── research.md          # Keputusan teknis: Enkripsi AES-GCM, Midtrans, & Docker
├── data-model.md        # Desain skema DB, migrasi, dan diagram ERD
├── quickstart.md        # Panduan pengujian & validasi lokal end-to-end
├── contracts/
│   ├── midtrans-webhook.md             # Kontrak API Webhook Midtrans
│   └── whatsapp-subscription-flow.md   # Kontrak dialog & template chat WhatsApp
└── checklists/
    └── requirements.md  # Checklist mutu spesifikasi
```

### Source Code Layout

```text
chatbot-money-save/
├── docker-compose.yml              # Orkestrasi Docker multi-kontainer (app + gateway)
├── docker-compose.prod.yml         # Konfigurasi produksi dengan Nginx reverse proxy
├── backend/
│   ├── Dockerfile                  # Multi-stage build PHP 8.4-FPM / CLI server
│   ├── app/
│   │   ├── Http/Controllers/Api/
│   │   │   ├── MidtransWebhookController.php # [NEW] Handler notifikasi pembayaran Midtrans
│   │   │   ├── SubscriptionController.php    # [NEW] Cek status langganan & beli paket
│   │   │   └── WebhookController.php         # [MODIFY] Integrasi guard kuota 100 & PIN
│   │   ├── Models/
│   │   │   ├── Subscription.php              # [NEW] Model data paket langganan
│   │   │   ├── PaymentOrder.php              # [NEW] Model pesanan Midtrans
│   │   │   ├── User.php                      # [MODIFY] Kolom tier, kuota, & PIN hash
│   │   │   └── Transaction.php               # [MODIFY] Kolom encrypted_amount & balance
│   │   └── Services/
│   │       ├── EncryptionService.php         # [NEW] PBKDF2 + AES-256-GCM Zero-Knowledge service
│   │       ├── SubscriptionService.php       # [NEW] Guard kuota 100 & pembaruan masa aktif
│   │       ├── MidtransService.php           # [NEW] Klien API Snap Midtrans & verifikasi SHA512
│   │       └── FinanceService.php            # [MODIFY] Integrasi nominal terenkripsi
│   ├── database/migrations/
│   │   ├── 2026_09_09_000001_add_subscription_and_pin_to_users.php # [NEW]
│   │   └── 2026_09_09_000002_create_subscription_and_payment_tables.php # [NEW]
│   └── tests/Feature/
│       ├── SubscriptionQuotaTest.php         # [NEW] Test kuota 100 & paywall
│       ├── ZeroKnowledgeEncryptionTest.php   # [NEW] Test enkripsi-dekripsi AES-GCM
│       └── MidtransWebhookTest.php           # [NEW] Test webhook settlement & signature
│
└── whatsapp-gateway/
    ├── Dockerfile                  # Node.js 22 LTS container build
    └── src/
        └── index.ts                # [MODIFY] Persistent session & rate-limited queue
```

---

## Phase Execution Summary

- **Phase 0: Research**: Selesai ([research.md](research.md)).
- **Phase 1: Design & Contracts**: Selesai ([data-model.md](data-model.md), [contracts/](contracts/), [quickstart.md](quickstart.md)).
- **Phase 2: Task Generation**: Siap dijalankan melalui `/speckit-tasks` untuk menjadwalkan implementasi file secara terstruktur dan terurut.
