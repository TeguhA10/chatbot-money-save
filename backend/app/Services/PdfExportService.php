<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * PdfExportService
 *
 * Generates official, bank-grade PDF financial statements (Rekening Koran)
 * with executive summary, per-wallet breakdown, and chronological transaction ledgers.
 */
class PdfExportService
{
    /**
     * Generate standard filename for the PDF export.
     */
    public function generateFilename(User $user, array $filters = []): string
    {
        $cleanPhone = preg_replace('/[^0-9]/', '', $user->phone_number ?: $user->jid);
        $timestamp  = now()->format('Ymd');
        $periodTag  = '';

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $periodTag = '_' . Carbon::parse($filters['start_date'])->format('Ymd') . '-' . Carbon::parse($filters['end_date'])->format('Ymd');
        } elseif (!empty($filters['month'])) {
            $periodTag = '_' . str_replace('-', '', $filters['month']);
        } elseif (!empty($filters['year'])) {
            $periodTag = '_' . $filters['year'];
        }

        return "Rekening_Koran_{$cleanPhone}{$periodTag}_{$timestamp}.pdf";
    }

    /**
     * Compile view data and generate DomPDF instance.
     */
    public function buildPdf(User $user, array $filters = [])
    {
        $query = Transaction::where('user_jid', $user->jid)
            ->active()
            ->with(['wallet', 'toWallet']);

        // Date Range Filtering
        $startDate = null;
        $endDate   = null;

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $startDate = Carbon::parse($filters['start_date'])->startOfDay();
            $endDate   = Carbon::parse($filters['end_date'])->endOfDay();
            $query->whereBetween('created_at', [$startDate, $endDate]);
            $periodLabel = $startDate->translatedFormat('d M Y') . ' — ' . $endDate->translatedFormat('d M Y');
        } elseif (!empty($filters['month'])) {
            $m = Carbon::parse($filters['month'] . '-01');
            $startDate = $m->copy()->startOfMonth();
            $endDate   = $m->copy()->endOfMonth();
            $query->whereBetween('created_at', [$startDate, $endDate]);
            $periodLabel = $m->translatedFormat('F Y');
        } elseif (!empty($filters['year'])) {
            $y = (int) $filters['year'];
            $startDate = Carbon::create($y, 1, 1)->startOfYear();
            $endDate   = Carbon::create($y, 12, 31)->endOfYear();
            $query->whereBetween('created_at', [$startDate, $endDate]);
            $periodLabel = "Tahun {$y}";
        } else {
            $periodLabel = "Seluruh Riwayat Transaksi";
        }

        // Wallet Filtering
        $filterLabels = [];
        if (!empty($filters['wallet_id'])) {
            $wallet = Wallet::where('user_jid', $user->jid)
                ->where(function ($q) use ($filters) {
                    $q->where('id', $filters['wallet_id'])
                      ->orWhereRaw('LOWER(name) = ?', [strtolower($filters['wallet_id'])]);
                })->first();

            if ($wallet) {
                $query->where(function ($q) use ($wallet) {
                    $q->where('wallet_id', $wallet->id)
                      ->orWhere('to_wallet_id', $wallet->id);
                });
                $filterLabels[] = "Dompet: {$wallet->name}";
            }
        }

        // Type Filtering (INCOME, EXPENSE, TRANSFER)
        if (!empty($filters['type']) && in_array(strtoupper($filters['type']), ['INCOME', 'EXPENSE', 'TRANSFER'], true)) {
            $typeUpper = strtoupper($filters['type']);
            $query->where('type', $typeUpper);
            $filterLabels[] = "Tipe: " . match($typeUpper) {
                'INCOME'  => 'Pemasukan',
                'EXPENSE' => 'Pengeluaran',
                'TRANSFER'=> 'Transfer',
                default   => $typeUpper,
            };
        }

        $filterLabel = empty($filterLabels) ? 'Semua Dompet' : implode(', ', $filterLabels);

        // Retrieve transactions ordered chronologically
        $transactions = $query->orderBy('created_at', 'asc')->get();

        // Calculate Totals
        $totalIncome  = 0;
        $totalExpense = 0;

        foreach ($transactions as $tx) {
            if ($tx->type === 'INCOME') {
                $totalIncome += $tx->amount;
            } elseif ($tx->type === 'EXPENSE') {
                $totalExpense += $tx->amount;
            }
        }

        $netCashflow = $totalIncome - $totalExpense;

        // Calculate Opening Balance (sum of mutations prior to startDate)
        $openingBalance = 0;
        if ($startDate) {
            $priorIncome = Transaction::where('user_jid', $user->jid)
                ->active()
                ->where('created_at', '<', $startDate)
                ->where('type', 'INCOME')
                ->sum('amount');

            $priorExpense = Transaction::where('user_jid', $user->jid)
                ->active()
                ->where('created_at', '<', $startDate)
                ->where('type', 'EXPENSE')
                ->sum('amount');

            $openingBalance = $priorIncome - $priorExpense;
        }

        $closingBalance = $openingBalance + $netCashflow;

        // Map transactions for view
        $mappedTransactions = $transactions->map(function ($tx) {
            return [
                'date'        => $tx->created_at ? $tx->created_at->format('d/m/Y H:i') : '-',
                'description' => $tx->description ?: ($tx->type === 'INCOME' ? 'Pemasukan' : 'Pengeluaran'),
                'wallet'      => $tx->wallet?->name ?? 'Cash',
                'to_wallet'   => $tx->toWallet?->name,
                'type'        => $tx->type,
                'amount'      => $tx->amount,
            ];
        });

        // Unique Statement Metadata
        $cleanJid = preg_replace('/[^0-9]/', '', $user->jid);
        $statementNumber = 'HW-STMT-' . now()->format('Ym') . '-' . strtoupper(substr(md5($cleanJid . ($startDate?->timestamp ?? 0)), 0, 6));
        $generatedAt = now()->translatedFormat('d F Y, H:i') . ' WIB';
        $userPhone   = '+' . ($user->phone_number ?: $cleanJid);
        $userTier    = $user->hasActivePremium() ? '⭐ PRO VERIFIED' : 'STANDAR';
        $securityHash= strtoupper(hash('crc32b', $statementNumber . $user->jid . $closingBalance));

        $data = [
            'statementNumber'    => $statementNumber,
            'generatedAt'        => $generatedAt,
            'userName'           => $user->display_name ?: $userPhone,
            'userPhone'          => $userPhone,
            'userTier'           => $userTier,
            'periodLabel'        => $periodLabel,
            'filterLabel'        => $filterLabel,
            'openingBalance'     => (int) $openingBalance,
            'totalIncome'        => (int) $totalIncome,
            'totalExpense'       => (int) $totalExpense,
            'netCashflow'        => (int) $netCashflow,
            'closingBalance'     => (int) $closingBalance,
            'transactions'       => $mappedTransactions,
            'securityHash'       => $securityHash,
        ];

        return Pdf::loadView('pdf.financial-statement', $data)
            ->setPaper('a4', 'portrait');
    }

    /**
     * Download the PDF document directly via HTTP response.
     */
    public function download(User $user, array $filters = []): Response
    {
        $filename = $this->generateFilename($user, $filters);
        $pdf = $this->buildPdf($user, $filters);

        return $pdf->download($filename);
    }

    /**
     * Stream the PDF document in browser.
     */
    public function stream(User $user, array $filters = []): Response
    {
        $filename = $this->generateFilename($user, $filters);
        $pdf = $this->buildPdf($user, $filters);

        return $pdf->stream($filename);
    }

    /**
     * Render raw PDF binary string for WhatsApp or storage saving.
     */
    public function output(User $user, array $filters = []): string
    {
        $pdf = $this->buildPdf($user, $filters);
        return $pdf->output();
    }
}
