# Implementation Plan: Smart Finance Ecosystem (Budgets, Multi-Wallet, Goals, Recurring Transactions & AI Insights)

**Branch**: `003-smart-finance-features` | **Date**: 2026-10-02 | **Spec**: [specs/003-smart-finance-features/spec.md](./spec.md)

**Input**: Feature specification from `/specs/003-smart-finance-features/spec.md`

---

## Summary

Deliver a comprehensive transformation of the WhatsApp Finance Bot from a passive expense recorder into an active, intelligent financial assistant. The technical implementation strictly adheres to a **Zero-Redundancy Schema** principle:
1. Reuses the existing `transactions` table for inter-wallet transfers (`type = 'TRANSFER'`, `wallet_id`, `to_wallet_id`) without creating a separate transfer table.
2. Synchronizes `users.current_balance` as the exact atomic sum of all active wallet balances, preserving $O(1)$ read performance and backwards compatibility.
3. Implements only 4 new essential domain tables (`wallets`, `budgets`, `recurring_schedules`, `financial_goals`).
4. Reuses `transactions.description` and `categories` for Freelancer project tagging and UMKM omzet/modal tracking without creating unnecessary relational tables.
5. Employs background schedulers (`finance:process-recurring`, `finance:send-daily-digest`) adhering to non-blocking Baileys event loop standards.

---

## Technical Context

**Language/Version**: PHP 8.2+ (Backend Laravel 11/12) & TypeScript 5.7+ / Node.js 20+ (WhatsApp Baileys Sidecar)

**Primary Dependencies**: Laravel Framework, `@whiskeysockets/baileys`, Pest PHP / PHPUnit

**Storage**: PostgreSQL (active production connection), compatible with MySQL 8.0+ and SQLite 3.35+

**Testing**: Pest PHP / PHPUnit Feature & Unit tests (`tests/Feature/SmartFinanceTest.php`)

**Target Platform**: Docker containerized Linux server (Ubuntu/Debian) or local Windows development environment

**Project Type**: Multi-tier architecture: Node.js WebSocket Gateway + Laravel REST API & Webhook Service

**Performance Goals**:
- WhatsApp message acknowledgment & reply latency < 1.5 seconds
- Sub-millisecond deterministic regex & token parser execution
- $O(\log N)$ indexed queries on all user transactions

**Constraints**:
- Zero floating-point arithmetic (100% exact integer Rupiah)
- AES-256-GCM encrypted storage at rest for all nominal balances, limits, and targets
- Strict multi-tenant data isolation partitioned by WhatsApp JID

**Scale/Scope**:
- 1,000+ registered active users
- 100,000+ monthly transaction ledger capacity

---

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Requirement | Plan Status | Verification |
|-----------|-------------|:-----------:|--------------|
| **I. Strict Multi-Tenant Isolation** | All tables and queries MUST be scoped strictly by `user_jid`. Zero data leakage across users. | **PASS** | Every new entity (`wallets`, `budgets`, `recurring_schedules`, `financial_goals`) enforces `user_jid` foreign key with cascade deletion. All services require explicit `User` instance. |
| **II. Non-Blocking Event Loop** | Baileys event loop must never be blocked by heavy computation or batch DB work. | **PASS** | Automated digests and recurring execution run via Laravel CLI scheduler (`schedule:run`), completely decoupled from incoming Baileys socket listeners. |
| **III. Financial Data Integrity & Atomic Transactions** | Exact integer Rupiah, zero float math, atomic `DB::transaction()` boundaries, AES-256-GCM nominal encryption at rest. | **PASS** | All wallet mutations, transfer transactions, budget evaluations, and goal savings execute inside `DB::transaction()`. All nominal columns use encrypted text wrappers. |
| **IV. Outbound Rate Limiting & Anti-Ban** | Paced messaging with human-like jitter queue. | **PASS** | Scheduled digest notifications route through the gateway's `OutboundQueue` with configured 700ms–1400ms pacing and idempotency keys. |
| **V. Test-First (TDD)** | Comprehensive automated test coverage before deployment. | **PASS** | Full test suite planned in `tests/Feature/SmartFinanceTest.php` covering all 8 user stories across 5 levels. |

---

## Project Structure

### Documentation (this feature)

```text
specs/003-smart-finance-features/
├── plan.md              # This file (Technical plan & architecture)
├── research.md          # Phase 0 decisions & schema minimization rationale
├── data-model.md        # Phase 1 database schema (PostgreSQL/MySQL/SQLite)
├── quickstart.md        # Phase 1 runnable validation & testing guide
├── contracts/           # Phase 1 external & chat interface contracts
│   ├── chat-commands.md # WhatsApp command grammars & visual reply cards
│   └── api-endpoints.md # REST API contracts for Web/Mobile clients
├── checklists/
│   └── requirements.md  # Specification quality validation checklist
└── tasks.md             # Phase 2 task decomposition (generated via /speckit-tasks)
```

### Source Code (repository root)

```text
backend/
├── app/
│   ├── Console/
│   │   └── Commands/
│   │       ├── ProcessRecurringCommand.php     # Scheduled recurring transaction executor
│   │       ├── SendDailyDigestCommand.php      # 21:00 WIB evening recap dispatcher
│   │       └── FinanceSimulateCommand.php      # Terminal chat tester (updated with new grammars)
│   ├── Http/
│   │   └── Controllers/
│   │       └── Api/
│   │           ├── WebhookController.php       # WhatsApp command routing & visual reply cards
│   │           ├── WalletController.php        # REST API for wallets & transfers
│   │           ├── BudgetController.php        # REST API for category budgets
│   │           └── GoalController.php          # REST API for financial goals
│   ├── Models/
│   │   ├── Wallet.php                          # Multi-wallet account model
│   │   ├── Budget.php                          # Static monthly category budget rule model
│   │   ├── RecurringSchedule.php               # Scheduled recurring commitment model
│   │   ├── FinancialGoal.php                   # Target savings milestone model
│   │   ├── Transaction.php                     # Updated with wallet_id, to_wallet_id, TRANSFER type
│   │   └── User.php                            # Updated with wallets relationship
│   └── Services/
│       ├── FinanceService.php                  # Extended with wallet mutations, transfers, goals
│       ├── BudgetService.php                   # Category budget limit calculation & progress bars
│       ├── RecurringService.php                # Recurring cron execution & next run computation
│       ├── InsightsService.php                 # MoM comparative spending variance & digests
│       └── TransactionParserService.php        # Extended regex grammars for all 5 levels
├── database/
│   └── migrations/
│       ├── 2026_10_02_000002_create_wallets_and_alter_transactions.php
│       ├── 2026_10_02_000003_create_budgets_table.php
│       ├── 2026_10_02_000004_create_recurring_schedules_table.php
│       └── 2026_10_02_000005_create_financial_goals_table.php
└── tests/
    └── Feature/
        └── SmartFinanceTest.php                # Comprehensive 8-story feature test suite
```

**Structure Decision**:
Standard Laravel service-repository modular structure with dedicated domain services (`BudgetService`, `RecurringService`, `InsightsService`) supporting the core `FinanceService`, preserving separation of concerns while keeping controllers lightweight.

---

## Complexity Tracking

| Decision | Why Needed | Simpler Alternative Rejected Because |
|----------|------------|-------------------------------------|
| Integrated `transactions` table for transfers | Prevents schema fragmentation and maintains single chronological ledger | Separate `wallet_transfers` table would require multi-table unions for history queries and complex distributed rollbacks |
| Static monthly budget rule | Saves millions of redundant database rows over time | Monthly snapshot rows (`month_year`) would require monthly cron copying and unnecessary disk bloat |
| Embedded project/UMKM tags in `description` | Minimizes database tables in accordance with user's strict zero-redundancy rule | Relational `projects` and `business_ledgers` tables would overcomplicate personal chat ledger operations |
