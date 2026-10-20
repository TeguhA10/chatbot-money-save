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
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // e.g. monthly, annual
            $table->string('name');            // e.g. Pro Bulanan, Pro 1 Tahun
            $table->string('tagline')->nullable(); // e.g. 30 Hari Akses
            $table->unsignedInteger('duration_days')->default(30);
            $table->unsignedBigInteger('price');   // in IDR, e.g. 25000
            $table->unsignedBigInteger('original_price')->nullable();
            $table->unsignedSmallInteger('discount_percentage')->default(0);
            $table->string('badge')->nullable(); // e.g. Paling Populer, HEMAT 35%
            $table->json('features')->nullable();
            $table->boolean('is_popular')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
