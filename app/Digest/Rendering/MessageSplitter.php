<?php

declare(strict_types=1);

namespace App\Digest\Rendering;

/** Packs text blocks (sections) into Discord messages without ever exceeding the length limit. */
final class MessageSplitter
{
    public const DISCORD_LIMIT = 2000;

    /**
     * @param  list<string>  $blocks
     * @return list<string>
     */
    public function split(array $blocks, int $limit = self::DISCORD_LIMIT): array
    {
        $messages = [];
        $current = '';

        foreach ($blocks as $block) {
            if (trim($block) === '') {
                continue;
            }

            foreach ($this->pieces($block, $limit) as $piece) {
                $joined = $current === '' ? $piece : "{$current}\n\n{$piece}";

                if (mb_strlen($joined) <= $limit) {
                    $current = $joined;
                } else {
                    $messages[] = $current;
                    $current = $piece;
                }
            }
        }

        if ($current !== '') {
            $messages[] = $current;
        }

        return $messages;
    }

    /**
     * A block that fits stays whole; a bigger one is split on lines (each continuation of a "## " section
     * repeats its heading as "## … (continued)" when it fits), and a line longer than the limit is cut.
     *
     * @return list<string>
     */
    private function pieces(string $block, int $limit): array
    {
        if (mb_strlen($block) <= $limit) {
            return [$block];
        }

        $lines = explode("\n", $block);
        $continued = str_starts_with($lines[0], '## ') ? "{$lines[0]} (continued)" : null;

        $pieces = [];
        $current = '';

        foreach ($lines as $line) {
            foreach (mb_strlen($line) > $limit ? mb_str_split($line, max(1, $limit)) : [$line] as $part) {
                $joined = $current === '' ? $part : "{$current}\n{$part}";

                if (mb_strlen($joined) <= $limit) {
                    $current = $joined;
                } else {
                    $pieces[] = $current;
                    $withHeading = "{$continued}\n{$part}";
                    $current = $continued !== null && mb_strlen($withHeading) <= $limit ? $withHeading : $part;
                }
            }
        }

        if ($current !== '') {
            $pieces[] = $current;
        }

        return $pieces;
    }
}
