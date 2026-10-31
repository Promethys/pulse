<?php

declare(strict_types=1);

namespace App\Digest\Catalog;

use App\Digest\Sources\FeedFetcher;
use App\Digest\Sources\SourceCollector;
use App\Digest\Support\PublicUrl;
use App\Models\Source;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Support\Facades\Http;
use Throwable;

final class MovedFeedFinder
{
    public function __construct(
        private readonly PublicUrl $publicUrl,
        private readonly SourceCollector $collector,
    ) {}

    public function find(Source $source): ?string
    {
        try {
            $http = Http::timeout(10)->withUserAgent(FeedFetcher::USER_AGENT);
            $url = (string) $source->url;
            $candidate = $this->publicUrl->finalUrl($http, $url);

            if ($candidate === $url) {
                if ($source->site_url === null) {
                    return null;
                }

                $candidate = $this->advertisedFeed($source->site_url);
            }

            if ($candidate === null || $candidate === $url || ! $this->publicUrl->isPublic($candidate)) {
                return null;
            }

            $replica = $source->replicate();
            $replica->url = $candidate;
            // Unsaved candidates use the fetcher's public redirect checks.
            $replica->user_id ??= 0;
            $this->collector->fetch($replica, CarbonImmutable::now()->subDays(30));

            return $candidate;
        } catch (Throwable) {
            return null;
        }
    }

    public function advertisedFeed(string $siteUrl): ?string
    {
        try {
            $http = Http::timeout(10)->withUserAgent(FeedFetcher::USER_AGENT);
            $response = $this->publicUrl->get($http, $siteUrl);

            if ($response->failed()) {
                return null;
            }

            return $this->discover($response->body(), (string) ($response->effectiveUri() ?? $siteUrl));
        } catch (Throwable) {
            return null;
        }
    }

    private function discover(string $html, string $pageUrl): ?string
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $dom = new DOMDocument;
            $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR);
            $links = (new DOMXPath($dom))->query('//link[@href]');

            foreach ($links ?: [] as $link) {
                if (! $link instanceof DOMElement) {
                    continue;
                }

                $rel = preg_split('/\s+/', strtolower(trim($link->getAttribute('rel')))) ?: [];
                $type = strtolower(trim($link->getAttribute('type')));

                if (in_array('alternate', $rel, true) && in_array($type, ['application/rss+xml', 'application/atom+xml'], true)) {
                    return (string) UriResolver::resolve(new Uri($pageUrl), new Uri(trim($link->getAttribute('href'))));
                }
            }

            return null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
