<?php

namespace App\Console\Commands;

use App\Services\RecurringService;
use Illuminate\Console\Command;

class ProcessRecurringCommand extends Command
{
    protected $signature = 'finance:process-recurring';
    protected $description = 'Process due recurring income and expense commitments';

    public function handle(RecurringService $recurringService): int
    {
        $this->info('Processing due recurring transactions...');
        $processed = $recurringService->processDueSchedules();
        $this->info(count($processed) . ' recurring transactions processed.');

        return Command::SUCCESS;
    }
}
