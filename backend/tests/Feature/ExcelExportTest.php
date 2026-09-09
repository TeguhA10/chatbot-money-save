<?php

namespace Tests\Feature;

use App\Exports\FinanceReportExport;
use App\Models\Category;
use App\Models\User;
use App\Services\ExcelExportService;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_it_generates_finance_report_export_with_multiple_sheets()
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
        $this->assertEquals('Dashboard', $sheets[0]->title());
        $this->assertEquals(now()->format('M Y'), $sheets[1]->title());
    }

    public function test_it_downloads_excel_via_user_jid_query_parameter()
    {
        $user = User::factory()->create([
            'jid'             => '628222222222@s.whatsapp.net',
            'current_balance' => 100000,
        ]);

        $response = $this->get('/api/v1/export/excel?user_jid=' . urlencode($user->jid));

        $response->assertStatus(200);
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('content-type')
        );
        $this->assertStringContainsString(
            'attachment; filename=Laporan_Keuangan_628222222222_',
            $response->headers->get('content-disposition')
        );
    }

    public function test_it_downloads_excel_via_sanctum_bearer_token()
    {
        $user = User::factory()->create([
            'jid'             => '628333333333@s.whatsapp.net',
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

    public function test_it_rejects_export_when_unauthenticated_and_no_user_jid()
    {
        $response = $this->getJson('/api/v1/export/excel');

        $response->assertStatus(401);
        $response->assertJson([
            'status' => 'error',
        ]);
    }

    public function test_export_service_stores_file_to_disk()
    {
        Excel::fake();

        $user = User::factory()->create([
            'jid' => '628444444444@s.whatsapp.net',
        ]);

        $service = app(ExcelExportService::class);
        $path = $service->store($user);

        Excel::assertStored($path, 'local');
    }
}
