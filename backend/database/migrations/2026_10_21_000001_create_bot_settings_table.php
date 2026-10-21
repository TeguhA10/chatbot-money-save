<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('bot_settings')) {
            Schema::create('bot_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->string('type')->default('string');
                $table->string('description')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasColumn('users', 'max_free_financial_messages')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedInteger('max_free_financial_messages')->nullable()->after('free_financial_message_count');
            });
        }

        // Seed default max_free_financial_messages so it is easily configurable via table
        DB::table('bot_settings')->updateOrInsert(
            ['key' => 'max_free_financial_messages'],
            [
                'value' => (string) env('FREE_FINANCIAL_MESSAGE_LIMIT', '100'),
                'type' => 'integer',
                'description' => 'Maksimal jumlah pesan & pertanyaan finansial gratis sebelum wajib berlangganan',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'max_free_financial_messages')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('max_free_financial_messages');
            });
        }

        Schema::dropIfExists('bot_settings');
    }
};
