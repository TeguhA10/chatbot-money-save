# Interface Contract: WhatsApp Chat Command Grammars & Reply Cards

**Feature**: `003-smart-finance-features`  
**Date**: 2026-10-02  
**Interface**: WhatsApp Inbound / Outbound Chat Gateway  

---

## 1. Budget Commands

### Set/Update Budget
- **Trigger Patterns**:
  - `budget <kategori> <nominal>` (e.g., `budget makan 1jt`, `budget transport 500rb`, `anggaran hiburan 300000`)
- **Action**: Upserts static monthly budget rule for the category.
- **Reply Card**:
  ```text
  🎯 *Plafon Anggaran Diatur*
  Kategori: 🍔 Makanan & Minuman
  Limit Bulanan: Rp1.000.000
  Status Saat Ini: Rp0 / Rp1.000.000 (0%)
  ░░░░░░░░░░░░░░░░░░░░ 0%
  ```

### Transaction with Budget Progress
- When logging an expense in a budgeted category (e.g., `keluar 250rb makan siang`):
  ```text
  ✅ *Pengeluaran Dicatat*
  Nominal: Rp250.000
  Kategori: 🍔 Makanan & Minuman
  Dompet: Cash

  🍔 *Budget Makanan*
  Terpakai: Rp750.000 / Rp1.000.000
  Sisa: Rp250.000
  ███████████████░░░░░ 75%
  ```

### Check All Budgets
- **Trigger Patterns**: `cek budget`, `status budget`, `list budget`
- **Reply Card**:
  ```text
  📊 *Ringkasan Budget Bulan Ini*

  🍔 Makanan
  Rp750.000 / Rp1.000.000 (Sisa: Rp250.000)
  ███████████████░░░░░ 75%

  🚗 Transportasi
  Rp400.000 / Rp500.000 (Sisa: Rp100.000)
  ████████████████░░░░ 80% ⚠️

  🎬 Hiburan
  Rp100.000 / Rp300.000 (Sisa: Rp200.000)
  ██████░░░░░░░░░░░░░░ 33%
  -----------------------------------
  Total Terpakai: Rp1.250.000 / Rp1.800.000 (69%)
  ```

---

## 2. Multi-Wallet & Transfer Commands

### Check Balances (`saldo`)
- **Trigger Patterns**: `saldo`, `cek saldo`, `dompet`, `rekening`
- **Reply Card**:
  ```text
  💰 *Saldo Anda*

  BCA           Rp2.100.000
  DANA            Rp350.000
  Cash            Rp200.000
  -------------------------
  *Total*       *Rp2.650.000*
  ```

### Wallet-Tagged Transaction
- **Trigger Patterns**:
  - `keluar <nominal> <ket> via <nama_wallet>` (e.g., `keluar 50rb makan via dana`)
  - `masuk <nominal> <ket> ke <nama_wallet>` (e.g., `masuk 5jt gaji ke bca`)
- **Reply Card**:
  ```text
  ✅ *Pengeluaran Dicatat*
  Nominal: Rp50.000
  Kategori: 🍔 Makanan & Minuman
  Dompet: DANA (Sisa: Rp300.000)
  Total Saldo: Rp2.600.000
  ```

### Inter-Wallet Transfer
- **Trigger Patterns**:
  - `transfer <nominal> dari <wallet_asal> ke <wallet_tujuan>` (e.g., `transfer 200rb dari bca ke dana`, `tarik tunai 500rb dari bca ke cash`)
- **Reply Card**:
  ```text
  🔄 *Transfer Berhasil*
  Nominal: Rp200.000
  Dari: BCA (Sisa: Rp1.900.000)
  Ke: DANA (Sisa: Rp550.000)
  Total Saldo Tetap: Rp2.600.000
  ```

### Add New Wallet
- **Trigger Patterns**: `tambah dompet <nama> [saldo <nominal>]` (e.g., `tambah dompet GoPay saldo 100rb`)

---

## 3. Recurring Transaction Commands

### Create Recurring Commitment
- **Trigger Patterns**:
  - `langganan <nama> <nominal> setiap tanggal <tgl> [via <wallet>]` (e.g., `langganan Netflix 186rb setiap tanggal 5 via bca`)
  - `gaji <nominal> setiap tanggal <tgl> ke <wallet>`
- **Reply Card**:
  ```text
  ⏰ *Transaksi Rutin Terjadwal*
  Nama: Netflix
  Nominal: Rp186.000
  Jadwal: Tanggal 5 setiap bulan
  Dompet: BCA
  Eksekusi Berikutnya: 05 Nov 2026
  ```

### Execution Notification (Automated)
- Dispatched to user on trigger date at 00:05 WIB:
  ```text
  🔔 *Transaksi Rutin Berhasil Diproses*
  Tagihan: Netflix
  Nominal: Rp186.000
  Dompet: BCA (Sisa: Rp1.714.000)
  ```

### List / Delete Recurring
- **Triggers**: `daftar langganan`, `hapus langganan <nama>`

---

## 4. Financial Goals (Target Tabungan)

### Create Goal
- **Trigger Patterns**: `buat target <nama> <nominal>` (e.g., `buat target apotek 100jt`, `target motor 20jt`)
- **Reply Card**:
  ```text
  🎯 *Target Finansial Dibuat*
  Target: Modal Apotek
  Tujuan: Rp100.000.000
  Terkumpul: Rp0 (0%)
  Sisa: Rp100.000.000
  ░░░░░░░░░░░░░░░░░░░░ 0%
  ```

### Add Savings to Goal
- **Trigger Patterns**: `tambah tabungan <nominal> untuk <nama_goal> [via <wallet>]` (e.g., `tambah tabungan 500rb untuk apotek via bca`)
- **Reply Card**:
  ```text
  🎯 *Target: Modal Apotek*
  Target: Rp100.000.000
  Terkumpul: Rp12.500.000 (+Rp500.000)
  Sisa: Rp87.500.000
  ██░░░░░░░░░░░░░░░░░░ 12.5%

  _Saldo BCA berkurang Rp500.000 (Sisa: Rp1.214.000)_
  ```

---

## 5. Automated Digests & Analytics Queries

### Daily Evening Digest (Auto 21:00 WIB)
- **Outbound Message**:
  ```text
  📊 *Ringkasan Keuangan Hari Ini*

  Pemasukan: Rp100.000
  Pengeluaran: Rp75.000
  Net: +Rp25.000

  Pengeluaran terbesar:
  🍔 Makan — Rp40.000
  🚗 Transport — Rp25.000
  ☕ Lainnya — Rp10.000

  Total Saldo: Rp2.350.000
  ```

### Analytical Inquiry
- **Trigger Patterns**: `bulan ini boros gak?`, `apakah boros`, `evaluasi pengeluaran`
- **Reply Card**:
  ```text
  📊 *Analisis Keuangan Bulan Ini*
  Dibandingkan bulan lalu, pengeluaran Anda naik *18%*.

  Peningkatan terbesar berasal dari:
  🍔 Makanan: +Rp320.000
  🚗 Transportasi: +Rp150.000

  Estimasi pengeluaran sampai akhir bulan: sekitar Rp5.100.000.
  ```

---

## 6. Freelancer & UMKM Tracking

### Project Income (Freelancer)
- **Trigger**: `masuk 2jt project website client A [ke bca]`
- **Query**: `rekap freelance`
- **Reply Card**:
  ```text
  💻 *Pendapatan Freelance September 2026*
  Total: Rp4.750.000

  Rincian Project:
  • Website Client A: Rp2.000.000
  • Website B: Rp1.500.000
  • Desain Logo C: Rp1.250.000
  ```

### UMKM Turnover & Cost
- **Triggers**: `jual nasi goreng 25rb`, `modal bahan 300rb`
- **Query**: `omzet hari ini`, `laba hari ini`
- **Reply Card**:
  ```text
  📈 *Penjualan Hari Ini*
  Omzet (Penjualan): Rp750.000
  Modal (HPP): Rp300.000
  -------------------------------
  *Estimasi Laba Kotor*: *Rp450.000*
  ```
