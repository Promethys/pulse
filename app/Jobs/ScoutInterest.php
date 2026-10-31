<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Digest\Catalog\CatalogScout;
use App\Models\Interest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ScoutInterest implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public Interest $interest,
        public int $max = 5,
    ) {}

    public function handle(CatalogScout $scout): void
    {
        $scout->scout($this->interest, $this->max);
    }
}
