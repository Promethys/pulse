<?php

declare(strict_types=1);

namespace App\Digest\Sources;

use App\Models\Source;
use Carbon\CarbonImmutable;

interface SourceFetcher
{
    /**
     * @return list<Candidate>
     *
     * @throws FetchException
     */
    public function fetch(Source $source, CarbonImmutable $since): array;
}
