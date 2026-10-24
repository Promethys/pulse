<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Enums\SourceType;
use App\Models\Source;
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
            'last_success_at' => '2026-10-16 07:00:00',
            'last_error' => 'boom',
        ]);

        $this->seed(SourceTableSeeder::class);

        $source->refresh();
        $this->assertSame(23, Source::query()->count());
        $this->assertSame('https://edited.example.com/feed', $source->url);
        $this->assertSame(3, $source->max_items);
        $this->assertFalse($source->enabled);
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
}
