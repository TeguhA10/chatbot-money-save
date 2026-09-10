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
            // Financial-command tests start after the explicit PIN onboarding flow.
            'pin_status'      => 'ACTIVE',
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
        $this->assertStringContainsString('Panduan WA Finance Bot', $res['reply_text']);
        $this->assertStringContainsString('Catat Pengeluaran', $res['reply_text']);
    }

    public function test_it_handles_transaction_list_today_and_month()
    {
        // 1. Empty list
        $emptyRes = $this->sendChat('pengeluaran hari ini');
        $this->assertEquals('REPLY_TEXT', $emptyRes['action']);
        $this->assertStringContainsString('Belum ada pengeluaran', $emptyRes['reply_text']);

        // 2. Add income and expense
        $this->sendChat('+500000 gaji');
        $this->sendChat('keluar 25000 makan siang');
        $this->sendChat('keluar 15000 kopi');

        // 3. Check pengeluaran hari ini
        $expRes = $this->sendChat('pengeluaran hari ini');
        $this->assertEquals('REPLY_TEXT', $expRes['action']);
        $this->assertStringContainsString('Pengeluaran - Hari Ini', $expRes['reply_text']);
        $this->assertStringContainsString('25.000', $expRes['reply_text']);
        $this->assertStringContainsString('15.000', $expRes['reply_text']);
        $this->assertStringContainsString('ubah transaksi ID nominal keterangan', $expRes['reply_text']);

        // 4. Check pengeluaran bulan ini
        $expMonthRes = $this->sendChat('pengeluaran bulan ini');
        $this->assertStringContainsString('Pengeluaran -', $expMonthRes['reply_text']);
        $this->assertStringContainsString('25.000', $expMonthRes['reply_text']);

        // 5. Check pemasukan hari ini
        $incRes = $this->sendChat('pemasukan hari ini');
        $this->assertEquals('REPLY_TEXT', $incRes['action']);
        $this->assertStringContainsString('Pemasukan - Hari Ini', $incRes['reply_text']);
        $this->assertStringContainsString('500.000', $incRes['reply_text']);

        // 6. Check pemasukan bulan ini
        $incMonthRes = $this->sendChat('pemasukan bulan ini');
        $this->assertStringContainsString('Pemasukan -', $incMonthRes['reply_text']);
        $this->assertStringContainsString('500.000', $incMonthRes['reply_text']);
    }

    public function test_it_handles_update_transaction_via_chat()
    {
        $this->sendChat('+1000000');
        $this->sendChat('keluar 50000 makan siang');

        $transaction = \App\Models\Transaction::where('user_jid', $this->jid)
            ->where('type', 'EXPENSE')
            ->first();

        $txId = $transaction->id;

        // 1. Ambiguity warning test (short prefix matching multiple transactions in same millisecond)
        $shortId = substr($txId, 0, 8);
        $ambiguousRes = $this->sendChat("ubah transaksi {$shortId} 75000 makan siang prasmanan");
        $this->assertStringContainsString('tidak unik', $ambiguousRes['reply_text']);

        // 2. Successful update with full UUID
        $res = $this->sendChat("ubah transaksi {$txId} 75000 makan siang prasmanan");

        $this->assertEquals('REPLY_TEXT', $res['action']);
        $this->assertStringContainsString('Transaksi Berhasil Diubah', $res['reply_text']);
        $this->assertStringContainsString('75.000', $res['reply_text']);
        $this->assertStringContainsString('makan siang prasmanan', $res['reply_text']);

        // Verify balance recalculated
        $user = User::where('jid', $this->jid)->first();
        $this->assertEquals(925000, $user->current_balance);

        // 3. Invalid amount
        $invalidRes = $this->sendChat("ubah transaksi {$txId} 0 nominal nol");
        $this->assertStringContainsString('Nominal tidak valid', $invalidRes['reply_text']);
    }

    public function test_it_handles_delete_transaction_via_chat()
    {
        $this->sendChat('+500000');
        $this->sendChat('keluar 50000 belanja');

        $transaction = \App\Models\Transaction::where('user_jid', $this->jid)
            ->where('type', 'EXPENSE')
            ->first();

        $txId = $transaction->id;

        // Delete / void with full UUID
        $res = $this->sendChat("hapus transaksi {$txId}");

        $this->assertEquals('REPLY_TEXT', $res['action']);
        $this->assertStringContainsString('Transaksi Berhasil Dihapus', $res['reply_text']);
        $this->assertStringContainsString('50.000', $res['reply_text']);

        // Verify balance restored
        $user = User::where('jid', $this->jid)->first();
        $this->assertEquals(500000, $user->current_balance);

        // Deleting non-existent transaction
        $notFoundRes = $this->sendChat("hapus transaksi 00000000-0000-0000-0000-000000000000");
        $this->assertStringContainsString('tidak ditemukan', $notFoundRes['reply_text']);
    }
}
