<?php

declare(strict_types=1);

namespace App\Digest\Sources;

use App\Digest\Support\PublicUrl;
use App\Digest\Support\Text;
use App\Digest\Support\UnsafeUrlException;
use App\Models\Source;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use SimpleXMLElement;
use Throwable;

/** RSS 2.0 and Atom feeds (blogs, hnrss.org, Reddit). */
final class FeedFetcher implements SourceFetcher
{
    public const USER_AGENT = 'pulse-digest/1.0 (+personal use)';

    private const EXCERPT_LENGTH = 300;

    private const MAX_RATE_LIMIT_WAIT = 60;

    public function fetch(Source $source, CarbonImmutable $since): array
    {
        $xml = $this->load($source);

        $candidates = array_filter(
            $xml->getName() === 'rss' ? $this->rssItems($xml) : $this->atomEntries($xml),
            fn (Candidate $c) => $c->publishedAt === null || $c->publishedAt->greaterThanOrEqualTo($since),
        );

        usort($candidates, fn (Candidate $a, Candidate $b) => ($b->publishedAt?->getTimestamp() ?? PHP_INT_MIN) <=> ($a->publishedAt?->getTimestamp() ?? PHP_INT_MIN));

        return array_slice($candidates, 0, $source->max_items);
    }

    private function load(Source $source): SimpleXMLElement
    {
        try {
            $http = Http::timeout(10)->withUserAgent(self::USER_AGENT);
            $url = (string) $source->url;
            $response = $source->user_id !== null
                ? app(PublicUrl::class)->get($http, $url)
                : $http->retry(3, $this->backoff(...), throw: false)->get($url);
        } catch (UnsafeUrlException $e) {
            throw new FetchException($e->getMessage(), previous: $e);
        } catch (ConnectionException $e) {
            throw new FetchException("Connection failed: {$e->getMessage()}", previous: $e);
        }

        if ($response->failed()) {
            throw new FetchException("HTTP {$response->status()}");
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response->body(), options: LIBXML_NOCDATA | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($xml === false || ! in_array($xml->getName(), ['rss', 'feed'], true)) {
            throw new FetchException('Response is not an RSS or Atom feed');
        }

        return $xml;
    }

    /** Milliseconds before retrying: on 429, Retry-After or Reddit's x-ratelimit-reset (anonymous: ~1 request/min), else 500 ms. */
    private function backoff(int $attempt, Throwable $exception): int
    {
        $response = $exception instanceof RequestException ? $exception->response : null;

        if ($response?->status() === 429) {
            $wait = $response->header('Retry-After') ?: $response->header('x-ratelimit-reset');

            if (is_numeric($wait)) {
                return min((int) ceil((float) $wait) + 2, self::MAX_RATE_LIMIT_WAIT) * 1000;
            }
        }

        return 500;
    }

    /**
     * @return list<Candidate>
     */
    private function rssItems(SimpleXMLElement $xml): array
    {
        $candidates = [];

        foreach ($xml->channel->item ?? [] as $item) {
            $candidates[] = $this->candidate((string) $item->title, (string) $item->link, (string) $item->description, (string) $item->pubDate);
        }

        return array_values(array_filter($candidates));
    }

    /**
     * @return list<Candidate>
     */
    private function atomEntries(SimpleXMLElement $xml): array
    {
        $candidates = [];

        foreach ($xml->entry ?? [] as $entry) {
            $candidates[] = $this->candidate(
                (string) $entry->title,
                $this->atomLink($entry),
                (string) ($entry->summary ?? '') ?: (string) $entry->content,
                (string) ($entry->published ?? '') ?: (string) $entry->updated,
            );
        }

        return array_values(array_filter($candidates));
    }

    private function atomLink(SimpleXMLElement $entry): string
    {
        foreach ($entry->link ?? [] as $link) {
            $rel = (string) $link['rel'];
            if ($rel === '' || $rel === 'alternate') {
                return (string) $link['href'];
            }
        }

        return '';
    }

    private function candidate(string $title, string $url, string $html, string $date): ?Candidate
    {
        $title = Text::plain($title);
        $url = trim($url);

        if ($title === '' || ! preg_match('#^https?://#i', $url)) {
            return null;
        }

        return new Candidate(
            title: $title,
            url: $url,
            excerpt: Str::limit(Text::plain($html), self::EXCERPT_LENGTH - 1, '…'),
            publishedAt: $this->date($date),
            links: [$url],
        );
    }

    private function date(string $value): ?CarbonImmutable
    {
        if (trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
