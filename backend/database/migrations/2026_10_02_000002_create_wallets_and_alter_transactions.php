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
        // 1. Create wallets table
        Schema::create('wallets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_jid');
            $table->string('name', 100);
            $table->string('type', 32)->default('CASH'); // CASH, BANK, EWALLET, OTHER
            $table->boolean('is_default')->default(false);
            $table->text('encrypted_balance')->nullable();
            $table->timestamps();

            $table->foreign('user_jid')->references('jid')->on('users')->cascadeOnDelete();
            $table->unique(['user_jid', 'name']);
            $table->index(['user_jid', 'is_default']);
        });

        // 2. Alter transactions table
        Schema::table('transactions', function (Blueprint $table) {
            $table->uuid('wallet_id')->nullable()->after('category_id');
            $table->uuid('to_wallet_id')->nullable()->after('wallet_id');

            $table->foreign('wallet_id')->references('id')->on('wallets')->nullOnDelete();
            $table->foreign('to_wallet_id')->references('id')->on('wallets')->nullOnDelete();

            $table->index(['user_jid', 'wallet_id']);
            $table->index(['user_jid', 'type']);
        });

        // Alter type column to allow 'TRANSFER'
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE transactions ALTER COLUMN type TYPE VARCHAR(32)');
            DB::statement('ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_type_check');
            DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_type_check CHECK (type IN ('EXPENSE', 'INCOME', 'TRANSFER'))");
        } elseif ($driver !== 'sqlite') {
            Schema::table('transactions', function (Blueprint $table) {
                $table->string('type', 32)->change();
            });
        }

        // 3. Backfill default 'Cash' wallet for existing users
        try {
            $users = DB::table('users')->get();
            $crypto = app(\App\Services\EncryptionService::class);
            foreach ($users as $user) {
                $walletId = (string) \Illuminate\Support\Str::uuid();
                $encBalance = $user->encrypted_current_balance ?? $crypto->encryptForStorage((int) ($user->current_balance ?? 0));
                
                DB::table('wallets')->insert([
                    'id' => $walletId,
                    'user_jid' => $user->jid,
                    'name' => 'Cash',
                    'type' => 'CASH',
                    'is_default' => true,
                    'encrypted_balance' => $encBalance,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('transactions')
                    ->where('user_jid', $user->jid)
                    ->whereNull('wallet_id')
                    ->update(['wallet_id' => $walletId]);
            }
        } catch (\Throwable $e) {
            // Ignore during dry-run or testing environments where encryption key might not yet be set
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['wallet_id']);
            $table->dropForeign(['to_wallet_id']);
            $table->dropIndex(['user_jid', 'wallet_id']);
            $table->dropIndex(['user_jid', 'type']);
            $table->dropColumn(['wallet_id', 'to_wallet_id']);
        });

        Schema::dropIfExists('wallets');
    }
};
