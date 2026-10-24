<?php

declare(strict_types=1);

namespace App\Digest\Learning;

use App\Digest\Sources\FeedFetcher;
use Closure;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Links suggested by the model are checked before being posted, on public hosts only (each redirect included). */
final class LinkChecker
{
    private const MAX_REDIRECTS = 3;

    /** @var Closure(string): list<string> */
    private readonly Closure $resolve;

    /**
     * @param  (Closure(string): list<string>)|null  $resolve  host => its IP addresses
     */
    public function __construct(?Closure $resolve = null)
    {
        $this->resolve = $resolve ?? self::dnsLookup(...);
    }

    public function alive(string $url): bool
    {
        $http = Http::timeout(5)->withUserAgent(FeedFetcher::USER_AGENT)->withoutRedirecting();

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            if (! $this->isPublicHttpUrl($url)) {
                return false;
            }

            try {
                $response = $http->head($url);

                if (in_array($response->status(), [403, 405], true)) {
                    $response = $http->get($url);
                }
            } catch (ConnectionException) {
                return false;
            }

            if (! $response->redirect()) {
                return $response->status() < 400;
            }

            $location = $response->header('Location');
            if ($location === '') {
                return false;
            }

            $url = (string) UriResolver::resolve(new Uri($url), new Uri($location));
        }

        return false;
    }

    private function isPublicHttpUrl(string $url): bool
    {
        $host = trim((string) parse_url($url, PHP_URL_HOST), '[]');

        if (! preg_match('#^https?://#i', $url) || $host === '') {
            return false;
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : ($this->resolve)($host);

        foreach ($ips as $ip) {
            // @phpstan-ignore identical.alwaysFalse (PHPStan doesn't model FILTER_FLAG_GLOBAL_RANGE)
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) === false) {
                return false;
            }
        }

        return $ips !== [];
    }

    /**
     * @return list<string>
     */
    private static function dnsLookup(string $host): array
    {
        $ipv4 = gethostbynamel($host) ?: [];
        $ipv6 = array_column(@dns_get_record($host, DNS_AAAA) ?: [], 'ipv6');

        return array_merge($ipv4, array_values(array_filter($ipv6, is_string(...))));
    }
}
