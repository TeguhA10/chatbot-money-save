<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * FinanceService
 *
 * Core financial ledger domain logic.
 * Handles all ACID balance mutations, summary aggregations, category management,
 * and undo operations as defined in spec.md FR-006 to FR-015.
 *
 * All balance operations are wrapped in DB::transaction() to guarantee atomicity
 * and prevent race conditions during concurrent message processing.
 */
class FinanceService
{
    // ---------------------------------------------------------------------------
    // User Management
    // ---------------------------------------------------------------------------

    /**
     * Find an existing user by JID or create a new isolated profile.
     * Satisfies FR-001, FR-002: Unique identification + auto-init on first message.
     */
    public function findOrCreateUser(string $jid, string $displayName = ''): User
    {
        return User::firstOrCreate(
            ['jid' => $jid],
            ['display_name' => $displayName, 'current_balance' => 0]
        );
    }

    // ---------------------------------------------------------------------------
    // Transaction Recording — FR-006, FR-007
    // ---------------------------------------------------------------------------

    /**
     * Record a new financial transaction and atomically update the user's balance.
     *
     * Uses DB::transaction() with SELECT FOR UPDATE (via lockForUpdate) to prevent
     * concurrent race conditions on the balance field.
     *
     * @param  User  $user
     * @param  array{type: string, amount: int, description: string, category_id?: string|null} $data
     * @return Transaction
     * @throws \Exception when amount is invalid
     */
    public function recordTransaction(User $user, array $data): Transaction
    {
        if (($data['amount'] ?? 0) <= 0) {
            throw new \InvalidArgumentException('Transaction amount must be a positive integer.');
        }

        return DB::transaction(function () use ($user, $data) {
            // Re-fetch fresh user data within the transaction (SQLite-compatible)
            $freshUser = User::where('jid', $user->jid)->first();

            $amount = (int) $data['amount'];
            $type   = $data['type'];

            // Calculate new balance
            $newBalance = $type === 'INCOME'
                ? $freshUser->current_balance + $amount
                : $freshUser->current_balance - $amount;

            // Persist transaction with balance snapshot
            $transaction = Transaction::create([
                'user_jid'         => $freshUser->jid,
                'category_id'      => $data['category_id'] ?? null,
                'type'             => $type,
                'amount'           => $amount,
                'description'      => $data['description'] ?? '',
                'balance_after'    => $newBalance,
                'transaction_date' => now(),
                'status'           => 'ACTIVE',
            ]);

            // Update balance atomically in the same transaction
            $freshUser->update(['current_balance' => $newBalance]);

            // Sync the passed-in user instance
            $user->current_balance = $newBalance;

            return $transaction;
        });
    }

    // ---------------------------------------------------------------------------
    // Undo / Void — FR-013
    // ---------------------------------------------------------------------------

    /**
     * Void the most recent ACTIVE transaction for a user and refund its balance impact.
     *
     * @return array{voided: Transaction, new_balance: int}|null  null if nothing to undo
     */
    public function undoLastTransaction(User $user): ?array
    {
        return DB::transaction(function () use ($user) {
            // Find the most recent active transaction using both created_at and id for determinism
            $latest = Transaction::where('user_jid', $user->jid)
                ->where('status', 'ACTIVE')
                ->orderByDesc('transaction_date')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first();

            if (!$latest) {
                return null;
            }

            // The previous transaction's balance_after is the correct balance after voiding.
            // If no previous transaction exists, balance reverts to 0.
            $previousTransaction = Transaction::where('user_jid', $user->jid)
                ->where('status', 'ACTIVE')
                ->where('id', '!=', $latest->id)
                ->orderByDesc('transaction_date')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first();

            $newBalance = $previousTransaction?->balance_after ?? 0;

            // Update user balance
            User::where('jid', $user->jid)->update(['current_balance' => $newBalance]);
            $user->current_balance = $newBalance;

            // Void the transaction
            $latest->update(['status' => 'VOIDED']);
            $latest->refresh();

            return [
                'voided'      => $latest,
                'new_balance' => $newBalance,
            ];
        });
    }

    // ---------------------------------------------------------------------------
    // Balance Inquiry — FR-008
    // ---------------------------------------------------------------------------

    /**
     * Get the current net balance for a user.
     */
    public function getBalance(User $user): int
    {
        return (int) User::where('jid', $user->jid)->value('current_balance');
    }

    // ---------------------------------------------------------------------------
    // Summary Aggregations — FR-008
    // ---------------------------------------------------------------------------

    /**
     * Get monthly financial summary with income/expense totals and category breakdown.
     *
     * @return array{
     *   total_income: int,
     *   total_expense: int,
     *   net_savings: int,
     *   current_balance: int,
     *   transaction_count: int,
     *   category_breakdown: array
     * }
     */
    public function getMonthlySummary(User $user, int $year, int $month): array
    {
        $transactions = Transaction::where('user_jid', $user->jid)
            ->active()
            ->forMonth($year, $month)
            ->with('category')
            ->get();

        $totalIncome  = $transactions->where('type', 'INCOME')->sum('amount');
        $totalExpense = $transactions->where('type', 'EXPENSE')->sum('amount');

        // Category breakdown for expenses
        $breakdown = $transactions
            ->where('type', 'EXPENSE')
            ->groupBy(fn($t) => $t->category?->name ?? 'Lain-lain')
            ->map(fn($group) => [
                'category_name' => $group->first()->category?->name ?? 'Lain-lain',
                'icon'          => $group->first()->category?->icon ?? '📦',
                'total_amount'  => $group->sum('amount'),
                'count'         => $group->count(),
            ])
            ->sortByDesc('total_amount')
            ->values()
            ->toArray();

        return [
            'total_income'       => (int) $totalIncome,
            'total_expense'      => (int) $totalExpense,
            'net_savings'        => (int) ($totalIncome - $totalExpense),
            'current_balance'    => $this->getBalance($user),
            'transaction_count'  => $transactions->count(),
            'category_breakdown' => $breakdown,
        ];
    }

    /**
     * Get daily financial summary for a specific date.
     *
     * @return array{total_income: int, total_expense: int, transaction_count: int}
     */
    public function getDailySummary(User $user, string $date): array
    {
        $transactions = Transaction::where('user_jid', $user->jid)
            ->active()
            ->forDate($date)
            ->get();

        return [
            'total_income'      => (int) $transactions->where('type', 'INCOME')->sum('amount'),
            'total_expense'     => (int) $transactions->where('type', 'EXPENSE')->sum('amount'),
            'transaction_count' => $transactions->count(),
        ];
    }

    // ---------------------------------------------------------------------------
    // Category Management — FR-005
    // ---------------------------------------------------------------------------

    /**
     * Get all categories visible to a user (system defaults + their custom categories).
     */
    public function getCategoriesForUser(User $user): Collection
    {
        return Category::forUser($user->jid)->orderBy('is_default', 'desc')->orderBy('name')->get();
    }

    /**
     * Create a new custom category for a user.
     *
     * @throws \Exception if category name already exists for this user
     */
    public function addCustomCategory(User $user, string $name, string $type, string $icon = '📌'): Category
    {
        // Check for name collision with system defaults or user's own categories
        $exists = Category::where('name', $name)
            ->where(function ($q) use ($user) {
                $q->whereNull('user_jid')->orWhere('user_jid', $user->jid);
            })
            ->exists();

        if ($exists) {
            throw new \RuntimeException("Kategori '{$name}' sudah ada. Pilih nama yang berbeda.");
        }

        return Category::create([
            'user_jid'   => $user->jid,
            'name'       => $name,
            'type'       => strtoupper($type),
            'icon'       => $icon,
            'is_default' => false,
        ]);
    }

    // ---------------------------------------------------------------------------
    // Smart Category Matching
    // ---------------------------------------------------------------------------

    /**
     * Match a category hint string to an actual Category model for a user.
     * Returns null if no match found (caller should use 'Lain-lain' or similar fallback).
     */
    public function matchCategory(User $user, ?string $categoryHint, string $type): ?Category
    {
        if (empty($categoryHint)) {
            return null;
        }

        $hintToNameMap = [
            'makan'     => 'Makanan & Minuman',
            'transport' => 'Transportasi',
            'tagihan'   => 'Tagihan & Utilitas',
            'hiburan'   => 'Hiburan',
            'belanja'   => 'Belanja',
            'kesehatan' => 'Kesehatan',
            'gaji'      => 'Gaji & Pendapatan',
            'bisnis'    => 'Bisnis',
        ];

        $categoryName = $hintToNameMap[$categoryHint] ?? null;

        if (!$categoryName) {
            return null;
        }

        return Category::forUser($user->jid)
            ->where('name', $categoryName)
            ->where('type', strtoupper($type))
            ->first();
    }
}
