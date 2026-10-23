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
    public function generateFilename(User $user, ?int $year = null, array $filters = []): string
    {
        $year = $year ?? (int) now()->year;
        $cleanPhone = preg_replace('/[^0-9]/', '', $user->phone_number ?: $user->jid);
        $filterTag = '';
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $filterTag = '_' . str_replace('-', '', $filters['start_date']) . '-' . str_replace('-', '', $filters['end_date']);
        } elseif (!empty($filters['month'])) {
            $filterTag = '_' . str_replace('-', '', $filters['month']);
        }

        return "Laporan_Keuangan_{$cleanPhone}{$filterTag}_{$year}.xlsx";
    }

    /**
     * Download the spreadsheet directly via HTTP response
     */
    public function download(User $user, ?int $year = null, array $filters = []): BinaryFileResponse
    {
        $filename = $this->generateFilename($user, $year, $filters);
        return Excel::download(new FinanceReportExport($user, $year, $filters), $filename);
    }

    /**
     * Store the spreadsheet to disk and return relative path
     */
    public function store(User $user, ?int $year = null, string $disk = 'local', array $filters = []): string
    {
        $filename = 'exports/' . $this->generateFilename($user, $year, $filters);
        Excel::store(new FinanceReportExport($user, $year, $filters), $filename, $disk);
        return $filename;
    }
}
