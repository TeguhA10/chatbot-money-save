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
        Schema::create('financial_goals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_jid');
            $table->string('name', 100);
            $table->text('encrypted_target_amount');
            $table->text('encrypted_current_amount');
            $table->date('target_date')->nullable();
            $table->string('status', 32)->default('ACTIVE');
            $table->timestamps();

            $table->foreign('user_jid')->references('jid')->on('users')->cascadeOnDelete();
            $table->index(['user_jid', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_goals');
    }
};
