<?php

namespace Tests\Feature;

use App\Models\BotMenu;
use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynamicPlanAndMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_active_subscription_plans(): void
    {
        SubscriptionPlan::create([
            'code'                => 'monthly',
            'name'                => 'Pro Bulanan',
            'tagline'             => '30 Hari Akses',
            'duration_days'       => 30,
            'price'               => 25000,
            'original_price'      => null,
            'discount_percentage' => 0,
            'badge'               => 'Paling Populer',
            'is_popular'          => true,
            'is_active'           => true,
            'sort_order'          => 1,
            'features'            => ['Unlimited Catat'],
        ]);

        SubscriptionPlan::create([
            'code'                => 'inactive_plan',
            'name'                => 'Paket Tidak Aktif',
            'duration_days'       => 7,
            'price'               => 10000,
            'is_active'           => false,
            'sort_order'          => 99,
        ]);

        $response = $this->getJson('/api/v1/plans');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'monthly')
            ->assertJsonPath('data.0.price', 25000)
            ->assertJsonPath('data.0.formatted_price', 'Rp 25.000');
    }

    public function test_it_returns_active_bot_menus_grouped_and_quick_commands(): void
    {
        BotMenu::create([
            'category'         => 'Catat Pengeluaran',
            'category_icon'    => '💸',
            'command'          => 'keluar 25000 makan siang',
            'description'      => null,
            'is_quick_command' => true,
            'is_active'        => true,
            'sort_order'       => 1,
        ]);

        BotMenu::create([
            'category'         => 'Catat Pengeluaran',
            'category_icon'    => '💸',
            'command'          => 'beli bensin 50rb',
            'description'      => 'via bca',
            'is_quick_command' => false,
            'is_active'        => true,
            'sort_order'       => 2,
        ]);

        $response = $this->getJson('/api/v1/bot-menus');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('grouped.0.category', 'Catat Pengeluaran')
            ->assertJsonPath('grouped.0.category_icon', '💸')
            ->assertJsonCount(2, 'grouped.0.items')
            ->assertJsonCount(1, 'quick_commands');
    }
}
