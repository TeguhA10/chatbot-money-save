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
        Schema::create('recurring_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_jid');
            $table->uuid('wallet_id')->nullable();
            $table->uuid('category_id')->nullable();
            $table->string('type', 32)->default('EXPENSE');
            $table->text('encrypted_amount');
            $table->string('description', 255);
            $table->string('frequency', 32)->default('MONTHLY');
            $table->smallInteger('day_of_month')->nullable()->default(1);
            $table->date('next_run_date');
            $table->timestamp('last_run_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('user_jid')->references('jid')->on('users')->cascadeOnDelete();
            $table->foreign('wallet_id')->references('id')->on('wallets')->nullOnDelete();
            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
            $table->index(['is_active', 'next_run_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_schedules');
    }
};
