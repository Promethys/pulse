<?php

declare(strict_types=1);

namespace App\Digest\Catalog;

final readonly class ProposalImport
{
    /**
     * @param  list<array{interest: string, name: string, url: string, site_url: ?string, reason: string}>  $rows
     * @param  list<string>  $rejected
     */
    public function __construct(
        public array $rows,
        public array $rejected,
    ) {}
}
