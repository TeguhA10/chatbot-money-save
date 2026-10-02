<?php

namespace Tests\Feature;

use App\Models\RecurringSchedule;
use App\Models\User;
use App\Models\Wallet;
use App\Services\FinanceService;
use App\Services\RecurringService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringTransactionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Wallet $bca;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'jid'             => '628444444444@s.whatsapp.net',
            'display_name'    => 'Recurring Tester',
            'current_balance' => 0,
            'is_active'       => true,
            'pin_status'      => 'ACTIVE',
            'tier'            => 'PREMIUM',
            'subscription_expires_at' => now()->addMonth(),
        ]);

        $finance = app(FinanceService::class);
        $this->bca = $finance->addWallet($this->user, 'BCA', 1000000);
    }

    public function test_register_recurring_commitment_via_chat_command(): void
    {
        $secret = config('app.gateway_webhook_secret', env('GATEWAY_WEBHOOK_SECRET'));
        $headers = $secret ? ['X-Gateway-Secret' => $secret] : [];

        $response = $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => 'MSG_REC_1',
            'from_jid'     => $this->user->jid,
            'push_name'    => 'Recurring Tester',
            'message_text' => 'langganan Netflix 186rb setiap tanggal 5 via bca',
        ], $headers);

        $response->assertStatus(200);
        $reply = $response->json('reply_text');

        $this->assertStringContainsString('Transaksi Rutin Terjadwal', $reply);
        $this->assertStringContainsString('Netflix', $reply);
        $this->assertStringContainsString('Rp186.000', $reply);
        $this->assertStringContainsString('BCA', $reply);

        $this->assertDatabaseHas('recurring_schedules', [
            'user_jid'    => $this->user->jid,
            'description' => 'Netflix',
            'type'        => 'EXPENSE',
            'wallet_id'   => $this->bca->id,
            'day_of_month'=> 5,
            'is_active'   => true,
        ]);
    }

    public function test_list_and_cancel_recurring_schedule(): void
    {
        $service = app(RecurringService::class);
        $service->createSchedule($this->user, [
            'description'  => 'Spotify',
            'amount'       => 55000,
            'day_of_month' => 10,
            'wallet_id'    => $this->bca->id,
        ]);

        $secret = config('app.gateway_webhook_secret', env('GATEWAY_WEBHOOK_SECRET'));
        $headers = $secret ? ['X-Gateway-Secret' => $secret] : [];

        // 1. List
        $listRes = $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => 'MSG_REC_LIST',
            'from_jid'     => $this->user->jid,
            'push_name'    => 'Recurring Tester',
            'message_text' => 'daftar langganan',
        ], $headers);

        $listRes->assertStatus(200);
        $this->assertStringContainsString('Spotify', $listRes->json('reply_text'));
        $this->assertStringContainsString('Rp55.000', $listRes->json('reply_text'));

        // 2. Cancel
        $delRes = $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => 'MSG_REC_DEL',
            'from_jid'     => $this->user->jid,
            'push_name'    => 'Recurring Tester',
            'message_text' => 'hapus langganan Spotify',
        ], $headers);

        $delRes->assertStatus(200);
        $this->assertStringContainsString('dibatalkan', $delRes->json('reply_text'));

        $this->assertDatabaseHas('recurring_schedules', [
            'user_jid'    => $this->user->jid,
            'description' => 'Spotify',
            'is_active'   => false,
        ]);
    }

    public function test_process_recurring_artisan_command(): void
    {
        $service = app(RecurringService::class);
        $schedule = $service->createSchedule($this->user, [
            'description'  => 'Server Cloud',
            'amount'       => 150000,
            'day_of_month' => 1,
            'wallet_id'    => $this->bca->id,
            'next_run_date'=> now()->subDay()->toDateString(), // Due
        ]);

        $this->artisan('finance:process-recurring')
            ->assertExitCode(0);

        // Transaction recorded
        $this->assertDatabaseHas('transactions', [
            'user_jid'    => $this->user->jid,
            'wallet_id'   => $this->bca->id,
            'description' => 'Server Cloud (Otomatis)',
        ]);

        // Balance deducted
        $this->bca->refresh();
        $this->assertEquals(850000, $this->bca->balance);

        // Next run date advanced
        $schedule->refresh();
        $this->assertTrue(Carbon::parse($schedule->next_run_date)->isFuture());
    }
}
