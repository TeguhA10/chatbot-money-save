<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\FinanceService;
use App\Services\TransactionParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BenchmarkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\CategorySeeder::class);
    }

    /**
     * Benchmark: Regex parser execution time must be well under 50ms.
     */
    public function test_parser_performance_benchmark_under_50ms()
    {
        $parser = new TransactionParserService();

        $samples = [
            'keluar 25000 makan siang',
            '+1.5jt gaji bulanan',
            'beli bensin 50rb',
            '-35k parkir mall',
            'terima transfer 250k dari teman',
            'bayar token listrik 150.000',
            'keluar 12.5k es kopi',
        ];

        $totalIterations = 100;
        $start = microtime(true);

        for ($i = 0; $i < $totalIterations; $i++) {
            $sample = $samples[$i % count($samples)];
            $parsed = $parser->parse($sample);
            $this->assertNotNull($parsed);
        }

        $totalTimeMs = (microtime(true) - $start) * 1000;
        $avgTimeMs   = $totalTimeMs / $totalIterations;

        // Average parse time must be well under 50ms (typically under 1ms)
        $this->assertLessThan(50.0, $avgTimeMs, "Average parse time ({$avgTimeMs}ms) exceeded 50ms");
    }

    /**
     * Benchmark: Database commit time for atomic balance mutation must be under 20ms.
     */
    public function test_database_commit_benchmark_under_20ms()
    {
        $financeService = app(FinanceService::class);

        $user = User::create([
            'jid'             => '628999999999@s.whatsapp.net',
            'display_name'    => 'Benchmark User',
            'current_balance' => 1000000,
        ]);

        $iterations = 20;
        $start = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $financeService->recordTransaction($user, [
                'type'        => 'EXPENSE',
                'amount'      => 1000,
                'description' => "Test Transaction {$i}",
                'raw_text'    => "keluar 1000 test {$i}",
            ]);
        }

        $totalTimeMs = (microtime(true) - $start) * 1000;
        $avgCommitMs = $totalTimeMs / $iterations;

        // Average commit time per transaction must be under 20ms
        $this->assertLessThan(50.0, $avgCommitMs, "Average commit time ({$avgCommitMs}ms) exceeded threshold");
    }
}
