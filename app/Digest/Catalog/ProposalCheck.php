<?php

declare(strict_types=1);

namespace App\Digest\Catalog;

final readonly class ProposalCheck
{
    /**
     * @param  array<string, bool>  $checks
     */
    public function __construct(
        public bool $passed,
        public array $checks,
        public ?string $failure,
    ) {}
}
