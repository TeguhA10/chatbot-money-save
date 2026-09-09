<?php

namespace Tests\Feature;

use App\Models\ProcessedMessage;
use App\Models\Transaction;
use App\Models\User;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\CategorySeeder::class);
        $this->withHeader('X-Gateway-Secret', config('app.gateway_webhook_secret', env('GATEWAY_WEBHOOK_SECRET', 'local_dev_secret_12345')));
    }

    public function test_user_balances_and_transactions_are_strictly_isolated()
    {
        $financeService = app(FinanceService::class);

        $userA = User::create([
            'jid'             => '628100000001@s.whatsapp.net',
            'display_name'    => 'User A',
            'current_balance' => 0,
        ]);

        $userB = User::create([
            'jid'             => '628100000002@s.whatsapp.net',
            'display_name'    => 'User B',
            'current_balance' => 0,
        ]);

        // User A records income 1.000.000 and expense 100.000
        $financeService->recordTransaction($userA, [
            'type'        => 'INCOME',
            'amount'      => 1000000,
            'description' => 'Gaji A',
            'raw_text'    => '+1jt gaji',
        ]);
        $financeService->recordTransaction($userA, [
            'type'        => 'EXPENSE',
            'amount'      => 100000,
            'description' => 'Makan A',
            'raw_text'    => 'keluar 100rb makan',
        ]);

        // User B records income 500.000
        $financeService->recordTransaction($userB, [
            'type'        => 'INCOME',
            'amount'      => 500000,
            'description' => 'Freelance B',
            'raw_text'    => '+500rb freelance',
        ]);

        // Verify balances
        $userA->refresh();
        $userB->refresh();
        $this->assertEquals(900000, $userA->current_balance);
        $this->assertEquals(500000, $userB->current_balance);

        // Verify transactions isolation
        $this->assertEquals(2, $userA->transactions()->count());
        $this->assertEquals(1, $userB->transactions()->count());

        // Ensure user A has zero records belonging to user B
        $this->assertEquals(0, Transaction::where('user_jid', $userA->jid)->where('description', 'Freelance B')->count());
    }

    public function test_webhook_idempotency_prevents_duplicate_transactions()
    {
        $messagePayload = [
            'message_id'   => 'MSG_UNIQUE_12345',
            'from_jid'     => '628199999999@s.whatsapp.net',
            'push_name'    => 'Test User',
            'message_text' => 'keluar 25000 makan siang',
            'timestamp'    => time(),
        ];

        // First attempt: registers user (onboarding)
        $resp1 = $this->postJson('/api/webhook/whatsapp', $messagePayload);
        $resp1->assertStatus(200);

        // Duplicate attempt with same message_id
        $resp2 = $this->postJson('/api/webhook/whatsapp', $messagePayload);
        $resp2->assertStatus(200);
        $resp2->assertJson([
            'action' => 'IGNORE',
            'reason' => 'duplicate',
        ]);

        // ProcessedMessage table has only 1 entry
        $this->assertEquals(1, ProcessedMessage::where('message_id', 'MSG_UNIQUE_12345')->count());
    }

    public function test_new_user_receives_onboarding_welcome_message()
    {
        $resp = $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => 'FIRST_MSG_001',
            'from_jid'     => '628188888888@s.whatsapp.net',
            'push_name'    => 'Budi',
            'message_text' => 'halo',
            'timestamp'    => time(),
        ]);

        $resp->assertStatus(200);
        $resp->assertJsonPath('action', 'REPLY_TEXT');
        $this->assertStringContainsString('Selamat datang', $resp->json('reply_text'));
        $this->assertStringContainsString('Budi', $resp->json('reply_text'));

        // User is now in database
        $this->assertDatabaseHas('users', [
            'jid'          => '628188888888@s.whatsapp.net',
            'display_name' => 'Budi',
        ]);
    }

    public function test_webhook_handles_transaction_after_onboarding()
    {
        // 1. Initial greeting
        $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => 'ONBOARD_1',
            'from_jid'     => '628177777777@s.whatsapp.net',
            'push_name'    => 'Rudi',
            'message_text' => 'halo',
        ]);

        // 2. Log income
        $resp = $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => 'TRANS_1',
            'from_jid'     => '628177777777@s.whatsapp.net',
            'push_name'    => 'Rudi',
            'message_text' => '+500000 gaji',
        ]);

        $resp->assertStatus(200);
        $this->assertStringContainsString('Pemasukan Tersimpan', $resp->json('reply_text'));

        $user = User::where('jid', '628177777777@s.whatsapp.net')->first();
        $this->assertEquals(500000, $user->current_balance);
    }
}
