<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FreelancerAndUmkmTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'jid'             => '628888888888@s.whatsapp.net',
            'display_name'    => 'Merchant Tester',
            'current_balance' => 0,
            'is_active'       => true,
            'pin_status'      => 'ACTIVE',
            'tier'            => 'PREMIUM',
            'subscription_expires_at' => now()->addMonth(),
        ]);
    }

    public function test_freelancer_project_tagging_and_rekap(): void
    {
        $secret = config('app.gateway_webhook_secret', env('GATEWAY_WEBHOOK_SECRET'));
        $headers = $secret ? ['X-Gateway-Secret' => $secret] : [];

        // 1. Log project 1
        $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => 'MSG_PROJ_1',
            'from_jid'     => $this->user->jid,
            'push_name'    => 'Merchant Tester',
            'message_text' => 'masuk 2jt project Website Client A',
        ], $headers);

        // 2. Log project 2
        $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => 'MSG_PROJ_2',
            'from_jid'     => $this->user->jid,
            'push_name'    => 'Merchant Tester',
            'message_text' => 'masuk 1.5jt project Mobile App',
        ], $headers);

        // 3. Query rekap freelance
        $response = $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => 'MSG_REKAP_FREE',
            'from_jid'     => $this->user->jid,
            'push_name'    => 'Merchant Tester',
            'message_text' => 'rekap freelance',
        ], $headers);

        $response->assertStatus(200);
        $reply = $response->json('reply_text');

        $this->assertStringContainsString('Pendapatan Freelance', $reply);
        $this->assertStringContainsString('Rp3.500.000', $reply);
        $this->assertStringContainsString('Website Client A', $reply);
        $this->assertStringContainsString('Mobile App', $reply);
    }

    public function test_umkm_turnover_and_gross_profit(): void
    {
        $secret = config('app.gateway_webhook_secret', env('GATEWAY_WEBHOOK_SECRET'));
        $headers = $secret ? ['X-Gateway-Secret' => $secret] : [];

        // 1. Sales: "jual paket nasi 750rb"
        $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => 'MSG_UMKM_JUAL',
            'from_jid'     => $this->user->jid,
            'push_name'    => 'Merchant Tester',
            'message_text' => 'jual paket nasi 750rb',
        ], $headers);

        // 2. Cost: "modal bahan 300rb"
        $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => 'MSG_UMKM_MODAL',
            'from_jid'     => $this->user->jid,
            'push_name'    => 'Merchant Tester',
            'message_text' => 'modal bahan 300rb',
        ], $headers);

        // 3. Query omzet hari ini
        $response = $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => 'MSG_UMKM_QUERY',
            'from_jid'     => $this->user->jid,
            'push_name'    => 'Merchant Tester',
            'message_text' => 'omzet hari ini',
        ], $headers);

        $response->assertStatus(200);
        $reply = $response->json('reply_text');

        $this->assertStringContainsString('Penjualan Hari Ini', $reply);
        $this->assertStringContainsString('Omzet (Penjualan): Rp750.000', $reply);
        $this->assertStringContainsString('Modal (HPP): Rp300.000', $reply);
        $this->assertStringContainsString('Estimasi Laba Kotor: Rp450.000', $reply);
    }
}
