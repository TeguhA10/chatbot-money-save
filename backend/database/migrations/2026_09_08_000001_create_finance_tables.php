<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates the four core tables for the WA Finance Tracker:
     * users, categories, transactions, processed_messages
     */
    public function up(): void
    {
        // 1. Users table (Partitioned by unique WhatsApp JID)
        // Note: We rename the default Laravel users table by dropping it first
        // and replacing it with our finance-specific users schema.
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->string('jid')->primary(); // e.g. "6281234567890@s.whatsapp.net"
            $table->string('display_name')->default('');
            $table->bigInteger('current_balance')->default(0); // Exact integer Rupiah (no float!)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Categories table (System global defaults + User-scoped custom categories)
        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_jid')->nullable(); // NULL = global default, non-null = user-scoped custom
            $table->string('name');
            $table->enum('type', ['EXPENSE', 'INCOME']);
            $table->string('icon')->default('📌');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->foreign('user_jid')->references('jid')->on('users')->cascadeOnDelete();
            $table->index(['user_jid', 'type']);
            $table->unique(['user_jid', 'name']); // Prevent duplicate category names per user
        });

        // 3. Transactions table (ACID Ledger — stores balance_after for fast balance lookups)
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_jid');
            $table->uuid('category_id')->nullable();
            $table->enum('type', ['EXPENSE', 'INCOME']);
            $table->bigInteger('amount'); // Must be > 0; stored in integer Rupiah
            $table->string('description')->default('');
            $table->bigInteger('balance_after'); // Snapshot of balance after this transaction
            $table->timestamp('transaction_date')->useCurrent();
            $table->enum('status', ['ACTIVE', 'VOIDED'])->default('ACTIVE');
            $table->timestamps();

            $table->foreign('user_jid')->references('jid')->on('users')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();

            $table->index(['user_jid', 'transaction_date']);
            $table->index(['user_jid', 'status']);
        });

        // 4. Processed Messages table (WhatsApp Message Idempotency — prevents duplicate processing)
        Schema::create('processed_messages', function (Blueprint $table) {
            $table->string('message_id')->primary(); // WA message ID (3EB0...)
            $table->string('user_jid');
            $table->timestamp('received_at')->useCurrent();

            $table->index('user_jid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('processed_messages');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('users');
    }
};
