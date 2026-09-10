<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PinOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_financial_message_requires_pin_and_pin_setup_returns_one_time_recovery_code(): void
    {
        $headers = ['X-Gateway-Secret' => 'local_dev_secret_12345'];
        $this->postJson('/api/webhook/whatsapp', ['message_id'=>'pin-first','from_jid'=>'628111@s.whatsapp.net','message_text'=>'keluar 50000 makan'], $headers)
            ->assertOk()->assertJsonPath('action','REPLY_TEXT')->assertJsonPath('reply_text','Sebelum transaksi pertama, buat PIN 6 digit: `set pin 123456`.');
        $reply=$this->postJson('/api/webhook/whatsapp', ['message_id'=>'pin-set','from_jid'=>'628111@s.whatsapp.net','message_text'=>'set pin 123456'], $headers)
            ->assertOk()->json('reply_text');
        $this->assertStringContainsString('Recovery Code', $reply);
        $user=User::findOrFail('628111@s.whatsapp.net');
        $this->assertSame('ACTIVE',$user->pin_status);
        $this->assertNotNull($user->recovery_code_hash);
    }
}
