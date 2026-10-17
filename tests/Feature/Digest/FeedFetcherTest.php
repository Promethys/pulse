<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Digest\Sources\Candidate;
use App\Digest\Sources\FeedFetcher;
use App\Digest\Sources\FetchException;
use App\Enums\SourceType;
use App\Models\Source;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class FeedFetcherTest extends TestCase
{
    private const URL = 'https://feeds.test/feed';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    public function test_parses_rss2(): void
    {
        $this->fakeFeed('rss2.xml');

        $candidates = $this->fetch(since: '2026-10-15 07:00:00');

        $this->assertSame(
            ['Laravel 13.18 adds deferred queue batches & more', 'Pest 5 is out', 'Undated community roundup'],
            array_map(fn (Candidate $c) => $c->title, $candidates),
        );

        $first = $candidates[0];
        $this->assertSame('laravel-news', $first->sourceSlug);
        $this->assertSame('https://laravel-news.com/laravel-13-18', $first->url);
        $this->assertSame(['https://laravel-news.com/laravel-13-18'], $first->links);
        $this->assertSame('2026-10-16 06:00:00', $first->publishedAt?->utc()->toDateTimeString());
        $this->assertStringStartsWith('Batches can now be built incrementally and dispatched once complete.', $first->excerpt);
        $this->assertStringNotContainsString('<', $first->excerpt);
        $this->assertLessThanOrEqual(300, mb_strlen($first->excerpt));
        $this->assertNull($candidates[2]->publishedAt);
    }

    public function test_parses_atom(): void
    {
        $this->fakeFeed('reddit.xml');

        $candidates = $this->fetch(since: '2026-10-15 07:00:00');

        $this->assertCount(2, $candidates);
        $this->assertSame('FrankenPHP workers in production: 6 months later', $candidates[0]->title);
        $this->assertSame('https://www.reddit.com/r/laravel/comments/abc/frankenphp_workers/', $candidates[0]->url);
        $this->assertSame('We moved our queue workers to FrankenPHP.', $candidates[0]->excerpt);
        $this->assertSame('2026-10-16 05:00:00', $candidates[0]->publishedAt?->utc()->toDateTimeString());
        $this->assertSame('https://www.reddit.com/r/laravel/comments/def/second/', $candidates[1]->url);
    }

    public function test_hnrss_links_to_the_article(): void
    {
        $this->fakeFeed('hnrss.xml');

        $candidates = $this->fetch(since: '2026-10-15 07:00:00');

        $this->assertCount(1, $candidates);
        $this->assertSame('https://example.dev/sqlite-queue', $candidates[0]->url);
    }

    public function test_filters_older_than_since_and_keeps_undated(): void
    {
        $this->fakeFeed('rss2.xml');

        $titles = array_map(fn (Candidate $c) => $c->title, $this->fetch(since: '2026-10-16 05:30:00'));

        $this->assertSame(['Laravel 13.18 adds deferred queue batches & more', 'Undated community roundup'], $titles);
    }

    public function test_respects_max_items(): void
    {
        $this->fakeFeed('rss2.xml');

        $candidates = $this->fetch(since: '2026-10-15 07:00:00', maxItems: 2);

        $this->assertSame(['Laravel 13.18 adds deferred queue batches & more', 'Pest 5 is out'], array_map(fn (Candidate $c) => $c->title, $candidates));
    }

    public function test_sends_a_user_agent(): void
    {
        $this->fakeFeed('rss2.xml');

        $this->fetch(since: '2026-10-15 07:00:00');

        Http::assertSent(fn (Request $request) => str_starts_with($request->header('User-Agent')[0] ?? '', 'pulse-digest/'));
    }

    public function test_malformed_feed_throws_feed_exception(): void
    {
        $this->fakeFeed('broken.xml');

        $this->expectException(FetchException::class);

        $this->fetch(since: '2026-10-15 07:00:00');
    }

    public function test_html_page_with_status_200_throws_fetch_exception(): void
    {
        Http::fake([self::URL => Http::response('<!doctype html><html><body>Just a moment...</body></html>', 200)]);

        $this->expectException(FetchException::class);

        $this->fetch(since: '2026-10-15 07:00:00');
    }

    public function test_http_500_throws_fetch_exception(): void
    {
        Http::fake([self::URL => Http::response('oops', 500)]);

        $this->expectException(FetchException::class);
        $this->expectExceptionMessage('HTTP 500');

        $this->fetch(since: '2026-10-15 07:00:00');
    }

    public function test_waits_for_retry_after_on_429_then_succeeds(): void
    {
        Sleep::fake();
        Http::fake([self::URL => Http::sequence()
            ->push('slow down', 429, ['Retry-After' => '3'])
            ->push((string) file_get_contents(base_path('tests/Fixtures/feeds/hnrss.xml')))]);

        $this->assertCount(1, $this->fetch(since: '2026-10-15 07:00:00'));
        Sleep::assertSlept(fn (CarbonInterval $duration) => (int) $duration->totalMilliseconds === 5000, times: 1);
    }

    public function test_waits_for_reddit_ratelimit_reset_on_429(): void
    {
        Sleep::fake();
        Http::fake([self::URL => Http::sequence()
            ->push('slow down', 429, ['x-ratelimit-remaining' => '0.0', 'x-ratelimit-reset' => '7'])
            ->push((string) file_get_contents(base_path('tests/Fixtures/feeds/hnrss.xml')))]);

        $this->assertCount(1, $this->fetch(since: '2026-10-15 07:00:00'));
        Sleep::assertSlept(fn (CarbonInterval $duration) => (int) $duration->totalMilliseconds === 9000, times: 1);
    }

    public function test_caps_the_wait_at_sixty_seconds(): void
    {
        Sleep::fake();
        Http::fake([self::URL => Http::sequence()
            ->push('slow down', 429, ['Retry-After' => '600'])
            ->push((string) file_get_contents(base_path('tests/Fixtures/feeds/hnrss.xml')))]);

        $this->fetch(since: '2026-10-15 07:00:00');

        Sleep::assertSlept(fn (CarbonInterval $duration) => (int) $duration->totalMilliseconds === 60000, times: 1);
    }

    private function fakeFeed(string $fixture): void
    {
        Http::fake([self::URL => Http::response((string) file_get_contents(base_path("tests/Fixtures/feeds/{$fixture}")), 200)]);
    }

    /**
     * @return list<Candidate>
     */
    private function fetch(string $since, int $maxItems = 10): array
    {
        $source = new Source([
            'slug' => 'laravel-news',
            'name' => 'Laravel News',
            'type' => SourceType::Feed,
            'url' => self::URL,
            'tag' => 'laravel',
            'max_items' => $maxItems,
            'position' => 1,
        ]);

        return (new FeedFetcher)->fetch($source, CarbonImmutable::parse($since, 'UTC'));
    }
}
