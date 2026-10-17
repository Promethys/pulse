<?php

declare(strict_types=1);

namespace App\Digest\Sources;

use App\Digest\Support\Text;
use App\Models\Source;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** GitHub has no trending API or feed, so this parses the HTML page. */
final class GithubTrendingFetcher implements SourceFetcher
{
    public function fetch(Source $source, CarbonImmutable $since): array
    {
        $xpath = $this->load((string) $source->url);
        $rows = $xpath->query("//article[contains(concat(' ', normalize-space(@class), ' '), ' Box-row ')]");

        $candidates = [];
        foreach ($rows ?: [] as $row) {
            if (! $row instanceof DOMElement) {
                continue;
            }

            $href = trim((string) $xpath->evaluate('string(.//h2//a/@href)', $row), '/');
            if ($href === '') {
                continue;
            }

            $url = 'https://github.com/'.$href;
            $candidates[] = new Candidate(
                sourceSlug: $source->slug,
                title: $href,
                url: $url,
                excerpt: Text::plain((string) $xpath->evaluate('string(.//p[1])', $row)),
                publishedAt: CarbonImmutable::now(),
                links: [$url],
            );
        }

        if ($candidates === []) {
            throw new FetchException('No trending rows found (page layout changed?)');
        }

        return array_slice($candidates, 0, $source->max_items);
    }

    private function load(string $url): DOMXPath
    {
        try {
            $response = Http::timeout(10)->retry(2, 500, throw: false)->withUserAgent(FeedFetcher::USER_AGENT)->get($url);
        } catch (ConnectionException $e) {
            throw new FetchException("Connection failed: {$e->getMessage()}", previous: $e);
        }

        if ($response->failed()) {
            throw new FetchException("HTTP {$response->status()}");
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument;
        $dom->loadHTML('<?xml encoding="UTF-8">'.$response->body(), LIBXML_NONET | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($dom);
    }
}
