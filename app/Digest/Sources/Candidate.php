<?php

declare(strict_types=1);

namespace App\Digest\Sources;

use Carbon\CarbonImmutable;

final readonly class Candidate
{
    /**
     * @param  list<string>  $links  URLs the model may cite for this candidate
     */
    public function __construct(
        public string $title,
        public string $url,
        public string $excerpt,
        public ?CarbonImmutable $publishedAt,
        public array $links,
    ) {}
}
