# Data Model Specification: Smart Finance Ecosystem

**Feature**: `003-smart-finance-features`  
**Date**: 2026-10-02  
**Database Engines Supported**: PostgreSQL (Active), MySQL, SQLite  

---

## 1. Schema Modifications to Existing Tables

### `transactions` (Altered)
Reused as the universal single ledger for Expenses, Income, and Transfers.

| Column | Type | Nullable | Default | Description |
|--------|------|:--------:|---------|-------------|
| `id` | UUID | No | Primary Key | Existing transaction UUID |
| `user_jid` | VARCHAR(255) | No | - | Foreign key → `users.jid` (CASCADE) |
| `category_id` | UUID | Yes | NULL | Foreign key → `categories.id` (NULL for transfers) |
| `wallet_id` | UUID | Yes | NULL | Foreign key → `wallets.id` (Source/debited account) |
| `to_wallet_id` | UUID | Yes | NULL | Foreign key → `wallets.id` (Destination/credited account for `TRANSFER`) |
| `type` | VARCHAR(32) | No | - | `'EXPENSE'`, `'INCOME'`, or `'TRANSFER'` |
| `amount` | BIGINT | No | 0 | Legacy numeric balance (0 for encrypted rows) |
| `description` | VARCHAR(255) | No | `''` | Transaction description / project tag / merchant |
| `balance_after` | BIGINT | No | 0 | Snapshot of wallet/user balance (0 for encrypted rows) |
| `encrypted_amount` | TEXT | Yes | NULL | AES-256-GCM encrypted amount in exact integer Rupiah |
| `encrypted_balance_after` | TEXT | Yes | NULL | AES-256-GCM encrypted snapshot after transaction |
| `key_version` | INT | No | 1 | Encryption key version |
| `transaction_date` | TIMESTAMP | No | CURRENT_TIMESTAMP | Date and time of transaction |
| `status` | VARCHAR(32) | No | `'ACTIVE'` | `'ACTIVE'` or `'VOIDED'` |
| `created_at`, `updated_at` | TIMESTAMP | Yes | NULL | Laravel standard timestamps |

**Indexes**:
- `index(['user_jid', 'transaction_date'])`
- `index(['user_jid', 'status'])`
- `index(['user_jid', 'wallet_id'])`
- `index(['user_jid', 'type'])`

---

## 2. New Essential Tables

### Table 1: `wallets`
Represents an isolated financial pocket, bank account, or digital wallet.

| Column | Type | Nullable | Default | Description |
|--------|------|:--------:|---------|-------------|
| `id` | UUID | No | Primary Key | Unique wallet identifier |
| `user_jid` | VARCHAR(255) | No | - | Foreign key → `users.jid` (CASCADE) |
| `name` | VARCHAR(100) | No | - | Wallet name (e.g., `'Cash'`, `'BCA'`, `'DANA'`, `'GoPay'`) |
| `type` | VARCHAR(32) | No | `'CASH'` | `'CASH'`, `'BANK'`, `'EWALLET'`, `'OTHER'` |
| `is_default` | BOOLEAN | No | `false` | True for the user's primary/fallback wallet |
| `encrypted_balance` | TEXT | Yes | NULL | AES-256-GCM encrypted balance in integer Rupiah |
| `created_at`, `updated_at` | TIMESTAMP | Yes | NULL | Laravel standard timestamps |

**Constraints & Indexes**:
- `foreign('user_jid')->references('jid')->on('users')->cascadeOnDelete()`
- `unique(['user_jid', 'name'])` (Prevents duplicate wallet names per user)
- `index(['user_jid', 'is_default'])`

---

### Table 2: `budgets`
Static monthly limit rule for a specific category per user.

| Column | Type | Nullable | Default | Description |
|--------|------|:--------:|---------|-------------|
| `id` | UUID | No | Primary Key | Unique budget rule identifier |
| `user_jid` | VARCHAR(255) | No | - | Foreign key → `users.jid` (CASCADE) |
| `category_id` | UUID | No | - | Foreign key → `categories.id` (CASCADE) |
| `encrypted_limit_amount` | TEXT | No | - | AES-256-GCM encrypted monthly ceiling in integer Rupiah |
| `alert_threshold_percent`| SMALLINT| No | 80 | Percentage (e.g., 80) triggering early warning |
| `created_at`, `updated_at` | TIMESTAMP | Yes | NULL | Laravel standard timestamps |

**Constraints & Indexes**:
- `foreign('user_jid')->references('jid')->on('users')->cascadeOnDelete()`
- `foreign('category_id')->references('id')->on('categories')->cascadeOnDelete()`
- `unique(['user_jid', 'category_id'])` (Ensures exactly 1 static monthly rule per category per user)

---

### Table 3: `recurring_schedules`
Defines automated recurring commitments (subscriptions, rent, fixed salary).

| Column | Type | Nullable | Default | Description |
|--------|------|:--------:|---------|-------------|
| `id` | UUID | No | Primary Key | Unique recurring schedule identifier |
| `user_jid` | VARCHAR(255) | No | - | Foreign key → `users.jid` (CASCADE) |
| `wallet_id` | UUID | Yes | NULL | Foreign key → `wallets.id` (Debited/credited account) |
| `category_id` | UUID | Yes | NULL | Foreign key → `categories.id` |
| `type` | VARCHAR(32) | No | `'EXPENSE'` | `'EXPENSE'` or `'INCOME'` |
| `encrypted_amount` | TEXT | No | - | AES-256-GCM encrypted recurring amount |
| `description` | VARCHAR(255) | No | - | Name/memo (e.g., `'Netflix'`, `'Uang Kos'`, `'Gaji'`) |
| `frequency` | VARCHAR(32) | No | `'MONTHLY'` | `'MONTHLY'`, `'WEEKLY'`, `'DAILY'` |
| `day_of_month` | SMALLINT | Yes | 1 | Execution day (1–31) |
| `next_run_date` | DATE | No | - | Next calendar date to execute |
| `last_run_at` | TIMESTAMP | Yes | NULL | Timestamp of last execution |
| `is_active` | BOOLEAN | No | `true` | Active status flag |
| `created_at`, `updated_at` | TIMESTAMP | Yes | NULL | Laravel standard timestamps |

**Constraints & Indexes**:
- `foreign('user_jid')->references('jid')->on('users')->cascadeOnDelete()`
- `foreign('wallet_id')->references('id')->on('wallets')->nullOnDelete()`
- `foreign('category_id')->references('id')->on('categories')->nullOnDelete()`
- `index(['is_active', 'next_run_date'])` (Optimized for daily cron polling)

---

### Table 4: `financial_goals`
Represents milestone savings objectives.

| Column | Type | Nullable | Default | Description |
|--------|------|:--------:|---------|-------------|
| `id` | UUID | No | Primary Key | Unique goal identifier |
| `user_jid` | VARCHAR(255) | No | - | Foreign key → `users.jid` (CASCADE) |
| `name` | VARCHAR(100) | No | - | Goal objective (e.g., `'Modal Apotek'`, `'Dana Darurat'`) |
| `encrypted_target_amount` | TEXT | No | - | AES-256-GCM encrypted target amount |
| `encrypted_current_amount`| TEXT | No | - | AES-256-GCM encrypted accumulated savings |
| `target_date` | DATE | Yes | NULL | Optional target completion deadline |
| `status` | VARCHAR(32) | No | `'ACTIVE'` | `'ACTIVE'`, `'COMPLETED'`, `'ABANDONED'` |
| `created_at`, `updated_at` | TIMESTAMP | Yes | NULL | Laravel standard timestamps |

**Constraints & Indexes**:
- `foreign('user_jid')->references('jid')->on('users')->cascadeOnDelete()`
- `index(['user_jid', 'status'])`

---

## 3. Entity Relationships Diagram

```
+-----------------------------------+
|               users               |
| jid (PK)                          |
| display_name                      |
| current_balance (Consolidated Sum)|
+-----------------+-----------------+
                  | 1:N
                  +-----------------------------------+-----------------------------------+
                  |                                   |                                   |
                  v                                   v                                   v
+-----------------+-----------------+   +-------------+---------------+   +---------------+---------------+
|             wallets               |   |           budgets           |   |      financial_goals          |
| id (PK)                           |   | id (PK)                     |   | id (PK)                       |
| user_jid (FK)                     |   | user_jid (FK)               |   | user_jid (FK)                 |
| name ('Cash', 'BCA', 'DANA')      |   | category_id (FK)            |   | name ('Modal Apotek')         |
| encrypted_balance                 |   | encrypted_limit_amount      |   | encrypted_target_amount       |
| is_default (bool)                 |   +-----------------------------+   | encrypted_current_amount      |
+-----------------+-----------------+                                     +-------------------------------+
                  | 1:N
                  | (source & destination)
                  v
+-----------------+-----------------+
|          transactions             |
| id (PK)                           |
| user_jid (FK)                     |
| wallet_id (FK -> source)          |
| to_wallet_id (FK -> dest/transfer)|
| category_id (FK)                  |
| type (EXPENSE | INCOME | TRANSFER)|
| encrypted_amount                  |
| description (with tags)           |
+-----------------------------------+
                  ^
                  | Generates rows on schedule
+-----------------+-----------------+
|       recurring_schedules         |
| id (PK)                           |
| user_jid (FK)                     |
| wallet_id (FK)                    |
| day_of_month                      |
| encrypted_amount                  |
+-----------------------------------+
```

---

## 4. Backwards Compatibility & Data Migration Strategy

1. **Auto-provisioning Default "Cash" Wallet**:
   When the migration runs on existing databases:
   For every existing user in `users`, if they have no wallet, create:
   ```php
   Wallet::create([
       'id' => Str::uuid(),
       'user_jid' => $user->jid,
       'name' => 'Cash',
       'type' => 'CASH',
       'is_default' => true,
       'encrypted_balance' => $user->attributes['encrypted_current_balance'] ?? $crypto->encryptForStorage(0),
   ]);
   ```
2. **Backfilling Past Transactions**:
   Update existing rows in `transactions` where `wallet_id IS NULL` to point to the user's default `Cash` wallet.
3. **Zero Downtime**:
   The migration executes non-destructively without modifying or resetting existing users, categories, or transaction amounts.
