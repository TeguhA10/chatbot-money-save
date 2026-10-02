<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class UserBlockedTest extends TestCase
{
    use RefreshDatabase;

    public function test_blocked_user_receives_blocked_message_on_webhook()
    {
        $jid = '628123456789@s.whatsapp.net';
        User::create([
            'jid'             => $jid,
            'display_name'    => 'Blocked Tester',
            'current_balance' => 100000,
            'is_active'       => false,
            'blocked_reason'  => 'Spam transaksi tidak wajar',
        ]);

        $secret = config('app.gateway_webhook_secret', env('GATEWAY_WEBHOOK_SECRET'));
        $headers = $secret ? ['X-Gateway-Secret' => $secret] : [];

        $response = $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => 'MSG_BLOCKED_1',
            'from_jid'     => $jid,
            'push_name'    => 'Blocked Tester',
            'message_text' => '+50000 bonus',
        ], $headers);

        $response->assertStatus(200);
        $response->assertJson([
            'action' => 'REPLY_TEXT',
        ]);
        $replyText = $response->json('reply_text');
        $this->assertStringContainsString('Akses Dinonaktifkan', $replyText);
        $this->assertStringContainsString('Spam transaksi tidak wajar', $replyText);

        // Balance should NOT change
        $user = User::where('jid', $jid)->first();
        $this->assertEquals(100000, $user->current_balance);
    }

    public function test_blocked_user_cannot_request_otp()
    {
        $phone = '628123456789';
        $jid   = $phone . '@s.whatsapp.net';

        User::create([
            'jid'            => $jid,
            'display_name'   => 'Blocked Web User',
            'is_active'      => false,
            'blocked_reason' => 'Akun ditangguhkan',
        ]);

        $response = $this->postJson('/api/v1/auth/request-otp', [
            'phone' => $phone,
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'error'   => [
                'code' => 'USER_BLOCKED',
            ],
        ]);
    }

    public function test_artisan_commands_block_and_unblock_user()
    {
        $jid = '628999888777@s.whatsapp.net';
        $user = User::create([
            'jid'          => $jid,
            'display_name' => 'CLI Tester',
            'is_active'    => true,
        ]);

        // Block user
        $this->artisan('user:block', [
            'phone'    => '08999888777',
            '--reason' => 'Uji coba blokir via CLI',
        ])->assertExitCode(0);

        $user->refresh();
        $this->assertFalse($user->is_active);
        $this->assertEquals('Uji coba blokir via CLI', $user->blocked_reason);

        // List command includes this user
        $this->artisan('user:blocked-list')
            ->expectsOutputToContain($jid)
            ->assertExitCode(0);

        // Unblock user
        $this->artisan('user:unblock', [
            'phone' => '08999888777',
        ])->assertExitCode(0);

        $user->refresh();
        $this->assertTrue($user->is_active);
        $this->assertNull($user->blocked_reason);
    }
}
