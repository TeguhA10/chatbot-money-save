<!--
Sync Impact Report:
- Version change: 0.0.0 (unratified template) → 1.0.0 (initial ratification)
- List of modified principles:
  * [PRINCIPLE_1_NAME] → I. Strict Multi-Tenant Isolation & Zero Data Leakage
  * [PRINCIPLE_2_NAME] → II. Non-Blocking Event Loop & Asynchronous Processing
  * [PRINCIPLE_3_NAME] → III. Financial Data Integrity & Atomic Transactions
  * [PRINCIPLE_4_NAME] → IV. Outbound Rate Limiting & Anti-Ban Safeguards
  * [PRINCIPLE_5_NAME] → V. Test-First (TDD) for Financial Calculation & Parsing
- Added sections:
  * Performance & Scalability Standards (1,000+ users target, memory management, export streaming)
  * Development Workflow & Quality Gates (Type safety, specification adherence, local validation)
- Removed sections: None
- Follow-up TODOs: None
-->

# Chatbot Money Save Constitution

## Core Principles

### I. Strict Multi-Tenant Isolation & Zero Data Leakage (NON-NEGOTIABLE)
Every transaction record, financial balance, and document export MUST be strictly partitioned and scoped by the caller's verified WhatsApp ID (JID). The system MUST NOT permit any query, cache, or report to access or expose data belonging to another user. Any occurrence of cross-tenant data exposure constitutes a critical P0 defect requiring immediate rollback.

### II. Non-Blocking Event Loop & Asynchronous Processing
The WhatsApp connection socket (Baileys event loop) MUST remain responsive at all times and MUST NEVER be blocked by CPU-intensive parsing, heavy database aggregations, or spreadsheet file generation. Document exports and intensive jobs MUST execute asynchronously or offload to background queues so that incoming chat acknowledgment latency remains strictly below 1.5 seconds.

### III. Financial Data Integrity & Atomic Transactions
Financial calculations MUST NEVER use floating-point arithmetic prone to rounding errors; all monetary amounts MUST be represented as exact integers (e.g., in cents/rupiah) or high-precision decimals. Balance mutations and transaction logging MUST execute inside atomic transactional boundaries (ACID) to eliminate race conditions, double-counting, or ledger inconsistencies during concurrent bursts.

### IV. Outbound Rate Limiting & Anti-Ban Safeguards
All outbound messaging through the WhatsApp connection MUST pass through a centralized rate-limiting queue that enforces safe pacing and human-like delays. System messages MUST be deduplicated using idempotency keys to prevent duplicate replies or repetitive triggers that violate messaging provider abuse policies.

### V. Test-First (TDD) for Financial Calculation & Parsing
All natural language parser rules, currency sanitizers, category inference engines, and mathematical aggregation routines MUST be validated by automated unit tests before functional code is written. Code modifications that alter financial outputs or export formats MUST pass end-to-end regression suites before merging.

## Performance & Scalability Standards

### 1,000+ Active Users Readiness
The architecture MUST be designed to scale gracefully to over 1,000 registered users without performance degradation. System state and historical chat logs MUST NOT reside solely in application memory; persistence MUST be backed by an optimized disk store with appropriate indexes on user ID, transaction timestamps, and category keys.

### Memory & Resource Hygiene
In-memory caching for WhatsApp chats and media buffers MUST be strictly bounded using LRU (Least Recently Used) policies or persistent caches to prevent memory leaks. Spreadsheet creation MUST stream or release memory immediately upon dispatch to prevent out-of-memory crashes during multi-user export bursts.

### Predictable Error Boundaries
Any failure in spreadsheet generation, third-party network APIs, or database I/O MUST be caught within an isolated error boundary and result in a user-friendly failure notice. The core WhatsApp bot daemon MUST self-heal and automatically reconnect on network drops without losing state.

## Development Workflow & Quality Gates

### Specification Alignment
Every implemented capability MUST trace directly to an approved feature specification in `specs/` and satisfy its acceptance criteria. Undocumented behavior or architectural bypasses are prohibited.

### Type Safety & Linting
The codebase MUST enforce strict typing (TypeScript) and linting standards. All domain models (User, Transaction, Category, ExportJob) MUST possess explicit interfaces and runtime validation schemas.

### Local Simulation & Verification
Features MUST be verified through unit and integration test harnesses before deployment to live WhatsApp accounts. Test runners MUST verify edge cases including malformed currency syntax, zero/negative inputs, and burst concurrency.

## Governance

This Constitution represents the supreme architectural and engineering policy for the Chatbot Money Save project. All design documents (`plan.md`), task breakdowns (`tasks.md`), pull requests, and implementations MUST verify compliance with the core principles herein. Amendments to this document require explicit documentation of rationale, semver version bumping, and an updated Sync Impact Report.

**Version**: 1.0.0 | **Ratified**: 2026-09-08 | **Last Amended**: 2026-09-08
