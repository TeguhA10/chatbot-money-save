# Quickstart & Validation Guide: Smart Finance Ecosystem

**Feature**: `003-smart-finance-features`  
**Date**: 2026-10-02  

---

## 1. Prerequisites & Environment Setup

Ensure database migrations are current on the active PostgreSQL database:
```bash
cd backend
php artisan migrate
```

Verify encryption key and queue configuration in `backend/.env`:
```env
FINANCE_ENCRYPTION_KEY=<32-byte-base64-key>
DB_CONNECTION=pgsql
```

---

## 2. Automated Test Suite Validation

Run the dedicated automated feature test suite verifying all 5 levels:
```bash
cd backend
php artisan test tests/Feature/SmartFinanceTest.php
```

Expected output:
```text
PASS  Tests\Feature\SmartFinanceTest
✓ test_user_can_set_and_view_category_budget
✓ test_budget_progress_bar_renders_in_expense_reply
✓ test_multi_wallet_balances_and_wallet_tagged_expense
✓ test_inter_wallet_transfer_mutates_balances_without_expense_inflation
✓ test_recurring_schedule_execution_and_notification
✓ test_financial_goal_creation_and_savings_contribution
✓ test_natural_inquiry_monthly_spending_variance
✓ test_freelancer_project_revenue_and_umkm_gross_profit
```

---

## 3. End-to-End Chat Simulation via Terminal

Test all new chat commands interactively without needing live WhatsApp phone connection:

### A. Budgeting Scenario
```bash
php artisan finance:simulate "budget makan 1jt"
# Verify: Replies with Plafon Anggaran Diatur (Rp 1.000.000 limit)

php artisan finance:simulate "keluar 250rb makan siang"
# Verify: Replies with 🍔 Budget Makanan progress bar (25% terpakai)
```

### B. Multi-Wallet & Transfer Scenario
```bash
php artisan finance:simulate "tambah dompet BCA saldo 2000000"
php artisan finance:simulate "tambah dompet DANA saldo 500000"
php artisan finance:simulate "saldo"
# Verify: Itemized breakdown (BCA: 2jt, DANA: 500rb, Total: 2.5jt)

php artisan finance:simulate "transfer 200rb dari bca ke dana"
# Verify: BCA becomes 1.8jt, DANA becomes 700rb, total remains 2.5jt
```

### C. Recurring Commitment Scenario
```bash
php artisan finance:simulate "langganan Netflix 186rb setiap tanggal 5 via bca"
php artisan finance:simulate "daftar langganan"
# Verify: Netflix listed with next run date
```

### D. Financial Goal Scenario
```bash
php artisan finance:simulate "buat target apotek 100jt"
php artisan finance:simulate "tambah tabungan 500rb untuk apotek via bca"
# Verify: Modal Apotek progress bar advances, BCA balance debited
```

### E. Analytical Insights Scenario
```bash
php artisan finance:simulate "bulan ini boros gak?"
# Verify: MoM percentage delta and top category cost driver comparison
```

---

## 4. Background Scheduler Verification

Simulate the daily cron scheduler triggering recurring bills and evening digests:
```bash
# Process due recurring transactions
php artisan finance:process-recurring

# Dispatch evening daily recap
php artisan finance:send-daily-digest
```
