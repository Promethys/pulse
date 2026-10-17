<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Digest\Sources\Candidate;
use App\Digest\Sources\FeedFetcher;
use App\Digest\Sources\FetchException;
use App\Digest\Sources\SourceFetcher;
use App\Enums\SourceType;
use App\Models\Item;
use App\Models\Source;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DigestSourcesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_counts_and_errors_without_storing_and_fails(): void
    {
        $this->app->instance(FeedFetcher::class, new class implements SourceFetcher
        {
            public function fetch(Source $source, CarbonImmutable $since): array
            {
                if ($source->slug === 'broken') {
                    throw new FetchException('HTTP 404');
                }

                return [new Candidate($source->slug, 'A', 'https://ok.test/a', '', null, ['https://ok.test/a'])];
            }
        });
        foreach (['ok', 'broken'] as $i => $slug) {
            Source::query()->create(['slug' => $slug, 'name' => $slug, 'type' => SourceType::Feed, 'url' => "https://{$slug}.test", 'tag' => 'ai', 'max_items' => 5, 'position' => $i]);
        }

        $this->artisan('digest:sources')
            ->expectsTable(['Source', 'Items', 'Error'], [['ok', '1', ''], ['broken', '0', 'HTTP 404']])
            ->assertExitCode(1);

        $this->assertSame(0, Item::query()->count());
        $this->assertNull(Source::query()->where('slug', 'ok')->value('last_fetched_at'));
    }

    public function test_succeeds_when_every_source_works(): void
    {
        $this->app->instance(FeedFetcher::class, new class implements SourceFetcher
        {
            public function fetch(Source $source, CarbonImmutable $since): array
            {
                return [];
            }
        });
        Source::query()->create(['slug' => 'ok', 'name' => 'ok', 'type' => SourceType::Feed, 'url' => 'https://ok.test', 'tag' => 'ai', 'max_items' => 5, 'position' => 1]);

        $this->artisan('digest:sources', ['--since' => '3d'])->assertExitCode(0);
    }
}
