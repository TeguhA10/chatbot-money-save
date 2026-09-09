# Research & Architectural Decisions: WhatsApp Personal Finance Tracker (Laravel 11 + Baileys)

**Feature Branch**: `001-wa-finance-tracker`
**Date**: 2026-09-08
**Status**: Completed

## 1. Core Application Framework: Laravel 11 (PHP 8.4)

- **Decision**: Use **Laravel 11** as the primary backend engine for all data modeling, financial transactions, database migrations, queue management, and REST API services.
- **Rationale**:
  - **Mature Ecosystem**: Eloquent ORM provides first-class support for ACID database transactions, model events, and query scopes.
  - **Built-in Queue System**: Laravel Queue (`sync`, `database`, or `redis`) enables smooth asynchronous background processing, anti-ban throttling, and non-blocking document exports.
  - **Testing Excellence**: Built-in integration with Pest PHP / PHPUnit allows complete offline local testing (`php artisan test`) with in-memory SQLite fixtures.
  - **Ready for Multi-Client**: Native REST API routing and token-based authentication (Laravel Sanctum) directly cater to future Vue 3 and React Native Expo clients.
- **Alternatives Considered**:
  - Node.js/Express monolith: Fast, but lacks Laravel's enterprise-grade migrations, Eloquent relationships, queue workers, and established testing harnesses out of the box.

## 2. WhatsApp Integration: Baileys Gateway Sidecar

- **Decision**: A lightweight Node.js daemon running `@whiskeysockets/baileys` located in `whatsapp-gateway/`, acting strictly as an I/O bridge between WhatsApp Web and the Laravel backend.
- **Rationale**:
  - Baileys is the most stable and lightweight Multi-Device WhatsApp Web client available.
  - **Decoupled Architecture**:
    - Incoming message received by Baileys $\rightarrow$ Forwarded via HTTP POST to Laravel Webhook (`http://localhost:8000/api/webhook/whatsapp`).
    - Laravel processes the message, updates balance, and responds with reply payload.
    - Baileys transmits the reply back to the WhatsApp user.
    - If WhatsApp socket drops, Laravel core logic and data remain completely unaffected.
  - **Local Testability**: Developers can test the entire finance logic without Baileys running, simply by making HTTP calls to Laravel or running the Artisan test simulator (`php artisan finance:simulate`).

## 3. Database & Storage Architecture: SQLite in WAL Mode via Eloquent

- **Decision**: SQLite with Write-Ahead Logging (`PRAGMA journal_mode = WAL;`) and integer monetary storage (`BIGINT`).
- **Rationale**:
  - **Zero Network Latency**: SQLite runs directly in-process with Laravel, executing queries in microseconds.
  - **ACID Atomicity**: `DB::transaction(function() { ... })` guarantees that debit/credit mutations and balance updates never desynchronize.
  - **1,000+ Users Scale**: SQLite WAL mode supports concurrent readers without locking the writer. Easily handles 1,000 users and 200,000+ transactions with a file size under 30MB.
  - **Portability**: The SQLite database file (`database/database.sqlite`) requires zero external server setup, making local Windows development seamless.
- **Alternatives Considered**:
  - Writing directly to raw `.xlsx` files per user on every chat: Rejected due to severe I/O overhead and lock contention.

## 4. Input Parsing: Laravel Regex & Service Layer

- **Decision**: Deterministic Regex Parser in `App\Services\TransactionParserService` with Indonesian colloquial normalization.
- **Rationale**:
  - Runs in < 1ms on PHP 8.4 with OPcache, with zero external API costs.
  - Normalizes colloquial suffixes:
    - `k` / `rb` $\rightarrow \times 1.000$ (e.g. `25k`, `25rb` $\rightarrow 25000$).
    - `jt` $\rightarrow \times 1.000.000$ (e.g. `1.5jt` $\rightarrow 1500000$).
    - Dot and comma sanitization (`25.000` $\rightarrow 25000$).
  - Maps directional keywords:
    - Expense: `keluar`, `beli`, `bayar`, `-`.
    - Income: `masuk`, `dapat`, `gaji`, `+`, `tf`.

## 5. Spreadsheet Generation: Laravel Excel (Maatwebsite/Excel)

- **Decision**: `maatwebsite/excel` (backed by PhpSpreadsheet).
- **Rationale**:
  - The industry-standard spreadsheet library for Laravel.
  - Native multi-sheet support (`WithMultipleSheets` interface):
    - Sheet 1: `SummaryDashboardExport` with KPI cards, category distribution, and automated Excel formulas (`=SUM(...)`).
    - Sheet 2..N: `MonthlyTransactionExport` with styled headers, alternating row colors, and currency number formatting (`"Rp"#,##0`).
  - Supports streaming / queued generation (`ShouldQueue`) for large files so the application thread is never blocked.
