<?php

declare(strict_types=1);

namespace App\Digest\Support;

final class Text
{
    /** HTML or text → a single line of plain text. */
    public static function plain(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
