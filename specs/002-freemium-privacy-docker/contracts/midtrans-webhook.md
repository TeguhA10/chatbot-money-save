# Interface Contract: Midtrans Webhook Notification

**Feature Branch**: `002-freemium-privacy-docker`  
**Endpoint**: `POST /api/webhook/midtrans`  
**Security**: Midtrans SHA512 signature verification

---

## 1. Purpose

This endpoint receives Midtrans payment notifications and updates `payment_orders`, `subscriptions`, and `users` atomically.

---

## 2. Request Contract

### Headers

```http
Content-Type: application/json
User-Agent: Midtrans-Notification-Engine
```

### Minimum payload fields

```json
{
  "transaction_time": "2026-09-10 09:30:00",
  "transaction_status": "settlement",
  "transaction_id": "8f6c4a3b-21e0-47cb-bc8e-123456789abc",
  "status_code": "200",
  "signature_key": "sha512-hex",
  "payment_type": "qris",
  "order_id": "SUB-6281234567890-1725957000",
  "gross_amount": "25000.00",
  "currency": "IDR"
}
```

### Preconditions
- `order_id` must exist in `payment_orders`
- `gross_amount` must match the stored order amount
- signature must match Midtrans' formula

---

## 3. Signature Verification

```php
$expectedSignature = hash(
    'sha512',
    $orderId . $statusCode . $grossAmount . $serverKey
);

if (! hash_equals($expectedSignature, $signatureKey)) {
    abort(403, 'Invalid signature key');
}
```

---

## 4. Processing Rules

### Paid states
For `transaction_status` in `settlement` or `capture`:
1. lock the `payment_orders` row,
2. ignore the webhook if the order is already marked paid,
3. mark the order as paid,
4. compute new subscription expiry:
   - if `users.subscription_expires_at > now()`, then `new_expires_at = current_expires_at + 30 days`
   - otherwise `new_expires_at = now() + 30 days`
5. upsert an active subscription history row,
6. set `users.tier = PREMIUM`.

### Non-paid states
- `pending`: keep order pending, no subscription change
- `expire`, `cancel`, `deny`: mark order terminal, no premium extension

### Idempotency
- `order_id` is the idempotency key
- repeated paid webhooks for the same `order_id` must return success without extending twice

---

## 5. Success Response

```json
{
  "success": true,
  "message": "Payment notification processed successfully.",
  "order_id": "SUB-6281234567890-1725957000"
}
```

---

## 6. Failure Responses

### Invalid signature

```json
{
  "success": false,
  "message": "Unauthorized: signature verification failed."
}
```

Status: `403 Forbidden`

### Unknown order

```json
{
  "success": false,
  "message": "Payment order not found."
}
```

Status: `404 Not Found`
