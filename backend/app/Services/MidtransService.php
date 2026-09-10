<?php
namespace App\Services;
use App\Models\PaymentOrder;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
class MidtransService
{
    public const PRICE = 25000;
    public function createOrder(User $user): PaymentOrder { return PaymentOrder::create(['order_id'=>'SUB-'.Str::upper(Str::random(20)),'user_jid'=>$user->jid,'gross_amount'=>self::PRICE]); }
    public function verify(array $payload): bool { $required=['order_id','status_code','gross_amount','signature_key']; foreach($required as $key) if(!isset($payload[$key])) return false; $expected=hash('sha512',$payload['order_id'].$payload['status_code'].$payload['gross_amount'].config('services.midtrans.server_key')); return hash_equals($expected,(string)$payload['signature_key']); }
    public function processNotification(array $payload): PaymentOrder
    {
        if(!$this->verify($payload)) throw new RuntimeException('Invalid Midtrans signature.');
        return DB::transaction(function() use($payload) {
            $order=PaymentOrder::whereKey($payload['order_id'])->lockForUpdate()->firstOrFail();
            if((int)$order->gross_amount !== (int)$payload['gross_amount']) throw new RuntimeException('Payment amount mismatch.');
            $order->update(['status'=>strtoupper($payload['transaction_status'] ?? 'PENDING'),'payment_type'=>$payload['payment_type'] ?? null,'last_webhook_payload'=>$payload]);
            if(!in_array(strtolower($payload['transaction_status'] ?? ''),['settlement','capture'],true) || $order->paid_at) return $order;
            $user=User::whereKey($order->user_jid)->lockForUpdate()->firstOrFail(); $start=$user->subscription_expires_at?->isFuture()?$user->subscription_expires_at:now(); $end=$start->copy()->addDays(30);
            $order->update(['paid_at'=>now(),'status'=>'SETTLEMENT']); $user->update(['tier'=>'PREMIUM','subscription_expires_at'=>$end]);
            Subscription::create(['user_jid'=>$user->jid,'starts_at'=>$start,'ends_at'=>$end,'source_order_id'=>$order->order_id]); return $order;
        });
    }
}
