<?php

declare(strict_types=1);

namespace App\Reading;

final readonly class SearchFilters
{
    /**
     * @param  list<string>  $tags
     */
    public function __construct(
        public ?string $q,
        public array $tags,
        public ?string $source,
        public ?string $from,
        public ?string $to,
    ) {}

    public function active(): int
    {
        return count($this->tags) + ($this->source !== null ? 1 : 0) + ($this->from !== null || $this->to !== null ? 1 : 0);
    }
}
