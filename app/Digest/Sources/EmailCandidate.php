<?php

declare(strict_types=1);

namespace App\Digest\Sources;

use App\Digest\Support\Text;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class EmailCandidate
{
    private const EXCERPT_LENGTH = 3000;

    private const EXCLUDED_LINK_PARTS = ['unsubscribe', '/open', 'list-manage.com/track'];

    public static function from(string $subject, string $html, string $text, CarbonImmutable $publishedAt, string $url): Candidate
    {
        return new Candidate(
            title: Text::plain($subject) ?: '(no subject)',
            url: $url,
            excerpt: Str::limit(Text::plain($text !== '' ? $text : $html), self::EXCERPT_LENGTH - 1, '…'),
            publishedAt: $publishedAt,
            links: self::links($html, $text),
        );
    }

    /**
     * @return list<string> anchors first, then bare URLs in the text
     */
    private static function links(string $html, string $text): array
    {
        preg_match_all('/href\s*=\s*["\']([^"\']+)["\']/i', $html, $anchors);
        preg_match_all('#https?://[^\s<>"\'()\[\]]+#i', $text.' '.Text::plain($html), $bare);

        $links = array_map(fn (string $url) => rtrim(html_entity_decode($url), '.,;:!?'), [...$anchors[1], ...$bare[0]]);

        return array_values(array_unique(array_filter($links, fn (string $url) => preg_match('#^https?://#i', $url)
            && ! Str::contains(strtolower($url), self::EXCLUDED_LINK_PARTS))));
    }
}
