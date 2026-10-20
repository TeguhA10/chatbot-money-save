<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'code'                => 'monthly',
                'name'                => 'Pro Bulanan',
                'tagline'             => '30 Hari Akses',
                'duration_days'       => 30,
                'price'               => 25000,
                'original_price'      => null,
                'discount_percentage' => 0,
                'badge'               => 'Paling Populer',
                'is_popular'          => true,
                'sort_order'          => 1,
                'features'            => [
                    'Unlimited Pencatatan Transaksi',
                    'Multi-Dompet (BCA, Mandiri, Cash, e-Wallet)',
                    'Plafon Anggaran & Anti-Boncos Alert',
                    '1-Klik Ekspor Laporan Excel Lengkap',
                    'AI Financial Spending Insights',
                    'Akses Penuh Web App Dashboard',
                ],
            ],
            [
                'code'                => 'annual',
                'name'                => 'Pro 1 Tahun',
                'tagline'             => '365 Hari Penuh',
                'duration_days'       => 365,
                'price'               => 199000,
                'original_price'      => 300000,
                'discount_percentage' => 35,
                'badge'               => 'HEMAT 35%',
                'is_popular'          => false,
                'sort_order'          => 2,
                'features'            => [
                    'Seluruh Fitur Paket Pro',
                    'Masa Aktif 365 Hari Penuh',
                    'Hemat Rp 101.000 dibanding bulanan',
                    'Prioritas Fitur AI Baru Mendatang',
                ],
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(
                ['code' => $plan['code']],
                $plan
            );
        }
    }
}
