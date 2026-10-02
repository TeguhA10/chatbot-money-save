<?php

namespace App\Models;

use App\Services\EncryptionService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\FinancialGoal
 *
 * Represents milestone savings objectives.
 */
class FinancialGoal extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_jid',
        'name',
        'encrypted_target_amount',
        'encrypted_current_amount',
        'target_date',
        'status',
    ];

    protected $casts = [
        'target_date' => 'date',
    ];

    protected $appends = [
        'target_amount',
        'current_amount',
        'progress_percent',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_jid', 'jid');
    }

    public function getTargetAmountAttribute(): int
    {
        if (!empty($this->attributes['encrypted_target_amount'])) {
            try {
                return app(EncryptionService::class)->decryptFromStorage($this->attributes['encrypted_target_amount']);
            } catch (\Throwable) {
                return 0;
            }
        }
        return 0;
    }

    public function setTargetAmountAttribute(int $value): void
    {
        $this->attributes['encrypted_target_amount'] = app(EncryptionService::class)->encryptForStorage($value);
    }

    public function getCurrentAmountAttribute(): int
    {
        if (!empty($this->attributes['encrypted_current_amount'])) {
            try {
                return app(EncryptionService::class)->decryptFromStorage($this->attributes['encrypted_current_amount']);
            } catch (\Throwable) {
                return 0;
            }
        }
        return 0;
    }

    public function setCurrentAmountAttribute(int $value): void
    {
        $this->attributes['encrypted_current_amount'] = app(EncryptionService::class)->encryptForStorage($value);
    }

    public function getProgressPercentAttribute(): float
    {
        $target = $this->target_amount;
        if ($target <= 0) {
            return 0.0;
        }
        return round(($this->current_amount / $target) * 100, 1);
    }
}
