<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class PaymentOrder extends Model { protected $primaryKey='order_id'; public $incrementing=false; protected $keyType='string'; protected $fillable=['order_id','user_jid','gross_amount','currency','snap_token','payment_url','payment_type','status','paid_at','last_webhook_payload']; protected $casts=['gross_amount'=>'integer','paid_at'=>'datetime','last_webhook_payload'=>'array']; public function user(): BelongsTo { return $this->belongsTo(User::class,'user_jid','jid'); } }
