<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->uuid('goal_id')->nullable()->after('to_wallet_id');
            $table->foreign('goal_id')->references('id')->on('financial_goals')->nullOnDelete();
            $table->index(['user_jid', 'goal_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['goal_id']);
            $table->dropIndex(['user_jid', 'goal_id']);
            $table->dropColumn('goal_id');
        });
    }
};
