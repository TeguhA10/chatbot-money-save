<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionFilterPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_filter_by_wallet_includes_both_source_and_destination_transfers(): void
    {
        /** @var FinanceService $financeService */
        $financeService = app(FinanceService::class);
        $user = $financeService->findOrCreateUser('62833333@s.whatsapp.net', 'Pagination User');

        $cash = Wallet::where('user_jid', $user->jid)->where('is_default', true)->first();
        $jago = $financeService->addWallet($user, 'Bank Jago', 1000000, 'BANK');

        // Create transaction on Cash
        Transaction::create([
            'user_jid'         => $user->jid,
            'wallet_id'        => $cash->id,
            'type'             => 'EXPENSE',
            'amount'           => 50000,
            'description'      => 'Makan Siang',
            'balance_after'    => 500000,
            'transaction_date' => now(),
            'status'           => 'ACTIVE',
        ]);

        // Create transaction on Jago
        Transaction::create([
            'user_jid'         => $user->jid,
            'wallet_id'        => $jago->id,
            'type'             => 'INCOME',
            'amount'           => 200000,
            'description'      => 'Gaji Freelance',
            'balance_after'    => 1200000,
            'transaction_date' => now(),
            'status'           => 'ACTIVE',
        ]);

        // Create transfer from Cash to Jago
        Transaction::create([
            'user_jid'         => $user->jid,
            'wallet_id'        => $cash->id,
            'to_wallet_id'     => $jago->id,
            'type'             => 'TRANSFER',
            'amount'           => 75000,
            'description'      => 'Pindah Saldo Tabungan',
            'balance_after'    => 425000,
            'transaction_date' => now(),
            'status'           => 'ACTIVE',
        ]);

        // Filter by Cash: should get expense (source) + transfer (source) = 2 items
        $resCash = $this->actingAs($user)->getJson("/api/v1/transactions?wallet_id={$cash->id}");
        $resCash->assertStatus(200);
        $this->assertEquals(2, $resCash->json('data.pagination.total_items'));

        // Filter by Jago: should get income (source) + transfer (destination) = 2 items
        $resJago = $this->actingAs($user)->getJson("/api/v1/transactions?wallet_id={$jago->id}");
        $resJago->assertStatus(200);
        $this->assertEquals(2, $resJago->json('data.pagination.total_items'));
    }

    public function test_filter_by_type_and_search(): void
    {
        /** @var FinanceService $financeService */
        $financeService = app(FinanceService::class);
        $user = $financeService->findOrCreateUser('62844444@s.whatsapp.net', 'Filter User');
        $cash = Wallet::where('user_jid', $user->jid)->where('is_default', true)->first();

        Transaction::create([
            'user_jid'         => $user->jid,
            'wallet_id'        => $cash->id,
            'type'             => 'EXPENSE',
            'amount'           => 25000,
            'description'      => 'Kopi Susu Senja',
            'balance_after'    => 100000,
            'transaction_date' => now(),
            'status'           => 'ACTIVE',
        ]);

        Transaction::create([
            'user_jid'         => $user->jid,
            'wallet_id'        => $cash->id,
            'type'             => 'INCOME',
            'amount'           => 500000,
            'description'      => 'Dividen Saham',
            'balance_after'    => 600000,
            'transaction_date' => now(),
            'status'           => 'ACTIVE',
        ]);

        // Filter type INCOME
        $resType = $this->actingAs($user)->getJson('/api/v1/transactions?type=INCOME');
        $resType->assertStatus(200);
        $this->assertEquals(1, $resType->json('data.pagination.total_items'));
        $this->assertEquals('Dividen Saham', $resType->json('data.items.0.description'));

        // Search query
        $resSearch = $this->actingAs($user)->getJson('/api/v1/transactions?search=Kopi');
        $resSearch->assertStatus(200);
        $this->assertEquals(1, $resSearch->json('data.pagination.total_items'));
        $this->assertEquals('Kopi Susu Senja', $resSearch->json('data.items.0.description'));
    }

    public function test_pagination_auto_clamps_out_of_range_page(): void
    {
        /** @var FinanceService $financeService */
        $financeService = app(FinanceService::class);
        $user = $financeService->findOrCreateUser('62855555@s.whatsapp.net', 'Clamp User');
        $cash = Wallet::where('user_jid', $user->jid)->where('is_default', true)->first();

        // Create 3 transactions
        for ($i = 1; $i <= 3; $i++) {
            Transaction::create([
                'user_jid'         => $user->jid,
                'wallet_id'        => $cash->id,
                'type'             => 'EXPENSE',
                'amount'           => 10000 * $i,
                'description'      => "Transaksi {$i}",
                'balance_after'    => 100000 - (10000 * $i),
                'transaction_date' => now(),
                'status'           => 'ACTIVE',
            ]);
        }

        // Request page 99 with limit 10
        // Auto-clamping should bring page back to 1 and return the items rather than returning an empty page
        $res = $this->actingAs($user)->getJson('/api/v1/transactions?page=99&limit=10');
        $res->assertStatus(200);
        $this->assertEquals(1, $res->json('data.pagination.page'));
        $this->assertEquals(3, count($res->json('data.items')));
    }
}
