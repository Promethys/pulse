<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Digest\Rendering\NewsRenderer;
use App\Digest\Rendering\RenderedPost;
use App\Enums\SourceType;
use App\Models\Item;
use App\Models\Source;
use App\Models\Tag;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsRendererTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $date;

    protected function setUp(): void
    {
        parent::setUp();
        $this->date = CarbonImmutable::parse('2026-10-16');
        foreach ([['ai', 'AI'], ['laravel', 'Laravel'], ['mcp', 'MCP'], ['php', 'PHP'], ['devtools', 'Dev tools'], ['engineering', 'Engineering']] as [$slug, $name]) {
            Tag::query()->create(['slug' => $slug, 'name' => $name]);
        }
        foreach ([['openai-news', 'ai', 1], ['laravel-news', 'laravel', 2], ['hacker-news', 'devtools', 3]] as [$slug, $tag, $position]) {
            Source::query()->create(['slug' => $slug, 'name' => $slug, 'type' => SourceType::Feed, 'url' => "https://{$slug}.test", 'tag' => $tag, 'max_items' => 10, 'position' => $position]);
        }
    }

    public function test_title_format(): void
    {
        $this->assertSame('📰 Tech digest — Fri 16 Oct', $this->render([])->title);
    }

    public function test_groups_by_the_days_most_common_tag_largest_section_first(): void
    {
        $post = $this->render([
            $this->item('Model A', ['ai']),
            $this->item('Model B', ['ai']),
            $this->item('Laravel and AI', ['laravel', 'ai']),
            $this->item('Laravel 13.18', ['laravel']),
        ]);

        $text = implode("\n", $post->messages);
        $this->assertStringContainsString("## AI\n- **Model A**: Summary of Model A. <https://example.com/model-a>", $text);
        $this->assertLessThan(strpos($text, '## Laravel'), strpos($text, '## AI'));
        $this->assertLessThan(strpos($text, '## Laravel'), strpos($text, 'Laravel and AI'));
    }

    public function test_top_five_tags(): void
    {
        $items = [];
        foreach (['ai' => 6, 'laravel' => 5, 'mcp' => 4, 'php' => 3, 'devtools' => 2, 'engineering' => 1] as $tag => $count) {
            for ($i = 0; $i < $count; $i++) {
                $items[] = $this->item("{$tag} {$i}", [$tag]);
            }
        }

        $this->assertSame(['ai', 'laravel', 'mcp', 'php', 'devtools'], $this->render($items)->tagSlugs);
    }

    public function test_renders_quiet_night(): void
    {
        $post = $this->render([]);

        $this->assertSame(['Quiet night: nothing notable.'], $post->messages);
        $this->assertSame([], $post->tagSlugs);
    }

    public function test_failure_footer(): void
    {
        $post = $this->render([$this->item('A', ['ai'])], failures: ['Example Blog' => 'HTTP 500', 'r/laravel' => 'HTTP 429']);

        $this->assertStringEndsWith('⚠️ Not fetched: Example Blog, r/laravel', $post->messages[array_key_last($post->messages)]);
    }

    public function test_quiet_night_keeps_the_footer(): void
    {
        $post = $this->render([], failures: ['Example Blog' => 'HTTP 500']);

        $this->assertSame(["Quiet night: nothing notable.\n\n⚠️ Not fetched: Example Blog"], $post->messages);
    }

    public function test_headlines_only_note_and_lines(): void
    {
        $post = $this->render([$this->item('Plain headline', [], source: 'openai-news', summary: null)], headlinesOnly: true);

        $text = implode("\n", $post->messages);
        $this->assertStringContainsString("## AI\n- **Plain headline** <https://example.com/plain-headline>", $text);
        $this->assertStringEndsWith('⚠️ Summaries unavailable today, headlines only.', $text);
    }

    public function test_escapes_discord_markup(): void
    {
        $post = $this->render([$this->item('**@everyone** <@123>', ['ai'], summary: 'Use `code` and _this_.')]);

        $text = implode("\n", $post->messages);
        $this->assertStringNotContainsString('@everyone', $text);
        $this->assertStringNotContainsString('<@123>', $text);
        $this->assertStringContainsString('\*\*', $text);
        $this->assertStringContainsString('\`code\`', $text);
    }

    public function test_splits_long_days_into_messages_under_two_thousand_characters(): void
    {
        $items = array_map(fn (int $i) => $this->item("Item {$i} ".str_repeat('t', 60), ['ai'], summary: str_repeat('s', 150)), range(1, 20));

        $post = $this->render($items);

        $this->assertGreaterThan(1, count($post->messages));
        foreach ($post->messages as $message) {
            $this->assertLessThanOrEqual(2000, mb_strlen($message));
        }
    }

    public function test_rendered_post_round_trips_through_an_array(): void
    {
        $post = new RenderedPost('Title', ['a', 'b'], ['ai']);

        $this->assertEquals($post, RenderedPost::fromArray($post->toArray()));
    }

    public function test_headlines_picks_by_source_priority_then_recency(): void
    {
        $old = $this->item('Laravel old', [], source: 'laravel-news', publishedAt: '2026-10-16 01:00');
        $new = $this->item('Laravel new', [], source: 'laravel-news', publishedAt: '2026-10-16 05:00');
        $openai = $this->item('OpenAI', [], source: 'openai-news', publishedAt: '2026-10-15 20:00');
        $hn = $this->item('HN', [], source: 'hacker-news', publishedAt: '2026-10-16 06:00');
        config(['digest.max_items' => 3]);

        $picked = app(NewsRenderer::class)->headlines(collect([$old, $hn, $new, $openai]));

        $this->assertSame(['OpenAI', 'Laravel new', 'Laravel old'], $picked->pluck('title')->all());
    }

    /**
     * @param  list<Item>  $items
     * @param  array<string, string>  $failures
     */
    private function render(array $items, array $failures = [], bool $headlinesOnly = false): RenderedPost
    {
        return app(NewsRenderer::class)->render($this->date, collect($items), $failures, $headlinesOnly);
    }

    /**
     * @param  list<string>  $tags
     */
    private function item(string $title, array $tags, string $source = 'openai-news', ?string $summary = 'default', string $publishedAt = '2026-10-16 05:00'): Item
    {
        $url = 'https://example.com/'.str($title)->slug();
        $item = Item::factory()->create([
            'source_slug' => $source,
            'url' => $url,
            'url_normalized' => $url.'-'.uniqid(),
            'title' => $title,
            'summary' => $summary === 'default' ? "Summary of {$title}." : $summary,
            'published_at' => $publishedAt,
        ]);
        $item->tags()->sync(Tag::query()->whereIn('slug', $tags)->pluck('id'));

        return $item->load('tags');
    }
}
