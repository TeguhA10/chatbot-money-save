<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\FinanceService;
use App\Services\InsightsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpendingInsightsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Category $food;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'jid'             => '628777777777@s.whatsapp.net',
            'display_name'    => 'Insights Tester',
            'current_balance' => 0,
            'is_active'       => true,
            'pin_status'      => 'ACTIVE',
            'tier'            => 'PREMIUM',
            'subscription_expires_at' => now()->addMonth(),
        ]);

        $this->food = Category::create([
            'name'       => 'Makanan & Minuman',
            'type'       => 'EXPENSE',
            'icon'       => '🍔',
            'is_default' => true,
        ]);

        $finance = app(FinanceService::class);
        $crypto  = app(\App\Services\EncryptionService::class);

        // Previous month transaction: 1.000.000
        Transaction::create([
            'user_jid'                => $this->user->jid,
            'category_id'             => $this->food->id,
            'type'                    => 'EXPENSE',
            'amount'                  => 0,
            'description'             => 'Belanja Makanan Bulan Lalu',
            'balance_after'           => 0,
            'encrypted_amount'        => $crypto->encryptForStorage(1000000),
            'encrypted_balance_after' => $crypto->encryptForStorage(0),
            'transaction_date'        => now()->subMonthNoOverflow()->startOfMonth()->addDays(5),
            'status'                  => 'ACTIVE',
        ]);

        // Current month transaction: 1.200.000 (+20%)
        Transaction::create([
            'user_jid'                => $this->user->jid,
            'category_id'             => $this->food->id,
            'type'                    => 'EXPENSE',
            'amount'                  => 0,
            'description'             => 'Belanja Makanan Bulan Ini',
            'balance_after'           => 0,
            'encrypted_amount'        => $crypto->encryptForStorage(1200000),
            'encrypted_balance_after' => $crypto->encryptForStorage(0),
            'transaction_date'        => now()->startOfMonth()->addDays(2),
            'status'                  => 'ACTIVE',
        ]);
    }

    public function test_spending_variance_calculation(): void
    {
        $insights = app(InsightsService::class);
        $analysis = $insights->analyzeSpendingVariance($this->user);

        $this->assertEquals(1200000, $analysis['current_spent']);
        $this->assertEquals(1000000, $analysis['previous_spent']);
        $this->assertEquals(20.0, $analysis['percentage_delta']);
        $this->assertTrue($analysis['is_higher']);
        $this->assertNotEmpty($analysis['top_increases']);
    }

    public function test_spending_inquiry_chat_command(): void
    {
        $secret = config('app.gateway_webhook_secret', env('GATEWAY_WEBHOOK_SECRET'));
        $headers = $secret ? ['X-Gateway-Secret' => $secret] : [];

        $response = $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => 'MSG_BOROS_1',
            'from_jid'     => $this->user->jid,
            'push_name'    => 'Insights Tester',
            'message_text' => 'bulan ini boros gak?',
        ], $headers);

        $response->assertStatus(200);
        $reply = $response->json('reply_text');

        $this->assertStringContainsString('Analisis Keuangan Bulan Ini', $reply);
        $this->assertStringContainsString('naik *20%*', $reply);
        $this->assertStringContainsString('Makanan & Minuman', $reply);
        $this->assertStringContainsString('Estimasi pengeluaran', $reply);
    }
}
