<?php

declare(strict_types=1);

namespace App\Digest\Sources;

use App\Digest\Support\Text;
use App\Models\Source;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use SimpleXMLElement;
use Throwable;

/** RSS 2.0 and Atom feeds (blogs, hnrss.org, Reddit). */
final class FeedFetcher implements SourceFetcher
{
    public const USER_AGENT = 'pulse-digest/1.0 (+personal use)';

    private const EXCERPT_LENGTH = 300;

    public function fetch(Source $source, CarbonImmutable $since): array
    {
        $xml = $this->load((string) $source->url);

        $candidates = array_filter(
            $xml->getName() === 'rss' ? $this->rssItems($xml, $source) : $this->atomEntries($xml, $source),
            fn (Candidate $c) => $c->publishedAt === null || $c->publishedAt->greaterThanOrEqualTo($since),
        );

        usort($candidates, fn (Candidate $a, Candidate $b) => ($b->publishedAt?->getTimestamp() ?? PHP_INT_MIN) <=> ($a->publishedAt?->getTimestamp() ?? PHP_INT_MIN));

        return array_slice($candidates, 0, $source->max_items);
    }

    private function load(string $url): SimpleXMLElement
    {
        try {
            $response = Http::timeout(10)->retry(2, 500, throw: false)->withUserAgent(self::USER_AGENT)->get($url);
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

    /**
     * @return list<Candidate>
     */
    private function rssItems(SimpleXMLElement $xml, Source $source): array
    {
        $candidates = [];

        foreach ($xml->channel->item ?? [] as $item) {
            $candidates[] = $this->candidate($source, (string) $item->title, (string) $item->link, (string) $item->description, (string) $item->pubDate);
        }

        return array_values(array_filter($candidates));
    }

    /**
     * @return list<Candidate>
     */
    private function atomEntries(SimpleXMLElement $xml, Source $source): array
    {
        $candidates = [];

        foreach ($xml->entry ?? [] as $entry) {
            $candidates[] = $this->candidate(
                $source,
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

    private function candidate(Source $source, string $title, string $url, string $html, string $date): ?Candidate
    {
        $title = Text::plain($title);
        $url = trim($url);

        if ($title === '' || ! preg_match('#^https?://#i', $url)) {
            return null;
        }

        return new Candidate(
            sourceSlug: $source->slug,
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
