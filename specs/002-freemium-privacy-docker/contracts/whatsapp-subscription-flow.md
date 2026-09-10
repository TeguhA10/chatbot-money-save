# Interface Contract: WhatsApp Subscription, PIN, and Quota Flows

**Feature Branch**: `002-freemium-privacy-docker`

This document defines the user-facing chat contract for quota usage, premium upgrade, PIN onboarding, and recovery.

---

## 1. First Financial Message Without PIN

Trigger: user sends a valid financial message while `pin_status = PENDING_SETUP`.

Expected bot reply:

```text
🔐 *Atur PIN Privasi Kamu*
━━━━━━━━━━━━━━━━━
Sebelum transaksi pertama diproses, kamu perlu membuat PIN 6 digit untuk mengenkripsi nominal uangmu.

Ketik:
`set pin 123456`

Setelah PIN aktif, bot akan lanjut memproses transaksi kamu.
```

Rules:
- the first financial message is not written yet
- the bot must not decrement quota before PIN setup succeeds
- the pending message may be resumed after setup, or the user may resend it

---

## 2. PIN Setup Success + Recovery Code

Trigger: user sends `set pin <6_digit_pin>` and validation passes.

Expected bot reply:

```text
✅ *PIN Berhasil Disimpan*
━━━━━━━━━━━━━━━━━
Mulai sekarang nominal keuangan kamu disimpan terenkripsi.

*Recovery Code kamu:*
`AB12CD34EF56GH78`

Simpan kode ini baik-baik. Kode ini hanya ditampilkan satu kali dan dipakai jika kamu lupa PIN.
```

Rules:
- recovery code length is exactly 16 characters
- only the hash of the recovery code is stored
- user state becomes `PIN_ACTIVE`

---

## 3. Recovery Reset

Trigger: user sends `reset pin <recovery_code> <pin_baru>`.

Success reply:

```text
✅ *PIN Berhasil Direset*
━━━━━━━━━━━━━━━━━
Semua data finansial kamu sudah dienkripsi ulang dengan PIN baru.
```

Failure reply:

```text
❌ Recovery Code tidak valid atau format PIN baru salah.
```

Rules:
- reset must verify the recovery code hash
- all historical encrypted values are re-encrypted atomically
- on failure, no partial re-encryption may remain

---

## 4. Low Quota Warning

Trigger: a free user successfully records a financial message and has 10 or fewer free units remaining.

Expected bot reply suffix:

```text
⚠️ Sisa kuota gratis kamu: 9 pesan finansial lagi.
Ketik `beli pro` untuk lanjut tanpa batas.
```

Rule:
- quota is counted per successful financial message, not per parsed line item

---

## 5. Quota Exhausted Paywall

Trigger: a `FREE` user has already consumed 100 free financial messages and sends the 101st financial message.

Expected bot reply:

```text
⛔ *Kuota Gratis Sudah Habis*
━━━━━━━━━━━━━━━━━
Kamu sudah memakai 100 pesan finansial gratis.

Upgrade ke *WA Finance Pro* untuk pencatatan tanpa batas.
Biaya: *Rp 25.000 / 30 hari*

Ketik `beli pro` atau buka link pembayaran:
https://app.sandbox.midtrans.com/snap/v2/example
```

Rules:
- read-only commands such as `saldo`, `rekap`, `bantuan`, and `status langganan` remain available
- blocked financial messages do not consume more quota

---

## 6. Premium Activation Confirmation

Trigger: valid paid Midtrans webhook settles an order.

Expected bot reply:

```text
🎉 *Langganan Pro Aktif*
━━━━━━━━━━━━━━━━━
Pembayaran kamu sudah terverifikasi.

Status: *PREMIUM*
Berlaku sampai: *09 Oktober 2026*

Sekarang kamu bisa mencatat transaksi tanpa batas.
```

Rules:
- if the user already has active premium time, the new 30-day period stacks on top of the existing expiry
- the shown expiry date must match the stored `subscription_expires_at`

---

## 7. Subscription Status Inquiry

Trigger: user sends `status langganan` or `kuota`.

Expected bot reply fields:
- current tier: `FREE` or `PREMIUM`
- remaining free financial message count when tier is `FREE`
- premium expiry date when tier is `PREMIUM`

Example:

```text
📄 *Status Langganan*
━━━━━━━━━━━━━━━━━
Status: FREE
Sisa kuota gratis: 24 dari 100 pesan finansial
```
