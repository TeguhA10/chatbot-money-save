<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

/**
 * App\Models\Category
 *
 * Represents an expense/income classification category.
 * System-wide defaults have user_jid = NULL.
 * User-scoped custom categories have user_jid set to the owner's JID.
 *
 * @property string       $id         UUID primary key
 * @property string|null  $user_jid   NULL = global default; non-null = user-specific
 * @property string       $name       Human-readable category name
 * @property string       $type       'EXPENSE' | 'INCOME'
 * @property string       $icon       Emoji icon
 * @property bool         $is_default True for system-seeded categories
 */
class Category extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_jid',
        'name',
        'type',
        'icon',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    protected $attributes = [
        'icon'       => '📌',
        'is_default' => false,
    ];

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    /**
     * The user who owns this custom category (null for system defaults).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_jid', 'jid');
    }

    /**
     * Transactions tagged under this category.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'category_id', 'id');
    }

    // -----------------------------------------------------------------------
    // Query Scopes
    // -----------------------------------------------------------------------

    /**
     * Scope: Returns categories visible to a given user —
     * includes both global defaults (user_jid IS NULL) and their custom categories.
     */
    public function scopeForUser(Builder $query, string $userJid): Builder
    {
        return $query->where(function (Builder $q) use ($userJid) {
            $q->whereNull('user_jid')
              ->orWhere('user_jid', $userJid);
        });
    }

    /**
     * Scope: Only expense categories.
     */
    public function scopeExpenses(Builder $query): Builder
    {
        return $query->where('type', 'EXPENSE');
    }

    /**
     * Scope: Only income categories.
     */
    public function scopeIncomes(Builder $query): Builder
    {
        return $query->where('type', 'INCOME');
    }
}
