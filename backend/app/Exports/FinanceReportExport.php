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
    private array $filters;

    public function __construct(User $user, ?int $year = null, array $filters = [])
    {
        $this->user = $user;
        $this->year = $year ?? (int) now()->year;
        $this->filters = $filters;

        if (!empty($filters['year'])) {
            $this->year = (int) $filters['year'];
        } elseif (!empty($filters['start_date'])) {
            $this->year = (int) Carbon::parse($filters['start_date'])->year;
        }
    }

    /**
     * @return array<int, \Maatwebsite\Excel\Concerns\WithTitle>
     */
    public function sheets(): array
    {
        $sheets = [];

        // Sheet 1: Executive Dashboard
        $sheets[] = new SummaryDashboardSheet($this->user, $this->year);

        if (!empty($this->filters['month'])) {
            $sheets[] = new MonthlyTransactionsSheet($this->user, $this->filters['month'], $this->filters);
            return $sheets;
        }

        $startOfYear = Carbon::create($this->year, 1, 1, 0, 0, 0)->startOfYear();
        $endOfYear   = Carbon::create($this->year, 12, 31, 23, 59, 59)->endOfYear();

        if (!empty($this->filters['start_date']) && Carbon::parse($this->filters['start_date'])->gt($startOfYear)) {
            $startOfYear = Carbon::parse($this->filters['start_date'])->startOfDay();
        }
        if (!empty($this->filters['end_date']) && Carbon::parse($this->filters['end_date'])->lt($endOfYear)) {
            $endOfYear = Carbon::parse($this->filters['end_date'])->endOfDay();
        }

        // Extract distinct YYYY-MM efficiently without hydrating full models
        $query = $this->user->transactions()
            ->active()
            ->whereBetween('created_at', [$startOfYear, $endOfYear]);

        if (!empty($this->filters['wallet_id'])) {
            $walletId = $this->filters['wallet_id'];
            $query->where(function ($q) use ($walletId) {
                $q->where('wallet_id', $walletId)
                  ->orWhere('to_wallet_id', $walletId);
            });
        }

        if (!empty($this->filters['type'])) {
            $query->where('type', strtoupper($this->filters['type']));
        }

        $months = $query->orderBy('created_at', 'asc')
            ->pluck('created_at')
            ->map(fn($d) => $d ? Carbon::parse($d)->format('Y-m') : null)
            ->filter()
            ->unique()
            ->values();

        if ($months->isEmpty()) {
            $currentMonth = Carbon::createFromDate($this->year, now()->month, 1)->format('Y-m');
            $sheets[] = new MonthlyTransactionsSheet($this->user, $currentMonth, $this->filters);
        } else {
            foreach ($months as $ym) {
                $sheets[] = new MonthlyTransactionsSheet($this->user, $ym, $this->filters);
            }
        }

        return $sheets;
    }
}
