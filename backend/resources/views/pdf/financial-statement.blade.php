<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Rekening Koran - {{ $statementNumber }}</title>
  <style>
    @page {
      margin: 28px 32px 35px 32px;
      size: A4 portrait;
    }

    body {
      font-family: 'DejaVu Sans', Helvetica, Arial, sans-serif;
      font-size: 9pt;
      line-height: 1.35;
      color: #1e293b;
      margin: 0;
      padding: 0;
    }

    /* Header & Branding */
    .header-table {
      width: 100%;
      border-collapse: collapse;
      border-bottom: 2px solid #059669;
      padding-bottom: 12px;
      margin-bottom: 14px;
    }

    .brand-title {
      font-size: 16pt;
      font-weight: bold;
      color: #059669;
      letter-spacing: -0.5px;
      margin: 0;
    }

    .brand-subtitle {
      font-size: 8pt;
      color: #64748b;
      margin-top: 2px;
    }

    .statement-title {
      font-size: 11pt;
      font-weight: bold;
      color: #0f172a;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-top: 4px;
    }

    .doc-meta {
      text-align: right;
      font-size: 8pt;
      color: #475569;
    }

    .doc-meta strong {
      color: #0f172a;
    }

    .badge-official {
      display: inline-block;
      background-color: #ecfdf5;
      color: #047857;
      border: 1px solid #a7f3d0;
      padding: 2px 7px;
      font-size: 7pt;
      font-weight: bold;
      border-radius: 4px;
      text-transform: uppercase;
      margin-bottom: 4px;
    }

    /* User & Period Info Grid */
    .info-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 14px;
      background-color: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 6px;
    }

    .info-table td {
      padding: 7px 10px;
      font-size: 8.5pt;
      vertical-align: top;
    }

    .info-label {
      color: #64748b;
      font-size: 7.5pt;
      text-transform: uppercase;
      margin-bottom: 2px;
    }

    .info-value {
      font-weight: bold;
      color: #0f172a;
    }

    /* Summary KPI Cards */
    .summary-table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 6px;
      margin-bottom: 14px;
    }

    .summary-card {
      background-color: #f8fafc;
      border: 1px solid #cbd5e1;
      border-radius: 6px;
      padding: 8px 10px;
      text-align: center;
      vertical-align: middle;
    }

    .summary-card.income {
      border-top: 3px solid #10b981;
      background-color: #f0fdf4;
    }

    .summary-card.expense {
      border-top: 3px solid #f43f5e;
      background-color: #fff1f2;
    }

    .summary-card.balance {
      border-top: 3px solid #0284c7;
      background-color: #f0f9ff;
    }

    .summary-card.net {
      border-top: 3px solid #6366f1;
      background-color: #f5f3ff;
    }

    .summary-card .label {
      font-size: 7.5pt;
      color: #64748b;
      text-transform: uppercase;
      font-weight: 600;
      margin-bottom: 3px;
    }

    .summary-card .amount {
      font-size: 10.5pt;
      font-weight: bold;
      font-family: 'Courier New', Courier, monospace;
    }

    .text-income { color: #059669; }
    .text-expense { color: #e11d48; }
    .text-balance { color: #0369a1; }
    .text-net { color: #4f46e5; }

    /* Transactions Table */
    .transactions-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 16px;
    }

    .transactions-table th {
      background-color: #0f172a;
      color: #ffffff;
      font-size: 7.5pt;
      text-transform: uppercase;
      font-weight: bold;
      letter-spacing: 0.5px;
      padding: 6px 8px;
      border: 1px solid #0f172a;
      text-align: left;
    }

    .transactions-table th.text-right {
      text-align: right;
    }

    .transactions-table th.text-center {
      text-align: center;
    }

    .transactions-table td {
      padding: 5px 8px;
      border-bottom: 1px solid #e2e8f0;
      font-size: 8pt;
      vertical-align: middle;
    }

    .transactions-table tr:nth-child(even) td {
      background-color: #f8fafc;
    }

    .mono {
      font-family: 'Courier New', Courier, monospace;
    }

    .text-right { text-align: right; }
    .text-center { text-align: center; }

    .tag-category {
      display: inline-block;
      background-color: #e2e8f0;
      color: #334155;
      padding: 1px 5px;
      border-radius: 3px;
      font-size: 7pt;
    }

    .tag-wallet {
      color: #475569;
      font-size: 7.5pt;
    }

    /* Footer & Disclaimer */
    .footer-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 15px;
      border-top: 1px solid #cbd5e1;
      padding-top: 8px;
      font-size: 7pt;
      color: #64748b;
    }

    .footer-left {
      text-align: left;
      line-height: 1.4;
    }

    .footer-right {
      text-align: right;
      vertical-align: top;
    }

    .security-hash {
      font-family: 'Courier New', Courier, monospace;
      color: #94a3b8;
      font-size: 6.5pt;
      margin-top: 3px;
    }
  </style>
</head>
<body>

  <!-- Header -->
  <table class="header-table">
    <tr>
      <td style="width: 55%; vertical-align: top;">
        <h1 class="brand-title">HEMATWOY</h1>
        <div class="brand-subtitle">Platform Asisten Keuangan Otomatis & Terpercaya</div>
        <div class="statement-title">Rekening Koran / Mutasi Keuangan</div>
      </td>
      <td style="width: 45%; vertical-align: top;" class="doc-meta">
        <span class="badge-official">Dokumen Sah / Terverifikasi</span><br>
        <strong>No. Referensi:</strong> <span class="mono">{{ $statementNumber }}</span><br>
        <strong>Tanggal Terbit:</strong> {{ $generatedAt }}<br>
        <strong>Status Akun:</strong> {{ $userTier }}
      </td>
    </tr>
  </table>

  <!-- User & Period Information -->
  <table class="info-table">
    <tr>
      <td style="width: 30%;">
        <div class="info-label">Nama Pengguna</div>
        <div class="info-value">{{ $userName }}</div>
      </td>
      <td style="width: 25%;">
        <div class="info-label">Nomor WhatsApp</div>
        <div class="info-value mono">{{ $userPhone }}</div>
      </td>
      <td style="width: 25%;">
        <div class="info-label">Periode Laporan</div>
        <div class="info-value">{{ $periodLabel }}</div>
      </td>
      <td style="width: 20%;">
        <div class="info-label">Filter Terpasang</div>
        <div class="info-value">{{ $filterLabel }}</div>
      </td>
    </tr>
  </table>

  <!-- Executive Financial Summary Cards -->
  <table class="summary-table">
    <tr>
      <td class="summary-card" style="width: 20%;">
        <div class="label">Saldo Awal</div>
        <div class="amount mono text-balance">Rp{{ number_format($openingBalance, 0, ',', '.') }}</div>
      </td>
      <td class="summary-card income" style="width: 20%;">
        <div class="label">Total Pemasukan (+)</div>
        <div class="amount mono text-income">+Rp{{ number_format($totalIncome, 0, ',', '.') }}</div>
      </td>
      <td class="summary-card expense" style="width: 20%;">
        <div class="label">Total Pengeluaran (-)</div>
        <div class="amount mono text-expense">-Rp{{ number_format($totalExpense, 0, ',', '.') }}</div>
      </td>
      <td class="summary-card net" style="width: 20%;">
        <div class="label">Arus Kas Bersih (Net)</div>
        <div class="amount mono {{ $netCashflow >= 0 ? 'text-income' : 'text-expense' }}">
          {{ $netCashflow >= 0 ? '+' : '-' }}Rp{{ number_format(abs($netCashflow), 0, ',', '.') }}
        </div>
      </td>
      <td class="summary-card balance" style="width: 20%;">
        <div class="label">Saldo Akhir</div>
        <div class="amount mono text-balance">Rp{{ number_format($closingBalance, 0, ',', '.') }}</div>
      </td>
    </tr>
  </table>

  <!-- Detail Mutasi Transaksi Table -->
  <table class="transactions-table">
    <thead>
      <tr>
        <th class="text-center" style="width: 4%;">No</th>
        <th style="width: 14%;">Tanggal & Jam</th>
        <th style="width: 28%;">Keterangan / Catatan</th>
        <th style="width: 14%;">Kategori</th>
        <th style="width: 12%;">Dompet</th>
        <th class="text-right" style="width: 14%;">Masuk (Kredit)</th>
        <th class="text-right" style="width: 14%;">Keluar (Debit)</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($transactions as $index => $tx)
        <tr>
          <td class="text-center mono">{{ $index + 1 }}</td>
          <td class="mono" style="font-size: 7.5pt;">{{ $tx['date'] }}</td>
          <td>
            <strong>{{ $tx['description'] }}</strong>
            @if(!empty($tx['to_wallet']))
              <span style="font-size: 7pt; color: #0284c7;"> &rarr; {{ $tx['to_wallet'] }}</span>
            @endif
          </td>
          <td><span class="tag-category">{{ $tx['category'] }}</span></td>
          <td class="tag-wallet">{{ $tx['wallet'] }}</td>
          <td class="text-right mono {{ $tx['type'] === 'INCOME' ? 'text-income' : '' }}">
            {{ $tx['type'] === 'INCOME' ? 'Rp' . number_format($tx['amount'], 0, ',', '.') : '-' }}
          </td>
          <td class="text-right mono {{ $tx['type'] === 'EXPENSE' ? 'text-expense' : '' }}">
            {{ $tx['type'] === 'EXPENSE' ? 'Rp' . number_format($tx['amount'], 0, ',', '.') : ($tx['type'] === 'TRANSFER' ? 'Rp' . number_format($tx['amount'], 0, ',', '.') : '-') }}
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="7" class="text-center" style="padding: 20px; color: #64748b;">
            Tidak ada transaksi yang tercatat pada periode dan filter ini.
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>

  <!-- Summary Footer & Official Disclaimer -->
  <table class="footer-table">
    <tr>
      <td class="footer-left" style="width: 70%;">
        <strong>Catatan & Pernyataan Resmi:</strong><br>
        1. Laporan ini dicetak dan diterbitkan secara digital oleh sistem pencatatan otomatis <strong>HematWoy</strong>.<br>
        2. Dokumen ini sah sebagai rekapitulasi data keuangan pribadi dan tidak memerlukan tanda tangan basah.<br>
        3. Seluruh nominal transaksi dienkripsi dengan standar AES-256-GCM pada basis data server kami.<br>
        <div class="security-hash">Digital Hash Verification: {{ $securityHash }}</div>
      </td>
      <td class="footer-right" style="width: 30%;">
        <strong>HematWoy Indonesia</strong><br>
        https://chatbot-money-save.onrender.com<br>
        <em>Smart & Secure Financial Tracking</em>
      </td>
    </tr>
  </table>

</body>
</html>
