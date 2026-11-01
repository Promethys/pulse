<?php

declare(strict_types=1);

namespace App\Digest\Support;

use Closure;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

final class PublicUrl
{
    /** @var Closure(string): list<string> */
    private readonly Closure $resolve;

    /**
     * @param  (Closure(string): list<string>)|null  $resolve  host => its IP addresses
     */
    public function __construct(?Closure $resolve = null)
    {
        $this->resolve = $resolve ?? self::dnsLookup(...);
    }

    public function isPublic(string $url): bool
    {
        $host = trim((string) parse_url($url, PHP_URL_HOST), '[]');

        if (! preg_match('#^https?://#i', $url) || $host === '') {
            return false;
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : ($this->resolve)($host);

        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) === false) {
                return false;
            }
        }

        return $ips !== [];
    }

    public function get(PendingRequest $http, string $url, int $maxRedirects = 3): Response
    {
        return $this->follow($http, $url, $maxRedirects)[1];
    }

    public function finalUrl(PendingRequest $http, string $url, int $maxRedirects = 3): string
    {
        return $this->follow($http, $url, $maxRedirects)[0];
    }

    /**
     * @return array{string, Response}
     */
    private function follow(PendingRequest $http, string $url, int $maxRedirects): array
    {
        $http = $http->withoutRedirecting();

        for ($hop = 0; $hop <= $maxRedirects; $hop++) {
            if (! $this->isPublic($url)) {
                throw new UnsafeUrlException('URL must be a public http or https URL');
            }

            $response = $http->get($url);

            if (! $response->redirect()) {
                return [$url, $response];
            }

            $location = $response->header('Location');
            if ($location === '') {
                throw new UnsafeUrlException('Redirect has no location');
            }

            $url = (string) UriResolver::resolve(new Uri($url), new Uri($location));
        }

        throw new UnsafeUrlException('Too many redirects');
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
