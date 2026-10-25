<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\FinancialGoal;
use App\Models\User;
use App\Models\Wallet;
use App\Services\FinanceService;
use App\Services\BudgetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrudWalletBudgetGoalTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_update_and_delete_via_api(): void
    {
        /** @var FinanceService $financeService */
        $financeService = app(FinanceService::class);
        $user = $financeService->findOrCreateUser('62811111@s.whatsapp.net', 'Test User');

        // Add a second wallet
        $bca = $financeService->addWallet($user, 'BCA', 500000, 'BANK');

        // Test Update Wallet via PUT /api/v1/wallets/{id}
        $response = $this->actingAs($user)->putJson("/api/v1/wallets/{$bca->id}", [
            'name'    => 'BluBCA',
            'type'    => 'BANK',
            'balance' => 600000,
        ]);

        $response->assertStatus(200);
        $this->assertEquals('BluBCA', $bca->fresh()->name);
        $this->assertEquals(600000, $bca->fresh()->balance);

        // Test Delete Wallet via DELETE /api/v1/wallets/{id}
        $delResponse = $this->actingAs($user)->deleteJson("/api/v1/wallets/{$bca->id}");
        $delResponse->assertStatus(200);
        $this->assertNull(Wallet::find($bca->id));

        // Remaining balance should have moved to default wallet
        $defaultWallet = Wallet::where('user_jid', $user->jid)->where('is_default', true)->first();
        $this->assertNotNull($defaultWallet);
        $this->assertGreaterThanOrEqual(600000, $defaultWallet->balance);
    }

    public function test_budget_delete_via_api(): void
    {
        /** @var BudgetService $budgetService */
        $budgetService = app(BudgetService::class);
        /** @var FinanceService $financeService */
        $financeService = app(FinanceService::class);
        $user = $financeService->findOrCreateUser('62822222@s.whatsapp.net', 'Budget User');

        $budgetService->setBudget($user, 1500000);
        $this->assertNotNull(Budget::where('user_jid', $user->jid)->first());

        // Test Delete Budget via DELETE /api/v1/budgets
        $response = $this->actingAs($user)->deleteJson('/api/v1/budgets');
        $response->assertStatus(200);
        $this->assertNull(Budget::where('user_jid', $user->jid)->first());
    }

    public function test_goal_update_and_delete_with_refund_via_api(): void
    {
        /** @var FinanceService $financeService */
        $financeService = app(FinanceService::class);
        $user = $financeService->findOrCreateUser('62833333@s.whatsapp.net', 'Goal User');

        // Give user some initial balance
        $cash = $financeService->getOrCreateDefaultWallet($user);
        $cash->balance = 10000000;
        $cash->save();
        $user->current_balance = 10000000;
        $user->save();

        $goal = $financeService->createGoal($user, 'Mobil Impian', 50000000);
        $this->assertEquals(50000000, $goal->target_amount);

        // Test Update Goal via PUT /api/v1/goals/{id}
        $updateResp = $this->actingAs($user)->putJson("/api/v1/goals/{$goal->id}", [
            'name'          => 'Mobil Baru',
            'target_amount' => 60000000,
        ]);
        $updateResp->assertStatus(200);
        $this->assertEquals('Mobil Baru', $goal->fresh()->name);
        $this->assertEquals(60000000, $goal->fresh()->target_amount);

        // Contribute 2.000.000 to goal
        $financeService->contributeToGoal($user, $goal->fresh(), 2000000, $cash->id);
        $this->assertEquals(2000000, $goal->fresh()->current_amount);
        $cashBalanceAfterNabung = $cash->fresh()->balance;

        // Test Delete Goal with refund via DELETE /api/v1/goals/{id}
        $delResp = $this->actingAs($user)->deleteJson("/api/v1/goals/{$goal->id}", [
            'refund' => true,
        ]);
        $delResp->assertStatus(200);
        $this->assertNull(FinancialGoal::find($goal->id));

        // The 2.000.000 must be refunded back to user's wallet
        $this->assertEquals($cashBalanceAfterNabung + 2000000, $cash->fresh()->balance);
    }

    public function test_whatsapp_commands_for_wallet_budget_and_goal_crud(): void
    {
        $phone = '62899999999';
        $fromJid = "{$phone}@s.whatsapp.net";

        $secret = config('app.gateway_webhook_secret', env('GATEWAY_WEBHOOK_SECRET'));
        $headers = $secret ? ['X-Gateway-Secret' => $secret] : [];

        // 1. Create wallet via WA command
        $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => 'msg_w_1',
            'from_jid'     => $fromJid,
            'push_name'    => 'WA User',
            'message_text' => 'tambah dompet Jago saldo 100rb',
        ], $headers)->assertStatus(200);

        // 2. Rename wallet via WA command
        $respRename = $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => 'msg_w_2',
            'from_jid'     => $fromJid,
            'push_name'    => 'WA User',
            'message_text' => 'ubah dompet Jago jadi BankJago',
        ], $headers);
        $respRename->assertStatus(200);
        $this->assertStringContainsString('BankJago', $respRename->json('reply_text'));

        // 3. Delete wallet via WA command
        $respDelWallet = $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => 'msg_w_3',
            'from_jid'     => $fromJid,
            'push_name'    => 'WA User',
            'message_text' => 'hapus dompet BankJago',
        ], $headers);
        $respDelWallet->assertStatus(200);
        $this->assertStringContainsString('berhasil dihapus', $respDelWallet->json('reply_text'));

        // 4. Set & Delete budget via WA command
        $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => 'msg_b_1',
            'from_jid'     => $fromJid,
            'push_name'    => 'WA User',
            'message_text' => 'budget 2jt',
        ], $headers)->assertStatus(200);

        $respDelBudget = $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => 'msg_b_2',
            'from_jid'     => $fromJid,
            'push_name'    => 'WA User',
            'message_text' => 'hapus budget',
        ], $headers);
        $respDelBudget->assertStatus(200);
        $this->assertStringContainsString('Plafon Anggaran Dihapus', $respDelBudget->json('reply_text'));

        // 5. Create, Update, & Delete goal via WA command
        $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => 'msg_g_1',
            'from_jid'     => $fromJid,
            'push_name'    => 'WA User',
            'message_text' => 'buat target Laptop 15jt',
        ], $headers)->assertStatus(200);

        $respUpdateGoal = $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => 'msg_g_2',
            'from_jid'     => $fromJid,
            'push_name'    => 'WA User',
            'message_text' => 'ubah target Laptop jadi 20jt',
        ], $headers);
        $respUpdateGoal->assertStatus(200);
        $this->assertStringContainsString('Target Diperbarui', $respUpdateGoal->json('reply_text'));

        $respDelGoal = $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => 'msg_g_3',
            'from_jid'     => $fromJid,
            'push_name'    => 'WA User',
            'message_text' => 'hapus target Laptop',
        ], $headers);
        $respDelGoal->assertStatus(200);
        $this->assertStringContainsString('Target Dihapus', $respDelGoal->json('reply_text'));
    }
}
