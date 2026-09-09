<?php

namespace App\Services;

use App\Exports\FinanceReportExport;
use App\Models\User;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExcelExportService
{
    /**
     * Generate filename for the exported spreadsheet
     */
    public function generateFilename(User $user, ?int $year = null): string
    {
        $year = $year ?? (int) now()->year;
        $cleanPhone = preg_replace('/[^0-9]/', '', $user->jid);
        return "Laporan_Keuangan_{$cleanPhone}_{$year}.xlsx";
    }

    /**
     * Download the spreadsheet directly via HTTP response
     */
    public function download(User $user, ?int $year = null): BinaryFileResponse
    {
        $filename = $this->generateFilename($user, $year);
        return Excel::download(new FinanceReportExport($user, $year), $filename);
    }

    /**
     * Store the spreadsheet to disk and return relative path
     */
    public function store(User $user, ?int $year = null, string $disk = 'local'): string
    {
        $filename = 'exports/' . $this->generateFilename($user, $year);
        Excel::store(new FinanceReportExport($user, $year), $filename, $disk);
        return $filename;
    }
}
