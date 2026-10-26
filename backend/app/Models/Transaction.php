<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * App\Models\Transaction
 *
 * The core ACID ledger entry. Each row represents one financial event.
 * balance_after stores a snapshot of the user's balance AFTER this transaction
 * was applied, enabling O(1) balance lookups without summing the entire history.
 *
 * @property string       $id               UUID primary key
 * @property string       $user_jid         FK to users.jid
 * @property string       $type             'EXPENSE' | 'INCOME'
 * @property int          $amount           Positive integer Rupiah (never zero, never negative)
 * @property string       $description      Short human-readable label
 * @property int          $balance_after    User's balance immediately after this transaction
 * @property \Carbon\Carbon $transaction_date Actual date of the transaction
 * @property string       $status           'ACTIVE' | 'VOIDED'
 */
class Transaction extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_jid',
        'wallet_id',
        'to_wallet_id',
        'goal_id',
        'budget_id',
        'type',
        'amount',
        'description',
        'balance_after',
        'transaction_date',
        'status',
        'encrypted_amount', 'encrypted_balance_after', 'key_version',
    ];

    protected $casts = [
        'amount'           => 'integer',
        'balance_after'    => 'integer',
        'transaction_date' => 'datetime',
        'status'           => 'string',
        'key_version' => 'integer',
    ];

    protected $attributes = [
        'description' => '',
        'status'      => 'ACTIVE',
    ];

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    /**
     * The user who owns this transaction.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_jid', 'jid');
    }

    /**
     * The source / debited wallet (nullable).
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'wallet_id', 'id');
    }

    /**
     * The destination / credited wallet for TRANSFER (nullable).
     */
    public function toWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'to_wallet_id', 'id');
    }

    /**
     * The financial goal this transaction contributes to (nullable).
     */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(FinancialGoal::class, 'goal_id', 'id');
    }

    /**
     * The budget limit this transaction draws from (nullable).
     */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class, 'budget_id', 'id');
    }

    // -----------------------------------------------------------------------
    // Query Scopes
    // -----------------------------------------------------------------------

    /**
     * Scope: Only active (non-voided) transactions.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('transactions.status', 'ACTIVE');
    }

    /**
     * Scope: Only voided transactions.
     */
    public function scopeVoided(Builder $query): Builder
    {
        return $query->where('transactions.status', 'VOIDED');
    }

    /**
     * Scope: Transactions in a specific year-month (YYYY-MM format).
     */
    public function scopeForMonth(Builder $query, int $year, int $month): Builder
    {
        return $query->whereYear('transactions.transaction_date', $year)
                     ->whereMonth('transactions.transaction_date', $month);
    }

    /**
     * Scope: Transactions on a specific date.
     */
    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('transactions.transaction_date', $date);
    }

    /**
     * Scope: Only expense transactions.
     */
    public function scopeExpenses(Builder $query): Builder
    {
        return $query->where('transactions.type', 'EXPENSE');
    }

    /**
     * Scope: Only income transactions.
     */
    public function scopeIncomes(Builder $query): Builder
    {
        return $query->where('transactions.type', 'INCOME');
    }

    /**
     * Scope: Only transfer transactions.
     */
    public function scopeTransfers(Builder $query): Builder
    {
        return $query->where('transactions.type', 'TRANSFER');
    }

    public function getAmountAttribute($value): int
    {
        if ((int) $value !== 0) {
            return (int) $value;
        }
        if (!empty($this->attributes['encrypted_amount'])) {
            return app(\App\Services\EncryptionService::class)->decryptFromStorage($this->attributes['encrypted_amount']);
        }
        return (int) $value;
    }

    public function getBalanceAfterAttribute($value): int
    {
        if ((int) $value !== 0) {
            return (int) $value;
        }
        if (!empty($this->attributes['encrypted_balance_after'])) {
            return app(\App\Services\EncryptionService::class)->decryptFromStorage($this->attributes['encrypted_balance_after']);
        }
        return (int) $value;
    }
}
