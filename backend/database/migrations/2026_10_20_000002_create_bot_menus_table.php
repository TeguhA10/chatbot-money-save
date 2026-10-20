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
        Schema::create('bot_menus', function (Blueprint $table) {
            $table->id();
            $table->string('category');             // e.g. Catat Pengeluaran
            $table->string('category_icon')->nullable(); // e.g. 💸
            $table->string('command');              // e.g. keluar 25000 makan siang
            $table->string('description')->nullable(); // e.g. via bca, analisis MoM
            $table->boolean('is_quick_command')->default(false); // Quick prompt / unrecognized hint
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
        Schema::dropIfExists('bot_menus');
    }
};
