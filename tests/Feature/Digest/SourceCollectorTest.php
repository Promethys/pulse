<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Digest\Sources\Candidate;
use App\Digest\Sources\CollectionResult;
use App\Digest\Sources\FeedFetcher;
use App\Digest\Sources\FetchException;
use App\Digest\Sources\SourceCollector;
use App\Digest\Sources\SourceFetcher;
use App\Enums\ItemStatus;
use App\Enums\SourceType;
use App\Models\Item;
use App\Models\Source;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class SourceCollectorTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, list<Candidate>|\Throwable> */
    private array $responses = [];

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-10-16 07:00:00');

        $responses = &$this->responses;
        $this->app->instance(FeedFetcher::class, new class($responses) implements SourceFetcher
        {
            /** @param  array<string, list<Candidate>|\Throwable>  $responses */
            public function __construct(private array &$responses) {}

            public function fetch(Source $source, CarbonImmutable $since): array
            {
                $response = $this->responses[$source->slug] ?? [];

                return $response instanceof \Throwable ? throw $response : $response;
            }
        });
    }

    public function test_stores_new_candidates(): void
    {
        $this->source('laravel-news');
        $this->responses['laravel-news'] = [$this->candidate('laravel-news', 'https://laravel-news.com/a'), $this->candidate('laravel-news', 'https://laravel-news.com/b')];

        $result = $this->collect();

        $this->assertSame(2, $result->stored);
        $this->assertSame([], $result->failures);
        $item = Item::query()->where('url', 'https://laravel-news.com/a')->firstOrFail();
        $this->assertSame(ItemStatus::Candidate, $item->status);
        $this->assertSame('laravel-news', $item->source_slug);
        $this->assertSame('https://laravel-news.com/a', $item->url_normalized);
        $this->assertSame(['https://laravel-news.com/a'], $item->links);
    }

    public function test_skips_already_known_urls(): void
    {
        $this->source('laravel-news');
        Item::factory()->create(['url' => 'https://example.com/a', 'url_normalized' => 'https://example.com/a']);
        $this->responses['laravel-news'] = [
            $this->candidate('laravel-news', 'https://www.example.com/a/?utm_source=x'),
            $this->candidate('laravel-news', 'https://example.com/new'),
            $this->candidate('laravel-news', 'https://example.com/new/'),
        ];

        $result = $this->collect();

        $this->assertSame(1, $result->stored);
        $this->assertSame(2, Item::query()->count());
    }

    public function test_one_failing_source_does_not_stop_the_others(): void
    {
        $this->source('broken', 'Broken Blog', position: 1);
        $this->source('laravel-news', position: 2);
        $this->responses['broken'] = new FetchException('HTTP 500');
        $this->responses['laravel-news'] = [$this->candidate('laravel-news', 'https://laravel-news.com/a')];

        $result = $this->collect();

        $this->assertSame(1, $result->stored);
        $this->assertSame(['Broken Blog' => 'HTTP 500'], $result->failures);
        $broken = Source::query()->where('slug', 'broken')->firstOrFail();
        $this->assertSame('HTTP 500', $broken->last_error);
        $this->assertNull($broken->last_success_at);
        $this->assertSame('2026-10-16 07:00:00', $broken->last_fetched_at?->toDateTimeString());
    }

    public function test_unexpected_exception_counts_as_a_failure(): void
    {
        $this->source('weird', 'Weird Feed');
        $this->responses['weird'] = new RuntimeException('Undefined index');

        $result = $this->collect();

        $this->assertSame(['Weird Feed' => 'Undefined index'], $result->failures);
    }

    public function test_records_last_success_at_and_clears_the_error(): void
    {
        $source = $this->source('laravel-news');
        $source->update(['last_error' => 'old failure']);

        $this->collect();

        $source->refresh();
        $this->assertSame('2026-10-16 07:00:00', $source->last_success_at?->toDateTimeString());
        $this->assertNull($source->last_error);
    }

    public function test_disabled_sources_are_skipped(): void
    {
        $this->source('laravel-news')->update(['enabled' => false]);
        $this->responses['laravel-news'] = [$this->candidate('laravel-news', 'https://laravel-news.com/a')];

        $result = $this->collect();

        $this->assertSame(0, $result->stored);
        $this->assertNull(Source::query()->firstOrFail()->last_fetched_at);
    }

    private function collect(): CollectionResult
    {
        return $this->app->make(SourceCollector::class)->collect(CarbonImmutable::parse('2026-10-15 07:00:00'));
    }

    private function source(string $slug, ?string $name = null, int $position = 1): Source
    {
        return Source::query()->create([
            'slug' => $slug,
            'name' => $name ?? $slug,
            'type' => SourceType::Feed,
            'url' => "https://{$slug}.test/feed",
            'tag' => 'laravel',
            'max_items' => 10,
            'position' => $position,
        ]);
    }

    private function candidate(string $slug, string $url): Candidate
    {
        return new Candidate($slug, "Title for {$url}", $url, 'Excerpt', CarbonImmutable::parse('2026-10-16 06:00:00'), [$url]);
    }
}
