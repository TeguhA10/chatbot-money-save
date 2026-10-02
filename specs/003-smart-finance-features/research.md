# Research & Architecture Decisions: Smart Finance Ecosystem

**Feature**: `003-smart-finance-features`  
**Date**: 2026-10-02  
**Spec Reference**: [spec.md](./spec.md)  

---

## 1. Database Schema Minimization (Zero-Redundancy Architecture)

### Decision 1.1: Single Integrated Ledger for Expenses, Income & Transfers
- **Decision**: Extend the existing `transactions` table with an additional enum type `'TRANSFER'`, a source `wallet_id`, and a nullable destination `to_wallet_id`. Do NOT create a separate `wallet_transfers` table.
- **Rationale**:
  - Eliminates table fragmentation. All balance-affecting operations remain in a single chronological audit ledger.
  - Ensures atomic reversal (`undo` / `batal`) operates on a single table.
  - Inter-wallet transfers have `amount > 0`, `wallet_id` (debited), `to_wallet_id` (credited), `category_id = NULL`, and `type = 'TRANSFER'`.
  - Monthly income and expense totals simply filter `WHERE type = 'EXPENSE'` or `type = 'INCOME'`, cleanly ignoring `TRANSFER` rows without complex subqueries.
- **Alternatives Considered**:
  - *Separate `wallet_transfers` table*: Rejected because it requires dual-table querying for transaction history and complicates atomic rollbacks.
  - *Two offsetting transaction rows (1 expense, 1 income)*: Rejected because it artificially inflates monthly income and expenditure totals.

---

### Decision 1.2: Dual-Tier Balance Synchronization (`users.current_balance` + `wallets`)
- **Decision**: Maintain `users.current_balance` as the exact atomic sum of all active wallet balances (`wallets.encrypted_balance`).
- **Rationale**:
  - Preserves 100% backwards compatibility: All existing user profile endpoints, auth checks, freemium limits, and Excel export dashboards read `users.current_balance` directly in $O(1)$ without executing `SUM(wallets.balance)` queries.
  - When any wallet balance mutates inside `DB::transaction()`, the wallet's `encrypted_balance` and the user's `encrypted_current_balance` are updated atomically in the same transaction block.
- **Alternatives Considered**:
  - *Deprecating `users.current_balance`*: Rejected because it breaks existing APIs and requires aggregate `SUM()` queries across joined tables on every message receipt.

---

### Decision 1.3: Static Monthly Rule for Category Budgets
- **Decision**: Define the `budgets` table with a unique constraint on `(user_jid, category_id)`:
  ```sql
  budgets (id, user_jid, category_id, encrypted_limit_amount, timestamps)
  ```
  Do NOT store `month_year` columns or create duplicate monthly snapshot rows.
- **Rationale**:
  - When a user sets a budget (`budget makan 1jt`), that limit rule applies to every subsequent calendar month until explicitly updated.
  - Budget consumption is evaluated dynamically:
    ```sql
    SELECT SUM(amount) FROM transactions 
    WHERE user_jid = ? AND category_id = ? AND type = 'EXPENSE' 
      AND transaction_date >= DATE_TRUNC('month', CURRENT_DATE)
    ```
  - Keeps the database extremely lightweight: a user with 5 category budgets occupies only 5 rows permanently, rather than 60 rows every year.
- **Alternatives Considered**:
  - *Monthly snapshot rows (`month_year`)*: Rejected due to table bloat and the requirement for monthly cron jobs to copy over unexpired budgets.

---

### Decision 1.4: In-Ledger Freelancer & UMKM Tracking
- **Decision**: Store freelancer project names and UMKM tags directly within existing transaction metadata (`description` and `categories`), avoiding standalone `projects` or `business_ledgers` tables.
- **Rationale**:
  - Messages like `masuk 2jt project website client A` classify category as `Freelance` and store `Website Client A` in `description`.
  - Messages like `jual nasi goreng 25rb` classify as `Penjualan` and `modal bahan 300rb` classify as `Modal / HPP`.
  - Gross profit queries compute:
    ```sql
    Turnover (Omzet) = SUM(Income WHERE category = 'Penjualan')
    Cost (Modal)     = SUM(Expense WHERE category = 'Modal / HPP')
    Gross Profit     = Turnover - Cost
    ```
- **Alternatives Considered**:
  - *Standalone `projects` and `business_orders` tables*: Rejected as over-engineering for a chat-based personal/micro-business tool.

---

## 2. Background Scheduling & Notification Architecture

### Decision 2.1: Recurring Transactions & Automated Digests via Laravel Scheduler
- **Decision**: Implement recurring transaction processing and automated daily digests using standard Laravel Console Commands scheduled via `routes/console.php` or `app/Console/Kernel.php`:
  1. `finance:process-recurring`: Runs daily at 00:05 WIB. Queries active `recurring_schedules` due today, creates corresponding `transactions` rows atomically, updates wallet balances, and queues WhatsApp outbound notifications.
  2. `finance:send-daily-digest`: Runs daily at 21:00 WIB. Queries users with recorded transactions today, compiles income/expense/net/top-3 summary, and queues WhatsApp outbound message.
- **Rationale**:
  - Adheres strictly to Constitution Principle II (Non-blocking Baileys event loop): The Node.js gateway is never blocked by batch financial aggregations.
  - Leverages existing outbound queue in `whatsapp-gateway` (`queue.ts`) with anti-ban jitter pacing.
- **Alternatives Considered**:
  - *Scheduling inside Node.js sidecar*: Rejected because financial calculation and atomic database mutations belong in the Laravel backend ledger.

---

## 3. Cryptography & Nominal Protection (Zero-Knowledge at Rest)

### Decision 3.1: Uniform AES-256-GCM Protection Across All New Entities
- **Decision**: All newly created tables holding monetary amounts (`wallets.encrypted_balance`, `budgets.encrypted_limit_amount`, `recurring_schedules.encrypted_amount`, `financial_goals.encrypted_target_amount`, `financial_goals.encrypted_current_amount`) MUST store values exclusively as AES-256-GCM ciphertexts via `EncryptionService`.
- **Rationale**:
  - Upholds Constitution Principle III and 002-freemium-privacy-docker security guarantees: Even with raw database dumps, an attacker or server operator cannot read wallet balances, budget ceilings, or goal targets.
  - Uses existing server-mediated storage key (`FINANCE_ENCRYPTION_KEY`) and PBKDF2 user-PIN derivation where applicable.

---

## 4. UI/UX Contract: Visual Indicators in WhatsApp

### Decision 4.1: Universal Unicode Progress Bar Rendering
- **Decision**: Render 20-segment Unicode progress bars using solid `█` (U+2588) and light shade `░` (U+2591):
  ```text
  ███████████████░░░░░ 75%
  ```
  For over-budget conditions (>100%), render full blocks with explicit percentage:
  ```text
  ████████████████████ 115% (Melebihi budget Rp150.000!)
  ```
- **Rationale**:
  - Standardized display across WhatsApp Android, iOS, Web, and Desktop with identical character width and zero rendering glitches.
