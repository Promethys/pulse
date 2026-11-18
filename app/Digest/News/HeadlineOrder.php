<?php

declare(strict_types=1);

namespace App\Digest\News;

use App\Models\Item;
use Illuminate\Support\Collection;

final class HeadlineOrder
{
    /**
     * @param  Collection<int, Item>  $candidates
     * @return Collection<int, Item>
     */
    public function sort(Collection $candidates): Collection
    {
        return $candidates->sortBy([
            fn (Item $a, Item $b) => ($a->sources->first()->position ?? PHP_INT_MAX) <=> ($b->sources->first()->position ?? PHP_INT_MAX),
            fn (Item $a, Item $b) => ($b->published_at?->getTimestamp() ?? 0) <=> ($a->published_at?->getTimestamp() ?? 0),
        ])->values();
    }
}
