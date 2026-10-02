<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class UserBlockedListCommand extends Command
{
    protected $signature = 'user:blocked-list';

    protected $description = 'Tampilkan daftar seluruh pengguna yang sedang diblokir';

    public function handle(): int
    {
        $blockedUsers = User::where('is_active', false)
            ->orderBy('updated_at', 'desc')
            ->get();

        if ($blockedUsers->isEmpty()) {
            $this->info("✨ Tidak ada pengguna yang sedang diblokir. Seluruh akun aktif.");
            return Command::SUCCESS;
        }

        $this->warn("Daftar Pengguna Yang Sedang Diblokir (" . $blockedUsers->count() . " user):");

        $rows = $blockedUsers->map(function ($u) {
            return [
                'JID'            => $u->jid,
                'Nama'           => $u->display_name ?: '-',
                'Alasan Blokir'  => $u->blocked_reason ?: '-',
                'Waktu Diblokir' => $u->updated_at ? $u->updated_at->format('Y-m-d H:i:s') : '-',
            ];
        })->toArray();

        $this->table(['JID', 'Nama', 'Alasan Blokir', 'Waktu Diblokir'], $rows);

        return Command::SUCCESS;
    }
}
