<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Seed the default system-wide categories.
     * These categories have user_jid = NULL, meaning they are available to ALL users.
     * Users can also create personal custom categories (user_jid = their JID).
     */
    public function run(): void
    {
        $defaultCategories = [
            // --- Expense Categories ---
            [
                'id'         => Str::uuid()->toString(),
                'user_jid'   => null, // Global default — no user ownership
                'name'       => 'Makanan & Minuman',
                'type'       => 'EXPENSE',
                'icon'       => '🍜',
                'is_default' => true,
            ],
            [
                'id'         => Str::uuid()->toString(),
                'user_jid'   => null,
                'name'       => 'Transportasi',
                'type'       => 'EXPENSE',
                'icon'       => '🛵',
                'is_default' => true,
            ],
            [
                'id'         => Str::uuid()->toString(),
                'user_jid'   => null,
                'name'       => 'Belanja',
                'type'       => 'EXPENSE',
                'icon'       => '🛍️',
                'is_default' => true,
            ],
            [
                'id'         => Str::uuid()->toString(),
                'user_jid'   => null,
                'name'       => 'Tagihan & Utilitas',
                'type'       => 'EXPENSE',
                'icon'       => '💡',
                'is_default' => true,
            ],
            [
                'id'         => Str::uuid()->toString(),
                'user_jid'   => null,
                'name'       => 'Hiburan',
                'type'       => 'EXPENSE',
                'icon'       => '🎮',
                'is_default' => true,
            ],
            [
                'id'         => Str::uuid()->toString(),
                'user_jid'   => null,
                'name'       => 'Kesehatan',
                'type'       => 'EXPENSE',
                'icon'       => '💊',
                'is_default' => true,
            ],
            [
                'id'         => Str::uuid()->toString(),
                'user_jid'   => null,
                'name'       => 'Lain-lain',
                'type'       => 'EXPENSE',
                'icon'       => '📦',
                'is_default' => true,
            ],
            // --- Income Categories ---
            [
                'id'         => Str::uuid()->toString(),
                'user_jid'   => null,
                'name'       => 'Gaji & Pendapatan',
                'type'       => 'INCOME',
                'icon'       => '💰',
                'is_default' => true,
            ],
            [
                'id'         => Str::uuid()->toString(),
                'user_jid'   => null,
                'name'       => 'Bisnis',
                'type'       => 'INCOME',
                'icon'       => '🏪',
                'is_default' => true,
            ],
            [
                'id'         => Str::uuid()->toString(),
                'user_jid'   => null,
                'name'       => 'Transfer Masuk',
                'type'       => 'INCOME',
                'icon'       => '📨',
                'is_default' => true,
            ],
        ];

        // Use insertOrIgnore to make seeder idempotent (safe to run multiple times)
        foreach ($defaultCategories as $category) {
            DB::table('categories')->insertOrIgnore([
                ...$category,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('✅ Seeded ' . count($defaultCategories) . ' default categories.');
    }
}
