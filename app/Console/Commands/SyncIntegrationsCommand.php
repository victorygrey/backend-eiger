<?php

namespace App\Console\Commands;

use App\Services\CareSyncService;
use App\Services\PimSyncService;
use Illuminate\Console\Command;

class SyncIntegrationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'integrations:sync {--source=console : Source label stored in sync_logs}';

    /**
     * The console command description.
     */
    protected $description = 'Synchronize master catalog from PIM and retail prices/stocks from CARE Omni';

    /**
     * Execute the console command.
     */
    public function handle(PimSyncService $pimSync, CareSyncService $careSync): int
    {
        $source = $this->option('source') ?: 'console';

        $this->info("==================================================");
        $this->info(" Starting Unified Synchronization (source: {$source})");
        $this->info("==================================================");

        // 1. Sync PIM Master Catalog
        $this->line("1. Syncing PIM master catalog...");
        $pimResult = $pimSync->sync($source);
        if ($pimResult['success']) {
            $this->info("   ✓ {$pimResult['message']}");
        } else {
            $this->warn("   ! PIM warning: {$pimResult['message']}");
        }

        // 2. Sync CARE Omni Pricing and Stocks
        $this->line("2. Syncing CARE Omni pricing & store inventory...");
        $careResult = $careSync->sync($source);
        if ($careResult['success']) {
            $this->info("   ✓ {$careResult['message']}");
        } else {
            $this->error("   ✗ CARE error: {$careResult['message']}");
            return self::FAILURE;
        }

        $this->info("==================================================");
        $this->info(" Unified synchronization completed successfully.");
        $this->info("==================================================");

        return self::SUCCESS;
    }
}
