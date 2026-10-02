<?php

namespace App\Models;

use App\Services\EncryptionService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\Budget
 *
 * Static monthly limit rule for a specific category per user.
 *
 * @property string $id
 * @property string $user_jid
 * @property string $category_id
 * @property string $encrypted_limit_amount
 * @property int    $alert_threshold_percent
 * @property int    $limit_amount Virtual decrypted limit
 */
class Budget extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_jid',
        'category_id',
        'encrypted_limit_amount',
        'alert_threshold_percent',
    ];

    protected $casts = [
        'alert_threshold_percent' => 'integer',
    ];

    protected $appends = [
        'limit_amount',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_jid', 'jid');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }

    public function getLimitAmountAttribute(): int
    {
        if (!empty($this->attributes['encrypted_limit_amount'])) {
            try {
                return app(EncryptionService::class)->decryptFromStorage($this->attributes['encrypted_limit_amount']);
            } catch (\Throwable) {
                return 0;
            }
        }
        return 0;
    }

    public function setLimitAmountAttribute(int $value): void
    {
        $this->attributes['encrypted_limit_amount'] = app(EncryptionService::class)->encryptForStorage($value);
    }
}
