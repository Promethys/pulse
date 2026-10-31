<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Enums\InterestStatus;
use App\Enums\SourceType;
use App\Models\Interest;
use App\Models\Source;
use Database\Seeders\InterestTableSeeder;
use Database\Seeders\SourceTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SourceTableSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_sources_in_priority_order(): void
    {
        $this->seed(SourceTableSeeder::class);

        $sources = Source::query()->orderBy('position')->get();

        $this->assertCount(23, $sources);
        $this->assertFalse(Source::query()->where('slug', 'like', 'r-%')->exists());
        $this->assertSame('anthropic-news', $sources->first()?->slug);
        $this->assertSame('newsletters', $sources->last()?->slug);
        $this->assertSame(SourceType::ResendInbox, $sources->last()?->type);
        $this->assertTrue($sources->every(fn (Source $source) => $source->enabled));
    }

    public function test_reseeding_never_changes_existing_rows(): void
    {
        $this->seed(SourceTableSeeder::class);

        $source = Source::query()->where('slug', 'laravel-news')->firstOrFail();
        $source->update([
            'url' => 'https://edited.example.com/feed',
            'max_items' => 3,
            'enabled' => false,
            'is_default' => false,
            'last_success_at' => '2026-10-16 07:00:00',
            'last_error' => 'boom',
        ]);

        $this->seed(SourceTableSeeder::class);

        $source->refresh();
        $this->assertSame(23, Source::query()->count());
        $this->assertSame('https://edited.example.com/feed', $source->url);
        $this->assertSame(3, $source->max_items);
        $this->assertFalse($source->enabled);
        $this->assertFalse($source->is_default);
        $this->assertSame('2026-10-16 07:00:00', $source->last_success_at?->toDateTimeString());
        $this->assertSame('boom', $source->last_error);
    }

    public function test_reseeding_restores_only_missing_sources(): void
    {
        $this->seed(SourceTableSeeder::class);
        Source::query()->where('slug', 'openai-news')->delete();
        $before = Source::query()->where('slug', 'laravel-news')->value('updated_at');

        $this->seed(SourceTableSeeder::class);

        $this->assertSame(23, Source::query()->count());
        $this->assertTrue(Source::query()->where('slug', 'openai-news')->exists());
        $this->assertEquals($before, Source::query()->where('slug', 'laravel-news')->value('updated_at'));
    }

    public function test_enabled_scope_orders_by_position(): void
    {
        $this->seed(SourceTableSeeder::class);
        Source::query()->where('slug', 'openai-news')->update(['enabled' => false]);

        $slugs = Source::query()->enabled()->pluck('slug');

        $this->assertNotContains('openai-news', $slugs);
        $this->assertSame('anthropic-news', $slugs->first());
        $this->assertCount(22, $slugs);
    }

    public function test_new_sources_are_defaults_except_newsletters(): void
    {
        $this->seed(SourceTableSeeder::class);

        $this->assertSame(22, Source::query()->where('is_default', true)->count());
        $this->assertFalse(Source::query()->where('slug', 'newsletters')->firstOrFail()->is_default);
    }

    public function test_sources_are_linked_to_their_interests(): void
    {
        $this->seed([InterestTableSeeder::class, SourceTableSeeder::class]);

        $expected = [
            'artificial-intelligence' => ['anthropic-news', 'openai-news', 'deepmind', 'huggingface', 'simon-willison', 'ainews', 'kilo', 'mcp-blog'],
            'programming' => ['mcp-blog', 'laravel-news', 'laravel-blog', 'freek', 'stitcher', 'filament-releases', 'laravel-io', 'pragmatic-engineer', 'bytebytego', 'martin-fowler', 'lobsters', 'github-trending', 'hacker-news'],
            'programming-languages' => ['laravel-news', 'laravel-blog', 'freek', 'stitcher', 'filament-releases', 'laravel-io'],
            'tech-companies' => ['hacker-news'],
            'devops' => ['railway'],
            'productivity' => ['notion'],
        ];

        foreach ($expected as $slug => $sources) {
            $interest = Interest::query()->where('slug', $slug)->firstOrFail();
            $this->assertEqualsCanonicalizing($sources, $interest->sources()->pluck('slug')->all());
            $this->assertSame(0, $interest->sources()->wherePivotNull('created_at')->count());
            $this->assertSame(0, $interest->sources()->wherePivotNull('updated_at')->count());
        }

        $this->assertSame(0, Source::query()->where('slug', 'newsletters')->firstOrFail()->interests()->count());
        $this->assertDatabaseCount('interest_source', 30);
    }

    public function test_reseeding_preserves_edited_links_and_restores_empty_links(): void
    {
        $this->seed([InterestTableSeeder::class, SourceTableSeeder::class]);
        $source = Source::query()->where('slug', 'mcp-blog')->firstOrFail();
        $interest = Interest::query()->where('slug', 'programming')->firstOrFail();
        $source->interests()->detach($interest);

        $this->seed(SourceTableSeeder::class);

        $this->assertSame(['artificial-intelligence'], $source->interests()->pluck('slug')->all());

        $source->interests()->detach();
        $this->seed(SourceTableSeeder::class);

        $this->assertEqualsCanonicalizing(['artificial-intelligence', 'programming'], $source->interests()->pluck('slug')->all());
    }

    public function test_seeding_refreshes_interest_statuses(): void
    {
        $this->seed([InterestTableSeeder::class, SourceTableSeeder::class]);

        foreach (['artificial-intelligence', 'programming', 'programming-languages'] as $slug) {
            $this->assertSame(InterestStatus::Live, Interest::query()->where('slug', $slug)->firstOrFail()->status);
        }

        foreach (['devops', 'productivity', 'tech-companies'] as $slug) {
            $this->assertSame(InterestStatus::Draft, Interest::query()->where('slug', $slug)->firstOrFail()->status);
        }
    }
}
