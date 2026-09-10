<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Services\EncryptionService;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('tier')->default('FREE')->index();
            $table->timestamp('subscription_expires_at')->nullable();
            $table->unsignedInteger('free_financial_message_count')->default(0);
            $table->string('pin_status')->default('PENDING_SETUP');
            $table->string('pin_hash')->nullable();
            $table->string('recovery_code_hash')->nullable();
            $table->string('encryption_salt')->nullable();
            $table->unsignedInteger('key_version')->default(1);
            $table->text('encrypted_current_balance')->nullable();
            $table->timestamp('last_pin_verified_at')->nullable();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->text('encrypted_amount')->nullable();
            $table->text('encrypted_balance_after')->nullable();
            $table->unsignedInteger('key_version')->default(1);
        });

        // Existing rows are migrated in-place before their legacy plaintext values
        // are overwritten. A deployment must set FINANCE_ENCRYPTION_KEY first.
        $crypto = app(EncryptionService::class);
        DB::table('users')->orderBy('jid')->each(function (object $user) use ($crypto) {
            DB::table('users')->where('jid', $user->jid)->update([
                'encrypted_current_balance' => $crypto->encryptForStorage((int) $user->current_balance),
                'current_balance' => 0,
            ]);
        });
        DB::table('transactions')->orderBy('id')->each(function (object $transaction) use ($crypto) {
            DB::table('transactions')->where('id', $transaction->id)->update([
                'encrypted_amount' => $crypto->encryptForStorage((int) $transaction->amount),
                'encrypted_balance_after' => $crypto->encryptForStorage((int) $transaction->balance_after),
                'amount' => 0,
                'balance_after' => 0,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', fn (Blueprint $table) => $table->dropColumn(['encrypted_amount', 'encrypted_balance_after', 'key_version']));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['tier', 'subscription_expires_at', 'free_financial_message_count', 'pin_status', 'pin_hash', 'recovery_code_hash', 'encryption_salt', 'key_version', 'encrypted_current_balance', 'last_pin_verified_at']));
    }
};
