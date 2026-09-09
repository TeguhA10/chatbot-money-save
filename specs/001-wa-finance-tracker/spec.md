# Feature Specification: WhatsApp Personal Finance & Expense Tracker Bot

**Feature Branch**: `001-wa-finance-tracker`

**Created**: 2026-09-08

**Status**: Ready for Planning

**Input**: User description: "Says mau buat chat bot wa catatan keuangan, tapi data nya di simpan di Excel masing-masing user, dan rencanya target mau 1000 user lebih, dan performanya yang lancar, dan mwnggunakan Baileys karena ini masih saya sendiri yang pakai, apa saja yang harus diplaningkan di spek kit"

## Clarifications

### Session 2026-09-08
- Q: How does the system manage expense and income categories? → A: System provides standard predefined categories by default, and allows users to add/customize categories via chat commands (Option B).
- Q: What layout structure should the generated Excel spreadsheet use? → A: Multi-sheet workbook consisting of an executive summary dashboard (total income, expense, balance, monthly delta, category breakdown, automated SUM formulas) and subsequent detailed monthly transaction tabs (Option B).
- Q: What method is used to parse transaction input messages? → A: High-performance deterministic Rule & Pattern Matching engine (Regex + token parsing) for sub-millisecond local processing with zero external API costs, accompanied by helpful syntax guidance when input is unrecognized (Option A).
- Q: Scope delivery order & local verification? → A: Backend-first release (WhatsApp Bot Daemon + SQLite Local Storage + Multi-sheet Excel Generator + REST API Service). All components MUST be 100% testable and runnable locally on the developer's Windows computer using automated test suites and an interactive terminal chat simulator without requiring external network dependencies or immediate live phone pairing. Frontend client applications (Vue 3 Web & Expo Mobile) consume the backend REST API in a follow-up milestone.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Quick Expense & Income Recording (Priority: P1)

As a WhatsApp user managing personal finances,
I want to log my daily expenses and income via quick, natural chat messages,
So that I can effortlessly record transactions without installing external apps or navigating complicated forms.

**Why this priority**: Core value proposition of the product. Without reliable, lightning-fast transaction logging directly through WhatsApp, the system has no utility.

**Independent Test**: Can be fully tested by sending various financial text inputs (e.g., "beli nasi padang 25000", "+500000 bonus project", "-15k bensin") and verifying that the system parses amount, type, description, updates user balance, and replies with an instant confirmation card in under 1.5 seconds.

**Acceptance Scenarios**:
1. **Given** an onboarded user with an initial balance of Rp 100.000, **When** the user sends "keluar 35000 makan siang", **Then** the system records an expense of Rp 35.000 under food/dining, recalculates the balance to Rp 65.000, and replies with a concise confirmation containing transaction ID, category, amount, and current balance.
2. **Given** an onboarded user, **When** the user sends shorthand syntax like "masuk 250k gaji", **Then** the system accurately recognizes "250k" as Rp 250.000, classifies it as income, records the entry, and confirms with updated balance.
3. **Given** an onboarded user, **When** the user sends an invalid message like "pengeluaran banyak hari ini" without any numeric amount, **Then** the system replies with a polite clarification message explaining accepted input formats with concrete examples.

---

### User Story 2 - Instant Balance & Periodic Summary Inquiries (Priority: P2)

As a WhatsApp user tracking a budget,
I want to ask the bot for my current balance and financial summaries (daily, weekly, monthly),
So that I can monitor my spending habits and remain within my budget limits.

**Why this priority**: Users need immediate visibility into their financial status to make informed spending decisions.

**Independent Test**: Can be independently tested by requesting "saldo", "rekap hari ini", or "laporan bulan ini" after multiple transactions have been logged, verifying that summary aggregates (total income, total expense, net savings, and category breakdown) are mathematically accurate and formatted cleanly.

**Acceptance Scenarios**:
1. **Given** a user with 5 logged transactions in the current month, **When** the user sends "saldo", **Then** the system replies immediately with the net current balance and summary of transactions today.
2. **Given** a user with recorded transactions across multiple categories, **When** the user sends "rekap bulan ini", **Then** the system sends a breakdown showing total income, total expenditure, remaining balance, and top spending categories sorted by nominal amount.

---

### User Story 3 - On-Demand Excel Spreadsheet Delivery (Priority: P3)

As a WhatsApp user who likes deeper financial analysis,
I want to request an export of my complete financial transactions as an Excel spreadsheet document directly in WhatsApp,
So that I have full, portable ownership of my raw financial records, with pre-calculated totals, dashboard summaries, and clean formatting that I can open in Excel or Google Sheets.

**Why this priority**: Fulfills the explicit business requirement of individual user Excel records while maintaining rapid bot responsiveness.

**Independent Test**: Can be independently tested by sending "export excel" or "laporan excel", verifying that the system compiles an individual user spreadsheet document (.xlsx), attaches it to the chat, and that the sheet opens without error showing a summary dashboard and monthly tabs with formulas.

**Acceptance Scenarios**:
1. **Given** an active user with historical transactions, **When** the user sends "export excel", **Then** the bot generates a customized multi-sheet spreadsheet document (Dashboard tab + Monthly log tabs), attaches it as a downloadable file in the chat within 4 seconds, and provides a download summary note.
2. **Given** a new user with zero recorded transactions, **When** the user requests "export excel", **Then** the system notifies the user that no transactions exist yet and offers quick instructions to log their first transaction.

---

### User Story 4 - High-Concurrency User Isolation & Onboarding (Priority: P4)

As a service operator supporting over 1,000 independent users,
I want each user's financial data to be strictly partitioned by their unique WhatsApp identity,
So that users never experience data leaks, crosstalk, or degraded response times during peak message traffic.

**Why this priority**: Essential for security, privacy, compliance, and scalability across the target 1,000+ user base.

**Independent Test**: Can be tested by simulating 100 concurrent incoming messages from different phone numbers simultaneously, verifying that each user receives only their own data and average response latency stays under 1.5 seconds.

**Acceptance Scenarios**:
1. **Given** a new WhatsApp number messaging the bot for the first time, **When** any initial message is sent, **Then** the bot initializes a fresh, isolated user profile, presents a welcome guide, and prepares the private ledger without exposing data from any other user.
2. **Given** 1,000 concurrent user accounts registered in the system, **When** multiple users submit transactions at the exact same second, **Then** all transactions are processed without race conditions, data corruption, or cross-account contamination.

---

### Edge Cases

- **Ambiguous or Mixed Format Inputs**: User enters amounts with confusing formatting (e.g. "25.000", "25,000", "25000", "25k", "2.5jt"). System must normalize Indonesian currency colloquialisms predictably using deterministic regex tokens.
- **Burst / Rapid Fire Messaging**: A user sends 5 messages in 2 seconds (e.g. rapid multi-item logging). The system must queue and order them sequentially without dropped messages or race condition on the balance.
- **Negative or Zero Amounts**: User inputs "beli baju -50000" or "nabung 0". The system must sanitize signs and reject zero or illogical negative amounts.
- **Large Excel Export**: User has over 5,000 transaction rows. Excel file generation must stream/process asynchronously without stalling the chat responder thread.
- **Deleted or Edited Transactions**: User mistakenly logs wrong amount and sends "batal" or "hapus transaksi terakhir". System must safely void the latest transaction and adjust the balance.
- **Custom Category Collisions**: User attempts to create a custom category that already exists or uses reserved system command words. System must provide clear feedback and avoid duplicate categories.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST uniquely identify and isolate each user based on their international WhatsApp phone number (JID).
- **FR-002**: System MUST automatically initialize an isolated financial ledger upon receiving a message from an unregistered phone number.
- **FR-003**: System MUST parse financial transaction messages containing amount, transaction direction (income vs. expense), optional category, and description.
- **FR-004**: System MUST support common Indonesian currency formats, including numeric ("50000"), dot separators ("50.000"), and abbreviations ("50k", "1.5jt").
- **FR-005**: System MUST provide built-in default categories (Makanan, Transportasi, Belanja, Tagihan, Hiburan, Gaji, Bisnis, Lainnya) and allow users to create, list, and manage custom categories via chat commands (e.g. `tambah kategori [nama]`).
- **FR-006**: System MUST update and persist running balance and transaction logs atomically to prevent race conditions during concurrent inputs.
- **FR-007**: System MUST provide instant transaction confirmation messages detailing type, nominal amount, category, and updated balance.
- **FR-008**: System MUST support inquiry commands for current balance ("saldo"), daily recap ("rekap hari ini"), and monthly recap ("rekap bulan ini").
- **FR-009**: System MUST allow users to request an on-demand spreadsheet file export (.xlsx) structured as a multi-sheet workbook containing: (1) a Summary Dashboard tab with overall income/expense totals, balance, and category distribution formulas, and (2) individual monthly transaction tabs detailing chronological records.
- **FR-010**: System MUST format exported spreadsheet documents with standard currency formatting, dates, categorized headers, and automatic summary formulas (`SUM`).
- **FR-011**: System MUST transmit generated spreadsheet files directly to the requesting user as a native document attachment in the WhatsApp conversation.
- **FR-012**: System MUST implement an incoming message queue and an outgoing message throttle to prevent triggering messaging provider spam thresholds and ensure responsive processing under load.
- **FR-013**: System MUST provide an undo command ("batal" / "hapus transaksi terakhir") allowing users to safely reverse their most recent transaction within a grace window.
- **FR-014**: System MUST process incoming messages using a high-performance local deterministic rule and regex parser (<50ms execution time, zero external API costs), and provide contextual syntax suggestions and guidance when messages cannot be recognized.
- **FR-015**: System MUST strictly isolate all financial queries and document exports so that zero cross-user data leakage can occur.
- **FR-016**: System MUST provide a local terminal test simulator and standalone automated test suites runnable on the local Windows environment with zero external service dependencies.

### Key Entities *(include if feature involves data)*

- **UserProfile**: Represents a registered WhatsApp account. Attributes: Unique Phone Identifier (JID), Display Name, Created Timestamp, Current Net Balance, Active Status.
- **Transaction**: Represents an individual financial event. Attributes: Transaction ID, User Reference, Type (Income / Expense), Amount, Category Reference, Description, Transaction Timestamp, Status (Active / Voided).
- **Category**: Represents a classification bucket. Attributes: Category ID, User Reference (null for system defaults, user-scoped for custom categories), Name, Direction (Expense / Income), Created Timestamp.
- **ExportJob**: Represents an on-demand spreadsheet export request. Attributes: Job ID, User Reference, Request Timestamp, Status (Pending, Completed, Failed), File Reference.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Users receive transaction confirmation replies via WhatsApp within 1.5 seconds of sending a valid expense or income message under normal operating conditions.
- **SC-002**: The system successfully supports at least 1,000 registered users and handles a minimum burst of 100 concurrent transactions per minute without message loss or transaction discrepancies.
- **SC-003**: On-demand multi-sheet Excel spreadsheet (.xlsx) generation and delivery completes within 4 seconds from user request for accounts with up to 2,000 historical transactions.
- **SC-004**: 100% of exported spreadsheet files open correctly across major spreadsheet viewers (Microsoft Excel, Google Sheets, LibreOffice, WPS Mobile) without file corruption or formula syntax errors.
- **SC-005**: 0% cross-tenant data contamination: independent automated audits confirm that no user can access or view another user's balance, transactions, or spreadsheet exports.
- **SC-006**: Greater than 95% of standard natural Indonesian expense phrases (e.g. "makan siang 25rb", "-15.000 parkir", "beli pulsa 50k") are correctly parsed on first attempt using local regex matching.
- **SC-007**: 100% of test suites (unit, integration, REST API, and terminal simulator) execute and pass locally on a standard Windows environment without requiring an active WhatsApp connection.

## Assumptions

- Each user is uniquely identified by their originating WhatsApp phone number; multi-device WhatsApp messages from the same account map to the same user identifier.
- Users primarily transact in Indonesian Rupiah (IDR); multi-currency conversion is out of scope for the initial release.
- Users have WhatsApp client versions capable of receiving standard document attachments (.xlsx).
- Storage and compute infrastructure for the bot will be provisioned to handle persistence, background job queues, and asynchronous file creation for the 1,000+ user target.
- Voice notes, image receipt OCR, and bank statement uploads are deferred to future feature iterations.
