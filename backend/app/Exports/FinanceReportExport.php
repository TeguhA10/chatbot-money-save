<?php

namespace App\Exports;

use App\Models\User;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class FinanceReportExport implements WithMultipleSheets, Export
{
    use Exportable;

    private User $user;
    private ?int $year;

    public function __construct(User $user, ?int $year = null)
    {
        $this->user = $user;
        $this->year = $year ?? (int) now()->year;
    }

    public function sheets(): array
    {
        $sheets = [];

        // Sheet 1: Executive Dashboard
        $sheets[] = new SummaryDashboardSheet($this->user, $this->year);

        // Fetch distinct months for active transactions
        $transactionsQuery = $this->user->transactions()
            ->active()
            ->whereYear('created_at', $this->year)
            ->orderBy('created_at', 'asc');

        // Extract distinct YYYY-MM
        $months = $transactionsQuery->get()
            ->map(fn($t) => $t->created_at ? $t->created_at->format('Y-m') : null)
            ->filter()
            ->unique()
            ->values();

        if ($months->isEmpty()) {
            // Include at least the current month tab
            $currentMonth = Carbon::createFromDate($this->year, now()->month, 1)->format('Y-m');
            $sheets[] = new MonthlyTransactionsSheet($this->user, $currentMonth);
        } else {
            foreach ($months as $ym) {
                $sheets[] = new MonthlyTransactionsSheet($this->user, $ym);
            }
        }

        return $sheets;
    }
}
