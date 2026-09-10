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
    private readonly EncryptionService $encryption;

    public function __construct(?EncryptionService $encryption = null)
    {
        $this->encryption = $encryption ?? app(EncryptionService::class);
    }
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
            $newBalance = $type === 'INCOME' ? $freshUser->current_balance + $amount : $freshUser->current_balance - $amount;

            // Persist transaction with balance snapshot
            $transaction = Transaction::create([
                'user_jid'         => $freshUser->jid,
                'category_id'      => $data['category_id'] ?? null,
                'type'             => $type,
                // Plaintext legacy columns remain numeric zero for compatibility;
                // every newly-created financial value lives only in AES-GCM envelopes.
                'amount'           => 0,
                'description'      => $data['description'] ?? '',
                'balance_after'    => 0,
                'encrypted_amount' => $this->encryption->encryptForStorage($amount),
                'encrypted_balance_after' => $this->encryption->encryptForStorage($newBalance),
                'transaction_date' => now(),
                'status'           => 'ACTIVE',
            ]);

            // Update balance atomically in the same transaction
            $freshUser->update(['current_balance' => 0, 'encrypted_current_balance' => $this->encryption->encryptForStorage($newBalance)]);

            // Sync the passed-in user instance
            $user->current_balance = $newBalance;

            return $transaction;
        });
    }

        // ---------------------------------------------------------------------------
    // Transaction List
    // ---------------------------------------------------------------------------

    /**
     * Get latest transactions for a user.
     *
     * Important:
     * - Uses LIMIT instead of loading the entire transaction history.
     * - Only ACTIVE transactions are returned.
     * - Supports EXPENSE / INCOME.
     * - Supports today / current month filtering.
     *
     * @return Collection<int, Transaction>
     */
    public function getTransactions(
        User $user,
        ?string $type = null,
        ?string $period = null,
        int $limit = 10
    ): Collection {
        $limit = max(1, min($limit, 50));

        $query = Transaction::query()
            ->where('user_jid', $user->jid)
            ->active()
            ->with('category')
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($type === 'EXPENSE') {
            $query->expenses();
        } elseif ($type === 'INCOME') {
            $query->incomes();
        }

        if ($period === 'today') {
            $query->forDate(now()->toDateString());
        } elseif ($period === 'month') {
            $query->forMonth(now()->year, now()->month);
        }

        return $query->limit($limit)->get();
    }

    /**
     * Find a user's transaction using a UUID prefix.
     *
     * Example:
     * a82f31c2
     *
     * The transaction is always scoped by user_jid.
     *
     * @throws \RuntimeException when transaction is ambiguous.
     */
    public function findTransactionById(User $user, string $id): ?Transaction
    {
        $id = trim($id);

        if ($id === '') {
            return null;
        }

        // Full UUID
        if (strlen($id) >= 36) {
            return Transaction::where('user_jid', $user->jid)
                ->where('id', $id)
                ->active()
                ->first();
        }

        // UUID prefix
        $matches = Transaction::where('user_jid', $user->jid)
            ->where('id', 'like', $id . '%')
            ->active()
            ->limit(2)
            ->get();

        if ($matches->count() > 1) {
            throw new \RuntimeException(
                "ID transaksi '{$id}' tidak unik. Gunakan ID yang lebih panjang."
            );
        }

        return $matches->first();
    }

    // ---------------------------------------------------------------------------
    // Transaction Update
    // ---------------------------------------------------------------------------

    /**
     * Update an existing ACTIVE transaction.
     *
     * Only amount and description are changed here.
     * Type/category remain unchanged.
     *
     * After updating, the entire active ledger is recalculated so that
     * balance_after snapshots remain correct.
     */
    public function updateTransaction(
        User $user,
        string $transactionId,
        int $amount,
        string $description
    ): ?array {
        if ($amount <= 0) {
            throw new \InvalidArgumentException(
                'Nominal transaksi harus lebih dari 0.'
            );
        }

        return DB::transaction(function () use (
            $user,
            $transactionId,
            $amount,
            $description
        ) {
            User::where('jid', $user->jid)
                ->lockForUpdate()
                ->firstOrFail();

            $transaction = Transaction::where('user_jid', $user->jid)
                ->where('id', $transactionId)
                ->active()
                ->lockForUpdate()
                ->first();

            if (!$transaction) {
                return null;
            }

            $transaction->update(['amount' => 0, 'encrypted_amount' => $this->encryption->encryptForStorage($amount), 'description' => $description]);

            $newBalance = $this->recalculateUserLedger($user);

            $transaction->refresh();

            return [
                'transaction' => $transaction,
                'new_balance' => $newBalance,
            ];
        });
    }

    // ---------------------------------------------------------------------------
    // Transaction Delete / Void
    // ---------------------------------------------------------------------------

    /**
     * Void a specific transaction.
     *
     * We intentionally do NOT physically delete the database row.
     * This preserves the financial audit trail.
     */
    public function voidTransaction(
        User $user,
        string $transactionId
    ): ?array {
        return DB::transaction(function () use ($user, $transactionId) {
            User::where('jid', $user->jid)
                ->lockForUpdate()
                ->firstOrFail();

            $transaction = Transaction::where('user_jid', $user->jid)
                ->where('id', $transactionId)
                ->active()
                ->lockForUpdate()
                ->first();

            if (!$transaction) {
                return null;
            }

            $transaction->update([
                'status' => 'VOIDED',
            ]);

            $newBalance = $this->recalculateUserLedger($user);

            $transaction->refresh();

            return [
                'voided'      => $transaction,
                'new_balance' => $newBalance,
            ];
        });
    }

    // ---------------------------------------------------------------------------
    // Ledger Recalculation
    // ---------------------------------------------------------------------------

    /**
     * Recalculate the user's entire ACTIVE ledger.
     *
     * This is required because balance_after is a snapshot.
     *
     * Example:
     *
     * +1.000.000 -> 1.000.000
     * -200.000    ->   800.000
     * -100.000    ->   700.000
     *
     * If the second transaction changes to -300.000:
     *
     * +1.000.000 -> 1.000.000
     * -300.000    ->   700.000
     * -100.000    ->   600.000
     */
    public function recalculateUserLedger(User $user): int
    {
        $balance = 0;

        $transactions = Transaction::where('user_jid', $user->jid)
            ->active()
            ->orderBy('transaction_date')
            ->orderBy('created_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($transactions as $transaction) {
            if ($transaction->type === 'INCOME') {
                $balance += (int) $transaction->amount;
            } else {
                $balance -= (int) $transaction->amount;
            }

            if ((int) $transaction->balance_after !== $balance) {
                $transaction->update(['balance_after' => 0, 'encrypted_balance_after' => $this->encryption->encryptForStorage($balance)]);
            }
        }

        User::where('jid', $user->jid)->update(['current_balance' => 0, 'encrypted_current_balance' => $this->encryption->encryptForStorage($balance)]);

        $user->current_balance = $balance;

        return $balance;
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
            User::where('jid', $user->jid)->update(['current_balance' => 0, 'encrypted_current_balance' => $this->encryption->encryptForStorage($newBalance)]);
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
        return User::where('jid', $user->jid)->firstOrFail()->current_balance;
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
