<?php

namespace Tests\Feature;

use App\Exports\FinanceReportExport;
use App\Exports\MonthlyTransactionsSheet;
use App\Exports\SummaryDashboardSheet;
use App\Models\User;
use App\Services\ExcelExportService;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ExcelExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\CategorySeeder::class);
    }

    public function test_it_generates_finance_report_export_with_correct_decrypted_values_on_dashboard()
    {
        $user = User::factory()->create([
            'jid'             => '628111111111@s.whatsapp.net',
            'current_balance' => 0,
        ]);

        $financeService = app(FinanceService::class);
        $financeService->recordTransaction($user, [
            'type'        => 'INCOME',
            'amount'      => 5000000,
            'description' => 'Gaji Bulanan',
            'raw_text'    => '+5jt gaji',
        ]);
        $financeService->recordTransaction($user, [
            'type'        => 'EXPENSE',
            'amount'      => 50000,
            'description' => 'Makan Siang',
            'raw_text'    => 'keluar 50rb makan siang',
        ]);

        $export = new FinanceReportExport($user, (int) now()->year);
        $sheets = $export->sheets();

        // Must have at least 2 sheets: Dashboard and the current month
        $this->assertGreaterThanOrEqual(2, count($sheets));

        /** @var SummaryDashboardSheet $dashboardSheet */
        $dashboardSheet = $sheets[0];
        /** @var MonthlyTransactionsSheet $monthlySheet */
        $monthlySheet = $sheets[1];

        $this->assertInstanceOf(SummaryDashboardSheet::class, $dashboardSheet);
        $this->assertEquals('Dashboard', $dashboardSheet->title());
        $this->assertEquals(now()->format('M Y'), $monthlySheet->title());

        // Verify that the dashboard values are properly decrypted and non-zero (Bug Fix Phase 1)
        $dashboardRows = $dashboardSheet->array();
        
        // Row 7 (index 7): Total Pemasukan
        $incomeRow = $dashboardRows[7];
        $this->assertEquals('Total Pemasukan', $incomeRow[0]);
        $this->assertEquals(5000000.0, (float) $incomeRow[1]);

        // Row 8 (index 8): Total Pengeluaran
        $expenseRow = $dashboardRows[8];
        $this->assertEquals('Total Pengeluaran', $expenseRow[0]);
        $this->assertEquals(50000.0, (float) $expenseRow[1]);
    }

    public function test_webhook_excel_export_returns_signed_url_with_export_id()
    {
        $user = User::factory()->create([
            'jid'         => '628222222222@s.whatsapp.net',
            'pin_status'  => 'ACTIVE',
        ]);

        $financeService = app(FinanceService::class);
        $financeService->recordTransaction($user, [
            'type'        => 'EXPENSE',
            'amount'      => 35000,
            'description' => 'Bensin',
        ]);

        $response = $this->postJson('/api/webhook/whatsapp', [
            'message_id'   => 'msg-export-1',
            'from_jid'     => $user->jid,
            'message_text' => 'export excel',
        ], ['X-Gateway-Secret' => config('app.gateway_webhook_secret', env('GATEWAY_WEBHOOK_SECRET', 'local_dev_secret_12345'))]);

        $response->assertStatus(200);
        $response->assertJson([
            'action' => 'SEND_DOCUMENT',
        ]);

        $data = $response->json();
        $this->assertArrayHasKey('file_url', $data);
        $this->assertStringContainsString('/api/v1/export/excel/exp_', $data['file_url']);
        $this->assertStringContainsString('signature=', $data['file_url']);
    }

    public function test_it_downloads_excel_via_single_use_signed_route()
    {
        $user = User::factory()->create([
            'jid'             => '628333333333@s.whatsapp.net',
            'current_balance' => 100000,
        ]);

        $exportId = 'exp_' . Str::ulid();
        Cache::put("export:{$exportId}", [
            'user_jid'   => $user->jid,
            'purpose'    => 'whatsapp_excel',
            'created_at' => now()->timestamp,
        ], 90);

        $signedUrl = URL::temporarySignedRoute(
            'api.export.excel',
            now()->addSeconds(90),
            ['export_id' => $exportId]
        );

        $response = $this->get($signedUrl);

        $response->assertStatus(200);
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('content-type')
        );
        $this->assertStringContainsString(
            'attachment; filename=Laporan_Keuangan_628333333333_',
            $response->headers->get('content-disposition')
        );
    }

    public function test_it_rejects_tampered_signature_or_export_id()
    {
        $user = User::factory()->create([
            'jid' => '628444444444@s.whatsapp.net',
        ]);

        $exportId = 'exp_' . Str::ulid();
        Cache::put("export:{$exportId}", [
            'user_jid'   => $user->jid,
            'purpose'    => 'whatsapp_excel',
            'created_at' => now()->timestamp,
        ], 90);

        $signedUrl = URL::temporarySignedRoute(
            'api.export.excel',
            now()->addSeconds(90),
            ['export_id' => $exportId]
        );

        // Tamper export_id in the URL
        $tamperedUrl = str_replace($exportId, 'exp_tampered_id', $signedUrl);

        $response = $this->get($tamperedUrl);
        $response->assertStatus(403);
    }

    public function test_it_rejects_expired_signature()
    {
        $user = User::factory()->create([
            'jid' => '628555555555@s.whatsapp.net',
        ]);

        $exportId = 'exp_' . Str::ulid();
        Cache::put("export:{$exportId}", [
            'user_jid'   => $user->jid,
            'purpose'    => 'whatsapp_excel',
            'created_at' => now()->timestamp,
        ], 90);

        // Create an already-expired signed URL
        $expiredUrl = URL::temporarySignedRoute(
            'api.export.excel',
            now()->subSeconds(10),
            ['export_id' => $exportId]
        );

        $response = $this->get($expiredUrl);
        $response->assertStatus(403);
    }

    public function test_it_enforces_one_time_use_atomic_claim_preventing_replay()
    {
        $user = User::factory()->create([
            'jid' => '628666666666@s.whatsapp.net',
        ]);

        $exportId = 'exp_' . Str::ulid();
        Cache::put("export:{$exportId}", [
            'user_jid'   => $user->jid,
            'purpose'    => 'whatsapp_excel',
            'created_at' => now()->timestamp,
        ], 90);

        $signedUrl = URL::temporarySignedRoute(
            'api.export.excel',
            now()->addSeconds(90),
            ['export_id' => $exportId]
        );

        // First attempt succeeds (Atomic Pull claims and burns the token)
        $response1 = $this->get($signedUrl);
        $response1->assertStatus(200);

        // Second attempt fails with 410 Gone because ticket has been consumed
        $response2 = $this->get($signedUrl);
        $response2->assertStatus(410);
        $response2->assertJson([
            'status'  => 'error',
            'message' => 'Export ticket expired, invalid, or already consumed.',
        ]);
    }

    public function test_it_handles_concurrent_replay_race_condition()
    {
        $user = User::factory()->create([
            'jid' => '628777777777@s.whatsapp.net',
        ]);

        $exportId = 'exp_' . Str::ulid();
        Cache::put("export:{$exportId}", [
            'user_jid'   => $user->jid,
            'purpose'    => 'whatsapp_excel',
            'created_at' => now()->timestamp,
        ], 90);

        // Simulate concurrent pull:
        // Request 1 claims the key atomically
        $claim1 = Cache::pull("export:{$exportId}");
        $this->assertNotNull($claim1);

        // Request 2 tries to claim the same key concurrently
        $claim2 = Cache::pull("export:{$exportId}");
        $this->assertNull($claim2);
    }

    public function test_it_downloads_excel_via_sanctum_bearer_token()
    {
        $user = User::factory()->create([
            'jid'             => '628888888888@s.whatsapp.net',
            'current_balance' => 250000,
        ]);
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->get('/api/v1/export/excel');

        $response->assertStatus(200);
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('content-type')
        );
    }

    public function test_it_rejects_export_when_unauthenticated_and_no_ticket()
    {
        $response = $this->getJson('/api/v1/export/excel');

        $response->assertStatus(401);
        $response->assertJson([
            'status' => 'error',
        ]);
    }

    public function test_it_purges_expired_export_files_idempotently()
    {
        $exportDir = storage_path('app/exports');
        if (!File::isDirectory($exportDir)) {
            File::makeDirectory($exportDir, 0755, true);
        }

        $testFile = $exportDir . '/old_test_export.xlsx';
        file_put_contents($testFile, 'dummy content');
        // Set modification time to 10 minutes ago
        touch($testFile, time() - 600);

        $this->assertTrue(file_exists($testFile));

        // Run purge command
        $this->artisan('exports:purge --max-age=180')
            ->expectsOutput('Purged 1 expired export files.')
            ->assertExitCode(0);

        $this->assertFalse(file_exists($testFile));

        // Run purge command again to test idempotency (should succeed with 0 purged)
        $this->artisan('exports:purge --max-age=180')
            ->expectsOutput('Purged 0 expired export files.')
            ->assertExitCode(0);
    }
}
