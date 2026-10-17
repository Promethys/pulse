<?php

declare(strict_types=1);

namespace App\Digest\Sources;

use App\Digest\Support\Text;
use App\Models\Source;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/** Resend Receiving: the list has no body and no documented order, so read every page, filter by date, then GET each email. */
final class ResendInboxFetcher implements SourceFetcher
{
    private const API = 'https://api.resend.com/emails/receiving';

    private const PAGE_SIZE = 100;

    private const MAX_PAGES = 10;

    private const EXCERPT_LENGTH = 3000;

    private const EXCLUDED_LINK_PARTS = ['unsubscribe', '/open', 'list-manage.com/track'];

    public function fetch(Source $source, CarbonImmutable $since): array
    {
        $http = $this->client();

        $emails = array_filter(
            $this->listEmails($http),
            fn (array $email) => CarbonImmutable::parse($email['created_at'])->greaterThanOrEqualTo($since),
        );
        usort($emails, fn (array $a, array $b) => strcmp($b['created_at'], $a['created_at']));

        return array_map(
            fn (array $email) => $this->candidate($source, $this->get($http, self::API.'/'.$email['id'])->json()),
            array_slice($emails, 0, $source->max_items),
        );
    }

    private function client(): PendingRequest
    {
        $key = config('services.resend.key');

        if (! is_string($key) || $key === '') {
            throw new FetchException('RESEND_API_KEY is not set');
        }

        return Http::timeout(15)->retry(2, 500, throw: false)->withToken($key)->acceptJson();
    }

    /**
     * @return list<array{id: string, subject: string, created_at: string}>
     */
    private function listEmails(PendingRequest $http): array
    {
        $emails = [];
        $after = null;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $query = array_filter(['limit' => self::PAGE_SIZE, 'after' => $after]);
            $body = $this->get($http, self::API, $query)->json();
            $data = $body['data'] ?? [];

            array_push($emails, ...$data);

            if (! ($body['has_more'] ?? false) || $data === []) {
                break;
            }

            $after = end($data)['id'];
        }

        return $emails;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function get(PendingRequest $http, string $url, array $query = []): Response
    {
        try {
            $response = $http->get($url, $query);
        } catch (ConnectionException $e) {
            throw new FetchException("Connection failed: {$e->getMessage()}", previous: $e);
        }

        if ($response->failed()) {
            throw new FetchException("HTTP {$response->status()}");
        }

        return $response;
    }

    /**
     * @param  array{id: string, subject?: string, created_at: string, html?: string|null, text?: string|null}  $email
     */
    private function candidate(Source $source, array $email): Candidate
    {
        $html = (string) ($email['html'] ?? '');
        $text = (string) ($email['text'] ?? '');

        return new Candidate(
            sourceSlug: $source->slug,
            title: Text::plain((string) ($email['subject'] ?? '')) ?: '(no subject)',
            url: 'resend://email/'.$email['id'],
            excerpt: Str::limit(Text::plain($text !== '' ? $text : $html), self::EXCERPT_LENGTH - 1, '…'),
            publishedAt: CarbonImmutable::parse($email['created_at']),
            links: $this->links($html, $text),
        );
    }

    /**
     * @return list<string> anchors first, then bare URLs in the text
     */
    private function links(string $html, string $text): array
    {
        preg_match_all('/href\s*=\s*["\']([^"\']+)["\']/i', $html, $anchors);
        preg_match_all('#https?://[^\s<>"\'()\[\]]+#i', $text.' '.Text::plain($html), $bare);

        $links = array_map(fn (string $url) => rtrim(html_entity_decode($url), '.,;:!?'), [...$anchors[1], ...$bare[0]]);

        return array_values(array_unique(array_filter($links, fn (string $url) => preg_match('#^https?://#i', $url)
            && ! Str::contains(strtolower($url), self::EXCLUDED_LINK_PARTS))));
    }
}
