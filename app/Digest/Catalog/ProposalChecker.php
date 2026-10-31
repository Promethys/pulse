<?php

declare(strict_types=1);

namespace App\Digest\Catalog;

use App\Digest\Sources\SourceCollector;
use App\Digest\Sources\UrlNormalizer;
use App\Digest\Support\PublicUrl;
use App\Enums\SourceType;
use App\Models\Source;
use Carbon\CarbonImmutable;
use Throwable;

final class ProposalChecker
{
    public function __construct(
        private readonly PublicUrl $publicUrl,
        private readonly SourceCollector $collector,
    ) {}

    public function check(string $url): ProposalCheck
    {
        $checks = ['public_url' => $this->publicUrl->isPublic($url)];

        if (! $checks['public_url']) {
            return new ProposalCheck(false, $checks, 'public_url');
        }

        $normalized = UrlNormalizer::normalize($url);
        $host = $this->host($url);
        $checks['not_in_catalog'] = ! Source::query()->catalog()->get()->contains(
            fn (Source $source) => UrlNormalizer::normalize((string) $source->url) === $normalized
                || $this->host((string) $source->url) === $host,
        );

        if (! $checks['not_in_catalog']) {
            return new ProposalCheck(false, $checks, 'not_in_catalog');
        }

        $source = new Source(['type' => SourceType::Feed, 'url' => $url, 'max_items' => 3]);
        // Unsaved candidates use the fetcher's public redirect checks.
        $source->user_id = 0;

        try {
            $candidates = $this->collector->fetch($source, CarbonImmutable::now()->subDays(30));
            $checks['feed_parses'] = true;
        } catch (Throwable) {
            $checks['feed_parses'] = false;

            return new ProposalCheck(false, $checks, 'feed_parses');
        }

        $checks['recent_posts'] = count($candidates) >= 3;

        return new ProposalCheck($checks['recent_posts'], $checks, $checks['recent_posts'] ? null : 'recent_posts');
    }

    private function host(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }
}
