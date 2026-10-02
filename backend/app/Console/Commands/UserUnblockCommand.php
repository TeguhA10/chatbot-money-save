<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class UserUnblockCommand extends Command
{
    protected $signature = 'user:unblock 
                            {phone : Nomor HP WhatsApp (contoh: 08123456789 atau 628123456789@s.whatsapp.net)}';

    protected $description = 'Buka blokir pengguna WhatsApp tertentu';

    public function handle(): int
    {
        $input = trim($this->argument('phone'));
        $jid = $this->normalizeJid($input);

        $user = User::where('jid', $jid)->first();

        if (! $user) {
            $this->error("❌ User dengan JID {$jid} tidak ditemukan.");
            return Command::FAILURE;
        }

        if ($user->is_active) {
            $this->warn("⚠️ User {$jid} saat ini sudah aktif (tidak diblokir).");
            return Command::SUCCESS;
        }

        $user->is_active = true;
        $user->blocked_reason = null;
        $user->save();

        $this->info("✅ Blokir pengguna {$jid} berhasil DIBUKA!");
        $this->table(
            ['JID', 'Nama', 'Status'],
            [
                [$user->jid, $user->display_name ?: '-', 'AKTIF (Bisa Chat & Login)'],
            ]
        );

        return Command::SUCCESS;
    }

    private function normalizeJid(string $input): string
    {
        if (str_ends_with($input, '@s.whatsapp.net')) {
            return $input;
        }

        $digits = preg_replace('/\D/', '', $input);
        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        }

        return $digits . '@s.whatsapp.net';
    }
}
