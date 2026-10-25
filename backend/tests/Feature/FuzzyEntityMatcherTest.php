<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\FinancialGoal;
use App\Models\User;
use App\Models\Wallet;
use App\Services\BudgetService;
use App\Services\FinanceService;
use App\Services\FuzzyEntityMatcherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FuzzyEntityMatcherTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected FuzzyEntityMatcherService $matcher;
    protected FinanceService $financeService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->financeService = app(FinanceService::class);
        $this->matcher = app(FuzzyEntityMatcherService::class);

        $this->user = $this->financeService->findOrCreateUser('628111222333@s.whatsapp.net', 'Budi');
    }

    public function test_fuzzy_score_matches_voice_typos_and_slang(): void
    {
        // sibang vs SeaBank
        $scoreSibang = $this->matcher->scoreSimilarity('sibang', 'SeaBank');
        $this->assertGreaterThanOrEqual(0.70, $scoreSibang);

        // kopi vs GoPay
        $scoreKopi = $this->matcher->scoreSimilarity('kopi', 'GoPay');
        $this->assertGreaterThanOrEqual(0.70, $scoreKopi);

        // bece a vs BCA
        $scoreBca = $this->matcher->scoreSimilarity('bece a', 'BCA');
        $this->assertGreaterThanOrEqual(0.75, $scoreBca);

        // lektop vs Beli Laptop
        $scoreLektop = $this->matcher->scoreSimilarity('lektop', 'Beli Laptop');
        $this->assertGreaterThanOrEqual(0.60, $scoreLektop);
    }

    public function test_resolve_wallet_identifies_existing_wallet_from_voice_typo(): void
    {
        $seaBank = $this->financeService->addWallet($this->user, 'SeaBank', 500000);
        $bca = $this->financeService->addWallet($this->user, 'BCA', 1000000);

        // User speaks "sibang"
        $resolved = $this->matcher->resolveWallet($this->user, 'sibang');
        $this->assertNotNull($resolved);
        $this->assertEquals($seaBank->id, $resolved->id);
        $this->assertEquals('SeaBank', $resolved->name);

        // User speaks "bece a"
        $resolvedBca = $this->matcher->resolveWallet($this->user, 'bece a');
        $this->assertNotNull($resolvedBca);
        $this->assertEquals($bca->id, $resolvedBca->id);
    }

    public function test_add_wallet_blocks_duplicate_creation_when_similar_wallet_exists(): void
    {
        $this->financeService->addWallet($this->user, 'SeaBank', 250000);

        // Trying to create "sibang" should throw an exception preventing duplication
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/sudah ada di akun Anda/i');

        $this->financeService->addWallet($this->user, 'sibang', 100000);
    }

    public function test_record_transaction_with_typo_wallet_name_updates_real_wallet(): void
    {
        $seaBank = $this->financeService->addWallet($this->user, 'SeaBank', 500000);

        // Record expense via 'sibang'
        $tx = $this->financeService->recordTransaction($this->user, [
            'type'        => 'EXPENSE',
            'amount'      => 50000,
            'description' => 'Makan malam sate',
            'wallet_name' => 'sibang',
        ]);

        $this->assertEquals($seaBank->id, $tx->wallet_id);
        $seaBank->refresh();
        $this->assertEquals(450000, $seaBank->balance);
    }

    public function test_resolve_goal_identifies_goal_with_voice_mishearing(): void
    {
        $goal = $this->financeService->createGoal($this->user, 'Beli Laptop', 15000000);

        // User mentions "lektop" or "beli lektop"
        $resolved = $this->matcher->resolveGoal($this->user, 'beli lektop');
        $this->assertNotNull($resolved);
        $this->assertEquals($goal->id, $resolved->id);
    }

    public function test_build_user_context_prompt_contains_all_active_entities(): void
    {
        $this->financeService->addWallet($this->user, 'SeaBank', 750000);
        $this->financeService->createGoal($this->user, 'Liburan Bali', 5000000);
        app(BudgetService::class)->setBudget($this->user, 2000000, 'Batas Jajan Bulanan');

        $prompt = $this->matcher->buildUserContextPrompt($this->user);

        $this->assertStringContainsString('SeaBank', $prompt);
        $this->assertStringContainsString('Liburan Bali', $prompt);
        $this->assertStringContainsString('Batas Jajan Bulanan', $prompt);
        $this->assertStringContainsString('ATURAN KRUSIAL PENCOCOKAN KATA & TYPO VOICE NOTE', $prompt);
    }
}
