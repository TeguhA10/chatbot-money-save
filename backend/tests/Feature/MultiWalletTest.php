<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use App\Models\Wallet;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiWalletTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'jid'             => '628222222222@s.whatsapp.net',
            'display_name'    => 'Wallet Tester',
            'current_balance' => 0,
            'is_active'       => true,
            'pin_status'      => 'ACTIVE',
            'tier'            => 'PREMIUM',
            'subscription_expires_at' => now()->addMonth(),
        ]);
    }

    public function test_user_has_default_cash_wallet(): void
    {
        $finance = app(FinanceService::class);
        $wallet = $finance->getOrCreateDefaultWallet($this->user);

        $this->assertEquals('Cash', $wallet->name);
        $this->assertTrue($wallet->is_default);
        $this->assertEquals($this->user->jid, $wallet->user_jid);
    }

    public function test_add_wallet_service_and_command(): void
    {
        $secret = config('app.gateway_webhook_secret', env('GATEWAY_WEBHOOK_SECRET'));
        $headers = $secret ? ['X-Gateway-Secret' => $secret] : [];

        $response = $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => 'MSG_WALLET_1',
            'from_jid'     => $this->user->jid,
            'push_name'    => 'Wallet Tester',
            'message_text' => 'tambah dompet BCA saldo 1jt',
        ], $headers);

        $response->assertStatus(200);
        $reply = $response->json('reply_text');

        $this->assertStringContainsString('Dompet Baru Ditambahkan', $reply);
        $this->assertStringContainsString('BCA', $reply);
        $this->assertStringContainsString('Rp1.000.000', $reply);

        $this->user->refresh();
        $this->assertEquals(1000000, $this->user->current_balance);
    }

    public function test_transaction_tagged_with_wallet(): void
    {
        $finance = app(FinanceService::class);
        $bca = $finance->addWallet($this->user, 'BCA', 1000000);
        $dana = $finance->addWallet($this->user, 'DANA', 500000);

        $secret = config('app.gateway_webhook_secret', env('GATEWAY_WEBHOOK_SECRET'));
        $headers = $secret ? ['X-Gateway-Secret' => $secret] : [];

        // Expense from DANA
        $res = $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => 'MSG_WALLET_EXP_1',
            'from_jid'     => $this->user->jid,
            'push_name'    => 'Wallet Tester',
            'message_text' => 'keluar 50rb makan siang via dana',
        ], $headers);

        $res->assertStatus(200);
        $reply = $res->json('reply_text');
        $this->assertStringContainsString('DANA', $reply);

        $dana->refresh();
        $this->assertEquals(450000, $dana->balance);

        $bca->refresh();
        $this->assertEquals(1000000, $bca->balance);

        $this->user->refresh();
        $this->assertEquals(1450000, $this->user->current_balance);
    }

    public function test_saldo_command_displays_all_wallets_and_total(): void
    {
        $finance = app(FinanceService::class);
        $finance->addWallet($this->user, 'BCA', 2100000);
        $finance->addWallet($this->user, 'DANA', 350000);
        $cash = $finance->getOrCreateDefaultWallet($this->user);
        $cash->balance = 200000;
        $cash->save();

        $this->user->current_balance = 2650000;
        $this->user->save();

        $secret = config('app.gateway_webhook_secret', env('GATEWAY_WEBHOOK_SECRET'));
        $headers = $secret ? ['X-Gateway-Secret' => $secret] : [];

        $res = $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => 'MSG_SALDO_1',
            'from_jid'     => $this->user->jid,
            'push_name'    => 'Wallet Tester',
            'message_text' => 'saldo',
        ], $headers);

        $res->assertStatus(200);
        $reply = $res->json('reply_text');

        $this->assertStringContainsString('Saldo Anda', $reply);
        $this->assertStringContainsString('BCA', $reply);
        $this->assertStringContainsString('DANA', $reply);
        $this->assertStringContainsString('Cash', $reply);
        $this->assertStringContainsString('Rp2.650.000', $reply);
    }

    public function test_rest_api_wallets_endpoints(): void
    {
        $token = $this->user->createToken('test-token')->plainTextToken;

        // POST /api/v1/wallets
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/wallets', [
                'name'            => 'GoPay',
                'type'            => 'EWALLET',
                'initial_balance' => 250000,
            ]);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'data' => [
                'name'    => 'GoPay',
                'balance' => 250000,
            ],
        ]);

        // GET /api/v1/wallets
        $getRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/wallets');

        $getRes->assertStatus(200);
        $getRes->assertJson(['success' => true]);
        $this->assertGreaterThanOrEqual(1, count($getRes->json('data.wallets')));
    }
}
