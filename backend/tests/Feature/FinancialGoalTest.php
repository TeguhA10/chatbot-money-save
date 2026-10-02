<?php

namespace Tests\Feature;

use App\Models\FinancialGoal;
use App\Models\User;
use App\Models\Wallet;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialGoalTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Wallet $bca;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'jid'             => '628555555555@s.whatsapp.net',
            'display_name'    => 'Goal Tester',
            'current_balance' => 0,
            'is_active'       => true,
            'pin_status'      => 'ACTIVE',
            'tier'            => 'PREMIUM',
            'subscription_expires_at' => now()->addMonth(),
        ]);

        $finance = app(FinanceService::class);
        $this->bca = $finance->addWallet($this->user, 'BCA', 5000000);
    }

    public function test_create_goal_via_chat_command(): void
    {
        $secret = config('app.gateway_webhook_secret', env('GATEWAY_WEBHOOK_SECRET'));
        $headers = $secret ? ['X-Gateway-Secret' => $secret] : [];

        $response = $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => 'MSG_GOAL_1',
            'from_jid'     => $this->user->jid,
            'push_name'    => 'Goal Tester',
            'message_text' => 'buat target Modal Apotek 100jt',
        ], $headers);

        $response->assertStatus(200);
        $reply = $response->json('reply_text');

        $this->assertStringContainsString('Target Finansial Dibuat', $reply);
        $this->assertStringContainsString('Modal Apotek', $reply);
        $this->assertStringContainsString('Rp100.000.000', $reply);

        $this->assertDatabaseHas('financial_goals', [
            'user_jid' => $this->user->jid,
            'name'     => 'Modal Apotek',
            'status'   => 'ACTIVE',
        ]);
    }

    public function test_contribute_to_goal_via_chat_command(): void
    {
        $finance = app(FinanceService::class);
        $goal = $finance->createGoal($this->user, 'Modal Apotek', 10000000);

        $secret = config('app.gateway_webhook_secret', env('GATEWAY_WEBHOOK_SECRET'));
        $headers = $secret ? ['X-Gateway-Secret' => $secret] : [];

        $response = $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => 'MSG_GOAL_CONTRIB',
            'from_jid'     => $this->user->jid,
            'push_name'    => 'Goal Tester',
            'message_text' => 'tambah tabungan 1jt untuk Modal Apotek via bca',
        ], $headers);

        $response->assertStatus(200);
        $reply = $response->json('reply_text');

        $this->assertStringContainsString('Target: Modal Apotek', $reply);
        $this->assertStringContainsString('Terkumpul: Rp1.000.000', $reply);
        $this->assertStringContainsString('10%', $reply);

        $goal->refresh();
        $this->assertEquals(1000000, $goal->current_amount);

        $this->bca->refresh();
        $this->assertEquals(4000000, $this->bca->balance);
    }

    public function test_list_goals_command(): void
    {
        $finance = app(FinanceService::class);
        $finance->createGoal($this->user, 'Dana Darurat', 20000000);

        $secret = config('app.gateway_webhook_secret', env('GATEWAY_WEBHOOK_SECRET'));
        $headers = $secret ? ['X-Gateway-Secret' => $secret] : [];

        $response = $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => 'MSG_GOAL_LIST',
            'from_jid'     => $this->user->jid,
            'push_name'    => 'Goal Tester',
            'message_text' => 'daftar target',
        ], $headers);

        $response->assertStatus(200);
        $this->assertStringContainsString('Dana Darurat', $response->json('reply_text'));
        $this->assertStringContainsString('Rp20.000.000', $response->json('reply_text'));
    }

    public function test_rest_api_goals_endpoints(): void
    {
        $token = $this->user->createToken('test-token')->plainTextToken;

        // 1. POST /api/v1/goals
        $res = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/goals', [
                'name'          => 'Beli Laptop',
                'target_amount' => 15000000,
            ]);

        $res->assertStatus(201);
        $goalId = $res->json('data.id');

        // 2. POST /api/v1/goals/{id}/contribute
        $contribRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/v1/goals/{$goalId}/contribute", [
                'amount'    => 2000000,
                'wallet_id' => $this->bca->id,
            ]);

        $contribRes->assertStatus(200);
        $contribRes->assertJson([
            'success' => true,
            'data'    => [
                'current_amount'   => 2000000,
                'progress_percent' => 13.3,
            ],
        ]);

        // 3. GET /api/v1/goals
        $listRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/goals');

        $listRes->assertStatus(200);
        $this->assertCount(1, $listRes->json('data.goals'));
    }
}
