<?php

declare(strict_types=1);

namespace App\Digest\News;

use App\Models\Item;
use Illuminate\Support\Collection;

final readonly class NewsSelection
{
    /**
     * @param  Collection<int, Item>  $items  selected items, in the model's order
     * @param  string  $model  "provider:model" that answered
     */
    public function __construct(
        public Collection $items,
        public string $model,
    ) {}
}
