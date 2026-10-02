<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class UserBlockCommand extends Command
{
    protected $signature = 'user:block 
                            {phone : Nomor HP WhatsApp (contoh: 08123456789 atau 628123456789@s.whatsapp.net)}
                            {--reason= : Alasan pemblokiran (ditampilkan ke pengguna)}';

    protected $description = 'Blokir pengguna WhatsApp tertentu dari sistem chatbot & web';

    public function handle(): int
    {
        $input = trim($this->argument('phone'));
        $reason = $this->option('reason') ?: 'Pelanggaran ketentuan penggunaan atau aktivitas mencurigakan.';

        $jid = $this->normalizeJid($input);

        $user = User::where('jid', $jid)->first();

        if (! $user) {
            if ($this->confirm("User dengan JID {$jid} belum terdaftar di database. Apakah ingin memblokir nomor ini secara preventif?", true)) {
                $user = User::create([
                    'jid'            => $jid,
                    'display_name'   => 'Blocked User',
                    'current_balance' => 0,
                    'is_active'      => false,
                    'blocked_reason' => $reason,
                ]);
                $this->info("✅ Nomor {$jid} berhasil dibuat dan langsung DIBLOKIR.");
                return Command::SUCCESS;
            }
            $this->warn("Operasi dibatalkan.");
            return Command::FAILURE;
        }

        $user->is_active = false;
        $user->blocked_reason = $reason;
        $user->save();

        // Revoke all active personal access tokens if any
        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }

        $this->info("✅ Pengguna berhasil DIBLOKIR!");
        $this->table(
            ['JID', 'Nama', 'Status', 'Alasan Pemblokiran'],
            [
                [$user->jid, $user->display_name ?: '-', 'NONAKTIF / DIBLOKIR', $user->blocked_reason],
            ]
        );

        $this->comment("Pesan yang akan diterima user saat mengirim pesan WA:");
        $this->line("-----------------------------------------------------------------");
        $this->line("⛔ *Akses Dinonaktifkan*\nNomor WhatsApp Anda telah diblokir dari layanan ini.\n*Alasan:* {$user->blocked_reason}\n\nSilakan hubungi administrator jika Anda merasa ini adalah kekeliruan.");
        $this->line("-----------------------------------------------------------------");

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
