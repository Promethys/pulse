<?php

declare(strict_types=1);

namespace App\Digest\Catalog;

final class ScoutResponseParser
{
    /**
     * @return list<array{name: string, url: string, site_url: ?string, reason: string}>
     */
    public function parse(string $text): array
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');

        if ($start === false || $end === false) {
            return [];
        }

        $data = json_decode(substr($text, $start, $end - $start + 1), true);

        if (! is_array($data) || ! isset($data['proposals']) || ! is_array($data['proposals'])) {
            return [];
        }

        $proposals = [];

        foreach ($data['proposals'] as $proposal) {
            if (! is_array($proposal) || ! is_string($proposal['name'] ?? null) || ! is_string($proposal['url'] ?? null)) {
                continue;
            }

            $proposals[] = [
                'name' => mb_substr(trim($proposal['name']), 0, 120),
                'url' => trim($proposal['url']),
                'site_url' => is_string($proposal['site_url'] ?? null) ? trim($proposal['site_url']) : null,
                'reason' => is_string($proposal['reason'] ?? null) ? mb_substr(trim($proposal['reason']), 0, 500) : '',
            ];
        }

        return $proposals;
    }
}
