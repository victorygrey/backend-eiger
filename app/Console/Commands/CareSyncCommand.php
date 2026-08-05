<?php

namespace App\Console\Commands;

use App\Services\CareSyncService;
use Illuminate\Console\Command;

class CareSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'care:sync {--source=console : Source label stored in sync_logs}';

    /**
     * The console command description.
     */
    protected $description = 'Trigger a CARE synchronization and persist the result to sync_logs';

    /**
     * Execute the console command.
     */
    public function handle(CareSyncService $careSync): int
    {
        $source = $this->option('source') ?: 'console';

        $this->info("Starting CARE synchronization (source: {$source})...");
        $result = $careSync->sync($source);

        if ($result['success']) {
            $this->info('✓ ' . $result['message']);
            $this->line("  Log ID : #{$result['source']}");
            $this->line("  Time   : {$result['synced_at']}");
            return self::SUCCESS;
        }

        $this->error('✗ ' . $result['message']);
        return self::FAILURE;
    }
}
