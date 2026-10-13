<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class VaultSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'jid'             => '628555123456@s.whatsapp.net',
            'phone_number'    => '628555123456',
            'display_name'    => 'Security Tester',
            'current_balance' => 0,
            'is_active'       => true,
            'pin_status'      => 'PENDING_SETUP',
        ]);

        $this->token = $this->user->createToken('test_token')->plainTextToken;
    }

    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept'        => 'application/json',
        ];
    }

    public function test_vault_setup_with_alphanumeric_passphrase()
    {
        $response = $this->postJson('/api/v1/vault/setup', [
            'pin' => 'RahasiaKuat2026!#',
        ], $this->headers());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['recovery_code', 'encryption_salt', 'is_unlocked']]);

        $this->user->refresh();
        $this->assertEquals('ACTIVE', $this->user->pin_status);
        $this->assertNotEmpty($this->user->pin_hash);
        $this->assertNotEmpty($this->user->recovery_code_hash);
    }

    public function test_vault_unlock_success_and_lock()
    {
        // 1. Setup
        $this->postJson('/api/v1/vault/setup', ['pin' => 'MySafePass123'], $this->headers());

        // 2. Lock
        $lockRes = $this->postJson('/api/v1/vault/lock', [], $this->headers());
        $lockRes->assertOk()->assertJsonPath('data.is_unlocked', false);

        // 3. Unlock with correct passphrase
        $unlockRes = $this->postJson('/api/v1/vault/unlock', ['pin' => 'MySafePass123'], $this->headers());
        $unlockRes->assertOk()->assertJsonPath('success', true)->assertJsonPath('data.is_unlocked', true);
    }

    public function test_vault_unlock_fails_with_wrong_passphrase()
    {
        $this->postJson('/api/v1/vault/setup', ['pin' => 'ValidPass123'], $this->headers());

        $res = $this->postJson('/api/v1/vault/unlock', ['pin' => 'WrongPass123'], $this->headers());
        $res->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'INVALID_PIN')
            ->assertJsonPath('error.remaining_attempts', 4);
    }

    public function test_vault_brute_force_lockout_after_five_failed_attempts()
    {
        $this->postJson('/api/v1/vault/setup', ['pin' => 'MasterPass123'], $this->headers());

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/v1/vault/unlock', ['pin' => 'WrongGuess'], $this->headers())->assertStatus(422);
        }

        // 5th attempt triggers lockout
        $fifth = $this->postJson('/api/v1/vault/unlock', ['pin' => 'WrongGuess'], $this->headers());
        $fifth->assertStatus(429)
            ->assertJsonPath('error.code', 'LOCKED_OUT');

        // Subsequent attempt is blocked by rate limiter
        $sixth = $this->postJson('/api/v1/vault/unlock', ['pin' => 'MasterPass123'], $this->headers());
        $sixth->assertStatus(429)
            ->assertJsonPath('error.code', 'RATE_LIMITED');
    }

    public function test_vault_reset_with_recovery_code()
    {
        $setupRes = $this->postJson('/api/v1/vault/setup', ['pin' => 'OldPassword123'], $this->headers());
        $recoveryCode = $setupRes->json('data.recovery_code');

        $resetRes = $this->postJson('/api/v1/vault/reset', [
            'recovery_code' => $recoveryCode,
            'new_pin'       => 'NewBetterPass456',
        ], $this->headers());

        $resetRes->assertOk()->assertJsonPath('success', true);

        // Verify old pass fails and new pass succeeds
        $this->postJson('/api/v1/vault/unlock', ['pin' => 'OldPassword123'], $this->headers())->assertStatus(422);
        $this->postJson('/api/v1/vault/unlock', ['pin' => 'NewBetterPass456'], $this->headers())->assertOk();
    }
}
