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
        // Categories have been permanently removed from the system.
    }
}
