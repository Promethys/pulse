<?php

declare(strict_types=1);

namespace App\Digest\News;

final readonly class SelectedItem
{
    /**
     * @param  list<string>  $tagSlugs
     */
    public function __construct(
        public int $candidateId,
        public string $url,
        public string $title,
        public string $summary,
        public array $tagSlugs,
    ) {}
}
