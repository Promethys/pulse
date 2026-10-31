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
        public string $type = 'news',
        public ?string $kind = null,
        public ?string $category = null,
    ) {}

    public function active(): int
    {
        return count($this->tags) + ($this->kind !== null ? 1 : 0) + ($this->category !== null ? 1 : 0) + ($this->source !== null ? 1 : 0) + ($this->from !== null || $this->to !== null ? 1 : 0);
    }
}
