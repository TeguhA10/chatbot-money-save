# WhatsApp Command & Message Contract

**Feature Branch**: `001-wa-finance-tracker`
**Date**: 2026-09-08

## 1. Natural Language Financial Recording Grammar

### A. Pengeluaran (Expense) Patterns
- **Regex Patterns**:
  - `^(?:keluar|beli|bayar|-)\s*(?:rp\.?\s*)?(\d+(?:[.,]\d+)?\s*(?:k|rb|jt)?)\s*(?:untuk|buat)?\s*(.*)$/i`
  - `^(.*)\s+(?:rp\.?\s*)?(\d+(?:[.,]\d+)?\s*(?:k|rb|jt)?)$/i`
- **Contoh Valid**:
  - `keluar 25000 makan siang`
  - `-15k bensin pertalite`
  - `beli kopi 28.000`
  - `nasi padang 25rb`
  - `bayar listrik 150k`
- **Format Respon Balasan Bot**:
  ```text
  ✅ *Catatan Pengeluaran Tersimpan*
  ━━━━━━━━━━━━━━━━━
  📝 *Keterangan*: Makan siang nasi padang
  🏷️ *Kategori*: 🍜 Makanan & Minuman
  💸 *Nominal*: Rp 25.000
  ━━━━━━━━━━━━━━━━━
  💰 *Sisa Saldo*: Rp 75.000
  _Ketik "batal" jika ingin membatalkan transaksi ini._
  ```

### B. Pemasukan (Income) Patterns
- **Regex Patterns**:
  - `^(?:masuk|gaji|dapat|terima|\+)\s*(?:rp\.?\s*)?(\d+(?:[.,]\d+)?\s*(?:k|rb|jt)?)\s*(?:dari|buat)?\s*(.*)$/i`
- **Contoh Valid**:
  - `masuk 5000000 gaji bulanan`
  - `+250k freelance desain`
  - `dapat transferan 1.5jt`
- **Format Respon Balasan Bot**:
  ```text
  🎉 *Pemasukan Berhasil Dicatat*
  ━━━━━━━━━━━━━━━━━
  📝 *Sumber*: Freelance desain logo
  🏷️ *Kategori*: 💰 Gaji & Pendapatan
  💵 *Nominal*: Rp 250.000
  ━━━━━━━━━━━━━━━━━
  💰 *Total Saldo Sekarang*: Rp 1.250.000
  ```

---

## 2. Inquiries & Report Commands

| Perintah | Deskripsi | Format Respon Ringkas |
|---|---|---|
| `saldo` | Cek saldo aktif & ringkasan | Menampilkan total saldo aktif terkini |
| `pengeluaran hari ini` | Daftar transaksi pengeluaran hari ini | List hingga 10 pengeluaran hari ini dengan ID singkat 8-karakter |
| `pengeluaran bulan ini` | Daftar transaksi pengeluaran bulan ini | List pengeluaran sepanjang bulan berjalan dengan ID singkat 8-karakter |
| `pemasukan hari ini` | Daftar transaksi pemasukan hari ini | List hingga 10 pemasukan hari ini dengan ID singkat 8-karakter |
| `pemasukan bulan ini` | Daftar transaksi pemasukan bulan ini | List pemasukan sepanjang bulan berjalan dengan ID singkat 8-karakter |
| `ubah transaksi [ID] [nominal] [keterangan]` | Mengubah nominal & keterangan transaksi | Konfirmasi perubahan transaksi dan rekalkulasi saldo mutasi baru |
| `hapus transaksi [ID]` | Membatalkan/menghapus transaksi berdasarkan ID | Konfirmasi penghapusan transaksi dan rekalkulasi saldo mutasi baru |
| `rekap` / `rekap hari ini` | Rekap transaksi hari ini | Daftar list pengeluaran hari ini & totalnya |
| `rekap bulan ini` | Rekap total pengeluaran & pemasukan bulan ini | Total masuk, total keluar, sisa surplus/defisit, dan rincian kategori |
| `kategori` / `list kategori` | Menampilkan seluruh kategori aktif | Daftar kategori bawaan + kategori kustom buatan user |
| `tambah kategori [nama]` | Menambahkan kategori baru | Konfirmasi penambahan kategori kustom baru |
| `export excel` / `laporan excel` | Meminta file spreadsheet (.xlsx) | Dokumen Excel multi-sheet dikirim sebagai file attachment WA |
| `batal` | Membatalkan transaksi terakhir | Transaksi terakhir di-void dan saldo dikembalikan |
| `bantuan` / `help` / `menu` | Menampilkan panduan cara mencatat | Menu panduan lengkap format pencatatan & pengelolaan |
