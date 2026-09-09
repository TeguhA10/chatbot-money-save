<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\ProcessedMessage
 *
 * Idempotency guard for incoming WhatsApp messages.
 * Before processing any webhook payload, the system checks if message_id already exists.
 * If found, the request is a duplicate and is silently discarded.
 *
 * This prevents double-processing during:
 * - WhatsApp message retransmission
 * - Baileys reconnect storms
 * - Load balancer retry attacks
 *
 * @property string       $message_id  WA message ID (e.g. "3EB0123456789ABCDEF") — PK
 * @property string       $user_jid    Sender's JID
 * @property \Carbon\Carbon $received_at Timestamp when first processed
 */
class ProcessedMessage extends Model
{
    /**
     * The primary key column.
     */
    protected $primaryKey = 'message_id';

    /**
     * Primary key is a string (WA message ID), not auto-increment.
     */
    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * No updated_at column — message IDs are immutable once recorded.
     */
    public $timestamps = false;

    protected $fillable = [
        'message_id',
        'user_jid',
        'received_at',
    ];

    protected $casts = [
        'received_at' => 'datetime',
    ];
}
