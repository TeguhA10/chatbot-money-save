<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use App\Services\FinanceService;
use App\Services\InsightsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialDigestTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Category $food;
    private Category $transport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'jid'             => '628666666666@s.whatsapp.net',
            'display_name'    => 'Digest Tester',
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

        $this->transport = Category::create([
            'name'       => 'Transportasi',
            'type'       => 'EXPENSE',
            'icon'       => '🚗',
            'is_default' => true,
        ]);

        $finance = app(FinanceService::class);
        $finance->recordTransaction($this->user, [
            'type'        => 'INCOME',
            'amount'      => 1000000,
            'description' => 'Gaji / Transfer',
        ]);
        $finance->recordTransaction($this->user, [
            'type'        => 'EXPENSE',
            'amount'      => 40000,
            'description' => 'Makan',
            'category_id' => $this->food->id,
        ]);
        $finance->recordTransaction($this->user, [
            'type'        => 'EXPENSE',
            'amount'      => 25000,
            'description' => 'Bensin',
            'category_id' => $this->transport->id,
        ]);
    }

    public function test_generate_daily_digest_card(): void
    {
        $insights = app(InsightsService::class);
        $card = $insights->generateDailyDigestCard($this->user);

        $this->assertNotNull($card);
        $this->assertStringContainsString('Ringkasan Keuangan Hari Ini', $card);
        $this->assertStringContainsString('Pemasukan: Rp1.000.000', $card);
        $this->assertStringContainsString('Pengeluaran: Rp65.000', $card);
        $this->assertStringContainsString('Net: +Rp935.000', $card);
        $this->assertStringContainsString('Makanan & Minuman', $card);
        $this->assertStringContainsString('Total Saldo: Rp935.000', $card);
    }

    public function test_send_daily_digest_console_command(): void
    {
        $this->artisan('finance:send-daily-digest')
            ->assertExitCode(0);
    }

    public function test_toggle_daily_digest_commands(): void
    {
        $secret = config('app.gateway_webhook_secret', env('GATEWAY_WEBHOOK_SECRET'));
        $headers = $secret ? ['X-Gateway-Secret' => $secret] : [];

        // 1. Opt-out
        $res1 = $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => 'MSG_DIGEST_OFF',
            'from_jid'     => $this->user->jid,
            'push_name'    => 'Digest Tester',
            'message_text' => 'matikan rekap harian',
        ], $headers);

        $res1->assertStatus(200);
        $this->assertStringContainsString('dinonaktifkan', $res1->json('reply_text'));

        $insights = app(InsightsService::class);
        $this->assertFalse($insights->isDailyDigestEnabled($this->user));

        // 2. Opt-in
        $res2 = $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => 'MSG_DIGEST_ON',
            'from_jid'     => $this->user->jid,
            'push_name'    => 'Digest Tester',
            'message_text' => 'aktifkan rekap harian',
        ], $headers);

        $res2->assertStatus(200);
        $this->assertStringContainsString('diaktifkan', $res2->json('reply_text'));
        $this->assertTrue($insights->isDailyDigestEnabled($this->user));
    }
}
