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
 * @property string|null  $category_id      FK to categories.id (nullable)
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
        'category_id',
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
     * The category this transaction is tagged under (can be null).
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }

    // -----------------------------------------------------------------------
    // Query Scopes
    // -----------------------------------------------------------------------

    /**
     * Scope: Only active (non-voided) transactions.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'ACTIVE');
    }

    /**
     * Scope: Only voided transactions.
     */
    public function scopeVoided(Builder $query): Builder
    {
        return $query->where('status', 'VOIDED');
    }

    /**
     * Scope: Transactions in a specific year-month (YYYY-MM format).
     */
    public function scopeForMonth(Builder $query, int $year, int $month): Builder
    {
        return $query->whereYear('transaction_date', $year)
                     ->whereMonth('transaction_date', $month);
    }

    /**
     * Scope: Transactions on a specific date.
     */
    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('transaction_date', $date);
    }

    /**
     * Scope: Only expense transactions.
     */
    public function scopeExpenses(Builder $query): Builder
    {
        return $query->where('type', 'EXPENSE');
    }

    /**
     * Scope: Only income transactions.
     */
    public function scopeIncomes(Builder $query): Builder
    {
        return $query->where('type', 'INCOME');
    }

    public function getAmountAttribute($value): int
    {
        if (!empty($this->attributes['encrypted_amount'])) return app(\App\Services\EncryptionService::class)->decryptFromStorage($this->attributes['encrypted_amount']);
        return (int) $value;
    }
    public function getBalanceAfterAttribute($value): int
    {
        if (!empty($this->attributes['encrypted_balance_after'])) return app(\App\Services\EncryptionService::class)->decryptFromStorage($this->attributes['encrypted_balance_after']);
        return (int) $value;
    }
}
