<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary(); $table->string('user_jid');
            $table->string('plan_code')->default('MONTHLY_PRO_25000');
            $table->unsignedBigInteger('price_amount')->default(25000);
            $table->timestamp('starts_at'); $table->timestamp('ends_at');
            $table->string('status')->default('ACTIVE'); $table->string('source_order_id')->nullable(); $table->timestamps();
            $table->foreign('user_jid')->references('jid')->on('users')->cascadeOnDelete(); $table->index(['user_jid', 'status']);
        });
        Schema::create('payment_orders', function (Blueprint $table) {
            $table->string('order_id')->primary(); $table->string('user_jid');
            $table->unsignedBigInteger('gross_amount')->default(25000); $table->string('currency')->default('IDR');
            $table->string('snap_token')->nullable(); $table->string('payment_url')->nullable(); $table->string('payment_type')->nullable();
            $table->string('status')->default('PENDING'); $table->timestamp('paid_at')->nullable(); $table->json('last_webhook_payload')->nullable(); $table->timestamps();
            $table->foreign('user_jid')->references('jid')->on('users')->cascadeOnDelete(); $table->index(['user_jid', 'status']);
        });
        Schema::create('usage_logs', function (Blueprint $table) {
            $table->uuid('id')->primary(); $table->string('user_jid'); $table->string('message_id')->unique();
            $table->unsignedTinyInteger('quota_units')->default(1); $table->unsignedInteger('transactions_recorded')->default(1); $table->timestamp('consumed_at')->useCurrent(); $table->timestamps();
            $table->foreign('user_jid')->references('jid')->on('users')->cascadeOnDelete(); $table->index(['user_jid', 'consumed_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('usage_logs'); Schema::dropIfExists('payment_orders'); Schema::dropIfExists('subscriptions'); }
};
