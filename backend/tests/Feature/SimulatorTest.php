<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SimulatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_simulator_status_endpoint_returns_initial_state(): void
    {
        $response = $this->getJson('/api/v1/simulator/status?visitor_token=test_tok_1');

        $response->assertOk()
            ->assertJsonPath('attempts', 0)
            ->assertJsonPath('max_attempts', 3)
            ->assertJsonPath('is_locked', false);
    }

    public function test_simulator_generates_real_bot_reply_cards(): void
    {
        $response = $this->postJson('/api/v1/simulator', [
            'message_text'  => 'keluar 25000 nasi padang',
            'visitor_token' => 'test_tok_2',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'OK')
            ->assertJsonPath('attempts', 1);

        $reply = $response->json('reply_text');
        $this->assertStringContainsString('Pengeluaran', $reply);
        $this->assertStringContainsString('Rp25.000', $reply);
    }

    public function test_simulator_enforces_maximum_three_attempts_and_locks(): void
    {
        $token = 'test_tok_limit';

        for ($i = 1; $i <= 3; $i++) {
            $resp = $this->postJson('/api/v1/simulator', [
                'message_text'  => "keluar {$i}0000 makan",
                'visitor_token' => $token,
            ]);
            $resp->assertOk()
                ->assertJsonPath('attempts', $i);
        }

        // 4th attempt must be rejected with 429
        $rejected = $this->postJson('/api/v1/simulator', [
            'message_text'  => 'keluar 50000 makan',
            'visitor_token' => $token,
        ]);

        $rejected->assertStatus(429)
            ->assertJsonPath('status', 'LIMIT_REACHED')
            ->assertJsonPath('attempts', 3);

        // Status check must report locked
        $statusResp = $this->getJson("/api/v1/simulator/status?visitor_token={$token}");
        $statusResp->assertOk()
            ->assertJsonPath('is_locked', true)
            ->assertJsonPath('attempts', 3);
    }
}
