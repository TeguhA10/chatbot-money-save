<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * High-volume database optimizations for millions of transactions and messages.
     */
    public function up(): void
    {
        // 1. Index received_at for fast time-range retention pruning in processed_messages
        Schema::table('processed_messages', function (Blueprint $table) {
            $table->index('received_at');
        });

        // 2. Composite index on transactions for high-speed dashboard analytics and statements
        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['user_jid', 'status', 'transaction_date'], 'idx_user_status_date');
        });

        // 3. Index created_at on payment_orders for order reconciliation and financial reporting
        Schema::table('payment_orders', function (Blueprint $table) {
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_orders', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('idx_user_status_date');
        });

        Schema::table('processed_messages', function (Blueprint $table) {
            $table->dropIndex(['received_at']);
        });
    }
};
