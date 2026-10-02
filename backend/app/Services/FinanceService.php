<?php

namespace App\Services;

use App\Models\Category;
use App\Models\FinancialGoal;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
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
        $user = User::firstOrCreate(
            ['jid' => $jid],
            ['display_name' => $displayName, 'current_balance' => 0]
        );
        $this->getOrCreateDefaultWallet($user);
        return $user;
    }

    /**
     * Ensure the user has at least one default wallet (Cash).
     */
    public function getOrCreateDefaultWallet(User $user): Wallet
    {
        $wallet = Wallet::where('user_jid', $user->jid)->where('is_default', true)->first();
        if (!$wallet) {
            $wallet = Wallet::where('user_jid', $user->jid)->where('name', 'Cash')->first();
        }
        if (!$wallet) {
            $wallet = Wallet::create([
                'user_jid'   => $user->jid,
                'name'       => 'Cash',
                'type'       => 'CASH',
                'is_default' => true,
            ]);
            $wallet->balance = $user->current_balance;
            $wallet->save();
        }
        return $wallet;
    }

    /**
     * Find a user's wallet by case-insensitive name.
     */
    public function findWalletByName(User $user, string $name): ?Wallet
    {
        $lower = strtolower(trim($name));
        return Wallet::where('user_jid', $user->jid)
            ->whereRaw('LOWER(name) = ?', [$lower])
            ->first();
    }

    /**
     * Create a new wallet with optional initial balance.
     */
    public function addWallet(User $user, string $name, int $initialBalance = 0, string $type = 'OTHER'): Wallet
    {
        $name = trim($name);
        $existing = $this->findWalletByName($user, $name);
        if ($existing) {
            throw new \RuntimeException("Dompet '{$name}' sudah ada.");
        }

        return DB::transaction(function () use ($user, $name, $initialBalance, $type) {
            $wallet = Wallet::create([
                'user_jid'   => $user->jid,
                'name'       => $name,
                'type'       => strtoupper($type),
                'is_default' => false,
            ]);
            $wallet->balance = $initialBalance;
            $wallet->save();

            if ($initialBalance > 0) {
                $freshUser = User::where('jid', $user->jid)->first();
                $newBal = $freshUser->current_balance + $initialBalance;
                $freshUser->update([
                    'current_balance'           => 0,
                    'encrypted_current_balance' => $this->encryption->encryptForStorage($newBal),
                ]);
                $user->current_balance = $newBal;
            }

            return $wallet;
        });
    }

    /**
     * Render multi-wallet balance card for "saldo" command.
     */
    public function renderWalletsCard(User $user): string
    {
        $wallets = Wallet::where('user_jid', $user->jid)->orderBy('created_at')->get();
        if ($wallets->isEmpty()) {
            $this->getOrCreateDefaultWallet($user);
            $wallets = Wallet::where('user_jid', $user->jid)->get();
        }

        $lines = ["💰 *Saldo Anda*\n"];
        $total = 0;
        foreach ($wallets as $w) {
            $bal = $w->balance;
            $total += $bal;
            $formatted = 'Rp' . number_format($bal, 0, ',', '.');
            $lines[] = sprintf("%-14s %s", $w->name, $formatted);
        }
        $lines[] = "-------------------------";
        $totalFormatted = 'Rp' . number_format($total, 0, ',', '.');
        $lines[] = "*Total*       *{$totalFormatted}*";

        return implode("\n", $lines);
    }

    // ---------------------------------------------------------------------------
    // Transaction Recording — FR-006, FR-007
    // ---------------------------------------------------------------------------

    /**
     * Record a new financial transaction and atomically update wallet and user balances.
     *
     * @param  User  $user
     * @param  array{type: string, amount: int, description: string, category_id?: string|null, wallet_id?: string|null, wallet_name?: string|null} $data
     * @return Transaction
     */
    public function recordTransaction(User $user, array $data): Transaction
    {
        if (($data['amount'] ?? 0) <= 0) {
            throw new \InvalidArgumentException('Transaction amount must be a positive integer.');
        }

        return DB::transaction(function () use ($user, $data) {
            $freshUser = User::where('jid', $user->jid)->first();

            $amount = (int) $data['amount'];
            $type   = $data['type'];

            // Resolve wallet
            $targetWallet = null;
            if (!empty($data['wallet_id'])) {
                $targetWallet = Wallet::where('user_jid', $freshUser->jid)->where('id', $data['wallet_id'])->first();
            } elseif (!empty($data['wallet_name'])) {
                $targetWallet = $this->findWalletByName($freshUser, $data['wallet_name']);
            }
            if (!$targetWallet) {
                $targetWallet = $this->getOrCreateDefaultWallet($freshUser);
            }

            // Update wallet balance
            $newWalletBalance = $type === 'INCOME' ? $targetWallet->balance + $amount : $targetWallet->balance - $amount;
            $targetWallet->balance = $newWalletBalance;
            $targetWallet->save();

            // Calculate new consolidated balance
            $newBalance = $type === 'INCOME' ? $freshUser->current_balance + $amount : $freshUser->current_balance - $amount;

            // Persist transaction with balance snapshot
            $transaction = Transaction::create([
                'user_jid'                => $freshUser->jid,
                'category_id'             => $data['category_id'] ?? null,
                'wallet_id'               => $targetWallet->id,
                'type'                    => $type,
                'amount'                  => 0,
                'description'             => $data['description'] ?? '',
                'balance_after'           => 0,
                'encrypted_amount'        => $this->encryption->encryptForStorage($amount),
                'encrypted_balance_after' => $this->encryption->encryptForStorage($newBalance),
                'transaction_date'        => now(),
                'status'                  => 'ACTIVE',
            ]);

            // Update user balance atomically
            $freshUser->update(['current_balance' => 0, 'encrypted_current_balance' => $this->encryption->encryptForStorage($newBalance)]);

            // Sync caller instance
            $user->current_balance = $newBalance;

            return $transaction;
        });
    }

    /**
     * Transfer funds between two wallets.
     */
    public function transferFunds(User $user, string $fromWalletId, string $toWalletId, int $amount, string $description = ''): Transaction
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Transfer amount must be a positive integer.');
        }

        if ($fromWalletId === $toWalletId) {
            throw new \InvalidArgumentException('Source and destination wallets must be different.');
        }

        return DB::transaction(function () use ($user, $fromWalletId, $toWalletId, $amount, $description) {
            $source = Wallet::where('user_jid', $user->jid)->where('id', $fromWalletId)->lockForUpdate()->firstOrFail();
            $dest   = Wallet::where('user_jid', $user->jid)->where('id', $toWalletId)->lockForUpdate()->firstOrFail();

            if ($source->balance < $amount) {
                throw new \RuntimeException("Saldo {$source->name} tidak mencukupi (Rp" . number_format($source->balance, 0, ',', '.') . ").");
            }

            $source->balance = $source->balance - $amount;
            $source->save();

            $dest->balance = $dest->balance + $amount;
            $dest->save();

            $freshUser = User::where('jid', $user->jid)->first();
            $memo = !empty($description) ? $description : "Transfer dari {$source->name} ke {$dest->name}";

            return Transaction::create([
                'user_jid'                => $freshUser->jid,
                'wallet_id'               => $source->id,
                'to_wallet_id'            => $dest->id,
                'category_id'             => null,
                'type'                    => 'TRANSFER',
                'amount'                  => 0,
                'description'             => $memo,
                'balance_after'           => 0,
                'encrypted_amount'        => $this->encryption->encryptForStorage($amount),
                'encrypted_balance_after' => $this->encryption->encryptForStorage($freshUser->current_balance),
                'transaction_date'        => now(),
                'status'                  => 'ACTIVE',
            ]);
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

            if ($latest->type === 'TRANSFER') {
                $newBalance = $user->current_balance;
                if ($latest->wallet_id) {
                    $src = Wallet::find($latest->wallet_id);
                    if ($src) {
                        $src->balance = $src->balance + $latest->amount;
                        $src->save();
                    }
                }
                if ($latest->to_wallet_id) {
                    $dst = Wallet::find($latest->to_wallet_id);
                    if ($dst) {
                        $dst->balance = $dst->balance - $latest->amount;
                        $dst->save();
                    }
                }
            } else {
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

                if ($latest->wallet_id) {
                    $w = Wallet::find($latest->wallet_id);
                    if ($w) {
                        if ($latest->type === 'EXPENSE') {
                            $w->balance = $w->balance + $latest->amount;
                        } elseif ($latest->type === 'INCOME') {
                            $w->balance = $w->balance - $latest->amount;
                        }
                        $w->save();
                    }
                }
            }

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

    // ---------------------------------------------------------------------------
    // Financial Goals (Target Tabungan)
    // ---------------------------------------------------------------------------

    public function createGoal(User $user, string $name, int $targetAmount, ?string $targetDate = null): FinancialGoal
    {
        if ($targetAmount <= 0) {
            throw new \InvalidArgumentException('Target nominal harus lebih dari 0.');
        }

        return FinancialGoal::create([
            'user_jid'                 => $user->jid,
            'name'                     => trim($name),
            'encrypted_target_amount'  => $this->encryption->encryptForStorage($targetAmount),
            'encrypted_current_amount' => $this->encryption->encryptForStorage(0),
            'target_date'              => $targetDate,
            'status'                   => 'ACTIVE',
        ]);
    }

    public function findGoalByNameOrId(User $user, string $identifier): ?FinancialGoal
    {
        $clean = trim($identifier);
        $isUuid = \Illuminate\Support\Str::isUuid($clean);

        return FinancialGoal::where('user_jid', $user->jid)
            ->where(function ($q) use ($clean, $isUuid) {
                if ($isUuid) {
                    $q->where('id', $clean)
                      ->orWhereRaw('LOWER(name) = ?', [strtolower($clean)]);
                } else {
                    $q->whereRaw('LOWER(name) = ?', [strtolower($clean)]);
                }
            })
            ->first();
    }

    public function contributeToGoal(User $user, FinancialGoal $goal, int $amount, ?string $walletIdOrName = null): array
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Nominal tabungan harus lebih dari 0.');
        }

        $targetWallet = null;
        if ($walletIdOrName) {
            if (\Illuminate\Support\Str::isUuid($walletIdOrName)) {
                $targetWallet = Wallet::where('user_jid', $user->jid)->where('id', $walletIdOrName)->first();
            }
            if (!$targetWallet) {
                $targetWallet = $this->findWalletByName($user, $walletIdOrName);
            }
        }
        if (!$targetWallet) {
            $targetWallet = $this->getOrCreateDefaultWallet($user);
        }

        if ($targetWallet->balance < $amount) {
            throw new \RuntimeException("Saldo {$targetWallet->name} tidak mencukupi (Rp" . number_format($targetWallet->balance, 0, ',', '.') . ").");
        }

        return DB::transaction(function () use ($user, $goal, $amount, $targetWallet) {
            $category = Category::where('user_jid', $user->jid)->where('name', 'Tabungan')->first();
            if (!$category) {
                $category = Category::whereNull('user_jid')->where('name', 'Tabungan')->first();
            }
            if (!$category) {
                $category = $this->addCustomCategory($user, 'Tabungan', 'EXPENSE');
            }

            $tx = $this->recordTransaction($user, [
                'type'        => 'EXPENSE',
                'amount'      => $amount,
                'description' => "Tabungan untuk {$goal->name}",
                'category_id' => $category->id,
                'wallet_id'   => $targetWallet->id,
            ]);

            $newCurrent = $goal->current_amount + $amount;
            $goal->current_amount = $newCurrent;
            if ($newCurrent >= $goal->target_amount) {
                $goal->status = 'COMPLETED';
            }
            $goal->save();

            $targetWallet->refresh();

            return [
                'goal'        => $goal,
                'transaction' => $tx,
                'wallet'      => $targetWallet,
                'amount'      => $amount,
            ];
        });
    }

    public function renderGoalCreatedCard(FinancialGoal $goal): string
    {
        $targetFmt = 'Rp' . number_format($goal->target_amount, 0, ',', '.');
        $currentFmt = 'Rp' . number_format($goal->current_amount, 0, ',', '.');
        $remaining = max(0, $goal->target_amount - $goal->current_amount);
        $remainingFmt = 'Rp' . number_format($remaining, 0, ',', '.');

        $bar = app(BudgetService::class)->renderProgressBar($goal->progress_percent);

        return "🎯 *Target Finansial Dibuat*\n" .
               "Target: {$goal->name}\n" .
               "Tujuan: {$targetFmt}\n" .
               "Terkumpul: {$currentFmt} (" . (int) round($goal->progress_percent) . "%)\n" .
               "Sisa: {$remainingFmt}\n" .
               $bar;
    }

    public function renderGoalContributionCard(array $result): string
    {
        /** @var FinancialGoal $goal */
        $goal = $result['goal'];
        /** @var Wallet $wallet */
        $wallet = $result['wallet'];
        $amount = $result['amount'];

        $targetFmt = 'Rp' . number_format($goal->target_amount, 0, ',', '.');
        $currentFmt = 'Rp' . number_format($goal->current_amount, 0, ',', '.');
        $addedFmt = '+Rp' . number_format($amount, 0, ',', '.');
        $remaining = max(0, $goal->target_amount - $goal->current_amount);
        $remainingFmt = 'Rp' . number_format($remaining, 0, ',', '.');

        $bar = app(BudgetService::class)->renderProgressBar($goal->progress_percent);

        $card = "🎯 *Target: {$goal->name}*\n" .
                "Target: {$targetFmt}\n" .
                "Terkumpul: {$currentFmt} ({$addedFmt})\n" .
                "Sisa: {$remainingFmt}\n" .
                "{$bar}\n\n" .
                "_Saldo {$wallet->name} berkurang Rp" . number_format($amount, 0, ',', '.') . " (Sisa: Rp" . number_format($wallet->balance, 0, ',', '.') . ")_";

        if ($goal->status === 'COMPLETED' || $goal->current_amount >= $goal->target_amount) {
            $card .= "\n\n🎉 *Selamat! Target Finansial Anda Telah Tercapai!* 🏆";
        }

        return $card;
    }

    public function renderGoalsListCard(User $user): string
    {
        $goals = FinancialGoal::where('user_jid', $user->jid)->orderBy('created_at')->get();

        if ($goals->isEmpty()) {
            return "🎯 *Daftar Target Finansial*\n\nBelum ada target finansial yang dibuat.\n\nContoh: `buat target Modal Apotek 100jt`";
        }

        $lines = ["🎯 *Daftar Target Finansial Anda*\n"];

        foreach ($goals as $goal) {
            $targetFmt = 'Rp' . number_format($goal->target_amount, 0, ',', '.');
            $currentFmt = 'Rp' . number_format($goal->current_amount, 0, ',', '.');
            $bar = app(BudgetService::class)->renderProgressBar($goal->progress_percent);
            $statusTag = $goal->status === 'COMPLETED' ? ' *(Tercapai! ✅)*' : '';

            $lines[] = "🎯 *{$goal->name}*{$statusTag}\n" .
                       "   Terkumpul: {$currentFmt} / {$targetFmt}\n" .
                       "   {$bar}";
        }

        $lines[] = "\n_Ketik \"tambah tabungan <nominal> untuk <nama>\" untuk menabung._";

        return implode("\n", $lines);
    }

    // ---------------------------------------------------------------------------
    // Freelancer & UMKM Tracking
    // ---------------------------------------------------------------------------

    public function getFreelanceSummary(User $user, ?int $year = null, ?int $month = null): array
    {
        $now = \Carbon\Carbon::now();
        $year = $year ?? $now->year;
        $month = $month ?? $now->month;

        $txs = Transaction::where('user_jid', $user->jid)
            ->forMonth($year, $month)
            ->active()
            ->incomes()
            ->get();

        $projectTotals = [];
        $totalIncome = 0;

        foreach ($txs as $tx) {
            $desc = $tx->description;
            if (preg_match('/(?:project|proyek)\s+([^,;.]+)/i', $desc, $m)) {
                $projName = trim($m[1]);
                $projectTotals[$projName] = ($projectTotals[$projName] ?? 0) + $tx->amount;
                $totalIncome += $tx->amount;
            } elseif (str_contains(strtolower($desc), 'freelance')) {
                $projName = trim(preg_replace('/freelance/i', '', $desc)) ?: 'Freelance';
                $projectTotals[$projName] = ($projectTotals[$projName] ?? 0) + $tx->amount;
                $totalIncome += $tx->amount;
            }
        }

        return [
            'total_income'   => $totalIncome,
            'project_totals' => $projectTotals,
        ];
    }

    public function renderFreelanceSummaryCard(User $user): string
    {
        $data = $this->getFreelanceSummary($user);

        if ($data['total_income'] <= 0 && empty($data['project_totals'])) {
            return "💻 *Pendapatan Freelance*\n\nBelum ada transaksi freelance atau project di bulan ini.\n\nContoh: `masuk 2jt project Website Client A`";
        }

        $totalFmt = 'Rp' . number_format($data['total_income'], 0, ',', '.');
        $lines = [
            "💻 *Pendapatan Freelance " . \Carbon\Carbon::now()->format('F Y') . "*",
            "Total: {$totalFmt}\n",
            "Rincian Project:",
        ];

        foreach ($data['project_totals'] as $name => $amount) {
            $amtFmt = 'Rp' . number_format($amount, 0, ',', '.');
            $lines[] = "• {$name}: {$amtFmt}";
        }

        return implode("\n", $lines);
    }

    public function getUmkmSummary(User $user, ?string $date = null): array
    {
        $targetDate = $date ?? \Carbon\Carbon::now()->toDateString();

        $txs = Transaction::where('user_jid', $user->jid)
            ->whereDate('transaction_date', $targetDate)
            ->active()
            ->get();

        $omzet = 0;
        $modal = 0;

        foreach ($txs as $tx) {
            if ($tx->type === 'INCOME') {
                $omzet += $tx->amount;
            } elseif ($tx->type === 'EXPENSE') {
                $modal += $tx->amount;
            }
        }

        $labaKotor = $omzet - $modal;

        return [
            'omzet'      => $omzet,
            'modal'      => $modal,
            'laba_kotor' => $labaKotor,
        ];
    }

    public function renderUmkmSummaryCard(User $user): string
    {
        $summary = $this->getUmkmSummary($user);

        $omzetFmt = 'Rp' . number_format($summary['omzet'], 0, ',', '.');
        $modalFmt = 'Rp' . number_format($summary['modal'], 0, ',', '.');
        $labaFmt  = 'Rp' . number_format($summary['laba_kotor'], 0, ',', '.');

        return "📈 *Penjualan Hari Ini*\n" .
               "Omzet (Penjualan): {$omzetFmt}\n" .
               "Modal (HPP): {$modalFmt}\n" .
               "-------------------------------\n" .
               "*Estimasi Laba Kotor: {$labaFmt}*";
    }
}
