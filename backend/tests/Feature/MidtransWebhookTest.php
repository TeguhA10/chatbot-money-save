<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MidtransWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_settlement_activates_premium_once_and_stacks_expiry(): void
    {
        config(['services.midtrans.server_key'=>'test-server-key']);
        $user=User::create(['jid'=>'628333@s.whatsapp.net','subscription_expires_at'=>now()->addDays(2),'tier'=>'PREMIUM']);
        $service=app(MidtransService::class); $order=$service->createOrder($user);
        $payload=['order_id'=>$order->order_id,'status_code'=>'200','gross_amount'=>'25000.00','transaction_status'=>'settlement'];
        $payload['signature_key']=hash('sha512',$payload['order_id'].$payload['status_code'].$payload['gross_amount'].'test-server-key');
        $service->processNotification($payload); $expiry=$user->fresh()->subscription_expires_at;
        $service->processNotification($payload);
        $this->assertTrue($user->fresh()->hasActivePremium());
        $this->assertTrue($expiry->equalTo($user->fresh()->subscription_expires_at));
    }
}
