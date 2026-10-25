<?php

namespace App\Services;


use App\Models\FinancialGoal;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
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
    public function findOrCreateUser(string $jid, string $displayName = '', ?string $phoneNumber = null, ?string $lid = null): User
    {
        $query = User::where('jid', $jid);
        if ($lid) {
            $query->orWhere('lid', $lid)->orWhere('jid', $lid);
        }
        if (str_ends_with($jid, '@lid')) {
            $query->orWhere('lid', $jid);
        }
        if ($phoneNumber) {
            $query->orWhere('phone_number', $phoneNumber);
        }
        if (str_ends_with($jid, '@s.whatsapp.net')) {
            $extractedPhone = explode('@', $jid)[0];
            $query->orWhere('phone_number', $extractedPhone);
        }

        $user = $query->first();

        if ($user) {
            $updates = [];
            if ($displayName && empty($user->display_name)) {
                $updates['display_name'] = $displayName;
            }
            if ($phoneNumber && empty($user->phone_number)) {
                $updates['phone_number'] = $phoneNumber;
            }
            if ($lid && empty($user->lid)) {
                $updates['lid'] = $lid;
            }
            if (str_ends_with($jid, '@lid') && empty($user->lid)) {
                $updates['lid'] = $jid;
            }
            if (str_ends_with($jid, '@s.whatsapp.net') && empty($user->phone_number)) {
                $updates['phone_number'] = explode('@', $jid)[0];
            }
            if (!empty($updates)) {
                $user->update($updates);
            }
            $this->getOrCreateDefaultWallet($user);
            return $user;
        }

        $isLid = str_ends_with($jid, '@lid');
        $phone = $phoneNumber ?? (str_ends_with($jid, '@s.whatsapp.net') ? explode('@', $jid)[0] : null);
        $userLid = $lid ?? ($isLid ? $jid : null);

        $user = User::create([
            'jid'             => $jid,
            'phone_number'    => $phone,
            'lid'             => $userLid,
            'display_name'    => $displayName,
            'current_balance' => 0,
        ]);
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
     * Find a user's wallet by case-insensitive name or fuzzy matching against existing wallets.
     */
    public function findWalletByName(User $user, string $name): ?Wallet
    {
        $clean = trim($name);
        if ($clean === '') {
            return null;
        }

        $exact = Wallet::where('user_jid', $user->jid)
            ->whereRaw('LOWER(name) = ?', [strtolower($clean)])
            ->first();

        if ($exact) {
            return $exact;
        }

        return app(FuzzyEntityMatcherService::class)->resolveWallet($user, $clean);
    }

    /**
     * Create a new wallet with optional initial balance.
     */
    public function addWallet(User $user, string $name, int $initialBalance = 0, string $type = 'OTHER'): Wallet
    {
        $name = trim($name);
        $existing = Wallet::where('user_jid', $user->jid)
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->first();
        if ($existing) {
            throw new \RuntimeException("Dompet '{$name}' sudah ada.");
        }

        // Fuzzy similarity check to guard against acoustic/voice mishearings or typo duplicates
        $similar = app(FuzzyEntityMatcherService::class)->checkSimilarWallet($user, $name, 0.70);
        if ($similar) {
            $matched = $similar['item'];
            $simPct = (int) round($similar['score'] * 100);
            throw new \RuntimeException("Dompet '{$matched->name}' sudah ada di akun Anda (kemiripan {$simPct}%). Silakan gunakan dompet yang sudah ada.");
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
     * Update an existing wallet's name, type, or balance.
     */
    public function updateWallet(User $user, string $walletId, array $data): Wallet
    {
        $wallet = Wallet::where('user_jid', $user->jid)
            ->where(function ($q) use ($walletId) {
                if (\Illuminate\Support\Str::isUuid($walletId)) {
                    $q->where('id', $walletId);
                } else {
                    $q->whereRaw('LOWER(name) = ?', [strtolower(trim($walletId))]);
                }
            })
            ->first();

        if (!$wallet) {
            throw new \RuntimeException("Dompet tidak ditemukan.");
        }

        return DB::transaction(function () use ($user, $wallet, $data) {
            if (!empty($data['name'])) {
                $newName = trim($data['name']);
                $duplicate = Wallet::where('user_jid', $user->jid)
                    ->where('id', '!=', $wallet->id)
                    ->whereRaw('LOWER(name) = ?', [strtolower($newName)])
                    ->exists();

                if ($duplicate) {
                    throw new \RuntimeException("Dompet dengan nama '{$newName}' sudah ada.");
                }
                $wallet->name = $newName;
            }

            if (!empty($data['type'])) {
                $wallet->type = strtoupper($data['type']);
            }

            if (array_key_exists('balance', $data) && $data['balance'] !== null) {
                $newBal = (int) $data['balance'];
                $oldBal = $wallet->balance;
                $diff = $newBal - $oldBal;

                if ($diff !== 0) {
                    $wallet->balance = $newBal;
                    $freshUser = User::where('jid', $user->jid)->first();
                    $newTotal = $freshUser->current_balance + $diff;
                    $freshUser->update([
                        'current_balance'           => 0,
                        'encrypted_current_balance' => $this->encryption->encryptForStorage($newTotal),
                    ]);
                    $user->current_balance = $newTotal;
                }
            }

            $wallet->save();
            return $wallet;
        });
    }

    /**
     * Delete a wallet, transferring any remaining balance and transactions to another wallet.
     */
    public function deleteWallet(User $user, string $walletId, ?string $transferToWalletId = null): void
    {
        $wallet = Wallet::where('user_jid', $user->jid)
            ->where(function ($q) use ($walletId) {
                if (\Illuminate\Support\Str::isUuid($walletId)) {
                    $q->where('id', $walletId);
                } else {
                    $q->whereRaw('LOWER(name) = ?', [strtolower(trim($walletId))]);
                }
            })
            ->first();

        if (!$wallet) {
            throw new \RuntimeException("Dompet tidak ditemukan.");
        }

        $totalWallets = Wallet::where('user_jid', $user->jid)->count();
        if ($totalWallets <= 1) {
            throw new \RuntimeException("Tidak dapat menghapus satu-satunya dompet utama Anda.");
        }

        if ($wallet->balance < 0) {
            throw new \RuntimeException("Tidak dapat menghapus dompet dengan saldo minus. Silakan lunasi saldo terlebih dahulu.");
        }

        DB::transaction(function () use ($user, $wallet, $transferToWalletId) {
            // Find target recipient wallet
            $targetWallet = null;
            if ($transferToWalletId) {
                $targetWallet = Wallet::where('user_jid', $user->jid)
                    ->where('id', '!=', $wallet->id)
                    ->where(function ($q) use ($transferToWalletId) {
                        if (\Illuminate\Support\Str::isUuid($transferToWalletId)) {
                            $q->where('id', $transferToWalletId);
                        } else {
                            $q->whereRaw('LOWER(name) = ?', [strtolower(trim($transferToWalletId))]);
                        }
                    })
                    ->first();
            }

            if (!$targetWallet) {
                $targetWallet = Wallet::where('user_jid', $user->jid)
                    ->where('id', '!=', $wallet->id)
                    ->where('is_default', true)
                    ->first()
                    ?? Wallet::where('user_jid', $user->jid)->where('id', '!=', $wallet->id)->first();
            }

            if (!$targetWallet) {
                throw new \RuntimeException("Tidak ada dompet lain untuk memindahkan transaksi/saldo.");
            }

            // Transfer balance if positive
            if ($wallet->balance > 0) {
                $targetWallet->balance += $wallet->balance;
                $targetWallet->save();
            }

            // Reassign default flag if this was the default wallet
            if ($wallet->is_default) {
                $targetWallet->is_default = true;
                $targetWallet->save();
            }

            // Reassign linked transactions
            Transaction::where('wallet_id', $wallet->id)->update(['wallet_id' => $targetWallet->id]);

            $wallet->delete();
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

        return Cache::lock("user_balance:{$user->jid}", 10)->block(5, function () use ($user, $data) {
            return DB::transaction(function () use ($user, $data) {
                $freshUser = User::where('jid', $user->jid)->lockForUpdate()->firstOrFail();

                $amount = (int) $data['amount'];
                $type   = $data['type'];

                // Resolve wallet
                $targetWallet = null;
                if (!empty($data['wallet_id'])) {
                    $targetWallet = Wallet::where('user_jid', $freshUser->jid)->where('id', $data['wallet_id'])->lockForUpdate()->first();
                } elseif (!empty($data['wallet_name'])) {
                    $targetWallet = $this->findWalletByName($freshUser, $data['wallet_name']);
                    if ($targetWallet) {
                        $targetWallet = Wallet::where('id', $targetWallet->id)->lockForUpdate()->first();
                    }
                }
                if (!$targetWallet) {
                    $targetWallet = $this->getOrCreateDefaultWallet($freshUser);
                    $targetWallet = Wallet::where('id', $targetWallet->id)->lockForUpdate()->first();
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
                    'wallet_id'               => $targetWallet->id,
                    'type'                    => $type,
                    'amount'                  => $amount,
                    'description'             => $data['description'] ?? '',
                    'balance_after'           => $newBalance,
                    'encrypted_amount'        => $this->encryption->encryptForStorage($amount),
                    'encrypted_balance_after' => $this->encryption->encryptForStorage($newBalance),
                    'transaction_date'        => !empty($data['transaction_date']) ? \Carbon\Carbon::parse($data['transaction_date']) : now(),
                    'status'                  => 'ACTIVE',
                ]);

                // Update user balance atomically
                $freshUser->update([
                    'current_balance'           => $newBalance,
                    'encrypted_current_balance' => $this->encryption->encryptForStorage($newBalance),
                ]);

                Cache::forget("dashboard_feed:{$freshUser->jid}:" . now()->format('Y-m'));

                // Sync caller instance
                $user->current_balance = $newBalance;

                return $transaction;
            });
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

        return Cache::lock("user_balance:{$user->jid}", 10)->block(5, function () use ($user, $fromWalletId, $toWalletId, $amount, $description) {
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

                $tx = Transaction::create([
                    'user_jid'                => $freshUser->jid,
                    'wallet_id'               => $source->id,
                    'to_wallet_id'            => $dest->id,
                    'type'                    => 'TRANSFER',
                    'amount'                  => $amount,
                    'description'             => $memo,
                    'balance_after'           => $freshUser->current_balance,
                    'encrypted_amount'        => $this->encryption->encryptForStorage($amount),
                    'encrypted_balance_after' => $this->encryption->encryptForStorage($freshUser->current_balance),
                    'transaction_date'        => now(),
                    'status'                  => 'ACTIVE',
                ]);

                Cache::forget("dashboard_feed:{$freshUser->jid}:" . now()->format('Y-m'));

                return $tx;
            });
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

        // 1. Exact match
        $exact = Transaction::where('user_jid', $user->jid)
            ->where('id', $id)
            ->active()
            ->first();

        if ($exact) {
            return $exact;
        }

        // 2. UUID suffix or prefix match
        $matches = Transaction::where('user_jid', $user->jid)
            ->where(function ($q) use ($id) {
                $q->where('id', 'like', '%' . $id)
                  ->orWhere('id', 'like', $id . '%');
            })
            ->active()
            ->limit(3)
            ->get();

        if ($matches->count() > 1) {
            // If one of them has exact suffix, prefer suffix
            $suffixMatches = $matches->filter(fn ($t) => str_ends_with(strtolower($t->id), strtolower($id)));
            if ($suffixMatches->count() === 1) {
                return $suffixMatches->first();
            }

            throw new \RuntimeException(
                "ID '{$id}' mirip dengan beberapa transaksi. Silakan gunakan nomor urut: contoh `hapus 1` atau `hapus 2`."
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
        string $description,
        ?string $newWalletId = null
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
            $description,
            $newWalletId
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

            $oldAmount = $transaction->amount;
            $diff = $amount - $oldAmount;

            $targetWallet = null;
            if ($newWalletId && $newWalletId !== $transaction->wallet_id) {
                $targetWallet = Wallet::where('user_jid', $user->jid)->where('id', $newWalletId)->lockForUpdate()->first();
            }

            if ($targetWallet) {
                // Revert effect on previous wallet
                if ($transaction->wallet_id) {
                    $oldWallet = Wallet::where('id', $transaction->wallet_id)->lockForUpdate()->first();
                    if ($oldWallet) {
                        if ($transaction->type === 'INCOME') {
                            $oldWallet->balance -= $oldAmount;
                        } elseif ($transaction->type === 'EXPENSE') {
                            $oldWallet->balance += $oldAmount;
                        }
                        $oldWallet->save();
                    }
                }
                // Apply on target wallet
                if ($transaction->type === 'INCOME') {
                    $targetWallet->balance += $amount;
                } elseif ($transaction->type === 'EXPENSE') {
                    $targetWallet->balance -= $amount;
                }
                $targetWallet->save();
                $transaction->wallet_id = $targetWallet->id;
            } elseif ($transaction->wallet_id && $diff !== 0) {
                $wallet = Wallet::where('id', $transaction->wallet_id)->lockForUpdate()->first();
                if ($wallet) {
                    if ($transaction->type === 'INCOME') {
                        $wallet->balance = $wallet->balance + $diff;
                    } elseif ($transaction->type === 'EXPENSE') {
                        $wallet->balance = $wallet->balance - $diff;
                    }
                    $wallet->save();
                }
            }

            $transaction->amount = $amount;
            $transaction->encrypted_amount = $this->encryption->encryptForStorage($amount);
            $transaction->description = $description;
            $transaction->save();

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

            if ($transaction->type === 'TRANSFER') {
                if ($transaction->wallet_id) {
                    $src = Wallet::where('id', $transaction->wallet_id)->lockForUpdate()->first();
                    if ($src) {
                        $src->balance = $src->balance + $transaction->amount;
                        $src->save();
                    }
                }
                if ($transaction->to_wallet_id) {
                    $dst = Wallet::where('id', $transaction->to_wallet_id)->lockForUpdate()->first();
                    if ($dst) {
                        $dst->balance = $dst->balance - $transaction->amount;
                        $dst->save();
                    }
                }
            } else {
                if ($transaction->wallet_id) {
                    $w = Wallet::where('id', $transaction->wallet_id)->lockForUpdate()->first();
                    if ($w) {
                        if ($transaction->type === 'INCOME') {
                            $w->balance = $w->balance - $transaction->amount;
                        } elseif ($transaction->type === 'EXPENSE') {
                            $w->balance = $w->balance + $transaction->amount;
                        }
                        $w->save();
                    }
                }
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
                $transaction->update(['balance_after' => $balance, 'encrypted_balance_after' => $this->encryption->encryptForStorage($balance)]);
            }
        }

        User::where('jid', $user->jid)->update(['current_balance' => $balance, 'encrypted_current_balance' => $this->encryption->encryptForStorage($balance)]);

        $user->current_balance = $balance;

        // Ensure single-wallet / default-wallet balance remains strictly consistent with running ledger balance
        $wallets = Wallet::where('user_jid', $user->jid)->get();
        if ($wallets->count() === 1 && $wallets->first()->is_default) {
            $defaultWallet = $wallets->first();
            if ($defaultWallet->balance !== $balance) {
                $defaultWallet->balance = $balance;
                $defaultWallet->save();
            }
        }

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
        return Cache::lock("user_balance:{$user->jid}", 10)->block(5, function () use ($user) {
            return DB::transaction(function () use ($user) {
                User::where('jid', $user->jid)->lockForUpdate()->firstOrFail();

                // Find the most recent active transaction using both created_at and id for determinism
                $latest = Transaction::where('user_jid', $user->jid)
                    ->where('status', 'ACTIVE')
                    ->orderByDesc('transaction_date')
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();

                if (!$latest) {
                    return null;
                }

                if ($latest->type === 'TRANSFER') {
                    $newBalance = $user->current_balance;
                    if ($latest->wallet_id) {
                        $src = Wallet::where('id', $latest->wallet_id)->lockForUpdate()->first();
                        if ($src) {
                            $src->balance = $src->balance + $latest->amount;
                            $src->save();
                        }
                    }
                    if ($latest->to_wallet_id) {
                        $dst = Wallet::where('id', $latest->to_wallet_id)->lockForUpdate()->first();
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
                User::where('jid', $user->jid)->update(['current_balance' => $newBalance, 'encrypted_current_balance' => $this->encryption->encryptForStorage($newBalance)]);
                $user->current_balance = $newBalance;

                // Void the transaction
                $latest->update(['status' => 'VOIDED']);
                $latest->refresh();

                return [
                    'voided'      => $latest,
                    'new_balance' => $newBalance,
                ];
            });
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
        $aggregates = Transaction::where('user_jid', $user->jid)
            ->active()
            ->forMonth($year, $month)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN type = 'INCOME' THEN amount ELSE 0 END), 0) as total_income,
                COALESCE(SUM(CASE WHEN type = 'EXPENSE' THEN amount ELSE 0 END), 0) as total_expense,
                COUNT(*) as transaction_count
            ")
            ->first();

        $totalIncome = (int) ($aggregates->total_income ?? 0);
        $totalExpense = (int) ($aggregates->total_expense ?? 0);
        $transactionCount = (int) ($aggregates->transaction_count ?? 0);

        // Fallback safety if any legacy transactions still had amount == 0 in raw database
        if ($transactionCount > 0) {
            $hasZeroAmounts = Transaction::where('user_jid', $user->jid)
                ->active()
                ->forMonth($year, $month)
                ->where('amount', 0)
                ->exists();

            if ($hasZeroAmounts) {
                $transactions = Transaction::where('user_jid', $user->jid)
                    ->active()
                    ->forMonth($year, $month)
                    ->get();

                $totalIncome = (int) $transactions->where('type', 'INCOME')->sum('amount');
                $totalExpense = (int) $transactions->where('type', 'EXPENSE')->sum('amount');
            }
        }

        return [
            'total_income'       => $totalIncome,
            'total_expense'      => $totalExpense,
            'net_savings'        => $totalIncome - $totalExpense,
            'current_balance'    => $this->getBalance($user),
            'transaction_count'  => $transactionCount,
            'category_breakdown' => [],
        ];
    }

    /**
     * Get daily financial summary for a specific date.
     *
     * @return array{total_income: int, total_expense: int, transaction_count: int}
     */
    public function getDailySummary(User $user, string $date): array
    {
        $aggregates = Transaction::where('user_jid', $user->jid)
            ->active()
            ->forDate($date)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN type = 'INCOME' THEN amount ELSE 0 END), 0) as total_income,
                COALESCE(SUM(CASE WHEN type = 'EXPENSE' THEN amount ELSE 0 END), 0) as total_expense,
                COUNT(*) as transaction_count
            ")
            ->first();

        $totalIncome = (int) ($aggregates->total_income ?? 0);
        $totalExpense = (int) ($aggregates->total_expense ?? 0);
        $transactionCount = (int) ($aggregates->transaction_count ?? 0);

        if ($transactionCount > 0 && $totalIncome === 0 && $totalExpense === 0) {
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

        return [
            'total_income'      => $totalIncome,
            'total_expense'     => $totalExpense,
            'transaction_count' => $transactionCount,
        ];
    }



    // ---------------------------------------------------------------------------
    // Financial Goals (Target Tabungan)
    // ---------------------------------------------------------------------------

    public function createGoal(User $user, string $name, int $targetAmount, ?string $targetDate = null): FinancialGoal
    {
        if ($targetAmount <= 0) {
            throw new \InvalidArgumentException('Target nominal harus lebih dari 0.');
        }

        $cleanName = trim($name);

        // Fuzzy similarity check against existing goals
        $similar = app(FuzzyEntityMatcherService::class)->checkSimilarGoal($user, $cleanName, 0.70);
        if ($similar) {
            $matched = $similar['item'];
            $simPct = (int) round($similar['score'] * 100);
            throw new \RuntimeException("Target tabungan '{$matched->name}' sudah ada (kemiripan {$simPct}%). Silakan gunakan target yang sudah ada.");
        }

        return FinancialGoal::create([
            'user_jid'                 => $user->jid,
            'name'                     => $cleanName,
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

        $exact = FinancialGoal::where('user_jid', $user->jid)
            ->where(function ($q) use ($clean, $isUuid) {
                if ($isUuid) {
                    $q->where('id', $clean)
                      ->orWhereRaw('LOWER(name) = ?', [strtolower($clean)]);
                } else {
                    $q->whereRaw('LOWER(name) = ?', [strtolower($clean)]);
                }
            })
            ->first();

        if ($exact) {
            return $exact;
        }

        return app(FuzzyEntityMatcherService::class)->resolveGoal($user, $clean);
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
            $tx = $this->recordTransaction($user, [
                'type'        => 'EXPENSE',
                'amount'      => $amount,
                'description' => "Tabungan untuk {$goal->name}",
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

    /**
     * Update an existing financial goal's name, target amount, or target date.
     */
    public function updateGoal(User $user, string $goalId, array $data): FinancialGoal
    {
        $goal = $this->findGoalByNameOrId($user, $goalId);

        if (!$goal) {
            throw new \RuntimeException("Target finansial tidak ditemukan.");
        }

        if (!empty($data['name'])) {
            $goal->name = trim($data['name']);
        }

        if (array_key_exists('target_amount', $data) && (int) $data['target_amount'] > 0) {
            $goal->encrypted_target_amount = $this->encryption->encryptForStorage((int) $data['target_amount']);
        }

        if (array_key_exists('target_date', $data)) {
            $goal->target_date = !empty($data['target_date']) ? \Carbon\Carbon::parse($data['target_date'])->toDateString() : null;
        }

        if ($goal->current_amount >= $goal->target_amount) {
            $goal->status = 'COMPLETED';
        } else {
            $goal->status = 'ACTIVE';
        }

        $goal->save();
        return $goal;
    }

    /**
     * Delete a financial goal with optional refund of accumulated funds back to a wallet.
     */
    public function deleteGoal(User $user, string $goalId, bool $refundToWallet = true, ?string $walletIdOrName = null): array
    {
        $goal = $this->findGoalByNameOrId($user, $goalId);

        if (!$goal) {
            throw new \RuntimeException("Target finansial tidak ditemukan.");
        }

        return DB::transaction(function () use ($user, $goal, $refundToWallet, $walletIdOrName) {
            $refundedAmount = 0;
            $targetWallet = null;

            if ($goal->current_amount > 0 && $refundToWallet) {
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

                $this->recordTransaction($user, [
                    'type'        => 'INCOME',
                    'amount'      => $goal->current_amount,
                    'description' => "Pengembalian dana target {$goal->name}",
                    'wallet_id'   => $targetWallet->id,
                ]);

                $refundedAmount = $goal->current_amount;
            }

            $goalName = $goal->name;
            $goal->delete();

            return [
                'success'         => true,
                'name'            => $goalName,
                'refunded_amount' => $refundedAmount,
                'wallet'          => $targetWallet,
            ];
        });
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
