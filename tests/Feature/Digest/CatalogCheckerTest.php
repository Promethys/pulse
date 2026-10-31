<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Digest\Catalog\CatalogChecker;
use App\Digest\Sources\Candidate;
use App\Digest\Sources\FeedFetcher;
use App\Digest\Sources\FetchException;
use App\Digest\Sources\GithubTrendingFetcher;
use App\Digest\Sources\SourceFetcher;
use App\Digest\Support\PublicUrl;
use App\Enums\InterestStatus;
use App\Enums\SourceType;
use App\Models\Interest;
use App\Models\Source;
use App\Models\SourceCheck;
use App\Models\SourceSubscription;
use Carbon\CarbonImmutable;
use Closure;
use Error;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class CatalogCheckerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-31 12:00:00', 'UTC'));
        $this->app->instance(PublicUrl::class, new PublicUrl(fn (string $host) => []));
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response('', 404)]);
        Exceptions::fake();
    }

    #[DataProvider('itemCounts')]
    public function test_success_records_a_check_without_changing_daily_collection_state_or_storing_items(int $count): void
    {
        $source = Source::factory()->create([
            'last_success_at' => now()->subDays(40),
            'last_fetched_at' => now()->subDay(),
            'last_error' => 'Previous daily failure',
        ]);
        SourceSubscription::factory()->for($source)->create();
        SourceCheck::factory()->for($source)->create(['checked_at' => now()->subMonth(), 'ok' => false]);
        $before = $source->fresh()->getAttributes();
        $this->fakeFetch(function (Source $actual, CarbonImmutable $since) use ($source, $count): array {
            $this->assertTrue($actual->is($source));
            $this->assertTrue($since->equalTo(now()->subDays(30)));

            return $count === 0 ? [] : [new Candidate('Headline', 'https://source.test/item', '', null, [])];
        });

        $check = app(CatalogChecker::class)->check($source);

        $this->assertInstanceOf(SourceCheck::class, $check);
        $this->assertTrue($check->exists);
        $this->assertSame($source->id, $check->source_id);
        $this->assertTrue($check->ok);
        $this->assertTrue($check->checked_at->equalTo(now()));
        $this->assertNull($check->error);
        $this->assertNull($check->suggested_url);
        $this->assertSame($before, $source->fresh()->getAttributes());
        $this->assertSame(2, $source->checks()->count());
        $this->assertDatabaseCount('items', 0);
        $this->assertDatabaseCount('source_proposals', 0);
        Exceptions::assertNothingReported();
    }

    public function test_first_fetch_failure_records_the_error_without_changing_daily_collection_state(): void
    {
        $lookups = [];
        $this->app->instance(PublicUrl::class, new PublicUrl(function (string $host) use (&$lookups): array {
            $lookups[] = $host;

            return [];
        }));
        $source = Source::factory()->create([
            'type' => SourceType::GithubTrending,
            'url' => 'https://source.test/trending',
            'site_url' => 'https://source.test/',
            'last_success_at' => now()->subDay(),
            'last_fetched_at' => now()->subHour(),
            'last_error' => 'Daily failure',
        ]);
        $before = $source->fresh()->getAttributes();
        $this->fakeFetch(fn () => throw new FetchException('HTTP 503'));

        $check = app(CatalogChecker::class)->check($source);

        $this->assertFalse($check->ok);
        $this->assertSame('HTTP 503', $check->error);
        $this->assertNull($check->suggested_url);
        $this->assertTrue($check->checked_at->equalTo(now()));
        $this->assertDatabaseHas('source_checks', ['id' => $check->id, 'source_id' => $source->id, 'ok' => false, 'error' => 'HTTP 503']);
        $this->assertSame($before, $source->fresh()->getAttributes());
        $this->assertDatabaseCount('source_proposals', 0);
        Http::assertNothingSent();
        $this->assertSame([], $lookups);
        Exceptions::assertNothingReported();
    }

    public function test_two_consecutive_failures_retire_even_an_unsubscribed_recently_successful_source_and_refresh_interests(): void
    {
        $interest = Interest::factory()->create(['status' => InterestStatus::Live]);
        $source = Source::factory()->create(['is_default' => true, 'last_success_at' => now()]);
        $interest->sources()->attach([$source->id, ...Source::factory()->count(2)->create(['is_default' => true])->modelKeys()]);
        SourceCheck::factory()->for($source)->create(['checked_at' => now()->subMonths(2), 'ok' => true]);
        SourceCheck::factory()->for($source)->create(['checked_at' => now()->subMonth(), 'ok' => false]);
        $this->fakeFetch(fn () => throw new FetchException('Still broken'));
        Log::spy();

        app(CatalogChecker::class)->check($source);

        $this->assertModelExists($source);
        $this->assertFalse($source->refresh()->enabled);
        $this->assertInstanceOf(CarbonImmutable::class, $source->retired_at);
        $this->assertTrue($source->retired_at->equalTo(now()));
        $this->assertSame(InterestStatus::Draft, $interest->refresh()->status);
        $this->assertSame(3, $source->checks()->count());
        Log::shouldHaveReceived('warning')->once();
    }

    #[DataProvider('staleness')]
    public function test_first_failure_retires_only_when_an_enabled_subscription_has_a_success_window_older_than_thirty_days(?int $successAge, int $createdAge, ?bool $subscriptionEnabled, bool $retired): void
    {
        $source = Source::factory()->create([
            'last_success_at' => $successAge === null ? null : now()->subSeconds($successAge),
            'created_at' => now()->subSeconds($createdAge),
        ]);
        $subscription = $subscriptionEnabled === null ? null : SourceSubscription::factory()->for($source)->create(['enabled' => $subscriptionEnabled]);
        $before = $subscription?->fresh()->getAttributes();
        $dailyState = $source->fresh()->only(['last_success_at', 'last_fetched_at', 'last_error']);
        $this->fakeFetch(fn () => throw new FetchException('Unavailable'));

        $check = app(CatalogChecker::class)->check($source);

        $this->assertFalse($check->ok);
        $this->assertSame(! $retired, $source->refresh()->enabled);
        if ($retired) {
            $this->assertTrue($source->retired_at->equalTo(now()));
        } else {
            $this->assertNull($source->retired_at);
        }
        $this->assertModelExists($source);
        $this->assertEquals($dailyState, $source->fresh()->only(['last_success_at', 'last_fetched_at', 'last_error']));
        if ($subscription !== null) {
            $this->assertModelExists($subscription);
            $this->assertSame($before, $subscription->fresh()->getAttributes());
        }
    }

    public function test_a_success_between_failures_breaks_the_retirement_streak(): void
    {
        $source = Source::factory()->create(['last_success_at' => now()]);
        SourceCheck::factory()->for($source)->create(['checked_at' => now()->subMonth(), 'ok' => true]);
        SourceCheck::factory()->for($source)->create(['checked_at' => now()->subMonths(2), 'ok' => false]);
        $this->fakeFetch(fn () => throw new FetchException('New failure'));

        app(CatalogChecker::class)->check($source);

        $this->assertTrue($source->refresh()->enabled);
        $this->assertNull($source->retired_at);
    }

    #[DataProvider('unexpectedFailures')]
    public function test_unexpected_throwables_are_reported_and_stored_as_failed_checks(Throwable $failure): void
    {
        $source = Source::factory()->create(['type' => SourceType::GithubTrending]);
        $this->fakeFetch(fn () => throw $failure);

        $check = app(CatalogChecker::class)->check($source);

        $this->assertFalse($check->ok);
        $this->assertSame($failure->getMessage(), $check->error);
        $this->assertDatabaseHas('source_checks', ['id' => $check->id, 'ok' => false, 'error' => $failure->getMessage()]);
        $this->assertContains($failure, Exceptions::reported());
        Http::assertNothingSent();
    }

    public function test_checks_have_a_source_month_index_and_are_deleted_with_only_their_source(): void
    {
        $source = Source::factory()->create();
        $other = Source::factory()->create();
        $check = SourceCheck::factory()->for($source)->create();
        $kept = SourceCheck::factory()->for($other)->create();

        $this->assertSame([$check->id], $source->checks->modelKeys());
        $this->assertTrue(collect(Schema::getIndexes('source_checks'))
            ->contains(fn (array $index) => $index['columns'] === ['source_id', 'checked_at']));
        $column = collect(Schema::getColumns('source_checks'))->firstWhere('name', 'checked_at');
        $this->assertNotNull($column);
        $this->assertSame('timestamp', $column['type_name']);

        $source->delete();

        $this->assertModelMissing($check);
        $this->assertModelExists($kept);
        $this->assertModelExists($other);
    }

    /** @return array<string, array{Throwable}> */
    public static function unexpectedFailures(): array
    {
        return [
            'exception' => [new RuntimeException('Unexpected parser failure')],
            'error' => [new Error('Unexpected parser error')],
        ];
    }

    /** @return array<string, array{int}> */
    public static function itemCounts(): array
    {
        return ['empty feed' => [0], 'one item' => [1]];
    }

    /** @return array<string, array{int|null, int, bool|null, bool}> */
    public static function staleness(): array
    {
        $days = 30 * 86400;

        return [
            'stale subscribed source' => [$days + 1, $days + 100, true, true],
            'exactly thirty days' => [$days, $days + 100, true, false],
            'recent success overrides old creation' => [1, $days + 100, true, false],
            'never successful and stale' => [null, $days + 1, true, true],
            'never successful at boundary' => [null, $days, true, false],
            'unsubscribed stale source on first failure' => [$days + 1, $days + 100, null, false],
            'unsubscribed never successful source' => [null, $days + 1, null, false],
            'disabled subscription does not count' => [$days + 1, $days + 100, false, false],
        ];
    }

    private function fakeFetch(Closure $fetch): void
    {
        $fetcher = new class($fetch) implements SourceFetcher
        {
            public function __construct(private Closure $fetch) {}

            public function fetch(Source $source, CarbonImmutable $since): array
            {
                return ($this->fetch)($source, $since);
            }
        };
        $this->app->instance(FeedFetcher::class, $fetcher);
        $this->app->instance(GithubTrendingFetcher::class, $fetcher);
    }
}
