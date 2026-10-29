<?php

declare(strict_types=1);

namespace App\Digest\Rendering;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class NewsRenderer
{
    private const MAX_FORUM_TAGS = 5;

    public function __construct(private readonly MessageSplitter $splitter) {}

    /**
     * @param  Collection<int, NewsLine>  $lines
     * @param  array<string, string>  $failures  source name => error
     */
    public function render(CarbonImmutable $date, Collection $lines, array $failures, bool $headlinesOnly = false): RenderedPost
    {
        $title = '📰 News digest — '.$date->format('D j M');
        $footer = $this->footer($failures, $headlinesOnly);

        if ($lines->isEmpty()) {
            return new RenderedPost($title, $this->splitter->split(['Quiet night: nothing notable.', $footer]), []);
        }

        $sections = NewsSections::group($lines, fn (NewsLine $line) => $line->tagSlugs);

        $blocks = array_map(fn (array $section) => "## {$section['name']}\n".implode("\n", array_map(
            fn (NewsLine $item) => $this->line($item, $headlinesOnly),
            $section['items'],
        )), $sections);

        return new RenderedPost(
            $title,
            $this->splitter->split([...$blocks, $footer]),
            array_slice(array_column($sections, 'slug'), 0, self::MAX_FORUM_TAGS),
        );
    }

    private function line(NewsLine $item, bool $headlinesOnly): string
    {
        $title = DiscordText::escape($item->title);
        $summary = $headlinesOnly ? '' : DiscordText::escape(trim((string) $item->summary));

        return $summary === ''
            ? "- **{$title}** <{$item->url}>"
            : "- **{$title}**: {$summary} <{$item->url}>";
    }

    /**
     * @param  array<string, string>  $failures
     */
    private function footer(array $failures, bool $headlinesOnly): string
    {
        $lines = [];

        if ($failures !== []) {
            $lines[] = '⚠️ Not fetched: '.implode(', ', array_keys($failures));
        }
        if ($headlinesOnly) {
            $lines[] = '⚠️ Summaries unavailable today, headlines only.';
        }

        return implode("\n", $lines);
    }
}
