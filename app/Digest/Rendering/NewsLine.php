<?php

declare(strict_types=1);

namespace App\Digest\Rendering;

final readonly class NewsLine
{
    /**
     * @param  list<string>  $tagSlugs
     */
    public function __construct(
        public string $title,
        public ?string $summary,
        public string $url,
        public array $tagSlugs,
    ) {}
}
