<?php

namespace App\Console\Commands;

use App\Services\InsightsService;
use Illuminate\Console\Command;

class SendDailyDigestCommand extends Command
{
    protected $signature = 'finance:send-daily-digest';
    protected $description = 'Send daily evening financial digest to active users';

    public function handle(InsightsService $insightsService): int
    {
        $this->info('Dispatching daily financial digests...');
        $count = $insightsService->sendDailyDigests();
        $this->info("{$count} daily digests processed.");

        return Command::SUCCESS;
    }
}
