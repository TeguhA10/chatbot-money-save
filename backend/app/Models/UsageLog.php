<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class UsageLog extends Model { use HasUuids; protected $fillable=['user_jid','message_id','quota_units','transactions_recorded','consumed_at']; protected $casts=['consumed_at'=>'datetime']; }
