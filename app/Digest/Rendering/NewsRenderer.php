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
     * @param  Collection<int, Item>  $items
     * @param  array<string, string>  $failures  source name => error
     */
    public function render(CarbonImmutable $date, Collection $items, array $failures, bool $headlinesOnly = false): RenderedPost
    {
        $title = '📰 Tech digest — '.$date->format('D j M');
        $footer = $this->footer($failures, $headlinesOnly);

        if ($items->isEmpty()) {
            return new RenderedPost($title, $this->splitter->split(['Quiet night: nothing notable.', $footer]), []);
        }

        $sections = $this->sections($items);

        $blocks = array_map(fn (array $section) => "## {$section['name']}\n".implode("\n", array_map(
            fn (Item $item) => $this->line($item, $headlinesOnly),
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
     * @param  Collection<int, Item>  $items
     * @return list<array{slug: string, name: string, items: list<Item>}>
     */
    private function sections(Collection $items): array
    {
        $defaultTags = Source::query()->pluck('tag', 'slug');
        $tagsOf = $items->mapWithKeys(fn (Item $item) => [$item->id => $item->tags->isNotEmpty()
            ? $item->tags->pluck('slug')->all()
            : [(string) ($defaultTags[$item->source_slug] ?? 'general')]]);

        $frequency = array_count_values(array_merge(...$tagsOf->values()->all()));

        $sections = [];
        foreach ($items as $item) {
            $slugs = $tagsOf[$item->id];
            usort($slugs, fn (string $a, string $b) => [$frequency[$b], $a] <=> [$frequency[$a], $b]);
            $slug = $slugs[0];

            $sections[$slug] ??= ['slug' => $slug, 'name' => Tag::displayName($slug), 'items' => []];
            $sections[$slug]['items'][] = $item;
        }

        usort($sections, fn (array $a, array $b) => [count($b['items']), $a['name']] <=> [count($a['items']), $b['name']]);

        return $sections;
    }

    private function line(Item $item, bool $headlinesOnly): string
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
