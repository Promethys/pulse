<?php

declare(strict_types=1);

namespace App\Digest\Sources;

final class UrlNormalizer
{
    private const TRACKING_PARAMS = ['ref', 'fbclid', 'gclid', 'mc_cid', 'mc_eid'];

    public static function normalize(string $url): string
    {
        $url = trim($url);
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return $url;
        }

        $host = strtolower($parts['host']);
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        $normalized = strtolower($parts['scheme']).'://'.$host;
        if (isset($parts['port'])) {
            $normalized .= ':'.$parts['port'];
        }

        $normalized .= rtrim($parts['path'] ?? '', '/');

        $query = self::normalizeQuery($parts['query'] ?? '');

        return $query === '' ? $normalized : $normalized.'?'.$query;
    }

    /**
     * Kept raw (no parse_str) so parameter names like "v.1" survive untouched.
     */
    private static function normalizeQuery(string $query): string
    {
        $pairs = array_filter(explode('&', $query), function (string $pair): bool {
            $name = strtolower(explode('=', $pair, 2)[0]);

            return $name !== '' && ! str_starts_with($name, 'utm_') && ! in_array($name, self::TRACKING_PARAMS, true);
        });

        sort($pairs, SORT_STRING);

        return implode('&', $pairs);
    }
}
