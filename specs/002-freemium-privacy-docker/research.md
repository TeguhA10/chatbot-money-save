# Research & Technical Decisions: Freemium Paywall, Zero-Knowledge Nominal Encryption, and Production Dockerization

**Feature Branch**: `002-freemium-privacy-docker`  
**Date**: 2026-09-10  
**Status**: Completed

> **Architecture revision — 2026-09-10**: The user selected strict zero-knowledge privacy. The earlier server-side PIN model below is superseded for nominal data: the Laravel and WhatsApp services must never receive a PIN, plaintext nominal, balance, or decryptable session key.

## 0. Strict Zero-Knowledge Companion Architecture

### Decision
Introduce a browser-based companion PWA. It generates a non-extractable AES-256-GCM key with Web Crypto from a locally entered passphrase and a locally held salt, encrypts every ledger payload before upload, and decrypts/report-calculates only in the browser. Laravel stores and returns opaque ciphertext envelopes scoped by JID, record ID, and ciphertext metadata; it performs no monetary arithmetic.

WhatsApp is retained only for account linking, quota/subscription status, payment links, and a signed deep link into the PWA. A user must not send financial amounts or their passphrase through WhatsApp.

### Rationale
- A server that receives a PIN or plaintext nominal is not zero knowledge, even if it immediately encrypts the database value.
- Client-side Web Crypto keeps the key material outside Laravel, Redis, logs, queues, Docker volumes, and administrator access.
- Browser-side reporting allows correct arithmetic without requiring homomorphic encryption.

### Alternatives Considered
- **Server-side AES-GCM derived from a WhatsApp PIN**: rejected because the server observes the PIN and can decrypt values during handling.
- **Encrypted browser local storage only**: rejected because it has no resilient multi-device backup/sync path.
- **Homomorphic encryption**: rejected because it is disproportionate for a personal-finance PWA and incompatible with the latency target.

### Consequences
- Existing plaintext `users.current_balance`, `transactions.amount`, and `transactions.balance_after` must not be used for any zero-knowledge user. A separate, explicit legacy-migration/export flow is required; it cannot be automated without the user supplying legacy data in the client.
- The original WhatsApp natural-language financial command workflow is replaced by PWA data entry. WhatsApp messages with financial-looking plaintext must be rejected with a link to the PWA.
- Recovery code semantics change: recovery can restore account access only. It cannot recover a forgotten encryption passphrase unless the user holds an encrypted recovery-key bundle created client-side.

---

## 1. Quota Accounting Model

### Decision
Free quota is charged per successful **financial WhatsApp message**, not per parsed ledger row. One inbound message such as `keluar 50000 bensin, 20000 parkir` consumes exactly 1 free unit when the message is accepted and stored atomically.

### Rationale
- This matches the approved clarification and prevents quota inflation for multi-transaction messages.
- Read-only commands such as `saldo`, `rekap`, `kategori`, `bantuan`, and `status langganan` remain available even when free quota is exhausted.
- Quota mutation can happen in the same database transaction as ledger writes, so message acceptance and quota consumption stay consistent.

### Alternatives Considered
- **Per transaction row**: rejected because it conflicts with the approved behavior and punishes users who send compact multi-item messages.
- **Per inbound WhatsApp message regardless of type**: rejected because it would block users from accessing their own data after quota exhaustion.

---

## 2. Zero-Knowledge Encryption Model

### Decision
Use **AES-256-GCM field-level encryption** for `amount`, `balance_after`, and the user's current balance, with a per-user key derived from the user's 6-digit PIN using **PBKDF2-HMAC-SHA256** and a unique per-user salt. The server stores only:
- PIN verifier hash
- recovery code hash
- encryption salt and metadata
- ciphertext payloads (`iv`, `tag`, `ciphertext`)

The raw encryption key is never persisted.

### Rationale
- One-way hashing is unusable for balances because the system must decrypt values to answer `saldo`, `rekap`, and Excel export requests.
- A single server-side master key would let operators decrypt every user, which violates the zero-knowledge requirement.
- AES-GCM provides confidentiality and tamper detection in one primitive, which is a good fit for encrypted integer payloads.
- The existing codebase currently stores `current_balance`, `amount`, and `balance_after` as plaintext integers, so the plan must replace those storage fields rather than layer the new model on top of them.

### Alternatives Considered
- **Hashing only**: rejected because reports and balance math become impossible.
- **Database file encryption only**: rejected because admins with host access could still read decrypted values through the app.
- **Homomorphic encryption**: rejected as unnecessary complexity and too slow for chat-driven usage.

---

## 3. PIN Onboarding, Recovery Code, and Re-Encryption

### Decision
On the first financial message from a user without an active PIN, the bot starts an inline setup flow instead of recording the transaction immediately. After the user sets a PIN:
1. generate a one-time 16-character recovery code,
2. show it once in chat,
3. store only its hash,
4. resume the pending transaction flow after PIN setup completes.

PIN reset uses `reset pin <recovery_code> <pin_baru>`. When reset succeeds, all encrypted user financial fields are re-encrypted atomically with the newly derived key inside one database transaction.

### Rationale
- This matches the approved onboarding behavior and recovery requirement.
- Storing only the recovery code hash preserves the same trust boundary as the PIN.
- Atomic re-encryption prevents half-migrated historical data and satisfies the explicit edge-case requirement.

### Alternatives Considered
- **Manual admin-assisted reset**: rejected because it breaks the zero-knowledge model.
- **Creating a second active key version**: rejected because the spec requires exactly one active key per user.

---

## 4. Subscription and Midtrans Integration

### Decision
Use **Midtrans Snap** for payment initiation and a signed webhook at `POST /api/webhook/midtrans` for activation. For a verified `settlement` or `capture` event:
- mark the payment order as settled idempotently,
- upgrade the user to `PREMIUM`,
- extend subscription expiry by 30 days from the current expiry if still active,
- otherwise start 30 days from the webhook verification time.

### Rationale
- Snap gives a mobile-friendly payment page suitable for WhatsApp users.
- Webhook signature verification with Midtrans' documented SHA512 formula is straightforward in Laravel.
- Idempotent order handling protects against webhook retries and duplicate subscription extension.

### Alternatives Considered
- **Manual transfer confirmation**: rejected because it adds human operational steps.
- **Another Indonesian gateway**: possible, but Midtrans already fits the stated requirement and has solid PHP support.

---

## 5. Runtime and Docker Topology

### Decision
Deploy the application as separate services under Docker Compose:
- `backend`: Laravel API + webhook receiver
- `gateway`: Baileys sidecar that receives and sends WhatsApp messages
- `redis`: queue/cache for outbound throttling and short-lived PIN session cache
- `worker`: Laravel queue worker for exports and heavy background jobs

Persistent volumes:
- `baileys_auth` for WhatsApp auth state
- `backend_storage` for exports and app storage
- `database_data` for SQLite or database service data

### Rationale
- The current repository already separates `backend/` and `whatsapp-gateway/`, so service boundaries map naturally to the codebase.
- Keeping gateway and backend isolated protects the Baileys event loop from slow report generation or payment processing.
- A dedicated queue/cache service supports the constitution requirement for non-blocking flow and outbound rate limiting.

### Alternatives Considered
- **Single container with PHP and Node together**: rejected because it couples failure domains and makes health checks less clear.
- **No Redis**: possible for local development, but not preferred for production because throttling, cache expiry, and queued jobs become harder to manage predictably.
