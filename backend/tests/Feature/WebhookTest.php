<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $jid = '628999888777@s.whatsapp.net';
    private string $secret = 'local_dev_secret_12345';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\CategorySeeder::class);
        $this->withHeader('X-Gateway-Secret', $this->secret);

        // Pre-create user so they are not treated as a new user onboarding
        User::create([
            'jid'             => $this->jid,
            'display_name'    => 'Test User',
            'current_balance' => 0,
        ]);
    }

    private function sendChat(string $text, ?string $msgId = null): array
    {
        $response = $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => $msgId ?? ('MSG_' . uniqid()),
            'from_jid'     => $this->jid,
            'push_name'    => 'Test User',
            'message_text' => $text,
            'timestamp'    => time(),
        ]);

        $response->assertStatus(200);
        return $response->json();
    }

    public function test_it_records_income_and_updates_balance()
    {
        $res = $this->sendChat('+1.5jt gaji');

        $this->assertEquals('REPLY_TEXT', $res['action']);
        $this->assertStringContainsString('Pemasukan Tersimpan', $res['reply_text']);
        $this->assertStringContainsString('1.500.000', $res['reply_text']);

        $user = User::where('jid', $this->jid)->first();
        $this->assertEquals(1500000, $user->current_balance);
    }

    public function test_it_records_expense_and_deducts_balance()
    {
        // First add income
        $this->sendChat('+500000');

        // Then record expense
        $res = $this->sendChat('keluar 25000 makan siang');

        $this->assertEquals('REPLY_TEXT', $res['action']);
        $this->assertStringContainsString('Pengeluaran Tersimpan', $res['reply_text']);
        $this->assertStringContainsString('25.000', $res['reply_text']);
        $this->assertStringContainsString('475.000', $res['reply_text']);

        $user = User::where('jid', $this->jid)->first();
        $this->assertEquals(475000, $user->current_balance);
    }

    public function test_it_handles_saldo_inquiry()
    {
        $this->sendChat('+100000 bonus');

        $res = $this->sendChat('saldo');

        $this->assertEquals('REPLY_TEXT', $res['action']);
        $this->assertStringContainsString('Informasi Saldo', $res['reply_text']);
        $this->assertStringContainsString('100.000', $res['reply_text']);
    }

    public function test_it_handles_rekap_hari_ini()
    {
        $this->sendChat('+200000');
        $this->sendChat('keluar 50000 makan');

        $res = $this->sendChat('rekap hari ini');

        $this->assertEquals('REPLY_TEXT', $res['action']);
        $this->assertStringContainsString('Rekap Hari Ini', $res['reply_text']);
        $this->assertStringContainsString('200.000', $res['reply_text']);
        $this->assertStringContainsString('50.000', $res['reply_text']);
    }

    public function test_it_handles_rekap_bulan_ini()
    {
        $this->sendChat('+1000000 gaji');
        $this->sendChat('keluar 150000 belanja');

        $res = $this->sendChat('rekap bulan ini');

        $this->assertEquals('REPLY_TEXT', $res['action']);
        $this->assertStringContainsString('Rekap Bulan', $res['reply_text']);
        $this->assertStringContainsString('1.000.000', $res['reply_text']);
    }

    public function test_it_handles_category_listing_and_creation()
    {
        // List categories
        $res = $this->sendChat('kategori');
        $this->assertStringContainsString('Daftar Kategori', $res['reply_text']);

        // Create new category
        $resAdd = $this->sendChat('tambah kategori Investasi');
        $this->assertStringContainsString('berhasil ditambahkan', $resAdd['reply_text']);
        $this->assertStringContainsString('Investasi', $resAdd['reply_text']);

        // Prevent duplicate
        $resDup = $this->sendChat('tambah kategori Investasi');
        $this->assertStringContainsString('sudah ada', $resDup['reply_text']);
    }

    public function test_it_handles_undo_transaction()
    {
        $this->sendChat('+500000');
        $this->sendChat('keluar 50000 makan');

        $user = User::where('jid', $this->jid)->first();
        $this->assertEquals(450000, $user->current_balance);

        // Undo expense
        $resUndo = $this->sendChat('batal');
        $this->assertStringContainsString('Berhasil Dibatalkan', $resUndo['reply_text']);
        $this->assertStringContainsString('500.000', $resUndo['reply_text']);

        $user->refresh();
        $this->assertEquals(500000, $user->current_balance);
    }

    public function test_it_handles_export_excel_command()
    {
        $this->sendChat('+500000 tabungan');

        $res = $this->sendChat('export excel');

        $this->assertEquals('SEND_DOCUMENT', $res['action']);
        $this->assertArrayHasKey('file_url', $res);
        $this->assertStringContainsString('/api/v1/export/excel', $res['file_url']);
        $this->assertStringEndsWith('.xlsx', $res['file_name']);
    }

    public function test_it_handles_help_command()
    {
        $res = $this->sendChat('bantuan');

        $this->assertEquals('REPLY_TEXT', $res['action']);
        $this->assertStringContainsString('Panduan Lengkap', $res['reply_text']);
        $this->assertStringContainsString('Catat Pengeluaran', $res['reply_text']);
    }
}
