<?php

namespace App\Exports;

use App\Models\User;
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
    private int $catStartRow = 12;
    private int $catEndRow = 12;

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
        $query = $this->user->transactions()
            ->active()
            ->whereYear('created_at', $this->year);

        $totalIncome  = (clone $query)->where('type', 'INCOME')->sum('amount');
        $totalExpense = (clone $query)->where('type', 'EXPENSE')->sum('amount');
        $currentBalance = (float) $this->user->current_balance;

        // Group expenses by category
        $categoryBreakdown = (clone $query)
            ->where('type', 'EXPENSE')
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->with('category')
            ->get();

        $rows = [
            ['RINGKASAN LAPORAN KEUANGAN PRIBADI', '', ''],
            ['Tahun', $this->year, ''],
            ['Pemilik', $this->user->name ?? $this->user->jid, ''],
            ['Dicetak Pada', Carbon::now()->isoFormat('D MMMM Y, HH:mm'), ''],
            ['', '', ''],
            ['RINGKASAN EKSEKUTIF (KPI)', '', ''],
            ['Indikator', 'Nominal (Rp)', 'Keterangan'],
            ['Total Pemasukan', (float) $totalIncome, 'Akumulasi tahun ' . $this->year],
            ['Total Pengeluaran', (float) $totalExpense, 'Akumulasi tahun ' . $this->year],
            ['Arus Kas Bersih (Net Cashflow)', '=B8-B9', 'Pemasukan dikurangi Pengeluaran'],
            ['Saldo Rekening Saat Ini', $currentBalance, 'Posisi saldo real-time'],
            ['', '', ''],
            ['DISTRIBUSI PENGELUARAN PER KATEGORI', '', ''],
            ['Kategori', 'Total Pengeluaran (Rp)', 'Persentase'],
        ];

        $this->catStartRow = 15;

        if ($categoryBreakdown->isEmpty()) {
            $rows[] = ['Belum ada pengeluaran', 0, 0];
            $this->catEndRow = 15;
        } else {
            $currentRow = $this->catStartRow;
            foreach ($categoryBreakdown as $item) {
                $catName = ($item->category?->icon ? $item->category->icon . ' ' : '') . ($item->category?->name ?? 'Lainnya');
                $rows[] = [
                    $catName,
                    (float) $item->total,
                    "=IF(\$B\$9>0, B{$currentRow}/\$B\$9, 0)",
                ];
                $currentRow++;
            }
            $this->catEndRow = $currentRow - 1;
        }

        $totalRowIndex = $this->catEndRow + 1;
        $rows[] = [
            'TOTAL PENGELUARAN',
            "=SUM(B{$this->catStartRow}:B{$this->catEndRow})",
            "=SUM(C{$this->catStartRow}:C{$this->catEndRow})",
        ];

        return $rows;
    }

    public function columnFormats(): array
    {
        return [
            'B' => '#,##0',
            'C' => '0.0%',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // Title banner
        $sheet->mergeCells('A1:C1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('1E3A8A');
        
        // Section headers
        $sheet->mergeCells('A6:C6');
        $sheet->getStyle('A6')->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('1E293B');
        $sheet->getStyle('A6:C6')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');

        $sheet->mergeCells('A13:C13');
        $sheet->getStyle('A13')->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('1E293B');
        $sheet->getStyle('A13:C13')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');

        // Table headers (KPI)
        $sheet->getStyle('A7:C7')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);

        // KPI Borders
        $sheet->getStyle('A7:C11')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // Highlight Net Cashflow and Saldo
        $sheet->getStyle('A10:C10')->getFont()->setBold(true);
        $sheet->getStyle('A11:C11')->getFont()->setBold(true);

        // Table headers (Category Breakdown)
        $sheet->getStyle('A14:C14')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0D9488']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);

        // Category Borders & Totals
        $lastRow = $this->catEndRow + 1;
        $sheet->getStyle("A14:C{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("A{$lastRow}:C{$lastRow}")->getFont()->setBold(true);
        $sheet->getStyle("A{$lastRow}:C{$lastRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F1F5F9');

        return [];
    }
}
