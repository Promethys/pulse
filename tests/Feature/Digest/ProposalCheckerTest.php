<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Digest\Catalog\ProposalCheck;
use App\Digest\Catalog\ProposalChecker;
use App\Digest\Sources\Candidate;
use App\Digest\Sources\FeedFetcher;
use App\Digest\Sources\FetchException;
use App\Digest\Sources\SourceFetcher;
use App\Digest\Support\PublicUrl;
use App\Enums\SourceType;
use App\Models\Source;
use App\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use Error;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class ProposalCheckerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-31 12:00:00', 'UTC'));
        $this->app->instance(PublicUrl::class, new PublicUrl(fn (string $host) => ['93.184.216.34']));
        Http::preventStrayRequests();
        Sleep::fake();
        $this->fakeFetch(function (): array {
            $this->fail('A failed preliminary check must not fetch the feed.');
        });
    }

    public function test_three_recent_candidates_pass_in_order_using_an_unsaved_feed_and_store_nothing(): void
    {
        Source::factory()->for(User::factory())->create(['url' => 'https://new.test/private-feed']);
        $this->fakeFetch(function (Source $source, CarbonImmutable $since): array {
            $this->assertFalse($source->exists);
            $this->assertSame(SourceType::Feed, $source->type);
            $this->assertSame('https://new.test/feed', $source->url);
            $this->assertTrue($since->equalTo(now()->subDays(30)));

            return $this->candidates(3);
        });

        $result = app(ProposalChecker::class)->check('https://new.test/feed');

        $this->assertInstanceOf(ProposalCheck::class, $result);
        $this->assertTrue((new ReflectionClass($result))->isFinal());
        $this->assertTrue((new ReflectionClass($result))->isReadOnly());
        $this->assertTrue($result->passed);
        $this->assertNull($result->failure);
        $this->assertSame(['public_url' => true, 'not_in_catalog' => true, 'feed_parses' => true, 'recent_posts' => true], $result->checks);
        $this->assertDatabaseCount('sources', 1);
        $this->assertDatabaseCount('items', 0);
        $this->assertDatabaseCount('source_checks', 0);
        $this->assertDatabaseCount('source_proposals', 0);
    }

    #[DataProvider('unsafeUrls')]
    public function test_unsafe_urls_fail_public_url_before_any_other_check(string $url, array $addresses): void
    {
        $this->app->instance(PublicUrl::class, new PublicUrl(fn (string $host) => $addresses));

        $result = app(ProposalChecker::class)->check($url);

        $this->assertFalse($result->passed);
        $this->assertSame('public_url', $result->failure);
        $this->assertSame(['public_url' => false], $result->checks);
        Http::assertNothingSent();
    }

    #[DataProvider('catalogDuplicates')]
    public function test_catalog_urls_and_domains_are_rejected_before_fetching(string $existing, string $proposed): void
    {
        Source::factory()->create(['url' => $existing, 'enabled' => false]);

        $result = app(ProposalChecker::class)->check($proposed);

        $this->assertFalse($result->passed);
        $this->assertSame('not_in_catalog', $result->failure);
        $this->assertSame(['public_url' => true, 'not_in_catalog' => false], $result->checks);
        Http::assertNothingSent();
    }

    #[DataProvider('tooFewPosts')]
    public function test_a_parsed_feed_with_fewer_than_three_recent_posts_fails_recent_posts(int $count): void
    {
        $this->fakeFetch(fn () => $this->candidates($count));

        $result = app(ProposalChecker::class)->check('https://new.test/feed');

        $this->assertFalse($result->passed);
        $this->assertSame('recent_posts', $result->failure);
        $this->assertSame(['public_url' => true, 'not_in_catalog' => true, 'feed_parses' => true, 'recent_posts' => false], $result->checks);
    }

    #[DataProvider('fetchFailures')]
    public function test_fetch_failures_are_failed_checks_and_never_escape(Throwable $failure): void
    {
        $this->fakeFetch(fn () => throw $failure);

        $result = app(ProposalChecker::class)->check('https://new.test/feed');

        $this->assertFalse($result->passed);
        $this->assertSame('feed_parses', $result->failure);
        $this->assertSame(['public_url' => true, 'not_in_catalog' => true, 'feed_parses' => false], $result->checks);
        $this->assertDatabaseCount('sources', 0);
    }

    public function test_real_feed_fetcher_counts_only_posts_in_the_last_thirty_days(): void
    {
        $this->app->forgetInstance(FeedFetcher::class);
        $items = '';
        foreach ([now(), now()->subDay(), now()->subDays(31)] as $index => $date) {
            $items .= '<item><title>Post '.$index.'</title><link>https://new.test/post-'.$index.'</link><guid>post-'.$index.'</guid><pubDate>'.$date->toRfc2822String().'</pubDate></item>';
        }
        Http::fake(['https://new.test/feed' => Http::response('<?xml version="1.0"?><rss version="2.0"><channel><title>News</title><link>https://new.test</link><description>News</description>'.$items.'</channel></rss>')]);

        $result = app(ProposalChecker::class)->check('https://new.test/feed');

        $this->assertFalse($result->passed);
        $this->assertTrue($result->checks['feed_parses']);
        $this->assertSame('recent_posts', $result->failure);
        Http::assertSentCount(1);
    }

    public function test_a_non_feed_response_fails_feed_parses_without_throwing(): void
    {
        $this->app->forgetInstance(FeedFetcher::class);
        Http::fake(['https://new.test/feed' => Http::response('<html><body>No feed here</body></html>')]);

        $result = app(ProposalChecker::class)->check('https://new.test/feed');

        $this->assertFalse($result->passed);
        $this->assertSame('feed_parses', $result->failure);
        $this->assertSame(['public_url' => true, 'not_in_catalog' => true, 'feed_parses' => false], $result->checks);
    }

    /** @return array<string, array{string, list<string>}> */
    public static function unsafeUrls(): array
    {
        return [
            'loopback' => ['http://127.0.0.1/feed', []],
            'private IP' => ['http://10.0.0.1/feed', []],
            'private DNS' => ['https://private.test/feed', ['192.168.1.1']],
            'mixed DNS' => ['https://mixed.test/feed', ['93.184.216.34', '10.0.0.1']],
            'unresolved DNS' => ['https://missing.test/feed', []],
            'invalid scheme' => ['file:///etc/passwd', []],
        ];
    }

    /** @return array<string, array{string, string}> */
    public static function catalogDuplicates(): array
    {
        return [
            'normalized URL' => ['https://EXISTING.test/feed/?utm_source=old', 'https://existing.test/feed'],
            'same domain different path' => ['https://existing.test/old', 'https://existing.test/new'],
            'catalog www' => ['https://www.existing.test/old', 'https://existing.test/new'],
            'proposal www' => ['https://existing.test/old', 'https://www.existing.test/new'],
        ];
    }

    /** @return array<string, array{int}> */
    public static function tooFewPosts(): array
    {
        return ['empty' => [0], 'one post' => [1], 'two posts' => [2]];
    }

    /** @return array<string, array{Throwable}> */
    public static function fetchFailures(): array
    {
        return [
            'fetch exception' => [new FetchException('HTTP 503')],
            'unexpected exception' => [new RuntimeException('Parser failed')],
            'unexpected error' => [new Error('Parser error')],
        ];
    }

    /** @return list<Candidate> */
    private function candidates(int $count): array
    {
        return array_map(fn (int $index) => new Candidate('Post '.$index, 'https://new.test/post-'.$index, '', CarbonImmutable::now(), []), $count === 0 ? [] : range(1, $count));
    }

    private function fakeFetch(Closure $fetch): void
    {
        $this->app->instance(FeedFetcher::class, new class($fetch) implements SourceFetcher
        {
            public function __construct(private Closure $fetch) {}

            public function fetch(Source $source, CarbonImmutable $since): array
            {
                return ($this->fetch)($source, $since);
            }
        });
    }
}
