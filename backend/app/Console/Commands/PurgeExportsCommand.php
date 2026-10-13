<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class PurgeExportsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'exports:purge {--max-age=180 : Maximum age in seconds before a file is purged}';

    /**
     * The console command description.
     */
    protected $description = 'Safely purge ephemeral spreadsheet export files older than specified age (default 3 minutes).';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $maxAgeSeconds = (int) $this->option('max-age');
        $now = time();
        $directories = [
            storage_path('app/exports'),
            storage_path('app/private/exports'),
            storage_path('framework/cache/exports'),
        ];

        $purgedCount = 0;

        foreach ($directories as $dir) {
            if (!File::isDirectory($dir)) {
                continue;
            }

            $files = File::files($dir);

            foreach ($files as $file) {
                try {
                    $mtime = $file->getMTime();
                    if (($now - $mtime) >= $maxAgeSeconds) {
                        $filePath = $file->getRealPath();
                        if ($filePath && File::exists($filePath)) {
                            File::delete($filePath);
                            $purgedCount++;
                        }
                    }
                } catch (\Throwable $e) {
                    // Idempotent: ignore if file was deleted concurrently
                    Log::debug('Failed to inspect or delete export file', ['error' => $e->getMessage()]);
                }
            }
        }

        $this->info("Purged {$purgedCount} expired export files.");
        return Command::SUCCESS;
    }
}
