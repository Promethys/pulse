<?php

declare(strict_types=1);

namespace App\Digest\Rendering;

/** Makes plain text (feed titles, model summaries) safe inside Discord Markdown. */
final class DiscordText
{
    private const ZERO_WIDTH_SPACE = "\u{200B}";

    public static function escape(string $text): string
    {
        $escaped = (string) preg_replace('/([\\\\*_~`|>\[\]])/', '\\\\$1', $text);

        return str_replace(
            ['@everyone', '@here', '<@', '<#'],
            ['@'.self::ZERO_WIDTH_SPACE.'everyone', '@'.self::ZERO_WIDTH_SPACE.'here', '<'.self::ZERO_WIDTH_SPACE.'@', '<'.self::ZERO_WIDTH_SPACE.'#'],
            $escaped,
        );
    }
}
