<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserFriendlyEnhancementTest extends TestCase
{
    use RefreshDatabase;

    private array $headers = ['X-Gateway-Secret' => 'local_dev_secret_12345'];

    public function test_typo_tolerant_commands_work_seamlessly(): void
    {
        $jid = '628999@s.whatsapp.net';

        // 1. Setup PIN with friendly 'buat pin'
        $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => 'msg-pin-1',
            'from_jid'     => $jid,
            'message_text' => 'buat pin 123456',
        ], $this->headers)->assertOk()->assertJsonPath('action', 'REPLY_TEXT');

        // 2. Typo-tolerant expense: 'kluar 25rb makan siang'
        $tx = $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => 'msg-tx-1',
            'from_jid'     => $jid,
            'message_text' => 'kluar 25rb makan siang',
        ], $this->headers);
        $tx->assertOk()->assertJsonPath('action', 'REPLY_TEXT');
        $this->assertStringContainsString('Pengeluaran', $tx->json('reply_text'));

        // 3. Typo-tolerant saldo: 'saldoo'
        $bal = $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => 'msg-bal-1',
            'from_jid'     => $jid,
            'message_text' => 'saldoo',
        ], $this->headers);
        $bal->assertOk()->assertJsonPath('action', 'REPLY_TEXT');
        $this->assertStringContainsString('Saldo Anda', $bal->json('reply_text'));

        // 4. Quick correction without ID: 'koreksi 30rb makan siang enak'
        $corr = $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => 'msg-corr-1',
            'from_jid'     => $jid,
            'message_text' => 'koreksi 30rb makan siang enak',
        ], $this->headers);
        $corr->assertOk()->assertJsonPath('action', 'REPLY_TEXT');
        $this->assertStringContainsString('Transaksi Berhasil Diubah', $corr->json('reply_text'));
        $this->assertStringContainsString('30.000', $corr->json('reply_text'));

        // 5. Typo-tolerant summary: 'rekap bln ini'
        $rekap = $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => 'msg-rekap-1',
            'from_jid'     => $jid,
            'message_text' => 'rekap bln ini',
        ], $this->headers);
        $rekap->assertOk()->assertJsonPath('action', 'REPLY_TEXT');
        $this->assertStringContainsString('Rekap Bulan', $rekap->json('reply_text'));
    }
}
