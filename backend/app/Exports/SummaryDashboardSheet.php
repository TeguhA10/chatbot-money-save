<?php

namespace App\Exports;

use App\Models\User;
use App\Services\FinanceService;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SummaryDashboardSheet implements FromArray, WithTitle, WithStyles, ShouldAutoSize, WithColumnFormatting
{
    private User $user;
    private ?int $year;

    // Track dynamic row ranges for styling
    private int $kpiStartRow = 7;
    private int $kpiEndRow = 11;

    private int $walletHeaderRow = 14;
    private int $walletStartRow = 15;
    private int $walletEndRow = 15;

    private int $budgetHeaderRow = 18;
    private int $budgetStartRow = 19;
    private int $budgetEndRow = 19;

    private int $goalsHeaderRow = 28;
    private int $goalsStartRow = 29;
    private int $goalsEndRow = 29;

    private int $recurringHeaderRow = 33;
    private int $recurringStartRow = 34;
    private int $recurringEndRow = 34;

    public function __construct(User $user, ?int $year = null)
    {
        $this->user = $user;
        $this->year = $year ?? (int) now()->year;
    }

    public function title(): string
    {
        return 'Dashboard';
    }

    public function array(): array
    {
        $startOfYear = Carbon::create($this->year, 1, 1, 0, 0, 0)->startOfYear();
        $endOfYear   = Carbon::create($this->year, 12, 31, 23, 59, 59)->endOfYear();

        $transactions = $this->user->transactions()
            ->active()
            ->whereBetween('created_at', [$startOfYear, $endOfYear])
            ->with(['wallet', 'toWallet'])
            ->get();

        $totalIncome  = (float) $transactions->where('type', 'INCOME')->sum('amount');
        $totalExpense = (float) $transactions->where('type', 'EXPENSE')->sum('amount');
        $currentBalance = (float) $this->user->current_balance;

        // 1. Title & Executive KPI (Preserve exact rows 0-10 for tests & KPI)
        $rows = [
            ['RINGKASAN LAPORAN KEUANGAN PRIBADI', '', '', '', ''],
            ['Tahun', $this->year, '', '', ''],
            ['Pemilik', $this->user->display_name !== '' ? $this->user->display_name : $this->user->jid, '', '', ''],
            ['Dicetak Pada', Carbon::now()->isoFormat('D MMMM Y, HH:mm'), '', '', ''],
            ['', '', '', '', ''],
            ['RINGKASAN EKSEKUTIF (KPI)', '', '', '', ''],
            ['Indikator', 'Nominal (Rp)', 'Keterangan', '', ''],
            ['Total Pemasukan', (float) $totalIncome, 'Akumulasi tahun ' . $this->year, '', ''],
            ['Total Pengeluaran', (float) $totalExpense, 'Akumulasi tahun ' . $this->year, '', ''],
            ['Arus Kas Bersih (Net Cashflow)', '=B8-B9', 'Pemasukan dikurangi Pengeluaran', '', ''],
            ['Saldo Rekening Saat Ini', $currentBalance, 'Posisi total saldo konsolidasi', '', ''],
            ['', '', '', '', ''],
        ];

        // 2. Multi-Wallet Breakdown
        $wallets = $this->user->wallets()->orderBy('created_at')->get();
        if ($wallets->isEmpty()) {
            app(FinanceService::class)->getOrCreateDefaultWallet($this->user);
            $wallets = $this->user->wallets()->get();
        }

        $rows[] = ['RINCIAN SALDO PER DOMPET (MULTI-WALLET)', '', '', '', ''];
        $this->walletHeaderRow = count($rows) + 1;
        $rows[] = ['Nama Dompet', 'Saldo Terkini (Rp)', 'Tipe Akun', 'Status Dompet', ''];
        $this->walletStartRow = count($rows) + 1;

        foreach ($wallets as $wallet) {
            $typeLabel = match (strtoupper($wallet->type)) {
                'BANK'    => 'Rekening Bank',
                'EWALLET' => 'Dompet Digital / e-Wallet',
                default   => 'Uang Tunai / Cash',
            };
            $rows[] = [
                $wallet->name,
                (float) $wallet->balance,
                $typeLabel,
                $wallet->is_default ? 'Utama (Default)' : 'Sekunder',
                '',
            ];
        }
        $this->walletEndRow = count($rows);
        $rows[] = ['', '', '', '', ''];

        // 3. Budgets / Plafon Anggaran Bulanan
        $budget = $this->user->budgets()->first();
        $rows[] = ['PLAFON ANGGARAN BULANAN (BUDGET)', '', '', '', ''];
        $this->budgetHeaderRow = count($rows) + 1;
        $rows[] = ['Keterangan', 'Batas Anggaran (Rp)', 'Realisasi Tahun Berjalan (Rp)', 'Alert Threshold', 'Status'];
        $this->budgetStartRow = count($rows) + 1;

        if (!$budget) {
            $rows[] = ['Belum ada budget ditentukan', 0, 0, '-', 'Gunakan: budget <nominal>'];
            $this->budgetEndRow = count($rows);
        } else {
            $spent = (float) $transactions->where('type', 'EXPENSE')->sum('amount');
            $limit = (float) $budget->limit_amount;
            $status = $spent > $limit ? '⚠️ Melebihi Plafon' : '✅ Aman';

            $rows[] = [
                'Batas Pengeluaran Bulanan',
                $limit,
                $spent,
                ($budget->alert_threshold_percent ?? 80) . '%',
                $status,
            ];
            $this->budgetEndRow = count($rows);
        }
        $rows[] = ['', '', '', '', ''];

        // 5. Target Tabungan (Financial Goals)
        $goals = $this->user->financialGoals()->get();
        $rows[] = ['TARGET TABUNGAN & RENCANA MASA DEPAN (GOALS)', '', '', '', ''];
        $this->goalsHeaderRow = count($rows) + 1;
        $rows[] = ['Nama Target', 'Target Nominal (Rp)', 'Terkumpul (Rp)', 'Progress (%)', 'Status Target'];
        $this->goalsStartRow = count($rows) + 1;

        if ($goals->isEmpty()) {
            $rows[] = ['Belum ada target tabungan', 0, 0, 0, 'Gunakan: buat target <nama> <nominal>'];
            $this->goalsEndRow = count($rows);
        } else {
            foreach ($goals as $goal) {
                $target = (float) $goal->target_amount;
                $current = (float) $goal->current_amount;
                $percent = $target > 0 ? ($current / $target) : 0;
                $rows[] = [
                    $goal->name,
                    $target,
                    $current,
                    $percent,
                    $goal->status === 'COMPLETED' ? '🎉 Tercapai' : '⏳ Berjalan',
                ];
            }
            $this->goalsEndRow = count($rows);
        }
        $rows[] = ['', '', '', '', ''];

        // 6. Langganan & Transaksi Rutin (Recurring Schedules)
        $schedules = $this->user->recurringSchedules()->with('wallet')->where('is_active', true)->get();
        $rows[] = ['LANGGANAN & TRANSAKSI RUTIN BULANAN (RECURRING)', '', '', '', ''];
        $this->recurringHeaderRow = count($rows) + 1;
        $rows[] = ['Keterangan Langganan', 'Nominal (Rp)', 'Jadwal Debit', 'Dompet Pembayaran', 'Tipe Transaksi'];
        $this->recurringStartRow = count($rows) + 1;

        if ($schedules->isEmpty()) {
            $rows[] = ['Belum ada transaksi rutin', 0, '-', '-', 'Gunakan: langganan <nama> <nominal> tanggal <tgl>'];
            $this->recurringEndRow = count($rows);
        } else {
            foreach ($schedules as $sched) {
                $rows[] = [
                    $sched->description,
                    (float) $sched->amount,
                    'Tanggal ' . $sched->day_of_month . ' setiap bulan',
                    $sched->wallet?->name ?? 'Cash',
                    $sched->type === 'INCOME' ? 'Pemasukan Rutin' : 'Pengeluaran Rutin',
                ];
            }
            $this->recurringEndRow = count($rows);
        }

        return $rows;
    }

    public function columnFormats(): array
    {
        return [
            'B' => '#,##0',
            'C' => '#,##0',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // 1. Title banner
        $sheet->mergeCells('A1:E1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('1E3A8A');

        // Helper function for table header styling
        $applySection = function ($headerRow, $colorRGB) use ($sheet) {
            $sheet->mergeCells("A{$headerRow}:E{$headerRow}");
            $sheet->getStyle("A{$headerRow}")->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('1E293B');
            $sheet->getStyle("A{$headerRow}:E{$headerRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');
        };

        $applyTableHeader = function ($row, $bgColorRGB) use ($sheet) {
            $sheet->getStyle("A{$row}:E{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColorRGB]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
            ]);
        };

        // KPI
        $applySection(6, 'E2E8F0');
        $applyTableHeader(7, '2563EB'); // Blue
        $sheet->getStyle('A7:E11')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A10:E11')->getFont()->setBold(true);

        // Wallets
        $walletSectionRow = $this->walletHeaderRow - 1;
        $applySection($walletSectionRow, 'E2E8F0');
        $applyTableHeader($this->walletHeaderRow, '4F46E5'); // Indigo
        $sheet->getStyle("A{$this->walletHeaderRow}:E{$this->walletEndRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // Budgets
        $budgetSectionRow = $this->budgetHeaderRow - 1;
        $applySection($budgetSectionRow, 'E2E8F0');
        $applyTableHeader($this->budgetHeaderRow, 'D97706'); // Amber
        $sheet->getStyle("A{$this->budgetHeaderRow}:E{$this->budgetEndRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // Goals
        $goalsSectionRow = $this->goalsHeaderRow - 1;
        $applySection($goalsSectionRow, 'E2E8F0');
        $applyTableHeader($this->goalsHeaderRow, '059669'); // Emerald
        $sheet->getStyle("A{$this->goalsHeaderRow}:E{$this->goalsEndRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("D{$this->goalsStartRow}:D{$this->goalsEndRow}")->getNumberFormat()->setFormatCode('0.0%');

        // Recurring
        $recurringSectionRow = $this->recurringHeaderRow - 1;
        $applySection($recurringSectionRow, 'E2E8F0');
        $applyTableHeader($this->recurringHeaderRow, '7C3AED'); // Purple
        $sheet->getStyle("A{$this->recurringHeaderRow}:E{$this->recurringEndRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        return [];
    }
}
