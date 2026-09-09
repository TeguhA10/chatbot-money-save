# Implementation Plan: WhatsApp Personal Finance & Expense Tracker Bot

**Branch**: `001-wa-finance-tracker` | **Date**: 2026-09-08 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/001-wa-finance-tracker/spec.md`

## Summary

Build a high-performance WhatsApp Personal Finance Tracker bot supporting 1,000+ isolated users with Baileys. The system processes natural financial text messages via an ultra-fast local regex parser (<50ms), persists transactions atomically in SQLite (WAL mode) with exact integer Rupiah precision, manages outbound message pacing via an anti-ban queue, and generates customized multi-sheet Excel workbooks (.xlsx) with an executive dashboard and monthly breakdown tabs delivered directly into the user's WhatsApp chat.

## Technical Context

**Language/Version**: PHP 8.4 / Laravel 11 (Core Backend) + TypeScript / Node.js 24 (Baileys Gateway Sidecar)  
**Primary Dependencies**: 
- Core Backend: `laravel/framework:^11.0`, `maatwebsite/excel:^3.1`, `laravel/sanctum:^4.0`
- WhatsApp Gateway Sidecar: `@whiskeysockets/baileys:^6.7`, `p-queue:^8.0`, `pino:^9.0`, `dotenv:^16.4`
- Frontend Clients (Milestone 2): Vue 3 (Vite + Tailwind + Pinia) & React Native Expo Mobile  
**Storage**: SQLite in Write-Ahead Logging mode (`PRAGMA journal_mode = WAL;`) via Laravel Eloquent  
**Testing**: Pest PHP / PHPUnit (`php artisan test`) for 100% offline local testing  
**Target Platform**: Multi-platform (Laravel 11 PHP Backend + Baileys Node.js Sidecar + Web + Expo Mobile)  
**Project Type**: Laravel 11 Application with Baileys Gateway Sidecar  
**Performance Goals**: 
- Inbound message parsing & intent detection: < 50ms
- REST API endpoint response time: < 30ms
- Transaction balance mutation & ACID commit: < 20ms
- Full chat reply latency: < 1.5s
- Multi-sheet Excel export generation (2,000 rows): < 3s  
**Constraints**: 
- Single-machine memory footprint < 300MB total (PHP + Baileys)
- 100% tenant isolation (zero cross-user data leakage)
- Zero floating-point rounding errors (all amounts stored in integer Rupiah)  
**Scale/Scope**: 1,000+ active users, 100+ burst transactions/minute, 200,000+ historical records/year  

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle / Gate | Status | Design Compliance Notes |
|---|---|---|
| **I. Strict Multi-Tenant Isolation** | ✅ PASSED | All Eloquent queries strictly scope by authenticated `user_jid`. Zero crosstalk. |
| **II. Non-Blocking Event Loop** | ✅ PASSED | Baileys event loop is decoupled via HTTP webhook; heavy Excel exports run via Laravel Queue. |
| **III. Financial Data Integrity** | ✅ PASSED | Amounts stored as exact integer Rupiah (`bigInteger`). Balance mutations wrapped in `DB::transaction()`. |
| **IV. Outbound Rate Limiting & Anti-Ban** | ✅ PASSED | Baileys gateway enforces pacing with random jitter (750-1500ms) and `sendPresenceUpdate('composing')`. |
| **V. Test-First (TDD)** | ✅ PASSED | Parser and financial domain test suites built with Pest PHP / PHPUnit before production endpoints. |

## Project Structure

### Documentation (this feature)

```text
specs/001-wa-finance-tracker/
├── spec.md                       # Feature specification
├── plan.md                       # This implementation plan
├── research.md                   # Phase 0 research & architectural decisions
├── data-model.md                 # Phase 1 SQLite schema & entity models
├── quickstart.md                 # Phase 1 validation and execution guide
├── contracts/
│   ├── whatsapp-commands.md      # Input grammar, regex catalog, & reply templates
│   ├── excel-export-schema.md    # Multi-sheet .xlsx structure & formula contract
│   └── rest-api.md               # REST API & Webhook endpoints
└── checklists/
    └── requirements.md           # Specification quality checklist (16/16 passed)
```

### Source Code Layout (Laravel 11 Core + Baileys Gateway)

```text
app/
├── Console/Commands/
│   └── FinanceSimulateCommand.php     # 100% Offline Interactive Terminal Chat Simulator
├── Exports/                           # Laravel Excel (Maatwebsite) Multi-sheet Export
│   ├── FinanceReportExport.php        # Master workbook coordinating sheets
│   ├── SummaryDashboardSheet.php      # Sheet 1: Dashboard with KPI cards and SUM formulas
│   └── MonthlyTransactionsSheet.php   # Sheet 2..N: Monthly logs with currency format ("Rp"#,##0)
├── Http/Controllers/Api/
│   ├── AuthController.php             # WhatsApp OTP request & JWT verification
│   ├── TransactionController.php      # Transaction CRUD & pagination
│   ├── CategoryController.php         # Custom & default category management
│   ├── AnalyticsController.php        # Summary KPIs, category breakdown, & monthly trends
│   ├── ExportController.php           # Excel binary stream download endpoint
│   └── WebhookController.php          # Receives incoming chats from Baileys gateway
├── Models/
│   ├── User.php                       # Eloquent model for user profiles & balances
│   ├── Category.php                   # Eloquent model for categories
│   ├── Transaction.php                # Eloquent model for transactions
│   └── ProcessedMessage.php           # Message idempotency model
└── Services/
    ├── TransactionParserService.php   # Deterministic regex parser (<50ms)
    ├── FinanceService.php             # Core ledger business logic & ACID mutations
    └── ExcelExportService.php         # Manages export compilation and file generation

config/
database/
├── migrations/
│   └── 2026_09_08_000001_create_finance_tables.php
├── seeders/
│   └── CategorySeeder.php
└── database.sqlite

routes/
├── api.php                            # REST API routes + /webhook/whatsapp
└── console.php

tests/
├── Feature/
│   ├── Api/TransactionApiTest.php     # REST API endpoint tests
│   ├── Api/AnalyticsApiTest.php       # Dashboard analytics tests
│   ├── WebhookTest.php                # Inbound chat processing tests
│   └── ExcelExportTest.php            # Multi-sheet Excel workbook tests
└── Unit/
    ├── ParserServiceTest.php          # Indonesian regex parsing tests
    └── FinanceServiceTest.php         # Balance mutation & ACID atomicity tests

whatsapp-gateway/                      # Lightweight Baileys Node.js Sidecar
├── src/
│   ├── index.ts                       # Baileys socket lifecycle & pairing code terminal linking
│   ├── webhook-client.ts              # Relays messages to Laravel POST /api/webhook/whatsapp
│   └── queue.ts                       # Outbound rate limiter & anti-ban pacing
├── package.json
└── tsconfig.json
```

**Structure Decision**: Clean decoupling of concerns. Laravel 11 serves as the single source of truth for finance logic, database, Excel generation, and REST APIs. Baileys functions strictly as a slim WhatsApp socket bridge.

## Complexity Tracking

> **No Constitution Violations**: The design adheres directly to all 5 constitutional principles without requiring unjustified architectural complexity.



