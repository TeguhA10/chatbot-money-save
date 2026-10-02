# Tasks Breakdown: Smart Finance Ecosystem (Budgets, Multi-Wallet, Goals, Recurring Transactions & AI Insights)

**Feature**: `003-smart-finance-features`  
**Plan Reference**: [plan.md](./plan.md)  
**Spec Reference**: [spec.md](./spec.md)  
**Data Model**: [data-model.md](./data-model.md)  
**Contracts**: [chat-commands.md](./contracts/chat-commands.md), [api-endpoints.md](./contracts/api-endpoints.md)  

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Prepare project directories, dependencies, and environment configuration.

- [X] T001 Verify database configuration and migration prerequisites in `backend/.env`
- [X] T002 [P] Create domain service and command skeletons in `backend/app/Services/` and `backend/app/Console/Commands/`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core database schema alterations and models that ALL subsequent user stories depend on.

**⚠️ CRITICAL**: Must be completed and migrated before any user story implementation begins.

- [X] T003 Create database migration for `wallets` table and alter `transactions` table with `wallet_id`, `to_wallet_id`, and `TRANSFER` type support in `backend/database/migrations/2026_10_02_000002_create_wallets_and_alter_transactions.php`
- [X] T004 [P] Create database migration for `budgets` table with static monthly rule unique constraint in `backend/database/migrations/2026_10_02_000003_create_budgets_table.php`
- [X] T005 [P] Create database migration for `recurring_schedules` table in `backend/database/migrations/2026_10_02_000004_create_recurring_schedules_table.php`
- [X] T006 [P] Create database migration for `financial_goals` table in `backend/database/migrations/2026_10_02_000005_create_financial_goals_table.php`
- [X] T007 Run database migrations and backfill default 'Cash' wallet for existing users in `backend/database/migrations/2026_10_02_000002_create_wallets_and_alter_transactions.php`
- [X] T008 [P] Implement `Wallet` model with AES-256-GCM encrypted balance accessors in `backend/app/Models/Wallet.php`
- [X] T009 [P] Implement `Budget` model with AES-256-GCM encrypted limit accessors in `backend/app/Models/Budget.php`
- [X] T010 [P] Implement `RecurringSchedule` model with frequency casts in `backend/app/Models/RecurringSchedule.php`
- [X] T011 [P] Implement `FinancialGoal` model with target progress calculations in `backend/app/Models/FinancialGoal.php`
- [X] T012 Update `Transaction` and `User` models with `wallet()`, `toWallet()`, and `wallets()` relationships in `backend/app/Models/Transaction.php` and `backend/app/Models/User.php`

**Checkpoint**: Foundation ready — database schema migrated, Eloquent models operational, user story implementation unblocked.

---

## Phase 3: User Story 1 - Category Budget Limits & Visual Consumption Tracking (Priority: P1) 🎯 MVP Component

**Goal**: Allow users to set monthly category budgets (e.g., `budget makan 1jt`) and receive visual progress bars (`████░░░ 75%`) and threshold alerts on expense replies.

**Independent Test**: Set a budget via chat, log an expense in that category, and verify the confirmation card contains the rendered Unicode progress bar and remaining budget balance.

- [X] T013 [P] [US1] Create automated feature tests for budget setting, consumption tracking, and threshold alerts in `backend/tests/Feature/BudgetTest.php`
- [X] T014 [US1] Implement `BudgetService` for upserting category limits, calculating current month consumption, and rendering Unicode progress bars in `backend/app/Services/BudgetService.php`
- [X] T015 [US1] Extend `TransactionParserService` with regex grammars for budget definition (`budget <kategori> <nominal>`) and inquiry (`cek budget`) in `backend/app/Services/TransactionParserService.php`
- [X] T016 [US1] Integrate `BudgetService` into expense confirmation replies in `backend/app/Services/FinanceService.php` and `backend/app/Http/Controllers/Api/WebhookController.php`
- [X] T017 [P] [US1] Implement REST API controller for category budgets in `backend/app/Http/Controllers/Api/BudgetController.php`

**Checkpoint**: User Story 1 is independently testable and operational.

---

## Phase 4: User Story 2 - Multi-Wallet & Multi-Account Partitioning (Priority: P1) 🎯 MVP Component

**Goal**: Support multiple isolated wallets (BCA, DANA, Cash), wallet-tagged expense/income logging, and consolidated balance breakdowns (`saldo`).

**Independent Test**: Add wallets, log transactions with `via dana` / `ke bca`, and query `saldo` to verify individual wallet balances and consolidated grand total.

- [X] T018 [P] [US2] Create automated feature tests for multi-wallet transactions and balance breakdowns in `backend/tests/Feature/MultiWalletTest.php`
- [X] T019 [US2] Extend `FinanceService` with wallet-scoped debit/credit mutations and atomic `users.current_balance` synchronization in `backend/app/Services/FinanceService.php`
- [X] T020 [US2] Extend `TransactionParserService` to extract wallet tags (`via <wallet>`, `ke <wallet>`, `dari <wallet>`) in `backend/app/Services/TransactionParserService.php`
- [X] T021 [US2] Update `saldo` / `dompet` command routing in `backend/app/Http/Controllers/Api/WebhookController.php` to render multi-wallet balance cards
- [X] T022 [P] [US2] Implement REST API controller for wallet listings and creation in `backend/app/Http/Controllers/Api/WalletController.php`

**Checkpoint**: User Stories 1 & 2 operational.

---

## Phase 5: User Story 3 - Inter-Wallet Transfers (Priority: P1) 🎯 MVP Component

**Goal**: Execute zero-sum fund movements between wallets (`transfer 200rb dari bca ke dana`) using the existing `transactions` table without inflating income or expense totals.

**Independent Test**: Execute a transfer between two wallets, verify both wallet balances update atomically, total net worth remains identical, and monthly expense metrics are unaffected.

- [X] T023 [P] [US3] Create automated feature tests for inter-wallet transfers and atomic undo in `backend/tests/Feature/WalletTransferTest.php`
- [X] T024 [US3] Implement `transferFunds` method in `FinanceService` executing single `transactions` row creation (`type = 'TRANSFER'`) with dual wallet balance mutation in `backend/app/Services/FinanceService.php`
- [X] T025 [US3] Extend `TransactionParserService` with inter-wallet transfer grammars (`transfer <nominal> dari <asal> ke <tujuan>`) in `backend/app/Services/TransactionParserService.php`
- [X] T026 [US3] Integrate transfer command execution and atomic undo reversal in `backend/app/Http/Controllers/Api/WebhookController.php`

**Checkpoint**: Complete Level 1 core (Budgets, Multi-Wallet, Transfers) operational as an integrated MVP.

---

## Phase 6: User Story 4 - Scheduled Recurring Transactions (Priority: P2)

**Goal**: Automate monthly fixed commitments (Netflix, rent, salary) triggered by scheduler on designated dates with WhatsApp notifications.

**Independent Test**: Register a recurring rule, trigger scheduler execution command, and verify transaction is recorded and confirmation notice is dispatched.

- [X] T027 [P] [US4] Create automated feature tests for recurring schedule registration and cron execution in `backend/tests/Feature/RecurringTransactionTest.php`
- [X] T028 [US4] Implement `RecurringService` for schedule management and date calculation (handling leap years and month ends) in `backend/app/Services/RecurringService.php`
- [X] T029 [US4] Extend `TransactionParserService` with recurring commands (`langganan <nama> <nominal> setiap tanggal <tgl>`, `daftar langganan`) in `backend/app/Services/TransactionParserService.php`
- [X] T030 [US4] Implement Artisan console command `finance:process-recurring` in `backend/app/Console/Commands/ProcessRecurringCommand.php` and schedule in `backend/routes/console.php`
- [X] T031 [US4] Wire recurring commands and cancellation routing into `backend/app/Http/Controllers/Api/WebhookController.php`

**Checkpoint**: User Story 4 independently functional.

---

## Phase 7: User Story 5 - Financial Goals & Savings Progress (Priority: P2)

**Goal**: Set financial savings goals (`target apotek 100jt`) and allocate savings from real wallets with visual milestone progress bars.

**Independent Test**: Create a target goal, contribute funds via chat, and verify progress percentage increases while the source wallet is deducted.

- [X] T032 [P] [US5] Create automated feature tests for financial goal creation and savings contributions in `backend/tests/Feature/FinancialGoalTest.php`
- [X] T033 [US5] Implement goal management and contribution methods in `backend/app/Services/FinanceService.php`
- [X] T034 [US5] Extend `TransactionParserService` with goal commands (`buat target <nama> <nominal>`, `tambah tabungan <nominal> untuk <nama>`) in `backend/app/Services/TransactionParserService.php`
- [X] T035 [US5] Integrate goal creation, contribution, and milestone celebration cards in `backend/app/Http/Controllers/Api/WebhookController.php`
- [X] T036 [P] [US5] Implement REST API controller for goals in `backend/app/Http/Controllers/Api/GoalController.php`

**Checkpoint**: User Story 5 independently functional.

---

## Phase 8: User Story 6 - Automated Daily & Monthly Financial Digests (Priority: P3)

**Goal**: Proactively dispatch automated evening recaps (21:00 WIB) and monthly wrap-ups summarizing income, expenses, top 3 categories, and net savings.

**Independent Test**: Trigger digest command for an active user and verify the structured summary card is generated and queued for WhatsApp delivery.

- [X] T037 [P] [US6] Create automated feature tests for daily and monthly digest generation in `backend/tests/Feature/FinancialDigestTest.php`
- [X] T038 [US6] Implement digest aggregation logic in `backend/app/Services/InsightsService.php`
- [X] T039 [US6] Implement Artisan console command `finance:send-daily-digest` in `backend/app/Console/Commands/SendDailyDigestCommand.php` and schedule in `backend/routes/console.php`
- [X] T040 [US6] Add digest opt-out / toggle commands (`aktifkan/matikan rekap harian`) in `backend/app/Http/Controllers/Api/WebhookController.php`

**Checkpoint**: User Story 6 independently functional.

---

## Phase 9: User Story 7 - Natural Financial Inquiries & Contextual Insights (Priority: P3)

**Goal**: Answer conversational questions like "bulan ini boros gak?" with empirical month-over-month variance calculations and category breakdowns.

**Independent Test**: Query "bulan ini boros gak?" with two months of test transactions and verify percentage delta and primary cost drivers are accurately reported.

- [X] T041 [P] [US7] Create automated feature tests for comparative spending analysis in `backend/tests/Feature/SpendingInsightsTest.php`
- [X] T042 [US7] Implement comparative variance algorithm (MoM percentage, top category increases) in `backend/app/Services/InsightsService.php`
- [X] T043 [US7] Extend `TransactionParserService` with conversational query triggers (`bulan ini boros gak?`, `evaluasi pengeluaran`) in `backend/app/Services/TransactionParserService.php`
- [X] T044 [US7] Wire comparative analysis replies into `backend/app/Http/Controllers/Api/WebhookController.php`

**Checkpoint**: User Story 7 independently functional.

---

## Phase 10: User Story 8 - Specialized Tracking for Freelancers & Micro-Businesses (Priority: P4)

**Goal**: Enable project-tagged freelancer income (`masuk 2jt project website A`) and micro-merchant turnover vs modal reporting (`omzet hari ini`, `laba hari ini`) without creating new tables.

**Independent Test**: Log project income and UMKM sales/cost entries, query `rekap freelance` and `omzet hari ini`, and verify gross profit calculations.

- [X] T045 [P] [US8] Create automated feature tests for project tagging and UMKM gross profit in `backend/tests/Feature/FreelancerAndUmkmTest.php`
- [X] T046 [US8] Implement project revenue and turnover/modal aggregation in `backend/app/Services/FinanceService.php`
- [X] T047 [US8] Extend `TransactionParserService` with project tag parsing and UMKM turnover triggers in `backend/app/Services/TransactionParserService.php`
- [X] T048 [US8] Wire project breakdown and gross profit summary cards into `backend/app/Http/Controllers/Api/WebhookController.php`

**Checkpoint**: All 8 user stories complete and functional.

---

## Phase 11: Polish & Cross-Cutting Concerns

**Purpose**: Terminal simulation updates, end-to-end regression validation, and performance verification.

- [X] T049 Update interactive CLI simulation tool with all new grammars in `backend/app/Console/Commands/FinanceSimulateCommand.php`
- [X] T050 [P] Execute end-to-end quickstart validation scenarios per `specs/003-smart-finance-features/quickstart.md`
- [X] T051 Run full automated test suite regression across all test suites via `php artisan test`

---

## Dependencies & Execution Order

### Phase Dependencies
1. **Setup (Phase 1)**: No dependencies — starts immediately.
2. **Foundational (Phase 2)**: Depends on Phase 1 — **BLOCKS all User Stories**.
3. **User Stories (Phase 3 through 10)**: All depend on Phase 2 completion.
   - **US1 (Budgets)**, **US2 (Multi-Wallet)**, **US3 (Transfers)** can be developed in priority order or concurrently.
   - **US4 (Recurring)** depends on US2 (wallets exist).
   - **US5 (Goals)** depends on US2 (wallets exist).
   - **US6 (Digests)** and **US7 (Insights)** depend on core transaction ledger.
   - **US8 (Freelancer/UMKM)** depends on core transaction ledger.
4. **Polish (Phase 11)**: Depends on all desired user stories being complete.

---

## Implementation Strategy: MVP First

1. **Step 1**: Complete Phase 1 (Setup) and Phase 2 (Foundational migrations & models).
2. **Step 2**: Implement Phase 3 (US1 - Budgets), Phase 4 (US2 - Multi-Wallet), and Phase 5 (US3 - Transfers).
3. **Step 3 (MVP Checkpoint)**: Run `php artisan test tests/Feature/BudgetTest.php tests/Feature/MultiWalletTest.php tests/Feature/WalletTransferTest.php`. The bot now has a fully working Level 1 financial assistant experience!
4. **Step 4**: Incrementally deliver Phase 6 (Recurring) and Phase 7 (Goals).
5. **Step 5**: Incrementally deliver Phase 8 (Digests), Phase 9 (Insights), and Phase 10 (Freelancer/UMKM).
6. **Step 6**: Complete Phase 11 regression polish.
