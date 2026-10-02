# Feature Specification: Smart Finance Ecosystem (Budgets, Multi-Wallet, Goals, Recurring Transactions & AI Insights)

**Feature Branch**: `003-smart-finance-features`

**Created**: 2026-10-02

**Status**: Draft

**Input**: User description: "LEVEL 1 — Wajib: Budget, Multiple wallet / rekening, Transfer, Transaksi berulang. LEVEL 2: Ringkasan otomatis harian/bulanan. LEVEL 3: Financial Goal / target tabungan. LEVEL 4: Analisis data pengguna / AI insights. LEVEL 5: Fitur khusus freelancer & UMKM (project tagging, omzet, modal, estimasi laba kotor)."

## Clarifications

### Session 2026-10-02
- Q: Bagaimana struktur tabel transaksi sebaiknya disesuaikan untuk mendukung multi-wallet dan transfer antar-rekening tanpa membuat tabel baru yang berlebihan? → A: Option A: Perluas tabel `transactions` yang sudah ada dengan menambahkan tipe `TRANSFER`, `wallet_id` (sumber), dan `to_wallet_id` (tujuan) tanpa membuat tabel `wallet_transfers` terpisah. Seluruh riwayat transaksi (Expense, Income, Transfer) tetap bersatu dalam satu ledger.
- Q: Bagaimana sinkronisasi saldo antara kolom users.current_balance yang sudah ada dengan tabel wallets baru sebaiknya dikelola? → A: Option A: Pertahankan `users.current_balance` sebagai *consolidated total balance* (jumlah saldo seluruh dompet). Setiap mutasi saldo dompet secara atomik memperbarui saldo dompet dan kolom `users.current_balance`, menjaga backwards compatibility dan performa kueri O(1).
- Q: Bagaimana fitur pencatatan Freelancer (nama project) dan UMKM (omzet vs modal) sebaiknya disimpan di database tanpa membuat tabel terpisah? → A: Option A: Manfaatkan tabel `transactions` dan `categories` yang sudah ada; nama project freelancer dan status omzet/modal diidentifikasi melalui relasi kategori dan teks kolom `description` (misal tag atau prefix dalam description), tanpa membuat tabel relasional baru (`projects` / `umkm_sales`).
- Q: Saat pengguna menabung untuk target finansial (contoh: "tambah tabungan 500rb untuk apotek"), bagaimana pengaruhnya terhadap saldo dompet riil pengguna? → A: Option A: Terintegrasi dengan dompet riil; alokasi tabungan memotong saldo dompet yang dipilih (misal BCA) dan dicatat sebagai transaksi di tabel `transactions` (kategori Tabungan/relasi goal), menjaga konsistensi neraca keuangan riil pengguna.
- Q: Bagaimana skema tabel budgets sebaiknya dirancang agar ukuran database tetap seringkas mungkin dari bulan ke bulan? → A: Option A: Static Monthly Rule; tabel `budgets` hanya menyimpan 1 baris per user & kategori (`user_jid`, `category_id`, `encrypted_limit_amount`) tanpa kolom `month_year`. Plafon limit ini otomatis berlaku setiap bulan berjalan dan dievaluasi terhadap transaksi bulan tersebut, menghemat penyimpanan secara maksimal tanpa duplikasi baris.

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Category Budget Limits & Visual Consumption Tracking (Priority: P1)

As a WhatsApp user managing monthly expenses,
I want to establish financial spending limits (budgets) for specific categories (e.g., dining, transport, entertainment) and inspect my remaining spending quota with visual progress indicators,
So that I know precisely how much money I can still spend before overspending.

**Why this priority**: Essential spending control. Simply logging transactions is informative, but knowing remaining allowances drives proactive financial discipline and user retention.

**Independent Test**: Can be tested independently by setting a budget (e.g., "budget makan 1jt"), recording an expense in that category (e.g., "keluar 250rb makan siang"), and verifying that the bot replies with updated progress bars, used amounts, and remaining allowances.

**Acceptance Scenarios**:
1. **Given** an onboarded user, **When** the user sends "budget makan 1jt", **Then** the system establishes or updates a monthly budget of Rp 1.000.000 for category "Makanan & Minuman" for the current calendar month and replies with a confirmation card.
2. **Given** an active budget of Rp 1.000.000 with Rp 500.000 already consumed, **When** the user logs "keluar 250rb makan siang", **Then** the system records the expense and includes a budget indicator card in the response:
   ```text
   🍔 Budget Makanan
   Terpakai: Rp750.000 / Rp1.000.000
   Sisa: Rp250.000
   ███████████████░░░ 75%
   ```
3. **Given** an active budget where a new expense pushes consumption to 85% or higher, **When** the expense is recorded, **Then** the system appends a friendly caution notice (e.g., "⚠️ Peringatan: Pengeluaran makanan sudah mencapai 85% dari plafon bulanan").
4. **Given** an active budget where an expense exceeds 100%, **When** the transaction is logged, **Then** the system logs the transaction without failing, but marks the visual indicator as over-budget (100%+, red indicator) and alerts the user to the overspend amount.
5. **Given** one or more active budgets, **When** the user queries "cek budget" or "status budget", **Then** the system lists all active category budgets, progress bars, and total budgeted vs spent.

---

### User Story 2 - Multi-Wallet & Multi-Account Balance Partitioning (Priority: P1)

As a WhatsApp user holding funds across various accounts (Cash, Bank BCA, Mandiri, e-wallets like GoPay/DANA),
I want to designate which wallet is credited or debited during transactions and view a unified breakdown of balances per account,
So that my recorded balances mirror my real-world banking and e-wallet balances accurately.

**Why this priority**: Single-pool balance models lead to discrepancies with physical bank/e-wallet accounts, eroding user trust in the tracking tool.

**Independent Test**: Can be tested independently by registering multiple wallets (e.g., BCA, DANA, Cash), logging transactions tied to specific wallets (e.g., "keluar 50rb makan via dana", "masuk 5jt gaji ke bca"), and sending "saldo" to inspect the individual and aggregate balance breakdown.

**Acceptance Scenarios**:
1. **Given** a user with wallets "BCA", "DANA", and "Cash", **When** the user sends "keluar 50rb makan via dana", **Then** the system debits Rp 50.000 exclusively from the "DANA" wallet, logs the transaction with the wallet reference, and displays the new DANA balance.
2. **Given** an expense message that does not specify a wallet (e.g., "keluar 25rb bensin"), **When** processed, **Then** the system debits the user's configured default wallet (default: "Cash / Tunai") and notes the wallet in the confirmation.
3. **Given** multiple active wallets, **When** the user sends "saldo" or "cek dompet", **Then** the system returns an itemized breakdown of each wallet's balance and the grand total:
   ```text
   💰 Saldo kamu

   BCA       Rp2.100.000
   DANA        Rp350.000
   Cash        Rp200.000
   ---------------------
   Total     Rp2.650.000
   ```
4. **Given** an onboarded user, **When** the user sends "tambah dompet GoPay" or "buat dompet Mandiri saldo 500rb", **Then** the system creates the new wallet with the specified initial balance.

---

### User Story 3 - Inter-Wallet Transfers (Priority: P1)

As a WhatsApp user transferring money between my own accounts (e.g., cash withdrawal or e-wallet top-up),
I want to log fund movements between wallets without having the transfer misclassified as an expense or income,
So that my wallet balances adjust accurately while my net worth and spending reports remain unbiased.

**Why this priority**: Transfers represent fund relocation, not wealth consumption. Treating an e-wallet top-up as an expense distorts budget limits and analytics.

**Independent Test**: Can be tested independently by executing "transfer 200rb dari bca ke dana" and verifying that BCA decreases by Rp 200.000, DANA increases by Rp 200.000, total user balance remains unchanged, and monthly expense totals do not rise.

**Acceptance Scenarios**:
1. **Given** user has BCA (Rp 1.000.000) and DANA (Rp 100.000), **When** user sends "transfer 200rb dari bca ke dana", **Then** the system atomically decreases BCA to Rp 800.000 and increases DANA to Rp 300.000, records an internal transfer entry, and confirms the new balances.
2. **Given** a transfer request where the source wallet has insufficient balance, **When** the user sends the transfer command, **Then** the system executes the transfer (or warns of negative balance according to configuration) and confirms the transaction clearly.
3. **Given** an undo request immediately following a transfer, **When** the user sends "batal" or "undo", **Then** the system reverses both legs of the transfer atomically.

---

### User Story 4 - Scheduled Recurring Transactions (Priority: P2)

As a busy WhatsApp user with fixed monthly commitments (rent, subscriptions, recurring salary),
I want to register recurring financial rules (e.g., Netflix on the 5th, apartment rent on the 1st),
So that the bot automatically creates transactions on the due date and notifies me, eliminating repetitive manual data entry.

**Why this priority**: Automates predictable transactions, drastically lowering daily logging friction and preventing forgotten subscription expenses.

**Independent Test**: Can be tested by creating a recurring schedule (e.g., "langganan Netflix 186rb setiap tanggal 5 via bca"), triggering the background scheduled job, and verifying that the transaction is posted and a WhatsApp notification summary is dispatched to the user.

**Acceptance Scenarios**:
1. **Given** an onboarded user, **When** the user sends "langganan Netflix 186rb setiap tanggal 5 via bca", **Then** the system registers a monthly recurring rule for Rp 186.000 on the 5th day of each month, charged to BCA under "Hiburan".
2. **Given** an active recurring rule, **When** the scheduler triggers on the scheduled date, **Then** the system atomically records the transaction, adjusts wallet balances, logs a processed message, and delivers an automated WhatsApp confirmation message to the user.
3. **Given** an onboarded user, **When** the user sends "daftar langganan" or "transaksi berulang", **Then** the system lists all active recurring rules, amounts, next due dates, and associated wallets.
4. **Given** an active recurring rule, **When** the user sends "hapus langganan Netflix", **Then** the rule is deactivated and will not generate future transactions.

---

### User Story 5 - Financial Goals & Savings Progress (Priority: P2)

As a goal-oriented individual saving for major milestones (e.g., emergency fund, vehicle, business capital),
I want to establish savings targets with target amounts and allocate savings incrementally via chat,
So that I feel motivated and can clearly see my progress toward achieving my life goals.

**Why this priority**: Elevates the product from a passive expense recorder to an active personal financial coach, fostering deep emotional engagement.

**Independent Test**: Can be tested by creating a target (e.g., "buat target apotek 100jt"), contributing funds (e.g., "tambah tabungan 500rb untuk apotek dari bca"), and inspecting the calculated progress percentage, accumulated funds, and remaining milestone balance.

**Acceptance Scenarios**:
1. **Given** an onboarded user, **When** user sends "buat target apotek 100jt", **Then** the system creates a financial goal named "Modal Apotek" with a target of Rp 100.000.000 and initial collected amount of Rp 0.
2. **Given** an existing goal "Modal Apotek" with Rp 12.000.000 collected, **When** the user sends "tambah tabungan 500rb untuk apotek via bca", **Then** the system adds Rp 500.000 to the goal (total Rp 12.500.000 / 12,5%), optionally deducts the amount from the BCA wallet, and renders a visual progress card:
   ```text
   🎯 Target: Modal Apotek
   Target: Rp100.000.000
   Terkumpul: Rp12.500.000
   Progress: 12,5%
   Sisa: Rp87.500.000
   ██░░░░░░░░░░░░░░░ 12.5%
   ```
3. **Given** a goal reaches 100% completion, **When** the threshold is met, **Then** the bot celebrates with a congratulatory milestone card (e.g., "🎉 Selamat! Target Modal Apotek telah tercapai 100%!").
4. **Given** multiple goals, **When** the user queries "cek target" or "progress tabungan", **Then** the system lists all active goals with percentage bars and remaining amounts.

---

### User Story 6 - Automated Daily & Monthly Financial Digests (Priority: P3)

As a WhatsApp user wanting regular financial awareness without running manual queries,
I want to receive an optional automated evening summary of today's spending and an executive monthly wrap-up,
So that I stay informed of my financial health on autopilot.

**Why this priority**: Proactive engagement that brings users back into the WhatsApp conversation daily with concise, actionable summaries.

**Independent Test**: Can be tested by scheduling the daily digest runner at 21:00, verifying that users with activity receive a structured summary showing income, expense, net savings, top expense categories, and closing balances.

**Acceptance Scenarios**:
1. **Given** user has recorded transactions today, **When** the evening digest runs (configurable, default 21:00), **Then** the bot sends an evening summary:
   ```text
   📊 Ringkasan Keuangan Hari Ini

   Pemasukan: Rp100.000
   Pengeluaran: Rp75.000
   Net: +Rp25.000

   Pengeluaran terbesar:
   🍔 Makan — Rp40.000
   🚗 Transport — Rp25.000
   ☕ Lainnya — Rp10.000

   Saldo: Rp2.350.000
   ```
2. **Given** a user with no transactions today, **When** the evening digest triggers, **Then** no message is sent (avoiding spam) or a quiet rest notice is dispatched based on user preference.
3. **Given** the 1st day of a new month, **When** the monthly summary triggers, **Then** the bot dispatches an executive recap of the preceding month with income, expenditure, total savings, and percentage breakdown by category.
4. **Given** user preferences, **When** user sends "matikan rekap harian" or "aktifkan rekap harian", **Then** the system toggles the automated digest setting.

---

### User Story 7 - Natural Financial Inquiries & Contextual Insights (Priority: P3)

As a WhatsApp user curious about my spending patterns,
I want to ask conversational questions like "bulan ini boros gak?" or "kenapa saldo cepat habis?",
So that the bot analyzes my empirical transaction history and provides factual, comparative explanations rather than generic chatbot replies.

**Why this priority**: Delivers smart assistant capabilities grounded in real data rather than disconnected AI hallucinations.

**Independent Test**: Can be tested by logging varying transactions across two months and asking "bulan ini boros gak?", verifying that the system calculates month-over-month percentage deltas, isolates the largest category increases, and summarizes actionable findings.

**Acceptance Scenarios**:
1. **Given** current month expenses increased by 18% over the previous month, with Food (+Rp 320.000) and Transport (+Rp 150.000) as primary drivers, **When** the user asks "bulan ini boros gak?", **Then** the bot replies with data-driven comparisons:
   ```text
   📊 Dibanding bulan lalu, pengeluaranmu naik 18%.

   Kenaikan terbesar berasal dari:
   🍔 Makanan +Rp320.000
   🚗 Transport +Rp150.000

   Estimasi pengeluaran sampai akhir bulan: sekitar Rp5.100.000.
   ```
2. **Given** an inquiry "kenapa saldo cepat habis?", **When** evaluated, **Then** the bot identifies the top 3 single largest expenditures and recurring outflows of the current billing cycle.

---

### User Story 8 - Specialized Tracking for Freelancers & Micro-Businesses (Priority: P4)

As a freelancer or micro-vendor (UMKM),
I want to tag income by client/project (e.g., "masuk 2jt project website client A") or log sales vs material costs (e.g., "jual nasi goreng 25rb", "modal bahan 300rb"),
So that I can see project earnings or calculate daily turnover, cost of goods sold, and gross profit directly inside WhatsApp.

**Why this priority**: Unlocks vertical use cases for independent workers and food/service micro-entrepreneurs without forcing them into heavy accounting software.

**Independent Test**: Can be tested by recording sales and cost entries with project/business tags and querying "omzet hari ini" or "rekap freelance", verifying that gross profit and project summaries are computed accurately.

**Acceptance Scenarios**:
1. **Given** a freelancer user, **When** the user logs "masuk 2jt project website client A", **Then** the system records the income tagged to project "Website Client A" under the Freelance income stream.
2. **Given** multiple project entries, **When** user sends "rekap freelance", **Then** the system lists total freelance earnings for the period broken down by individual project name.
3. **Given** a micro-merchant logging "jual nasi goreng 25rb" and "modal bahan 300rb", **When** the user queries "laba hari ini" or "omzet hari ini", **Then** the bot presents:
   ```text
   📈 Penjualan Hari Ini
   Omzet: Rp750.000
   Modal: Rp300.000
   Estimasi Laba Kotor: Rp450.000
   ```

---

### Edge Cases

- **Budget Exhaustion**: What happens when an expense is logged after a budget is 100% consumed? The transaction is still recorded and saved to maintain ledger integrity; the reply shows over-budget status (`████████████████ 112%`) without blocking the ledger.
- **Unspecified Wallet**: If a transaction does not mention a wallet, the system falls back to the user's default wallet ("Cash" or configured default) rather than rejecting the input.
- **Transferring to Same Wallet**: If a user attempts "transfer 100rb dari bca ke bca", the system rejects the operation with a friendly error.
- **Negative Wallet Balance**: If a wallet balance drops below zero, the transaction records successfully (reflecting overdraft/cash shortfall) with an alert highlighting the negative balance.
- **Leap Years and Month Ends for Recurring**: If a recurring transaction is scheduled for the 31st and the current month has only 28 or 30 days, the execution occurs on the last calendar day of that month.
- **Timezone Alignment for Digests**: Daily digests must trigger relative to user timezone (default Asia/Jakarta, WIB) rather than UTC server time.
- **Goal Over-allocation**: When a contribution exceeds 100% of the goal target, the progress caps gracefully at 100%+ and notes the surplus.

---

## Requirements *(mandatory)*

### Functional Requirements

#### Budget Management
- **FR-001**: System MUST allow users to define, update, or remove monthly spending limits (budgets) for any category via natural chat commands (e.g., `budget <kategori> <nominal>`).
- **FR-002**: System MUST automatically evaluate category budget consumption whenever an active expense transaction is recorded in that category.
- **FR-003**: System MUST render an ASCII/Unicode progress bar, used amount, total budget, and percentage in transaction confirmations when a category budget exists.
- **FR-004**: System MUST emit warning alerts when budget consumption crosses 80% and 100% thresholds.
- **FR-005**: System MUST provide an on-demand budget overview command (`cek budget`) listing all active budgets for the current calendar month.

#### Multi-Wallet & Account Management
- **FR-006**: System MUST support multiple isolated wallets/accounts per user (e.g., Cash, BCA, Mandiri, DANA, GoPay) with individual balances.
- **FR-007**: System MUST automatically create a default "Cash / Tunai" wallet for every user to preserve backwards compatibility with single-balance records, while maintaining `users.current_balance` as the exact atomic sum of all active wallet balances.
- **FR-008**: System MUST parse wallet tags in transaction messages (e.g., `via dana`, `dari bca`, `ke gopay`) and credit/debit the designated wallet atomically.
- **FR-009**: System MUST debit/credit the user's default wallet when no wallet tag is specified in a transaction message.
- **FR-010**: System MUST provide a multi-wallet balance summary (`saldo`, `dompet`) showing each wallet balance and the consolidated total net balance from `users.current_balance`.
- **FR-011**: System MUST allow users to add new custom wallets and set/adjust initial balances via chat.

#### Inter-Wallet Transfers
- **FR-012**: System MUST parse transfer commands between user-owned wallets (e.g., `transfer <nominal> dari <wallet_asal> ke <wallet_tujuan>`).
- **FR-013**: System MUST execute inter-wallet transfers by creating a single ledger entry in the existing `transactions` table with `type = 'TRANSFER'`, referencing `wallet_id` (source) and `to_wallet_id` (destination), atomically adjusting both wallet balances without inflating income/expense aggregates.
- **FR-014**: System MUST allow atomic undo/reversal of transfer transactions via the `batal` / `undo` command.

#### Recurring Transactions
- **FR-015**: System MUST allow users to register recurring transaction rules with specified frequencies (monthly on specific date, daily, weekly), category, amount, description, and designated wallet.
- **FR-016**: System MUST execute scheduled recurring transactions on their due dates in the background and dispatch a WhatsApp confirmation notice to the user.
- **FR-017**: System MUST provide commands to list (`daftar langganan`) and cancel (`hapus langganan <nama>`) active recurring rules.

#### Financial Goals & Savings
- **FR-018**: System MUST allow users to create named financial goals with a target monetary amount (e.g., `target <nama> <nominal>`).
- **FR-019**: System MUST allow users to contribute savings to a specific goal, tracking accumulated savings, percentage completion, and remaining balance.
- **FR-020**: System MUST render progress bars and milestone celebrations upon goal completion.

#### Automated Digests & Analytics
- **FR-021**: System MUST provide an optional automated daily evening summary at a scheduled hour (default 21:00 WIB) detailing income, expenses, top 3 categories, and closing balances for users with activity today.
- **FR-022**: System MUST generate an executive monthly recap on the 1st of each month with category percentage distribution and net savings rate.
- **FR-023**: System MUST answer comparative questions (e.g., `bulan ini boros gak?`) by calculating month-over-month spending variances, highlighting leading category increases.

#### Freelancer & UMKM Support
- **FR-024**: System MUST support optional project tags on income entries (e.g., `project <nama_project>`) and provide on-demand project revenue aggregation.
- **FR-025**: System MUST support business sales vs cost tags (`jual`, `modal`) and compute daily turnover (omzet), total cost (modal), and gross profit (laba kotor).

---

### Key Entities *(include if feature involves data)*

- **Wallet**: Represents a discrete financial account (e.g., Cash, BCA, DANA) owned by a user (`user_jid`). Attributes include `id` (UUID), `user_jid`, `name`, `type` (BANK, EWALLET, CASH), `is_default` (boolean), `encrypted_balance` (text), and timestamps.
- **Transaction (Reused & Extended)**: Reuses the existing `transactions` table by extending `type` with `'TRANSFER'`, adding `wallet_id` (source account) and `to_wallet_id` (destination account for transfers). No separate transfer table is created, preserving an integrated single ledger.
- **Budget**: Represents a monthly spending limit for a specific category. Attributes include `id` (UUID), `user_jid`, `category_id`, `encrypted_limit_amount`, and timestamps. Unique constraint: `['user_jid', 'category_id']`. Only 1 row per user-category pair exists, dynamically evaluated against transactions in the current calendar month.
- **RecurringTransaction**: Represents a scheduled recurring transaction template. Attributes include `id` (UUID), `user_jid`, `wallet_id`, `category_id`, `type` (EXPENSE, INCOME), `encrypted_amount`, `description`, `day_of_month` (1-31), `frequency` (MONTHLY), `next_run_date`, `is_active`, and timestamps.
- **FinancialGoal**: Represents a savings objective. Attributes include `id` (UUID), `user_jid`, `wallet_id` (optional), `name`, `encrypted_target_amount`, `encrypted_current_amount`, `deadline` (nullable), `status` (ACTIVE, COMPLETED), and timestamps. Contributions directly log transactions under category 'Tabungan' linked to the goal.
- **ProjectTag**: Embedded tag attribute or prefix within existing `description` column (e.g., `[project:Client A]`), avoiding an extraneous relational table.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Users can establish a category budget and view their remaining limit in under 2 seconds via a single WhatsApp message.
- **SC-002**: Multi-wallet balance inquiries (`saldo`) return an itemized, mathematically verified breakdown across all user accounts within 1.5 seconds.
- **SC-003**: 100% of inter-wallet transfers execute atomically without creating artificial expense or income inflation in reports.
- **SC-004**: Recurring transactions trigger on their designated calendar date with 99.9% scheduling reliability and send notifications within 60 seconds of trigger time.
- **SC-005**: Visual progress bars render cleanly on all standard WhatsApp clients (Android, iOS, Web) using universal Unicode block characters.
- **SC-006**: All nominal amounts across wallets, budgets, goals, and transfers maintain full compatibility with zero-knowledge AES-256-GCM encryption at rest.

---

## Assumptions

- **Calendar Month Budgeting**: Budgets default to the current calendar month (1st day to last day of month) and reset automatically each month unless configured otherwise.
- **Default Wallet Fallback**: Existing users with a single global balance will have their balance seamlessly migrated into a default "Cash / Tunai" wallet upon migration, maintaining 100% backwards compatibility.
- **Timezone**: Scheduled recurring transactions and daily evening digests execute based on Western Indonesian Time (WIB / UTC+7) as the primary user locale.
- **Pure Rule-Based Parsing First**: All new command syntax (budgets, wallets, transfers, goals, recurring) uses deterministic regex and token parsing with sub-millisecond execution, completely independent of external LLM API availability or latency.
- **Data Protection**: All new tables storing monetary values (Wallet, Budget, Goal, Recurring) adhere strictly to Constitution Principle I (Multi-Tenant Isolation) and Principle III (Integer Rupiah & AES-256-GCM encryption at rest).
