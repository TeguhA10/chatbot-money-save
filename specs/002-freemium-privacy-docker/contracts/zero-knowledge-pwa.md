# Interface Contract: Zero-Knowledge Ledger PWA

## Trust boundary

The companion PWA is the only component permitted to handle plaintext financial data and encryption keys. The API, gateway, queue, logs, and database are untrusted for confidentiality and must receive only opaque encrypted envelopes.

## Client responsibilities

1. Derive a non-extractable AES-256-GCM key locally using Web Crypto and an account-specific random salt.
2. Encrypt an entire ledger record locally, including amount, running balance, description, category, and timestamp.
3. Bind each envelope to `user_jid` and the record UUID using AES-GCM additional authenticated data.
4. Decrypt, aggregate, export, and render every financial value locally.
5. Never send the passphrase, derived key, plaintext amount, or plaintext balance in HTTP requests, URLs, analytics, or WhatsApp messages.

## Blind API payload

`POST /api/v1/zk/records`

```json
{
  "record_id": "uuid",
  "ciphertext": "base64",
  "iv": "base64",
  "tag": "base64",
  "key_version": 1,
  "created_at": "2026-09-10T10:00:00Z"
}
```

The server authenticates the account and record ownership, validates envelope shape and size, and stores it unchanged. It must not accept fields named `amount`, `balance`, `description`, `category`, `pin`, `passphrase`, or `recovery_code`.

`GET /api/v1/zk/records` returns only the caller's stored envelopes in cursor order.

## Recovery

Account recovery and cryptographic recovery are separate. A server-held recovery code may restore the account link but cannot decrypt records. A client-created recovery-key bundle, encrypted to a user-chosen recovery secret, is required if users want passphrase recovery.
