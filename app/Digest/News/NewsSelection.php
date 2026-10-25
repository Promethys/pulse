<?php

declare(strict_types=1);

namespace App\Digest\News;

final readonly class NewsSelection
{
    /**
     * @param  string  $model  "provider:model" that answered
     */
    public function __construct(
        public string $model,
    ) {}
}
