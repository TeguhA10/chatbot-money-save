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

        $budgetService->setBudget($user, 1500000, 'Batas Belanja Bulanan');
        $this->assertNotNull(Budget::where('user_jid', $user->jid)->first());

        // Test Delete Budget via DELETE /api/v1/budgets
        $response = $this->actingAs($user)->deleteJson('/api/v1/budgets');
        $response->assertStatus(200);
        $this->assertNull(Budget::where('user_jid', $user->jid)->first());
    }

    public function test_budget_requires_description_via_api_and_whatsapp(): void
    {
        /** @var FinanceService $financeService */
        $financeService = app(FinanceService::class);
        $user = $financeService->findOrCreateUser('62866666@s.whatsapp.net', 'Budget Desc User');

        // 1. POST /api/v1/budgets without description -> 422
        $resNoDesc = $this->actingAs($user)->postJson('/api/v1/budgets', [
            'limit_amount' => 2000000,
        ]);
        $resNoDesc->assertStatus(422);
        $resNoDesc->assertJsonValidationErrors(['description']);

        // 2. POST /api/v1/budgets with description -> 201
        $resWithDesc = $this->actingAs($user)->postJson('/api/v1/budgets', [
            'limit_amount' => 2500000,
            'description'  => 'Plafon Belanja & Jajan',
        ]);
        $resWithDesc->assertStatus(201);
        $this->assertEquals('Plafon Belanja & Jajan', $resWithDesc->json('data.description'));
        $this->assertEquals(2500000, $resWithDesc->json('data.limit_amount'));

        // 3. GET /api/v1/budgets returns description
        $resGet = $this->actingAs($user)->getJson('/api/v1/budgets');
        $resGet->assertStatus(200);
        $this->assertEquals('Plafon Belanja & Jajan', $resGet->json('data.budget.description'));

        // 4. WhatsApp command with description: "budget 3jt Batas Lifestyle"
        $secret = config('app.gateway_webhook_secret', env('GATEWAY_WEBHOOK_SECRET'));
        $headers = $secret ? ['X-Gateway-Secret' => $secret] : [];

        $respWa = $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => 'msg_b_desc_1',
            'from_jid'     => $user->jid,
            'push_name'    => 'WA User',
            'message_text' => 'budget 3jt Batas Lifestyle',
        ], $headers);
        $respWa->assertStatus(200);
        $this->assertStringContainsString('Batas Lifestyle', $respWa->json('reply_text'));
        $this->assertEquals('Batas Lifestyle', Budget::where('user_jid', $user->jid)->first()->description);
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

    public function test_transactions_require_wallet_and_default_to_cash(): void
    {
        /** @var FinanceService $financeService */
        $financeService = app(FinanceService::class);
        $user = $financeService->findOrCreateUser('62855555@s.whatsapp.net', 'Transaction Wallet User');

        // Ensure default Cash wallet exists
        $cash = $financeService->getOrCreateDefaultWallet($user);
        $cash->balance = 500000;
        $cash->save();
        $user->current_balance = 500000;
        $user->save();

        // Create a secondary wallet (BCA)
        $bca = $financeService->addWallet($user, 'BCA', 1000000, 'BANK');

        // 1. Create transaction via API without wallet_id -> must default to Cash
        $resDefault = $this->actingAs($user)->postJson('/api/v1/transactions', [
            'type'        => 'EXPENSE',
            'amount'      => 50000,
            'description' => 'Makan siang default Cash',
        ]);
        $resDefault->assertStatus(201);
        $this->assertEquals($cash->id, $resDefault->json('data.wallet_id'));
        $this->assertEquals('Cash', $resDefault->json('data.wallet_name'));
        $this->assertEquals(450000, $cash->fresh()->balance);

        // 2. Create transaction via API with specific wallet_id (BCA)
        $resBca = $this->actingAs($user)->postJson('/api/v1/transactions', [
            'type'        => 'EXPENSE',
            'amount'      => 100000,
            'description' => 'Beli buku via BCA',
            'wallet_id'   => $bca->id,
        ]);
        $resBca->assertStatus(201);
        $this->assertEquals($bca->id, $resBca->json('data.wallet_id'));
        $this->assertEquals('BCA', $resBca->json('data.wallet_name'));
        $this->assertEquals(900000, $bca->fresh()->balance);

        // 3. Index must include wallet info
        $resIndex = $this->actingAs($user)->getJson('/api/v1/transactions');
        $resIndex->assertStatus(200);
        $items = $resIndex->json('data.items');
        $this->assertNotEmpty($items);
        $this->assertNotNull($items[0]['wallet_id']);
        $this->assertNotNull($items[0]['wallet_name']);

        // 4. Update transaction to switch wallet from BCA to Cash
        $txId = $resBca->json('data.id');
        $resUpdate = $this->actingAs($user)->putJson("/api/v1/transactions/{$txId}", [
            'amount'      => 100000,
            'description' => 'Beli buku dipindah ke Cash',
            'wallet_id'   => $cash->id,
        ]);
        $resUpdate->assertStatus(200);
        $this->assertEquals($cash->id, $resUpdate->json('data.transaction.wallet_id'));
        $this->assertEquals('Cash', $resUpdate->json('data.transaction.wallet_name'));
        // BCA balance should be reverted (+100k) to 1.000.000
        $this->assertEquals(1000000, $bca->fresh()->balance);
        // Cash balance should be debited (-100k) from 450.000 to 350.000
        $this->assertEquals(350000, $cash->fresh()->balance);

        // 5. WhatsApp parsing default to Cash
        $secret = config('app.gateway_webhook_secret', env('GATEWAY_WEBHOOK_SECRET'));
        $headers = $secret ? ['X-Gateway-Secret' => $secret] : [];
        $user->pin_status = 'ACTIVE';
        $user->pin_hash = bcrypt('123456');
        $user->save();

        $respWa = $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => 'msg_tx_cash_1',
            'from_jid'     => $user->jid,
            'push_name'    => 'WA User',
            'message_text' => 'keluar 25rb kopi',
        ], $headers);
        $respWa->assertStatus(200);
        $this->assertStringContainsString('Cash', $respWa->json('reply_text'));
    }
}
