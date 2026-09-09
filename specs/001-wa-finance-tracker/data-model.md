# Data Model: WhatsApp Personal Finance Tracker (Laravel Eloquent)

**Feature Branch**: `001-wa-finance-tracker`
**Date**: 2026-09-08
**Status**: Ready

## 1. Laravel Eloquent Migrations Schema

```php
// database/migrations/2026_09_08_000001_create_finance_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Users table (Partitioned by unique WhatsApp JID)
        Schema::create('users', function (Blueprint $table) {
            $table->string('jid')->primary(); // e.g. "6281234567890@s.whatsapp.net"
            $table->string('display_name')->default('');
            $table->bigInteger('current_balance')->default(0); // Exact integer Rupiah
            $table->timestamps();
        });

        // 2. Categories table (System defaults + User custom categories)
        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_jid')->nullable(); // NULL = global default, non-null = user-scoped
            $table->string('name');
            $table->enum('type', ['EXPENSE', 'INCOME']);
            $table->string('icon')->default('📌');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->foreign('user_jid')->references('jid')->on('users')->cascadeOnDelete();
            $table->index(['user_jid', 'type']);
        });

        // 3. Transactions table (ACID Ledger)
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_jid');
            $table->uuid('category_id')->nullable();
            $table->enum('type', ['EXPENSE', 'INCOME']);
            $table->bigInteger('amount'); // Must be > 0
            $table->string('description');
            $table->bigInteger('balance_after');
            $table->timestamp('transaction_date')->useCurrent();
            $table->enum('status', ['ACTIVE', 'VOIDED'])->default('ACTIVE');
            $table->timestamps();

            $table->foreign('user_jid')->references('jid')->on('users')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();

            $table->index(['user_jid', 'transaction_date']);
            $table->index(['user_jid', 'status']);
        });

        // 4. Processed Messages table (WhatsApp Message Idempotency)
        Schema::create('processed_messages', function (Blueprint $table) {
            $table->string('message_id')->primary();
            $table->string('user_jid');
            $table->timestamp('received_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processed_messages');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('users');
    }
};
```

---

## 2. Eloquent Models & Relationships

### `App\Models\User`
- **Primary Key**: `jid` (string, non-incrementing)
- **Relationships**:
  - `hasMany(Transaction::class, 'user_jid', 'jid')`
  - `hasMany(Category::class, 'user_jid', 'jid')`
- **Casts**:
  - `current_balance` => `integer`

### `App\Models\Category`
- **Primary Key**: `id` (UUID)
- **Relationships**:
  - `belongsTo(User::class, 'user_jid', 'jid')`
  - `hasMany(Transaction::class, 'category_id', 'id')`
- **Scopes**:
  - `scopeForUser($query, $userJid)`: Returns system default categories (`user_jid IS NULL`) plus categories owned by the specific `$userJid`.

### `App\Models\Transaction`
- **Primary Key**: `id` (UUID)
- **Relationships**:
  - `belongsTo(User::class, 'user_jid', 'jid')`
  - `belongsTo(Category::class, 'category_id', 'id')`
- **Scopes**:
  - `scopeActive($query)`: Filters where `status = 'ACTIVE'`
  - `scopeForMonth($query, $year, $month)`

---

## 3. Predefined Seed Categories

```php
// database/seeders/CategorySeeder.php
$defaultCategories = [
    ['name' => 'Makanan & Minuman', 'type' => 'EXPENSE', 'icon' => '🍜', 'is_default' => true],
    ['name' => 'Transportasi',       'type' => 'EXPENSE', 'icon' => '🛵', 'is_default' => true],
    ['name' => 'Belanja',            'type' => 'EXPENSE', 'icon' => '🛍️', 'is_default' => true],
    ['name' => 'Tagihan & Utilitas', 'type' => 'EXPENSE', 'icon' => '💡', 'is_default' => true],
    ['name' => 'Hiburan',            'type' => 'EXPENSE', 'icon' => '🎮', 'is_default' => true],
    ['name' => 'Kesehatan',          'type' => 'EXPENSE', 'icon' => '💊', 'is_default' => true],
    ['name' => 'Gaji & Pendapatan',  'type' => 'INCOME',  'icon' => '💰', 'is_default' => true],
    ['name' => 'Lain-lain',          'type' => 'EXPENSE', 'icon' => '📦', 'is_default' => true],
];
```
