<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        // 1. Remove category_id from transactions
        if (Schema::hasTable('transactions') && Schema::hasColumn('transactions', 'category_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                if (DB::getDriverName() !== 'sqlite') {
                    $table->dropForeign(['category_id']);
                }
                $table->dropColumn('category_id');
            });
        }

        // 2. Remove category_id from recurring_schedules
        if (Schema::hasTable('recurring_schedules') && Schema::hasColumn('recurring_schedules', 'category_id')) {
            Schema::table('recurring_schedules', function (Blueprint $table) {
                if (DB::getDriverName() !== 'sqlite') {
                    $table->dropForeign(['category_id']);
                }
                $table->dropColumn('category_id');
            });
        }

        // 3. Update budgets table: remove category_id, convert to single overall monthly budget per user
        if (Schema::hasTable('budgets')) {
            if (Schema::hasColumn('budgets', 'category_id')) {
                Schema::table('budgets', function (Blueprint $table) {
                    if (DB::getDriverName() !== 'sqlite') {
                        $table->dropForeign(['category_id']);
                        $table->dropUnique(['user_jid', 'category_id']);
                    }
                    $table->dropColumn('category_id');
                });
            }

            try {
                Schema::table('budgets', function (Blueprint $table) {
                    $table->unique('user_jid');
                });
            } catch (\Throwable) {
                // Ignore if unique index already exists or table structure handles it
            }
        }

        // 4. Drop categories table permanently
        Schema::dropIfExists('categories');

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Irreversible as per user requirement to fully eliminate categories
    }
};
