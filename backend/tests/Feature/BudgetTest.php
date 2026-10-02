<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use App\Services\BudgetService;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BudgetTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'jid'             => '628111111111@s.whatsapp.net',
            'display_name'    => 'Budget Tester',
            'current_balance' => 2000000,
            'is_active'       => true,
            'pin_status'      => 'ACTIVE',
            'tier'            => 'PREMIUM',
            'subscription_expires_at' => now()->addMonth(),
        ]);

        $this->category = Category::create([
            'user_jid'   => null,
            'name'       => 'Makanan & Minuman',
            'type'       => 'EXPENSE',
            'icon'       => '🍔',
            'is_default' => true,
        ]);
    }

    public function test_user_can_set_category_budget_via_service(): void
    {
        $service = app(BudgetService::class);
        $budget = $service->setBudget($this->user, $this->category->id, 1000000);

        $this->assertDatabaseHas('budgets', [
            'user_jid'    => $this->user->jid,
            'category_id' => $this->category->id,
        ]);

        $this->assertEquals(1000000, $budget->limit_amount);

        // Verify limit is stored encrypted
        $raw = DB::table('budgets')->where('id', $budget->id)->first();
        $this->assertNotEmpty($raw->encrypted_limit_amount);
        $this->assertStringContainsString('ciphertext', $raw->encrypted_limit_amount);
    }

    public function test_setting_budget_via_chat_command(): void
    {
        $secret = config('app.gateway_webhook_secret', env('GATEWAY_WEBHOOK_SECRET'));
        $headers = $secret ? ['X-Gateway-Secret' => $secret] : [];

        $response = $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => 'MSG_BUDGET_1',
            'from_jid'     => $this->user->jid,
            'push_name'    => 'Budget Tester',
            'message_text' => 'budget makan 1jt',
        ], $headers);

        $response->assertStatus(200);
        $response->assertJson(['action' => 'REPLY_TEXT']);
        $reply = $response->json('reply_text');

        $this->assertStringContainsString('Plafon Anggaran Diatur', $reply);
        $this->assertStringContainsString('Rp1.000.000', $reply);
        $this->assertStringContainsString('0%', $reply);
    }

    public function test_expense_includes_budget_progress_and_warning(): void
    {
        $service = app(BudgetService::class);
        $service->setBudget($this->user, $this->category->id, 1000000);

        $secret = config('app.gateway_webhook_secret', env('GATEWAY_WEBHOOK_SECRET'));
        $headers = $secret ? ['X-Gateway-Secret' => $secret] : [];

        // Log expense 850rb (85%, should trigger warning)
        $response = $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => 'MSG_EXPENSE_BUDGET_1',
            'from_jid'     => $this->user->jid,
            'push_name'    => 'Budget Tester',
            'message_text' => 'keluar 850rb makan siang',
        ], $headers);

        $response->assertStatus(200);
        $reply = $response->json('reply_text');

        $this->assertStringContainsString('Budget', $reply);
        $this->assertStringContainsString('Terpakai: Rp850.000 / Rp1.000.000', $reply);
        $this->assertStringContainsString('Sisa: Rp150.000', $reply);
        $this->assertStringContainsString('85%', $reply);
        $this->assertStringContainsString('⚠️', $reply);
    }

    public function test_check_budget_command(): void
    {
        $service = app(BudgetService::class);
        $service->setBudget($this->user, $this->category->id, 1000000);

        $secret = config('app.gateway_webhook_secret', env('GATEWAY_WEBHOOK_SECRET'));
        $headers = $secret ? ['X-Gateway-Secret' => $secret] : [];

        $response = $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => 'MSG_CHECK_BUDGET_1',
            'from_jid'     => $this->user->jid,
            'push_name'    => 'Budget Tester',
            'message_text' => 'cek budget',
        ], $headers);

        $response->assertStatus(200);
        $reply = $response->json('reply_text');

        $this->assertStringContainsString('Ringkasan Budget Bulan Ini', $reply);
        $this->assertStringContainsString('Makanan', $reply);
        $this->assertStringContainsString('Rp1.000.000', $reply);
    }

    public function test_rest_api_budget_endpoints(): void
    {
        $token = $this->user->createToken('test-token')->plainTextToken;

        // POST /api/v1/budgets
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/budgets', [
                'category_id'  => $this->category->id,
                'limit_amount' => 1500000,
            ]);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'data' => [
                'limit_amount' => 1500000,
            ],
        ]);

        // GET /api/v1/budgets
        $getRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/budgets');

        $getRes->assertStatus(200);
        $getRes->assertJson([
            'success' => true,
        ]);
        $this->assertCount(1, $getRes->json('data.budgets'));
    }
}
