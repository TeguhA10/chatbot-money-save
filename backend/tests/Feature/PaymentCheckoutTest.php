<?php

namespace Tests\Feature;

use App\Models\PaymentOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_responds_to_langganan_with_in_chat_checkout_link(): void
    {
        $headers = ['X-Gateway-Secret' => 'local_dev_secret_12345'];
        $user = User::create([
            'jid'          => '628999@s.whatsapp.net',
            'display_name' => 'Budi',
            'pin_status'   => 'ACTIVE',
        ]);

        $response = $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => 'msg-sub-1',
            'from_jid'     => $user->jid,
            'message_text' => 'langganan',
        ], $headers);

        $response->assertOk()
            ->assertJsonPath('action', 'REPLY_TEXT');

        $reply = $response->json('reply_text');
        $this->assertStringContainsString('HematWoy Pro', $reply);
        $this->assertStringContainsString('Rp25.000', $reply);
        $this->assertTrue(str_contains($reply, 'midtrans.com') || str_contains($reply, '/pay/SUB-'));

        // Verify order created in database
        $order = PaymentOrder::where('user_jid', $user->jid)->first();
        $this->assertNotNull($order);
        $this->assertEquals(25000, $order->gross_amount);
        $this->assertEquals('PENDING', $order->status);
    }

    public function test_webhook_responds_to_kuota_with_freemium_progress_card(): void
    {
        $headers = ['X-Gateway-Secret' => 'local_dev_secret_12345'];
        $user = User::create([
            'jid'          => '628998@s.whatsapp.net',
            'display_name' => 'Siti',
            'tier'         => 'FREE',
            'free_financial_message_count' => 15,
            'pin_status'   => 'ACTIVE',
        ]);

        $response = $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => 'msg-kuota-1',
            'from_jid'     => $user->jid,
            'message_text' => 'kuota',
        ], $headers);

        $response->assertOk()
            ->assertJsonPath('action', 'REPLY_TEXT');

        $reply = $response->json('reply_text');
        $this->assertStringContainsString('Status Kuota Finansial', $reply);
        $this->assertStringContainsString('85', $reply); // 100 - 15 = 85 remaining
        $this->assertStringContainsString('langganan', $reply);
    }

    public function test_payment_order_show_and_simulate_settlement(): void
    {
        $user = User::create([
            'jid'          => '628997@s.whatsapp.net',
            'display_name' => 'Dewi',
            'tier'         => 'FREE',
            'pin_status'   => 'ACTIVE',
        ]);

        $order = PaymentOrder::create([
            'order_id'     => 'SUB-TEST-12345',
            'user_jid'     => $user->jid,
            'gross_amount' => 25000,
            'currency'     => 'IDR',
            'status'       => 'PENDING',
        ]);

        // GET /api/v1/payment/order/{orderId}
        $showResp = $this->getJson("/api/v1/payment/order/{$order->order_id}");
        $showResp->assertOk()
            ->assertJsonPath('order_id', 'SUB-TEST-12345')
            ->assertJsonPath('gross_amount', 25000)
            ->assertJsonPath('status', 'PENDING');

        // POST /api/v1/payment/simulate/{orderId}
        $simResp = $this->postJson("/api/v1/payment/simulate/{$order->order_id}");
        $simResp->assertOk()
            ->assertJsonPath('order.status', 'SETTLEMENT');

        // Verify user upgraded to PREMIUM with 30 days
        $user->refresh();
        $this->assertEquals('PREMIUM', $user->tier);
        $this->assertNotNull($user->subscription_expires_at);
        $this->assertTrue($user->subscription_expires_at->isFuture());
    }
}
