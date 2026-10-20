<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'jid'             => '6281234567890@s.whatsapp.net',
            'phone_number'    => '6281234567890',
            'display_name'    => 'Sultan Budi',
            'current_balance' => 1000000,
            'tier'            => 'FREE',
            'free_financial_message_count' => 15,
        ]);

        Sanctum::actingAs($this->user);
    }

    public function test_can_retrieve_subscription_status(): void
    {
        $response = $this->getJson('/api/v1/subscription/status');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tier', 'FREE')
            ->assertJsonPath('data.is_pro', false)
            ->assertJsonPath('data.remaining', 85)
            ->assertJsonPath('data.used', 15);
    }

    public function test_can_create_monthly_subscription_order(): void
    {
        $response = $this->postJson('/api/v1/subscription/create-order', [
            'plan' => 'monthly',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('gross_amount', 25000)
            ->assertJsonPath('plan', 'monthly')
            ->assertJsonPath('plan_name', 'HematWoy Pro 30 Hari');

        $this->assertDatabaseHas('payment_orders', [
            'user_jid'     => $this->user->jid,
            'gross_amount' => 25000,
            'status'       => 'PENDING',
        ]);
    }

    public function test_can_create_annual_subscription_order(): void
    {
        $response = $this->postJson('/api/v1/subscription/create-order', [
            'plan' => 'annual',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('gross_amount', 199000)
            ->assertJsonPath('plan', 'annual')
            ->assertJsonPath('plan_name', 'HematWoy Pro 1 Tahun');

        $this->assertDatabaseHas('payment_orders', [
            'user_jid'     => $this->user->jid,
            'gross_amount' => 199000,
            'status'       => 'PENDING',
        ]);
    }
}
