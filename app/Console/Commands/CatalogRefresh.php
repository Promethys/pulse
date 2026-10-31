<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Digest\Catalog\CatalogRefresher;
use Illuminate\Console\Command;

class CatalogRefresh extends Command
{
    protected $signature = 'catalog:refresh {--force : Check sources already checked this month}';

    protected $description = 'Dispatch monthly health checks for enabled catalog sources';

    public function handle(CatalogRefresher $refresher): int
    {
        $dispatched = $refresher->dispatch((bool) $this->option('force'));
        $this->info("Dispatched {$dispatched} catalog checks");

        return self::SUCCESS;
    }
}
