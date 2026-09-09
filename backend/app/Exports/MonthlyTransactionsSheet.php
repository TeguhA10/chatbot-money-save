<?php

namespace App\Exports;

use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MonthlyTransactionsSheet implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles, ShouldAutoSize, WithColumnFormatting
{
    private User $user;
    private string $yearMonth; // Format 'YYYY-MM'
    private int $rowNumber = 0;
    private int $totalRows = 0;

    public function __construct(User $user, string $yearMonth)
    {
        $this->user = $user;
        $this->yearMonth = $yearMonth;
    }

    public function title(): string
    {
        return Carbon::parse($this->yearMonth . '-01')->format('M Y');
    }

    public function collection(): Collection
    {
        [$year, $month] = explode('-', $this->yearMonth);

        $transactions = $this->user->transactions()
            ->active()
            ->whereYear('created_at', (int) $year)
            ->whereMonth('created_at', (int) $month)
            ->with('category')
            ->orderBy('created_at', 'asc')
            ->get();

        $this->totalRows = $transactions->count();

        return $transactions;
    }

    public function headings(): array
    {
        return [
            'No',
            'Tanggal & Waktu',
            'Tipe',
            'Kategori',
            'Keterangan',
            'Nominal (Rp)',
            'Saldo Sesudah (Rp)',
        ];
    }

    /**
     * @param Transaction $row
     */
    public function map($row): array
    {
        $this->rowNumber++;

        return [
            $this->rowNumber,
            $row->created_at ? $row->created_at->format('Y-m-d H:i') : '',
            $row->type === 'INCOME' ? 'Pemasukan' : 'Pengeluaran',
            $row->category ? (($row->category->icon ? $row->category->icon . ' ' : '') . $row->category->name) : 'Lainnya',
            $row->description ?? '-',
            (float) $row->amount,
            (float) $row->balance_after,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'F' => '#,##0',
            'G' => '#,##0',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // Header styling
        $sheet->getStyle('A1:G1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E40AF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $lastRow = max(1, $this->totalRows + 1);

        // Center align No, Tanggal, and Tipe
        if ($lastRow > 1) {
            $sheet->getStyle("A2:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B2:B{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("C2:C{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("F2:G{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            
            // All borders
            $sheet->getStyle("A1:G{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }

        return [];
    }
}
