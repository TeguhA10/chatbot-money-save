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
    private array $filters;
    private int $rowNumber = 0;
    private int $totalRows = 0;

    public function __construct(User $user, string $yearMonth, array $filters = [])
    {
        $this->user = $user;
        $this->yearMonth = $yearMonth;
        $this->filters = $filters;
    }

    public function title(): string
    {
        return Carbon::parse($this->yearMonth . '-01')->format('M Y');
    }

    public function collection(): Collection
    {
        [$year, $month] = explode('-', $this->yearMonth);

        $start = Carbon::create((int) $year, (int) $month, 1, 0, 0, 0)->startOfMonth();
        $end   = Carbon::create((int) $year, (int) $month, 1, 0, 0, 0)->endOfMonth();

        if (!empty($this->filters['start_date']) && Carbon::parse($this->filters['start_date'])->gt($start)) {
            $start = Carbon::parse($this->filters['start_date'])->startOfDay();
        }
        if (!empty($this->filters['end_date']) && Carbon::parse($this->filters['end_date'])->lt($end)) {
            $end = Carbon::parse($this->filters['end_date'])->endOfDay();
        }

        $query = $this->user->transactions()
            ->active()
            ->whereBetween('created_at', [$start, $end])
            ->with(['wallet', 'toWallet']);

        if (!empty($this->filters['wallet_id'])) {
            $walletId = $this->filters['wallet_id'];
            $query->where(function ($q) use ($walletId) {
                $q->where('wallet_id', $walletId)
                  ->orWhere('to_wallet_id', $walletId);
            });
        }

        if (!empty($this->filters['type']) && in_array(strtoupper($this->filters['type']), ['INCOME', 'EXPENSE', 'TRANSFER'], true)) {
            $query->where('type', strtoupper($this->filters['type']));
        }

        $transactions = $query->orderBy('created_at', 'asc')->get();

        $this->totalRows = $transactions->count();

        return $transactions;
    }

    public function headings(): array
    {
        return [
            'No',
            'Tanggal & Waktu',
            'Tipe',
            'Keterangan',
            'Dompet Asal',
            'Dompet Tujuan',
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

        $typeLabel = match ($row->type) {
            'INCOME'   => 'Pemasukan',
            'EXPENSE'  => 'Pengeluaran',
            'TRANSFER' => 'Transfer',
            default    => $row->type,
        };

        $fromWallet = $row->wallet?->name ?? 'Cash';
        $toWallet   = $row->type === 'TRANSFER' ? ($row->toWallet?->name ?? '-') : '-';

        return [
            $this->rowNumber,
            $row->created_at ? $row->created_at->format('Y-m-d H:i') : '',
            $typeLabel,
            $row->description !== '' ? $row->description : '-',
            $fromWallet,
            $toWallet,
            (float) $row->amount,
            (float) $row->balance_after,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'G' => '#,##0',
            'H' => '#,##0',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // Header styling
        $sheet->getStyle('A1:H1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E40AF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $lastRow = max(1, $this->totalRows + 1);

        // Center align No, Tanggal, Tipe, Dompet
        if ($lastRow > 1) {
            $sheet->getStyle("A2:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B2:B{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("C2:C{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("E2:F{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("G2:H{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            
            // All borders
            $sheet->getStyle("A1:H{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }

        return [];
    }
}
