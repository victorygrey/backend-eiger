<?php

namespace App\Console\Commands;

use App\Services\PimFolderImporter;
use Illuminate\Console\Command;

class PimScanCommand extends Command
{
    protected $signature = 'pim:scan {--retry-failed : Retry unchanged failed batches}';
    protected $description = 'Import completed PIM file drops from the NAS folder (no PIM API calls)';

    public function handle(PimFolderImporter $importer): int
    {
        try {
            $result = $importer->scan((bool) $this->option('retry-failed'));
            $this->info(json_encode($result));
            return $result['failed'] ? self::FAILURE : self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error($error->getMessage());
            return self::FAILURE;
        }
    }
}
