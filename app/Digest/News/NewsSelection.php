<?php

declare(strict_types=1);

namespace App\Digest\News;

use App\Models\Item;
use App\Models\PostItem;
use Illuminate\Support\Collection;

/** @template T of Item|PostItem */
final readonly class NewsSelection
{
    /**
     * @param  Collection<int, T>  $items  selected items, in the model's order
     * @param  string  $model  "provider:model" that answered
     */
    public function __construct(
        public Collection $items,
        public string $model,
    ) {}
}
