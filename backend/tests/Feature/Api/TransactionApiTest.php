<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\User;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TransactionApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\CategorySeeder::class);

        $this->user = User::create([
            'jid'             => '628123456789@s.whatsapp.net',
            'display_name'    => 'API Tester',
            'current_balance' => 0,
            'is_active'       => true,
        ]);

        $this->token = $this->user->createToken('test_token')->plainTextToken;
    }

    private function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept'        => 'application/json',
        ];
    }

    public function test_auth_flow_otp_request_and_verification()
    {
        $phone = '628987654321';

        // 1. Request OTP
        $req = $this->postJson('/api/v1/auth/request-otp', ['phone' => $phone]);
        $req->assertStatus(200);
        $req->assertJson(['success' => true]);

        $cachedOtp = Cache::get("otp:{$phone}@s.whatsapp.net");
        $this->assertNotNull($cachedOtp);
        $this->assertEquals(6, strlen($cachedOtp));

        // 2. Verify wrong OTP
        $fail = $this->postJson('/api/v1/auth/verify-otp', [
            'phone' => $phone,
            'otp'   => '000000',
        ]);
        $fail->assertStatus(422);

        // 3. Verify correct OTP
        $ok = $this->postJson('/api/v1/auth/verify-otp', [
            'phone' => $phone,
            'otp'   => $cachedOtp,
        ]);
        $ok->assertStatus(200);
        $ok->assertJsonPath('success', true);
        $this->assertNotEmpty($ok->json('data.token'));
    }

    public function test_profile_endpoint_returns_user_data()
    {
        $res = $this->getJson('/api/v1/profile', $this->authHeaders());

        $res->assertStatus(200);
        $res->assertJsonPath('success', true);
        $res->assertJsonPath('data.jid', $this->user->jid);
    }

    public function test_transaction_crud_flow()
    {
        $foodCat = Category::where('name', 'Makanan & Minuman')->first();

        // 1. Create Income
        $inc = $this->postJson('/api/v1/transactions', [
            'type'        => 'INCOME',
            'amount'      => 1000000,
            'description' => 'Gaji Project',
        ], $this->authHeaders());

        $inc->assertStatus(201);
        $inc->assertJsonPath('data.amount', 1000000);
        $inc->assertJsonPath('data.balance_after', 1000000);

        // 2. Create Expense
        $exp = $this->postJson('/api/v1/transactions', [
            'type'        => 'EXPENSE',
            'amount'      => 50000,
            'category_id' => $foodCat->id,
            'description' => 'Nasi Padang',
        ], $this->authHeaders());

        $exp->assertStatus(201);
        $exp->assertJsonPath('data.amount', 50000);
        $exp->assertJsonPath('data.balance_after', 950000);
        $expId = $exp->json('data.id');

        // 3. List Transactions
        $list = $this->getJson('/api/v1/transactions', $this->authHeaders());
        $list->assertStatus(200);
        $list->assertJsonPath('data.pagination.total_items', 2);

        // 4. Update Transaction (PUT)
        $put = $this->putJson("/api/v1/transactions/{$expId}", [
            'amount'      => 75000,
            'description' => 'Nasi Padang + Ayam Bakar',
        ], $this->authHeaders());
        $put->assertStatus(200);
        $put->assertJsonPath('success', true);
        $put->assertJsonPath('data.transaction.amount', 75000);
        $put->assertJsonPath('data.transaction.description', 'Nasi Padang + Ayam Bakar');
        $put->assertJsonPath('data.new_balance', 925000);

        $this->user->refresh();
        $this->assertEquals(925000, $this->user->current_balance);

        // 5. Void (Destroy) Transaction
        $del = $this->deleteJson("/api/v1/transactions/{$expId}", [], $this->authHeaders());
        $del->assertStatus(200);
        $del->assertJsonPath('success', true);

        // Balance should restore to 1.000.000
        $this->user->refresh();
        $this->assertEquals(1000000, $this->user->current_balance);
    }

    public function test_category_endpoints()
    {
        // 1. List default categories
        $list = $this->getJson('/api/v1/categories', $this->authHeaders());
        $list->assertStatus(200);
        $this->assertGreaterThanOrEqual(8, count($list->json('data')));

        // 2. Create custom category
        $create = $this->postJson('/api/v1/categories', [
            'name' => 'Koleksi Buku',
            'type' => 'EXPENSE',
            'icon' => '📚',
        ], $this->authHeaders());

        $create->assertStatus(201);
        $create->assertJsonPath('data.name', 'Koleksi Buku');

        // 3. Duplicate rejected
        $dup = $this->postJson('/api/v1/categories', [
            'name' => 'Koleksi Buku',
            'type' => 'EXPENSE',
        ], $this->authHeaders());

        $dup->assertStatus(422);
    }

    public function test_analytics_endpoints()
    {
        $financeService = app(FinanceService::class);
        $financeService->recordTransaction($this->user, [
            'type'        => 'INCOME',
            'amount'      => 2000000,
            'description' => 'Gaji',
            'raw_text'    => '+2jt gaji',
        ]);
        $financeService->recordTransaction($this->user, [
            'type'        => 'EXPENSE',
            'amount'      => 100000,
            'description' => 'Makan',
            'raw_text'    => 'keluar 100rb makan',
        ]);

        // 1. Summary
        $sum = $this->getJson('/api/v1/analytics/summary', $this->authHeaders());
        $sum->assertStatus(200);
        $sum->assertJsonPath('data.total_income', 2000000);
        $sum->assertJsonPath('data.total_expense', 100000);

        // 2. Category Breakdown
        $cat = $this->getJson('/api/v1/analytics/category-breakdown', $this->authHeaders());
        $cat->assertStatus(200);
        $this->assertIsArray($cat->json('data'));

        // 3. Monthly Trend
        $trend = $this->getJson('/api/v1/analytics/monthly-trend', $this->authHeaders());
        $trend->assertStatus(200);
        $this->assertIsArray($trend->json('data'));
        $this->assertCount(6, $trend->json('data'));
    }
}
