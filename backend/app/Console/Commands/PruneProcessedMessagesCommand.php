<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneProcessedMessagesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'messages:prune {--days=30 : Number of days to keep}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune old processed idempotency messages to prevent database bloat';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $threshold = now()->subDays($days);

        $deleted = DB::table('processed_messages')
            ->where('received_at', '<', $threshold)
            ->delete();

        $this->info("Pruned {$deleted} processed messages older than {$days} days.");

        return Command::SUCCESS;
    }
}
