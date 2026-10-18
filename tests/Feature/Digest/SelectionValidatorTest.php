<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Digest\News\SelectedItem;
use App\Digest\News\SelectionValidator;
use App\Models\Item;
use Illuminate\Support\Collection;
use Tests\TestCase;

class SelectionValidatorTest extends TestCase
{
    public function test_keeps_a_valid_entry(): void
    {
        $selected = $this->validate([$this->entry(1, 'https://laravel-news.com/a', tags: ['Laravel', 'php'])]);

        $this->assertEquals([new SelectedItem(1, 'https://laravel-news.com/a', 'Title', 'Summary.', ['laravel', 'php'])], $selected);
    }

    public function test_drops_unknown_candidate_id(): void
    {
        $this->assertSame([], $this->validate([$this->entry(99, 'https://laravel-news.com/a')]));
    }

    public function test_drops_url_not_in_candidate_links(): void
    {
        $this->assertSame([], $this->validate([$this->entry(1, 'https://invented.example.com/story')]));
    }

    public function test_compares_links_after_normalization(): void
    {
        $selected = $this->validate([$this->entry(1, 'https://www.laravel-news.com/a/?utm_source=x')]);

        $this->assertSame('https://www.laravel-news.com/a/?utm_source=x', $selected[0]->url);
    }

    public function test_accepts_a_link_from_a_newsletter(): void
    {
        $selected = $this->validate([$this->entry(2, 'https://laravelshift.com/blog/upgrade-notes')]);

        $this->assertCount(1, $selected);
    }

    public function test_truncates_title_and_summary(): void
    {
        $selected = $this->validate([$this->entry(1, 'https://laravel-news.com/a', title: str_repeat('t', 120), summary: str_repeat('s', 300))]);

        $this->assertLessThanOrEqual(80, mb_strlen($selected[0]->title));
        $this->assertLessThanOrEqual(160, mb_strlen($selected[0]->summary));
    }

    public function test_slugs_dedupes_and_caps_tags(): void
    {
        $selected = $this->validate([$this->entry(1, 'https://laravel-news.com/a', tags: ['AI', 'ai', 'Dev Tools', 'mcp', 'php'])]);

        $this->assertSame(['ai', 'dev-tools', 'mcp'], $selected[0]->tagSlugs);
    }

    public function test_falls_back_to_the_source_tag(): void
    {
        $selected = $this->validate([$this->entry(1, 'https://laravel-news.com/a', tags: ['', '!!'])]);

        $this->assertSame(['laravel'], $selected[0]->tagSlugs);
    }

    public function test_keeps_the_first_of_duplicate_urls(): void
    {
        $selected = $this->validate([
            $this->entry(1, 'https://laravel-news.com/a', title: 'First'),
            $this->entry(1, 'https://laravel-news.com/a/', title: 'Second'),
        ]);

        $this->assertSame(['First'], array_map(fn (SelectedItem $s) => $s->title, $selected));
    }

    public function test_caps_at_max_items(): void
    {
        config(['digest.max_items' => 20]);
        $entries = array_map(fn (int $i) => $this->entry(3, "https://news.ycombinator.com/item/{$i}"), range(1, 25));

        $this->assertCount(20, $this->validate($entries, newsletterLinks: array_map(fn (int $i) => "https://news.ycombinator.com/item/{$i}", range(1, 25))));
    }

    public function test_empty_or_malformed_output_gives_nothing(): void
    {
        $this->assertSame([], (new SelectionValidator)->validate([], $this->candidates()));
        $this->assertSame([], (new SelectionValidator)->validate(['items' => 'nope'], $this->candidates()));
        $this->assertSame([], $this->validate(['not an entry', ['candidate_id' => 'x']]));
    }

    /**
     * @param  list<mixed>  $entries
     * @param  list<string>  $newsletterLinks
     * @return list<SelectedItem>
     */
    private function validate(array $entries, array $newsletterLinks = []): array
    {
        return (new SelectionValidator)->validate(['items' => $entries], $this->candidates($newsletterLinks), ['laravel-news' => 'laravel', 'newsletters' => 'devtools']);
    }

    /**
     * @param  list<string>  $extraLinks
     * @return Collection<int, Item>
     */
    private function candidates(array $extraLinks = []): Collection
    {
        $make = function (int $id, string $slug, string $url, array $links) {
            $item = new Item(['source_slug' => $slug, 'url' => $url, 'links' => $links]);
            $item->id = $id;

            return $item;
        };

        return collect([
            1 => $make(1, 'laravel-news', 'https://laravel-news.com/a', ['https://laravel-news.com/a']),
            2 => $make(2, 'newsletters', 'resend://email/x', ['https://laravelshift.com/blog/upgrade-notes', 'https://github.com/laravel/framework/pull/1']),
            3 => $make(3, 'newsletters', 'resend://email/y', $extraLinks),
        ]);
    }

    /**
     * @param  list<string>  $tags
     * @return array<string, mixed>
     */
    private function entry(int $id, string $url, string $title = 'Title', string $summary = 'Summary.', array $tags = ['laravel']): array
    {
        return ['candidate_id' => $id, 'url' => $url, 'title' => $title, 'summary' => $summary, 'tags' => $tags];
    }
}
