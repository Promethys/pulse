<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Digest\Catalog\CatalogChecker;
use App\Models\Source;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckCatalogSource implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public Source $source,
        public bool $force = false,
    ) {}

    public function handle(CatalogChecker $checker): void
    {
        $this->source->refresh();

        if (! $this->source->enabled || $this->source->user_id !== null) {
            return;
        }

        $start = CarbonImmutable::now('UTC')->startOfMonth();

        if (! $this->force && $this->source->checks()
            ->where('checked_at', '>=', $start)
            ->where('checked_at', '<', $start->addMonth())
            ->exists()) {
            return;
        }

        $checker->check($this->source);
    }
}
