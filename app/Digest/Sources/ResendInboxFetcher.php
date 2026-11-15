<?php

declare(strict_types=1);

namespace App\Digest\Sources;

use App\Models\Source;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/** Resend Receiving: the list has no body and no documented order, so read every page, filter by date, then GET each email. */
final class ResendInboxFetcher implements SourceFetcher
{
    private const API = 'https://api.resend.com/emails/receiving';

    private const PAGE_SIZE = 100;

    private const MAX_PAGES = 10;

    private const MAX_RETRIEVALS = 100;

    public function fetch(Source $source, CarbonImmutable $since): array
    {
        $http = $this->client($source);

        $emails = array_filter(
            $this->listEmails($http),
            fn (array $email) => CarbonImmutable::parse($email['created_at'])->greaterThanOrEqualTo($since),
        );
        usort($emails, fn (array $a, array $b) => strcmp($b['created_at'], $a['created_at']));

        $address = trim($source->credentials['address'] ?? '');

        if ($address !== '') {
            return $this->filteredCandidates($http, $emails, $address, $source->max_items);
        }

        return array_map(
            fn (array $email) => $this->candidate($this->get($http, self::API.'/'.$email['id'])->json()),
            array_slice($emails, 0, $source->max_items),
        );
    }

    /**
     * @param  array<array{id: string, created_at: string, to?: list<string>, cc?: list<string>}>  $emails
     * @return list<Candidate>
     */
    private function filteredCandidates(PendingRequest $http, array $emails, string $address, int $maxItems): array
    {
        $candidates = [];
        $retrievals = 0;

        foreach ($emails as $email) {
            if (count($candidates) >= $maxItems || $retrievals >= self::MAX_RETRIEVALS) {
                break;
            }

            $retrieved = $this->get($http, self::API.'/'.$email['id'])->json();
            $retrievals++;

            if ($this->matchesAddress($email, $address) || $this->matchesAddress($retrieved, $address)) {
                $candidates[] = $this->candidate($retrieved);
            }
        }

        return $candidates;
    }

    /**
     * @param  array{to?: list<string>, cc?: list<string>, received_for?: list<string>}  $email
     */
    private function matchesAddress(array $email, string $address): bool
    {
        foreach ([...($email['to'] ?? []), ...($email['cc'] ?? []), ...($email['received_for'] ?? [])] as $recipient) {
            preg_match('/<([^<>]+)>/', $recipient, $matches);

            if (strcasecmp(trim($matches[1] ?? $recipient), $address) === 0) {
                return true;
            }
        }

        return false;
    }

    private function client(Source $source): PendingRequest
    {
        $key = $source->credentials['api_key'] ?? null;

        if (! is_string($key) || $key === '') {
            throw new FetchException('This newsletter source has no Resend API key');
        }

        return Http::timeout(15)->retry(2, 500, throw: false)->withToken($key)->acceptJson();
    }

    /**
     * @return list<array{id: string, subject: string, created_at: string, to?: list<string>, cc?: list<string>}>
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
    private function candidate(array $email): Candidate
    {
        return EmailCandidate::from(
            (string) ($email['subject'] ?? ''),
            (string) ($email['html'] ?? ''),
            (string) ($email['text'] ?? ''),
            CarbonImmutable::parse($email['created_at']),
            'resend://email/'.$email['id'],
        );
    }
}
