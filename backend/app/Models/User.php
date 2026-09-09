<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * App\Models\User
 *
 * Represents a WhatsApp user identified by their unique JID (phone@s.whatsapp.net).
 * This replaces the default Laravel users table with a finance-domain model.
 *
 * @property string   $jid              Primary key — e.g. "6281234567890@s.whatsapp.net"
 * @property string   $display_name     Push name from WhatsApp
 * @property int      $current_balance  Running net balance in integer Rupiah (no float!)
 * @property bool     $is_active        Whether the user account is active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory;
    /**
     * The primary key for this model is the WhatsApp JID string (not auto-increment integer).
     */
    protected $primaryKey = 'jid';

    /**
     * The primary key is a string, not an integer.
     */
    public $incrementing = false;

    /**
     * The primary key type.
     */
    protected $keyType = 'string';

    /**
     * Mass-assignable attributes.
     */
    protected $fillable = [
        'jid',
        'display_name',
        'current_balance',
        'is_active',
    ];

    /**
     * Attribute casts.
     * current_balance must always be an integer to avoid floating-point precision errors.
     */
    protected $casts = [
        'current_balance' => 'integer',
        'is_active'       => 'boolean',
    ];

    /**
     * Default attribute values.
     */
    protected $attributes = [
        'display_name'    => '',
        'current_balance' => 0,
        'is_active'       => true,
    ];

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    /**
     * All transactions belonging to this user.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'user_jid', 'jid');
    }

    /**
     * All custom categories created by this user.
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class, 'user_jid', 'jid');
    }
}
