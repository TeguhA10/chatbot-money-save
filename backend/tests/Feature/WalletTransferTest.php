<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletTransferTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Wallet $bca;
    private Wallet $dana;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'jid'             => '628333333333@s.whatsapp.net',
            'display_name'    => 'Transfer Tester',
            'current_balance' => 0,
            'is_active'       => true,
            'pin_status'      => 'ACTIVE',
            'tier'            => 'PREMIUM',
            'subscription_expires_at' => now()->addMonth(),
        ]);

        $finance = app(FinanceService::class);
        $this->bca  = $finance->addWallet($this->user, 'BCA', 1000000);
        $this->dana = $finance->addWallet($this->user, 'DANA', 300000);
    }

    public function test_transfer_between_wallets_via_chat_command(): void
    {
        $secret = config('app.gateway_webhook_secret', env('GATEWAY_WEBHOOK_SECRET'));
        $headers = $secret ? ['X-Gateway-Secret' => $secret] : [];

        $response = $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => 'MSG_TRF_1',
            'from_jid'     => $this->user->jid,
            'push_name'    => 'Transfer Tester',
            'message_text' => 'transfer 200rb dari bca ke dana',
        ], $headers);

        $response->assertStatus(200);
        $reply = $response->json('reply_text');

        $this->assertStringContainsString('Transfer Berhasil', $reply);
        $this->assertStringContainsString('Rp200.000', $reply);
        $this->assertStringContainsString('BCA', $reply);
        $this->assertStringContainsString('DANA', $reply);
        $this->assertStringContainsString('Total Saldo Tetap: Rp1.300.000', $reply);

        $this->bca->refresh();
        $this->assertEquals(800000, $this->bca->balance);

        $this->dana->refresh();
        $this->assertEquals(500000, $this->dana->balance);

        $this->user->refresh();
        $this->assertEquals(1300000, $this->user->current_balance);

        // Check single transaction entry in database
        $this->assertDatabaseHas('transactions', [
            'user_jid'     => $this->user->jid,
            'wallet_id'    => $this->bca->id,
            'to_wallet_id' => $this->dana->id,
            'type'         => 'TRANSFER',
        ]);
    }

    public function test_transfer_fails_when_insufficient_balance(): void
    {
        $secret = config('app.gateway_webhook_secret', env('GATEWAY_WEBHOOK_SECRET'));
        $headers = $secret ? ['X-Gateway-Secret' => $secret] : [];

        $response = $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => 'MSG_TRF_OVERFLOW',
            'from_jid'     => $this->user->jid,
            'push_name'    => 'Transfer Tester',
            'message_text' => 'transfer 5jt dari bca ke dana',
        ], $headers);

        $response->assertStatus(200);
        $reply = $response->json('reply_text');
        $this->assertStringContainsString('Saldo BCA tidak mencukupi', $reply);
    }

    public function test_undo_reverses_transfer_atomically(): void
    {
        $secret = config('app.gateway_webhook_secret', env('GATEWAY_WEBHOOK_SECRET'));
        $headers = $secret ? ['X-Gateway-Secret' => $secret] : [];

        // 1. Transfer
        $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => 'MSG_TRF_TO_UNDO',
            'from_jid'     => $this->user->jid,
            'push_name'    => 'Transfer Tester',
            'message_text' => 'transfer 200rb dari bca ke dana',
        ], $headers);

        $this->bca->refresh();
        $this->dana->refresh();
        $this->assertEquals(800000, $this->bca->balance);
        $this->assertEquals(500000, $this->dana->balance);

        // 2. Undo
        $undoRes = $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => 'MSG_UNDO_TRF',
            'from_jid'     => $this->user->jid,
            'push_name'    => 'Transfer Tester',
            'message_text' => 'batal',
        ], $headers);

        $undoRes->assertStatus(200);
        $reply = $undoRes->json('reply_text');
        $this->assertStringContainsString('Transaksi Dibatalkan', $reply);

        $this->bca->refresh();
        $this->dana->refresh();
        $this->assertEquals(1000000, $this->bca->balance);
        $this->assertEquals(300000, $this->dana->balance);
    }
}
