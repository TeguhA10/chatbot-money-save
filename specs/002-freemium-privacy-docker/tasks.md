---

description: "Implementation tasks for freemium subscriptions, encrypted financial data, and Docker deployment"
---

# Tasks: Freemium Paywall, Zero-Knowledge Nominal Encryption, and Production Dockerization

**Input**: Design documents from `specs/002-freemium-privacy-docker/`

**Prerequisites**: `plan.md`, `spec.md`, `research.md`, `data-model.md`, `contracts/`, and `quickstart.md`

**Tests**: Required. The project constitution mandates test-first coverage for financial calculation and parsing; each story therefore starts with its focused tests.

**Organization**: Tasks are grouped by user story so every increment can be implemented and validated independently after the shared foundation is complete.

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Add the packages, environment settings, and container entry points used by the feature.

- [ ] T001 Add Midtrans PHP SDK and document the required payment, Redis, and encryption environment variables in `backend/composer.json` and `backend/.env.example`
- [ ] T002 [P] Add Redis-backed queue, cache, and session connection settings for production in `backend/config/queue.php`, `backend/config/cache.php`, and `backend/config/database.php`
- [X] T003 [P] Add gateway-to-backend authentication, outbound pacing, and production environment variables in `whatsapp-gateway/.env.example`
- [X] T004 [P] Create the Laravel multi-stage production image in `backend/Dockerfile`
- [X] T005 [P] Create the Baileys gateway production image in `whatsapp-gateway/Dockerfile`
- [X] T006 Define local multi-service orchestration, named persistent volumes, Redis, health checks, and restart policies in `docker-compose.yml`
- [X] T007 Define production overrides for reverse proxy, non-development commands, and resource-safe service configuration in `docker-compose.prod.yml`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Establish tenant-safe persistence, cryptographic primitives, idempotency, and asynchronous message plumbing before any story work begins.

**⚠️ CRITICAL**: Complete this phase before beginning user-story implementation.

- [X] T008 Create migrations for user subscription/PIN metadata, encrypted ledger columns, subscriptions, payment orders, and usage logs in `backend/database/migrations/2026_09_10_000001_add_freemium_encryption_to_users_and_transactions.php` and `backend/database/migrations/2026_09_10_000002_create_subscription_payment_and_usage_tables.php`
- [X] T009 [P] Add encrypted-field casts, tenant relations, subscription state helpers, and safe plaintext-field retirement to `backend/app/Models/User.php` and `backend/app/Models/Transaction.php`
- [X] T010 [P] Create tenant-scoped Eloquent models and relationships for subscription, payment order, and usage accounting in `backend/app/Models/Subscription.php`, `backend/app/Models/PaymentOrder.php`, and `backend/app/Models/UsageLog.php`
- [ ] T011 [P] Write unit tests for AES-GCM payload validation, integer-only financial values, and cross-user decryption rejection in `backend/tests/Unit/EncryptionServiceTest.php`
- [ ] T012 Implement per-user PBKDF2-HMAC-SHA256 key derivation, AES-256-GCM envelopes, secure key disposal, PIN verification, and recovery-code hashing in `backend/app/Services/EncryptionService.php`
- [ ] T013 [P] Write feature tests proving inbound message IDs are idempotent and tenant queries cannot cross WhatsApp JID boundaries in `backend/tests/Feature/IdempotentMessageProcessingTest.php`
- [ ] T014 Centralize authenticated gateway webhook validation, inbound message-id claiming, and safe domain error responses in `backend/app/Http/Middleware/AuthenticateGatewayRequest.php`, `backend/app/Http/Middleware/EnsureMessageIsUnprocessed.php`, and `backend/bootstrap/app.php`
- [ ] T015 Register queue-backed outbound reply dispatch, retry policy, and structured redacted logging in `backend/app/Jobs/SendWhatsAppReply.php`, `backend/app/Services/WhatsAppReplyService.php`, and `backend/config/logging.php`

**Checkpoint**: Database, encryption boundary, idempotency guard, and non-blocking reply foundation are ready.

---

## Phase 3: User Story 1 - Freemium Usage Quota & Subscription Paywall (Priority: P1) 🎯 MVP

**Goal**: Let a user record exactly 100 successful financial messages for free, retain read-only access afterward, and purchase the Rp25.000 monthly plan.

**Independent Test**: Simulate 100 successful single- and multi-line financial messages for one JID, assert the 101st is rejected with the paywall, assert `saldo` remains available, then settle an order and confirm unlimited processing.

### Tests for User Story 1

- [ ] T016 [P] [US1] Write feature tests for exactly-one quota debit per successful financial message, including multi-transaction input and quota-exhausted read-only commands, in `backend/tests/Feature/SubscriptionQuotaTest.php`
- [ ] T017 [P] [US1] Write feature tests for order creation, price validation, duplicate payment protection, 30-day stacking, and webhook signature rejection in `backend/tests/Feature/MidtransWebhookTest.php`
- [ ] T018 [P] [US1] Write API contract tests from `contracts/midtrans-webhook.md` in `backend/tests/Feature/Api/MidtransWebhookContractTest.php`

### Implementation for User Story 1

- [ ] T019 [US1] Implement transaction-aware quota eligibility, one-unit usage logging, low-quota warnings, and premium expiry evaluation in `backend/app/Services/SubscriptionService.php`
- [ ] T020 [US1] Implement Midtrans Snap order creation, exact-price enforcement, SHA512 notification verification, and idempotent 30-day subscription extension in `backend/app/Services/MidtransService.php`
- [ ] T021 [US1] Add subscription purchase/status endpoints and the Midtrans webhook endpoint in `backend/app/Http/Controllers/Api/SubscriptionController.php`, `backend/app/Http/Controllers/Api/MidtransWebhookController.php`, and `backend/routes/api.php`
- [ ] T022 [US1] Integrate financial-message quota checks and paywall/status command handling while preserving read-only commands in `backend/app/Http/Controllers/Api/WebhookController.php` and `backend/app/Services/FinanceService.php`
- [ ] T023 [US1] Add `beli pro`, quota, expiration, and paid-activation reply templates from `contracts/whatsapp-subscription-flow.md` in `backend/app/Services/WhatsAppReplyService.php`

**Checkpoint**: Free users receive 100 successful financial messages; payment activation makes the account unlimited without blocking data access.

---

## Phase 4: User Story 2 - Zero-Knowledge Financial Privacy & Nominal Encryption (Priority: P2)

**Goal**: Store all monetary values as user-keyed ciphertext and provide safe PIN onboarding, recovery, and atomic re-encryption.

**Independent Test**: Set a PIN, record a transaction, verify raw database monetary columns contain no plaintext amount, retrieve the correct balance after PIN verification, then reset the PIN and confirm all historical data is decryptable only with the new PIN.

### Tests for User Story 2

- [ ] T024 [P] [US2] Write feature tests for first-financial-message PIN onboarding, recovery-code one-time display, and pending-message resume in `backend/tests/Feature/PinOnboardingTest.php`
- [ ] T025 [P] [US2] Write feature tests for encrypted transaction/current-balance storage, authenticated report decryption, malformed amount rejection, and no plaintext persistence in `backend/tests/Feature/ZeroKnowledgeEncryptionTest.php`
- [ ] T026 [P] [US2] Write feature tests for recovery-code reset, transaction-wide atomic re-encryption, and rollback on one corrupt encrypted record in `backend/tests/Feature/PinResetReencryptionTest.php`

### Implementation for User Story 2

- [ ] T027 [US2] Implement six-digit PIN setup state, encrypted pending financial-message storage, 16-character recovery-code generation, and reset command validation in `backend/app/Services/PinLifecycleService.php`
- [ ] T028 [US2] Replace plaintext amount and running-balance writes with encrypted integer envelopes, transaction-scoped balance calculation, and in-memory decryption in `backend/app/Services/FinanceService.php`
- [ ] T029 [US2] Implement atomic historical decryption and re-encryption with key-version replacement during PIN reset in `backend/app/Services/PinLifecycleService.php`
- [ ] T030 [US2] Require a recent valid PIN context before balance, recap, analytics, and spreadsheet decryption in `backend/app/Http/Controllers/Api/WebhookController.php`, `backend/app/Http/Controllers/Api/AnalyticsController.php`, and `backend/app/Services/ExcelExportService.php`
- [ ] T031 [US2] Add PIN setup, recovery, and reset reply flows specified by `contracts/whatsapp-subscription-flow.md` in `backend/app/Services/WhatsAppReplyService.php`

**Checkpoint**: Database operators cannot read nominal values, while a verified user can still obtain correct reports and recover access without partial re-encryption.

---

## Phase 5: User Story 3 - Production Readiness & High-Performance Security Hardening (Priority: P3)

**Goal**: Keep message acknowledgement responsive under bursts, prevent duplicate work and spam, and safely throttle WhatsApp replies.

**Independent Test**: Send a burst of duplicate and distinct gateway events for a single JID, verify duplicates mutate nothing, rate-limited replies are queued, and gateway acknowledgement remains below the 1.5-second target.

### Tests for User Story 3

- [ ] T032 [P] [US3] Add Laravel feature tests for burst idempotency, rate-limit responses, queue dispatch, and transaction rollback under concurrent financial messages in `backend/tests/Feature/MessageHardeningTest.php`
- [ ] T033 [P] [US3] Add gateway unit tests for outbound queue spacing, response deduplication, retry backoff, and reconnect behavior in `whatsapp-gateway/src/queue.test.ts` and `whatsapp-gateway/src/index.test.ts`
- [ ] T034 [P] [US3] Add a repeatable acknowledgment-latency benchmark and acceptance threshold in `backend/tests/Feature/GatewayAcknowledgementBenchmarkTest.php`

### Implementation for User Story 3

- [ ] T035 [US3] Add per-JID inbound rate limiting, request-size validation, and financial command sanitization before parser execution in `backend/app/Http/Middleware/RateLimitGatewayMessages.php` and `backend/app/Http/Controllers/Api/WebhookController.php`
- [ ] T036 [US3] Ensure parser, ledger write, quota debit, usage log, and processed-message claim use one ACID transaction in `backend/app/Services/FinanceService.php`
- [ ] T037 [US3] Implement Redis-backed FIFO outbound throttling, idempotency keys, bounded retries, and exponential reconnect backoff in `whatsapp-gateway/src/queue.ts` and `whatsapp-gateway/src/index.ts`
- [ ] T038 [US3] Make the gateway acknowledge inbound WhatsApp events before asynchronous backend work and route reply sends through the throttled queue in `whatsapp-gateway/src/index.ts` and `whatsapp-gateway/src/webhook-client.ts`
- [X] T039 [US3] Add backend and gateway liveness/readiness health endpoints with no sensitive diagnostic output in `backend/routes/api.php`, `backend/app/Http/Controllers/Api/HealthController.php`, and `whatsapp-gateway/src/index.ts`

**Checkpoint**: Burst traffic is safely bounded, duplicate financial mutations are prevented, and outbound traffic follows centralized anti-ban pacing.

---

## Phase 6: User Story 4 - Multi-Container Docker Deployment & Zero-Configuration Publish (Priority: P4)

**Goal**: Start the backend, worker, gateway, Redis, and persistent storage from a clean clone with Docker Compose and recover safely after restarts.

**Independent Test**: On a clean Docker host, bring the compose stack up, wait for health checks, pair WhatsApp, restart every service, and confirm the database, exports, and Baileys session persist.

### Tests for User Story 4

- [ ] T040 [P] [US4] Add a Docker Compose smoke-test script that waits for all health checks and exercises the backend health endpoint in `scripts/verify-compose.ps1`
- [ ] T041 [P] [US4] Add a deployment persistence verification script for named volumes and Baileys auth state in `scripts/verify-compose-persistence.ps1`

### Implementation for User Story 4

- [ ] T042 [US4] Configure production Laravel container startup, migrations, queue worker supervision, non-root execution, and health checks in `backend/Dockerfile` and `docker-compose.yml`
- [ ] T043 [US4] Configure gateway container auth volume mounting, graceful shutdown, automatic reconnect, and health checks in `whatsapp-gateway/Dockerfile` and `docker-compose.yml`
- [ ] T044 [US4] Configure persistent isolated volumes for database, Laravel exports, and `auth_info_baileys`, plus backup-safe paths, in `docker-compose.yml` and `docker-compose.prod.yml`
- [ ] T045 [US4] Document clean-host deployment, required secret injection, pairing, health validation, rollback, and backup procedures in `README.md` and `specs/002-freemium-privacy-docker/quickstart.md`

**Checkpoint**: The full stack starts with Compose, reports healthy, and keeps session and financial data through recreates.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Validate the complete security, operational, and documentation outcome.

- [ ] T046 [P] Update OpenAPI descriptions and schemas without exposing encrypted values, PINs, recovery codes, or Midtrans secrets in `backend/public/openapi.json`
- [ ] T047 [P] Add redaction assertions for structured logs and exception responses in `backend/tests/Feature/SensitiveDataRedactionTest.php`
- [ ] T048 Run the complete backend and gateway test suites, Docker smoke tests, and every scenario in `specs/002-freemium-privacy-docker/quickstart.md`; record any remediation in `specs/002-freemium-privacy-docker/quickstart.md`
- [ ] T049 Review migrations and operational documentation against all FR-001 through FR-020 and SC-001 through SC-008 in `specs/002-freemium-privacy-docker/spec.md`

---

## Phase 8: Strict Zero-Knowledge Architecture Amendment (Blocking)

**Purpose**: Replace the server-observed PIN design with the user-approved PWA trust boundary before any encrypted financial feature is released.

- [ ] T050 [P] Write browser unit tests for Web Crypto key derivation, AES-GCM envelope authentication, and wrong-passphrase rejection in `backend/resources/js/zk-crypto.test.js`
- [ ] T051 [P] Write API feature tests rejecting plaintext financial fields and enforcing JID-scoped opaque-envelope access in `backend/tests/Feature/ZeroKnowledgeRecordApiTest.php`
- [ ] T052 Create a PWA Web Crypto module that derives a local non-extractable key and encrypts/decrypts authenticated ledger envelopes in `backend/resources/js/zk-crypto.js`
- [ ] T053 Create local-only ledger aggregation, encrypted recovery-key bundle creation, and export formatting in `backend/resources/js/zk-ledger.js`
- [ ] T054 Create the unlock, record-entry, sync, and report companion PWA interface in `backend/resources/js/app.js`, `backend/resources/css/app.css`, and `backend/resources/views/wallet.blade.php`
- [ ] T055 Create an opaque-envelope schema and tenant-scoped blind-sync endpoints in `backend/database/migrations/2026_09_10_000003_create_encrypted_ledger_envelopes.php`, `backend/app/Models/EncryptedLedgerEnvelope.php`, and `backend/app/Http/Controllers/Api/ZeroKnowledgeRecordController.php`
- [ ] T056 Route financial-looking WhatsApp messages to the companion PWA without forwarding their plaintext to ledger services in `backend/app/Http/Controllers/Api/WebhookController.php` and `whatsapp-gateway/src/index.ts`
- [ ] T057 Replace server-side PIN/recovery-key handling and plaintext financial routes with PWA linking and opaque record synchronization in `backend/routes/api.php`, `backend/app/Services/EncryptionService.php`, and `backend/app/Services/FinanceService.php`
- [ ] T058 Add client-side backup/restore, lost-passphrase warnings, CSP, and end-to-end privacy validation to `README.md` and `specs/002-freemium-privacy-docker/quickstart.md`

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)** has no dependencies.
- **Phase 2 (Foundational)** depends on Phase 1 and blocks every user story.
- **US1 (P1)** depends on Phase 2; it is the recommended MVP.
- **US2 (P2)** depends on the encryption and persistence foundation, then integrates the US1 financial-message path.
- **US3 (P3)** depends on Phase 2 and should be completed before production deployment.
- **US4 (P4)** depends on the production runtime contracts from US1–US3.
- **Phase 7 (Polish)** depends on the desired stories being complete.

### User Story Dependencies

- **US1**: Start after foundational work; delivers quota and payment independently.
- **US2**: Requires the foundational cryptography service and modifies the financial flow delivered by US1.
- **US3**: Starts after foundational work; must be integrated with the final US1/US2 reply path.
- **US4**: Validates the operational form of all preceding stories.

### Parallel Opportunities

- T002–T005 can proceed in parallel after T001.
- T009–T011 and T013 can proceed in parallel after T008.
- Per-story tests marked `[P]` can be authored in parallel before their corresponding implementation tasks.
- US3 test and gateway queue work can proceed alongside US2 after Phase 2, provided integration changes to shared files are coordinated.
- T040, T041, T046, and T047 are independent file-level parallel work.

## Parallel Example: User Story 2

```text
Task: "Write PIN onboarding coverage in backend/tests/Feature/PinOnboardingTest.php"
Task: "Write encrypted-storage coverage in backend/tests/Feature/ZeroKnowledgeEncryptionTest.php"
Task: "Write atomic PIN-reset coverage in backend/tests/Feature/PinResetReencryptionTest.php"
```

## Implementation Strategy

### MVP First (User Story 1)

1. Complete setup and foundational work.
2. Implement and validate US1 through T023.
3. Demonstrate exactly-100-message quota enforcement, read-only access, and a verified payment activation.

### Incremental Delivery

1. Add US2 to encrypt actual financial records and enable recovery.
2. Add US3 to harden gateway throughput, idempotency, and anti-ban behavior.
3. Add US4 to make the validated stack reproducible in production.
4. Complete cross-cutting security and operational validation.

## Format Validation

All 49 tasks use the required checkbox, sequential task ID, optional `[P]` marker only for independent work, story label for story tasks, and an exact repository path.
