<?php

namespace App\Models;

use App\Services\EncryptionService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * App\Models\Wallet
 *
 * Represents an isolated financial account (Cash, Bank, E-Wallet, etc.)
 *
 * @property string $id
 * @property string $user_jid
 * @property string $name
 * @property string $type
 * @property bool   $is_default
 * @property string|null $encrypted_balance
 * @property int    $balance Virtual decrypted balance
 */
class Wallet extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_jid',
        'name',
        'type',
        'is_default',
        'encrypted_balance',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    protected $appends = [
        'balance',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_jid', 'jid');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'wallet_id', 'id');
    }

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(Transaction::class, 'to_wallet_id', 'id');
    }

    public function getBalanceAttribute(): int
    {
        if (!empty($this->attributes['encrypted_balance'])) {
            try {
                return app(EncryptionService::class)->decryptFromStorage($this->attributes['encrypted_balance']);
            } catch (\Throwable) {
                return 0;
            }
        }
        return 0;
    }

    public function setBalanceAttribute(int $value): void
    {
        $this->attributes['encrypted_balance'] = app(EncryptionService::class)->encryptForStorage($value);
    }
}
