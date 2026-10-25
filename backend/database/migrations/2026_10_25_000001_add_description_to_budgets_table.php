<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('budgets') && !Schema::hasColumn('budgets', 'description')) {
            Schema::table('budgets', function (Blueprint $table) {
                $table->string('description', 255)->default('Batas Pengeluaran Bulanan')->after('user_jid');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('budgets') && Schema::hasColumn('budgets', 'description')) {
            Schema::table('budgets', function (Blueprint $table) {
                $table->dropColumn('description');
            });
        }
    }
};
