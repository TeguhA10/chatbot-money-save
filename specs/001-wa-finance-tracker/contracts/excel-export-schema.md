# Excel Export Schema & Template Contract

**Feature Branch**: `001-wa-finance-tracker`
**Date**: 2026-09-08
**Format**: Multi-sheet Microsoft Excel Workbook (.xlsx)
**MIME Type**: `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`

## 1. Structure of the Workbook

The generated spreadsheet file name follows: `Laporan_Keuangan_[USER_PHONE]_[YYYYMMDD].xlsx`.

```text
Laporan_Keuangan_6281234567890_20260908.xlsx
├── Sheet 1: "Dashboard Ringkasan" (Executive Overview & Formulas)
└── Sheet 2..N: "YYYY-MM" (e.g. "2026-09", "2026-08" - Monthly Detailed Logs)
```

---

## 2. Sheet 1: "Dashboard Ringkasan"

### Header Block (Styled Banner)
- **A1:E1**: Merged Title: `RINGKASAN LAPORAN KEUANGAN PRIBADI` (Navy Blue Background `#1B365D`, Bold White Text, 14pt).
- **A2:E2**: Subtitle: `No. WhatsApp: [USER_PHONE] | Tanggal Export: [YYYY-MM-DD HH:mm]`.

### KPI Metric Cards (Rows 4-6)
- **B4:C5 (Total Pemasukan)**: Card dengan latar hijau lembut (`#E8F5E9`), formula `=SUM('2026-09'!E:E, ...)`, format `"Rp"#,##0`.
- **D4:E5 (Total Pengeluaran)**: Card dengan latar merah lembut (`#FFEBEE`), formula `=SUM('2026-09'!F:F, ...)`, format `"Rp"#,##0`.
- **B6:E6 (Saldo Bersih)**: Merged Card dengan latar biru lembut (`#E3F2FD`), formula `=B4-D4`, format `"Rp"#,##0`.

### Category Breakdown Table (Row 8+)
| Kolom A (No) | Kolom B (Kategori) | Kolom C (Tipe) | Kolom D (Total Nominal) | Kolom E (% dari Pengeluaran) |
|---|---|---|---|---|
| 1 | 🍜 Makanan & Minuman | Pengeluaran | Rp 1.250.000 | `=D9/$D$4` (Percentage format `0.0%`) |
| 2 | 🛵 Transportasi | Pengeluaran | Rp 450.000 | `=D10/$D$4` |
| ... | ... | ... | ... | ... |

---

## 3. Sheet 2..N: Monthly Tabs (e.g. "2026-09")

### Table Columns
| Kolom | Nama Header | Tipe Data | Format Sel | Lebar Kolom |
|---|---|---|---|---|
| A | No | Integer | `0` | 6 |
| B | Tanggal | Date | `YYYY-MM-DD` | 13 |
| C | Waktu | Time | `HH:mm` | 10 |
| D | Kategori | Text | `@` (Left-aligned) | 20 |
| E | Pemasukan (Rp) | Currency / Integer | `"Rp"#,##0` | 18 |
| F | Pengeluaran (Rp) | Currency / Integer | `"Rp"#,##0` | 18 |
| G | Saldo Berjalan (Rp) | Currency / Formula | `"Rp"#,##0` | 20 |
| H | Keterangan | Text | `@` (Left-aligned) | 35 |

### Baris Total (Bawah)
- Label `TOTAL` di Kolom D.
- Formula Kolom E: `=SUM(E2:E[last])`.
- Formula Kolom F: `=SUM(F2:F[last])`.
- Net Flow: `=E[total] - F[total]`.
- Top Border: Thin line, Bottom Border: Double accounting line.
