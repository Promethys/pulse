<?php

declare(strict_types=1);

namespace App\Digest\Rendering;

use App\Models\Item;
use App\Models\Source;
use App\Models\Tag;
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

        $sections = $this->sections($lines);

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

    /**
     * Fallback when no AI provider answered: the newest candidates of the highest-priority sources.
     *
     * @param  Collection<int, Item>  $candidates
     * @return Collection<int, Item>
     */
    public function headlines(Collection $candidates): Collection
    {
        $positions = Source::query()->pluck('position', 'slug');

        return $candidates
            ->sortBy([
                fn (Item $a, Item $b) => ($positions[$a->source_slug] ?? PHP_INT_MAX) <=> ($positions[$b->source_slug] ?? PHP_INT_MAX),
                fn (Item $a, Item $b) => ($b->published_at?->getTimestamp() ?? 0) <=> ($a->published_at?->getTimestamp() ?? 0),
            ])
            ->take((int) config('digest.max_items'))
            ->values();
    }

    /**
     * Each item goes to the section of its tag that is most common that day (fewer one-item sections),
     * then sections are ordered by size.
     *
     * @param  Collection<int, NewsLine>  $items
     * @return list<array{slug: string, name: string, items: list<NewsLine>}>
     */
    private function sections(Collection $items): array
    {
        $tagsOf = $items->map(fn (NewsLine $item) => $item->tagSlugs ?: ['general']);

        $frequency = array_count_values(array_merge(...$tagsOf->values()->all()));

        $sections = [];
        foreach ($items as $index => $item) {
            $slugs = $tagsOf[$index];
            usort($slugs, fn (string $a, string $b) => [$frequency[$b], $a] <=> [$frequency[$a], $b]);
            $slug = $slugs[0];

            $sections[$slug] ??= ['slug' => $slug, 'name' => Tag::displayName($slug), 'items' => []];
            $sections[$slug]['items'][] = $item;
        }

        usort($sections, fn (array $a, array $b) => [count($b['items']), $a['name']] <=> [count($a['items']), $b['name']]);

        return $sections;
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
