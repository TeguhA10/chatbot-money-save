<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Subscription extends Model { use HasUuids; protected $fillable=['user_jid','plan_code','price_amount','starts_at','ends_at','status','source_order_id']; protected $casts=['starts_at'=>'datetime','ends_at'=>'datetime','price_amount'=>'integer']; public function user(): BelongsTo { return $this->belongsTo(User::class,'user_jid','jid'); } }
