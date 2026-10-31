<?php

declare(strict_types=1);

namespace App\Digest\Catalog;

use App\Jobs\CheckCatalogSource;
use App\Models\Source;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Bus;

final class CatalogRefresher
{
    public function dispatch(bool $force = false): int
    {
        $sources = Source::query()->catalog()->enabled();

        if (! $force) {
            $start = CarbonImmutable::now('UTC')->startOfMonth();
            $sources->whereDoesntHave('checks', fn (Builder $query) => $query
                ->where('checked_at', '>=', $start)
                ->where('checked_at', '<', $start->addMonth()));
        }

        $dispatched = 0;

        foreach ($sources->get() as $source) {
            Bus::dispatch(new CheckCatalogSource($source, $force));
            $dispatched++;
        }

        return $dispatched;
    }
}
