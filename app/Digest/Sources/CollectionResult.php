<?php

declare(strict_types=1);

namespace App\Digest\Sources;

final readonly class CollectionResult
{
    /**
     * @param  array<string, string>  $failures  source name => error message
     */
    public function __construct(
        public int $stored,
        public array $failures,
    ) {}
}
