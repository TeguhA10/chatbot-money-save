<?php

namespace App\Models;

use App\Services\EncryptionService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\RecurringSchedule
 *
 * Defines scheduled recurring income or expense commitments.
 */
class RecurringSchedule extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_jid',
        'wallet_id',
        'category_id',
        'type',
        'encrypted_amount',
        'description',
        'frequency',
        'day_of_month',
        'next_run_date',
        'last_run_at',
        'is_active',
    ];

    protected $casts = [
        'day_of_month'  => 'integer',
        'next_run_date' => 'date',
        'last_run_at'   => 'datetime',
        'is_active'     => 'boolean',
    ];

    protected $appends = [
        'amount',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_jid', 'jid');
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'wallet_id', 'id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }

    public function getAmountAttribute(): int
    {
        if (!empty($this->attributes['encrypted_amount'])) {
            try {
                return app(EncryptionService::class)->decryptFromStorage($this->attributes['encrypted_amount']);
            } catch (\Throwable) {
                return 0;
            }
        }
        return 0;
    }

    public function setAmountAttribute(int $value): void
    {
        $this->attributes['encrypted_amount'] = app(EncryptionService::class)->encryptForStorage($value);
    }
}
